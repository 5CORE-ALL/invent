<?php

namespace App\Services\Wayfair;

use App\Models\WayfairPriceUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;

/**
 * Pull Wayfair's real price (Base Cost) into wayfair_pricing_prices.price.
 *
 * No Wayfair API exposes it to this app (the catalog APIs have no price; PO line prices can carry a
 * Wayfair-side discount, e.g. 29.39 on a PO for a SKU whose Base Cost is 41.98). Partner Home's
 * "Export Product Spreadsheet" does carry it: its Pricing sheet has a "CurrentBaseCost" column for every
 * product. This service downloads that workbook with the existing Partner Home browser login and stores it.
 */
class WayfairBaseCostPullService
{
    public const SOURCE_MARK = 'PARTNER_HOME_EXPORT';

    /**
     * Parse a Partner Home product export.
     *
     * @return array<string, array{sku: string, status: string, cost: float}> keyed by normalized SKU
     */
    public function readExport(string $absolutePath): array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new \RuntimeException('Wayfair export file is not readable: '.$absolutePath);
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $found = [];

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $columns = [];
                $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
                for ($col = 1; $col <= $highestCol; $col++) {
                    $key = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col).'2')->getValue());
                    if ($key !== '') {
                        $columns[$key] = $col;
                    }
                }
                if (! isset($columns['SupplierPartNumber'], $columns['CurrentBaseCost'])) {
                    continue;
                }

                $partCol = Coordinate::stringFromColumnIndex($columns['SupplierPartNumber']);
                $costCol = Coordinate::stringFromColumnIndex($columns['CurrentBaseCost']);
                $statusCol = isset($columns['Status']) ? Coordinate::stringFromColumnIndex($columns['Status']) : null;

                $highest = (int) $sheet->getHighestRow();
                for ($row = 5; $row <= $highest; $row++) {
                    $sku = trim((string) $sheet->getCell($partCol.$row)->getValue());
                    $cost = $sheet->getCell($costCol.$row)->getCalculatedValue();
                    if ($sku === '' || ! is_numeric($cost) || (float) $cost <= 0) {
                        continue;
                    }
                    $found[WayfairPriceSyncService::normalizeSku($sku)] = [
                        'sku' => $sku,
                        'status' => $statusCol ? trim((string) $sheet->getCell($statusCol.$row)->getValue()) : '',
                        'cost' => round((float) $cost, 2),
                    ];
                }
                break;
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return $found;
    }

    /**
     * Write the export's Current Base Cost into wayfair_pricing_prices.price.
     * Only SKUs that already have a row are touched.
     *
     * @param  array<string, array{sku: string, status: string, cost: float}>  $export
     * @return array{success: bool, message: string, in_export: int, matched: int, updated: int, unchanged: int, no_row: int, aborted: bool, skipped_upload_in_flight: bool, changes: list<array{sku: string, old: float, new: float, status: string}>}
     */
    public function apply(array $export, bool $dryRun = false, bool $force = false): array
    {
        $stats = [
            'success' => true,
            'message' => '',
            'in_export' => count($export),
            'matched' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'no_row' => 0,
            'aborted' => false,
            'skipped_upload_in_flight' => false,
            'changes' => [],
        ];

        foreach (['price_po_number', 'price_po_date', 'price_synced_at'] as $column) {
            if (! Schema::hasTable('wayfair_pricing_prices') || ! Schema::hasColumn('wayfair_pricing_prices', $column)) {
                $stats['success'] = false;
                $stats['message'] = 'wayfair_pricing_prices is missing '.$column.'. Run `php artisan migrate`.';

                return $stats;
            }
        }
        if ($export === []) {
            $stats['success'] = false;
            $stats['message'] = 'The export has no Current Base Cost rows.';

            return $stats;
        }

        // A price file Wayfair has not finished applying would make the export look stale. Do not
        // overwrite our fresh S PRC with the old base cost while it is still on its way.
        if (! $force && ! $dryRun && $this->uploadInFlight()) {
            $stats['skipped_upload_in_flight'] = true;
            $stats['message'] = 'A Wayfair price upload started in the last '
                .(float) config('wayfair_upload.base_cost_pull.upload_wait_hours', 4)
                .'h is still queued or processing. Skipped so the old base cost does not overwrite the new price. Use --force to override.';
            Log::warning('Wayfair base cost pull skipped: '.$stats['message']);

            return $stats;
        }

        $rowsByKey = [];
        DB::table('wayfair_pricing_prices')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$rowsByKey) {
                foreach ($rows as $row) {
                    $rowsByKey[WayfairPriceSyncService::normalizeSku((string) $row->sku)][] = $row;
                }
            });

        $plan = [];
        foreach ($export as $key => $hit) {
            $rows = $rowsByKey[$key] ?? [];
            if ($rows === []) {
                $stats['no_row']++;
                continue;
            }
            foreach ($rows as $row) {
                $stats['matched']++;
                $stored = (float) $row->price;
                if (abs($stored - $hit['cost']) < 0.005) {
                    $stats['unchanged']++;
                    $plan[] = [$row->id, null, $hit];
                    continue;
                }
                $stats['updated']++;
                $stats['changes'][] = [
                    'sku' => (string) $row->sku,
                    'old' => round($stored, 2),
                    'new' => $hit['cost'],
                    'status' => $hit['status'],
                ];
                $plan[] = [$row->id, $hit['cost'], $hit];
            }
        }

        $limit = (float) config('wayfair_upload.base_cost_pull.max_change_percent', 50);
        if (! $force && $limit > 0 && $stats['matched'] > 0) {
            $percent = $stats['updated'] / $stats['matched'] * 100;
            if ($percent > $limit) {
                $stats['aborted'] = true;
                $stats['success'] = false;
                $stats['message'] = sprintf(
                    '%d of %d SKUs (%.0f%%) would change, above the %.0f%% safety limit. Nothing was saved. Check the export, then use --force.',
                    $stats['updated'],
                    $stats['matched'],
                    $percent,
                    $limit
                );

                return $stats;
            }
        }

        if (! $dryRun) {
            $now = now();
            foreach ($plan as [$id, $newPrice, $hit]) {
                $update = [
                    'price_po_number' => self::SOURCE_MARK,
                    'price_po_date' => null,
                    'price_synced_at' => $now,
                ];
                if ($newPrice !== null) {
                    $update['price'] = $newPrice;
                    $update['updated_at'] = $now;
                }
                DB::table('wayfair_pricing_prices')->where('id', $id)->update($update);
            }
        }

        $stats['message'] = sprintf(
            '%d SKU price(s) %s from Wayfair Current Base Cost, %d already matched, %d SKUs in the export have no row here (%d in export).',
            $stats['updated'],
            $dryRun ? 'would change' : 'updated',
            $stats['unchanged'],
            $stats['no_row'],
            $stats['in_export']
        );
        Log::info('Wayfair base cost pull', array_diff_key($stats, ['changes' => 1]) + ['dry_run' => $dryRun]);

        return $stats;
    }

    /**
     * Download a fresh export from Partner Home.
     *
     * @return array{success: bool, path: ?string, message: string}
     */
    public function download(): array
    {
        $cfg = (array) config('wayfair_upload.base_cost_pull', []);
        $exportUrl = trim((string) ($cfg['export_url'] ?? ''));
        if ($exportUrl === '') {
            $exportUrl = trim((string) config('wayfair_upload.upload_url', ''));
        }
        if ($exportUrl === '') {
            return ['success' => false, 'path' => null, 'message' => 'Set WAYFAIR_EXPORT_URL (or WAYFAIR_UPLOAD_URL) to the Partner Home page that has "Export Product Spreadsheet".'];
        }

        $username = (string) config('wayfair_upload.username', '');
        $password = (string) config('wayfair_upload.password', '');
        if ($username === '' || $password === '') {
            return ['success' => false, 'path' => null, 'message' => 'WAYFAIR_USERNAME and WAYFAIR_PASSWORD are required for the Partner Home export.'];
        }

        $script = (string) ($cfg['browser_script'] ?? base_path('scripts/wayfair/export-base-cost.js'));
        if (! is_file($script)) {
            return ['success' => false, 'path' => null, 'message' => 'Export script is missing at scripts/wayfair/export-base-cost.js.'];
        }

        $dir = storage_path('app/'.trim((string) ($cfg['directory'] ?? 'wayfair/exports'), '/'));
        $errorDir = storage_path('app/'.trim((string) config('wayfair_upload.error_directory', 'wayfair/errors'), '/'));
        File::ensureDirectoryExists($dir);
        File::ensureDirectoryExists($errorDir);

        $savePath = $dir.'/base-cost-'.now()->format('Ymd-His').'.xlsx';
        $payloadPath = storage_path('app/wayfair/tmp/export-'.bin2hex(random_bytes(4)).'.json');
        File::ensureDirectoryExists(dirname($payloadPath));
        file_put_contents($payloadPath, json_encode([
            'portalUrl' => (string) config('wayfair_upload.portal_url'),
            'exportUrl' => $exportUrl,
            'exportLabel' => (string) ($cfg['export_label'] ?? 'Export Product Spreadsheet'),
            'username' => $username,
            'password' => $password,
            'savePath' => $savePath,
            'screenshotDir' => $errorDir,
            'timeoutMs' => max(10, (int) config('wayfair_upload.upload_timeout', 120)) * 1000,
            'selectors' => config('wayfair_upload.selectors', []),
        ]));
        chmod($payloadPath, 0600);

        try {
            $process = new Process([(string) config('wayfair_upload.node_binary', 'node'), $script, $payloadPath]);
            $process->setTimeout((int) config('wayfair_upload.upload_timeout', 120) + 60);
            $process->run();
            $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        } catch (\Throwable $e) {
            return ['success' => false, 'path' => null, 'message' => $e->getMessage()];
        } finally {
            @unlink($payloadPath);
        }

        $decoded = $this->lastJson($output);
        if ($decoded === null) {
            return ['success' => false, 'path' => null, 'message' => 'Export worker did not return a result. '.mb_substr($output, 0, 500)];
        }
        if (($decoded['state'] ?? '') !== 'success' || ! is_file($savePath)) {
            $message = (string) ($decoded['message'] ?? 'Export failed');
            if (! empty($decoded['screenshot'])) {
                $message .= ' (screenshot: '.$decoded['screenshot'].')';
            }

            return ['success' => false, 'path' => null, 'message' => $message];
        }

        return ['success' => true, 'path' => $savePath, 'message' => (string) ($decoded['message'] ?? 'Downloaded')];
    }

    private function uploadInFlight(): bool
    {
        if (! Schema::hasTable('wayfair_price_uploads')) {
            return false;
        }

        return WayfairPriceUpload::query()
            ->whereIn('status', [
                WayfairPriceUpload::QUEUED,
                WayfairPriceUpload::UPLOADING,
                WayfairPriceUpload::UPLOADED,
                WayfairPriceUpload::PROCESSING,
            ])
            ->where('created_at', '>=', now()->subMinutes((int) round(
                max(0.0, (float) config('wayfair_upload.base_cost_pull.upload_wait_hours', 4)) * 60
            )))
            ->exists();
    }

    private function lastJson(string $output): ?array
    {
        $lines = preg_split('/\R/', $output) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
