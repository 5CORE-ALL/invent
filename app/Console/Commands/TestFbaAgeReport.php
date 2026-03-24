<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Probe FBA inventory data and expose every available age/health column.
 *
 * Three sources are tried in order:
 *   1. FBA Inventory REST API v1       (/fba/inventory/v1/summaries) — instant, always works
 *   2. GET_FBA_INVENTORY_PLANNING_DATA — THE correct age bucket report (from Amazon docs)
 *      Columns: inv-age-0-to-90-days, inv-age-91-to-180-days, inv-age-181-to-270-days,
 *               inv-age-271-to-365-days, inv-age-365-plus-days, units-shipped-t7/t30/t60/t90,
 *               estimated-storage-cost-next-month, recommended-action, sell-through, etc.
 *   3. GET_FBA_RECOMMENDED_REMOVAL_DATA — age-bucket removal recommendations
 *
 * NOTE: GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA is a legacy/deprecated type — do NOT use it.
 *
 * Run:
 *   php artisan fba:test-age-report
 *   php artisan fba:test-age-report --source=api       # only FBA Inventory REST API
 *   php artisan fba:test-age-report --source=planning  # FBA Manage Inventory Health (age data)
 *   php artisan fba:test-age-report --source=removal   # FBA Recommended Removal (age buckets)
 *   php artisan fba:test-age-report --rows=10 --save
 */
class TestFbaAgeReport extends Command
{
    protected $signature = 'fba:test-age-report
        {--source=all  : Which source to test: all | api | planning | removal}
        {--rows=5      : Number of sample rows/items to display}
        {--save        : Save raw responses to storage/app/}';

    protected $description = 'Test FBA inventory age/health data — checks REST API + report types';

    private const MAX_WAIT    = 600;
    private const POLL_SECS   = 20;

    public function handle(): int
    {
        $source = $this->option('source');
        $rows   = (int) $this->option('rows');

        $this->banner('FBA Inventory Age / Health — Data Structure Explorer');

        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('❌  Cannot obtain access token. Check SPAPI_* env vars.');
            return 1;
        }
        $this->info('✅  Access token obtained.');

        $endpoint      = config('services.amazon_sp.endpoint');
        $marketplaceId = config('services.amazon_sp.marketplace_id');

        if (in_array($source, ['all', 'api'])) {
            $this->line('');
            $this->runInventoryApi($token, $endpoint, $marketplaceId, $rows);
        }

        if (in_array($source, ['all', 'planning'])) {
            $this->line('');
            $this->runReport($token, $endpoint, $marketplaceId, 'GET_FBA_INVENTORY_PLANNING_DATA', $rows);
        }

        if (in_array($source, ['all', 'removal'])) {
            $this->line('');
            $this->runReport($token, $endpoint, $marketplaceId, 'GET_FBA_RECOMMENDED_REMOVAL_DATA', $rows);
        }

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  SOURCE 1 — FBA Inventory REST API v1  (instant, no polling)
    // ══════════════════════════════════════════════════════════════════════════

    private function runInventoryApi(string $token, string $endpoint, string $marketplaceId, int $rows): void
    {
        $this->banner('SOURCE 1 — FBA Inventory REST API v1  (/fba/inventory/v1/summaries)', '=');

        $allItems  = [];
        $nextToken = null;

        do {
            // SP-API requires repeated scalar params, not PHP array notation
            $qs = http_build_query([
                'granularityType' => 'Marketplace',
                'granularityId'   => $marketplaceId,
                'details'         => 'true',
            ]);
            $qs .= '&marketplaceIds=' . urlencode($marketplaceId);
            if ($nextToken) {
                $qs .= '&nextToken=' . urlencode($nextToken);
            }

            $res = Http::withHeaders(['x-amz-access-token' => $token])
                ->get("{$endpoint}/fba/inventory/v1/summaries?{$qs}");

            if ($res->failed()) {
                $this->error('  API call failed (' . $res->status() . '): ' . $res->body());
                return;
            }

            $payload   = $res->json();
            $items     = $payload['payload']['inventorySummaries'] ?? [];
            $allItems  = array_merge($allItems, $items);
            $nextToken = $payload['pagination']['nextToken'] ?? null;

            $this->line('  Page fetched: ' . count($items) . ' items. (Total so far: ' . count($allItems) . ')');

        } while ($nextToken);

        if (empty($allItems)) {
            $this->warn('  ⚠️  No inventory items returned.');
            return;
        }

        $this->info('  ✅  Total items: ' . count($allItems));

        // Print full field structure from first item
        $first = $allItems[0];
        $this->line('');
        $this->info('  Full field structure (from first item):');
        $this->printNested($first, '    ');

        // Show sample rows
        $this->line('');
        $this->info("  Sample items (first {$rows}):");
        $this->line('  ' . str_repeat('─', 60));

        foreach (array_slice($allItems, 0, $rows) as $idx => $item) {
            $this->line('  ── Item ' . ($idx + 1) . ' ──');
            $this->printNested($item, '    ');
            $this->line('');
        }

        if ($this->option('save')) {
            $path = storage_path('app/fba_inventory_api.json');
            file_put_contents($path, json_encode($allItems, JSON_PRETTY_PRINT));
            $this->info("  💾  Saved {$path}");
        }
    }

    private function printNested(array $data, string $indent = ''): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line("{$indent}{$key}:");
                $this->printNested($value, $indent . '  ');
            } else {
                $display = ($value === null) ? '(null)' : (string) $value;
                $this->line("{$indent}{$key}: {$display}");
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  SOURCE 2 & 3 — Reports API (TSV, polled)
    // ══════════════════════════════════════════════════════════════════════════

    private function runReport(string $token, string $endpoint, string $marketplaceId, string $reportType, int $rows): void
    {
        $this->banner("SOURCE — Report: {$reportType}", '=');

        // Create
        $this->line("  Creating report…");
        $payload = ['reportType' => $reportType, 'marketplaceIds' => [$marketplaceId]];
        $res = Http::withHeaders([
            'x-amz-access-token' => $token,
            'Content-Type'       => 'application/json',
        ])->post("{$endpoint}/reports/2021-06-30/reports", $payload);

        if ($res->failed()) {
            $this->error("  ❌ Create failed ({$res->status()}): {$res->body()}");
            return;
        }

        $reportId = $res->json()['reportId'] ?? null;
        $this->info("  Report ID: {$reportId}");

        // Poll
        $waited = 0;
        $statusData = null;
        while ($waited < self::MAX_WAIT) {
            sleep(self::POLL_SECS);
            $waited += self::POLL_SECS;

            $poll = Http::withHeaders(['x-amz-access-token' => $token])
                ->get("{$endpoint}/reports/2021-06-30/reports/{$reportId}");

            if ($poll->failed()) {
                $this->error("  Poll failed ({$poll->status()})");
                return;
            }

            $statusData = $poll->json();
            $status     = $statusData['processingStatus'] ?? 'UNKNOWN';
            $this->line("  [{$waited}s] status = {$status}");

            if ($status === 'DONE') break;

            if (in_array($status, ['FATAL', 'CANCELLED'])) {
                $this->error("  ❌ Report ended: {$status}");
                $this->line('  Response: ' . json_encode($statusData, JSON_PRETTY_PRINT));
                return;
            }
        }

        if (($statusData['processingStatus'] ?? '') !== 'DONE') {
            $this->error("  ❌ Timed out after {$waited}s.");
            return;
        }

        $docId = $statusData['reportDocumentId'] ?? null;
        if (!$docId) {
            $this->warn('  ⚠️  DONE but no document ID (empty report).');
            return;
        }

        // Download
        $docRes = Http::withHeaders(['x-amz-access-token' => $token])
            ->get("{$endpoint}/reports/2021-06-30/documents/{$docId}");

        if ($docRes->failed()) {
            $this->error("  ❌ Document fetch failed ({$docRes->status()})");
            return;
        }

        $meta = $docRes->json();
        $url  = $meta['url'] ?? null;
        if (!$url) {
            $this->error('  ❌ No URL in document metadata.');
            return;
        }

        $raw = Http::timeout(120)->get($url)->body();
        if (($meta['compressionAlgorithm'] ?? null) === 'GZIP') {
            $raw = gzdecode($raw);
        }

        $this->info('  ✅  Report downloaded.');

        if ($this->option('save')) {
            $slug = strtolower(str_replace(['GET_', '_'], ['', '-'], $reportType));
            $path = storage_path("app/fba_{$slug}.tsv");
            file_put_contents($path, $raw);
            $this->info("  💾  Saved: {$path}");
        }

        $this->displayTsv($raw, $rows, $reportType);
    }

    private function displayTsv(string $raw, int $maxRows, string $reportType): void
    {
        $lines = array_values(array_filter(explode("\n", trim($raw)), fn($l) => trim($l) !== ''));

        if (empty($lines)) {
            $this->warn('  ⚠️  Empty report body.');
            return;
        }

        $headers = str_getcsv(array_shift($lines), "\t");
        $total   = count($lines);

        $this->info("  Total rows : {$total}");
        $this->info("  Columns (" . count($headers) . "):");

        // Highlight age-related columns
        $ageCols = [];
        foreach ($headers as $i => $h) {
            $isAge = preg_match('/age|ltsf|long.term|storage|removal|health/i', $h);
            $tag   = $isAge ? ' ◀ AGE/HEALTH' : '';
            if ($isAge) $ageCols[] = $h;
            $this->line("    [" . str_pad($i, 2, '0', STR_PAD_LEFT) . "] {$h}{$tag}");
        }

        if ($ageCols) {
            $this->line('');
            $this->info('  Age / health related columns found: ' . implode(', ', $ageCols));
        }

        $this->line('');
        $this->info("  Sample rows (first {$maxRows} of {$total}):");
        $this->line('  ' . str_repeat('─', 60));

        foreach (array_slice($lines, 0, $maxRows) as $idx => $line) {
            $values = str_getcsv($line, "\t");
            $this->line('  ── Row ' . ($idx + 1) . ' ──');
            foreach ($headers as $i => $h) {
                $val = $values[$i] ?? '(n/a)';
                $this->line("    {$h}: {$val}");
            }
            $this->line('');
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  Shared helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function getAccessToken(): ?string
    {
        try {
            $res = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => config('services.amazon_sp.refresh_token'),
                'client_id'     => config('services.amazon_sp.client_id'),
                'client_secret' => config('services.amazon_sp.client_secret'),
            ]);
            return $res->ok() ? ($res->json()['access_token'] ?? null) : null;
        } catch (\Throwable $e) {
            $this->error('Token error: ' . $e->getMessage());
            return null;
        }
    }

    private function banner(string $title, string $char = '─'): void
    {
        $line = str_repeat($char, 60);
        $this->line('');
        $this->info($line);
        $this->info("  {$title}");
        $this->info($line);
    }
}
