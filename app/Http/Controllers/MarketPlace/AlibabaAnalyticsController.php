<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\AlibabaSheetPrice;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class AlibabaAnalyticsController extends Controller
{
    public const SHEET_HEADERS = ['Product Id', 'SKU', 'Status', 'SKU Price.1', 'SOH', 'Inv Update'];

    public function index(): View
    {
        return view('market-places.alibaba_analytics');
    }

    public function data(): JsonResponse
    {
        $sheetRows = AlibabaSheetPrice::query()
            ->orderBy('sku')
            ->orderBy('product_id')
            ->get();

        $skus = $sheetRows->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);
        $pmByNorm = $this->productMasterByNormalizedSku($skus);

        $children = [];
        foreach ($sheetRows as $row) {
            $sku = (string) $row->sku;
            $pm = $pmByNorm[strtoupper(trim($sku))] ?? null;
            $shopify = $shopifyData->get($sku);
            $inv = (int) ($shopify->inv ?? 0);
            $ovL30 = (int) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0;
            $parent = trim((string) ($pm->parent ?? ''));

            $children[] = [
                'Parent' => $parent,
                'sku' => $sku,
                '(Child) sku' => $sku,
                'product_id' => (string) $row->product_id,
                'status' => $row->status,
                'sku_price' => $row->sku_price !== null ? (float) $row->sku_price : null,
                'soh' => $row->soh !== null ? (int) $row->soh : null,
                'inv_update' => $row->inv_update,
                'INV' => $inv,
                'L30' => $ovL30,
                'ov_l30' => $ovL30,
                'dil_percent' => $dil,
                'is_parent' => false,
                'is_parent_summary' => false,
                'is_parent_row' => false,
            ];
        }

        usort($children, function (array $a, array $b): int {
            $p = strcasecmp((string) $a['Parent'], (string) $b['Parent']);
            if ($p !== 0) {
                return $p;
            }
            $s = strcasecmp((string) $a['sku'], (string) $b['sku']);
            if ($s !== 0) {
                return $s;
            }

            return strcasecmp((string) $a['product_id'], (string) $b['product_id']);
        });

        $rows = collect($this->insertAlibabaParentRows($children));
        $childRows = $rows->where(fn ($r) => empty($r['is_parent_summary']));

        $active = $childRows->where(fn ($r) => strcasecmp((string) ($r['status'] ?? ''), 'Active') === 0)->count();
        $bulk = $childRows->where(fn ($r) => strcasecmp((string) ($r['inv_update'] ?? ''), 'Bulk') === 0)->count();
        $manual = $childRows->where(fn ($r) => strcasecmp((string) ($r['inv_update'] ?? ''), 'Manual') === 0)->count();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $rows->values(),
            'stats' => [
                'total' => $childRows->count(),
                'parents' => $rows->where(fn ($r) => ! empty($r['is_parent_summary']))->count(),
                'active' => $active,
                'bulk' => $bulk,
                'manual' => $manual,
                'soh' => (int) $childRows->sum(fn ($r) => (int) ($r['soh'] ?? 0)),
                'inv' => (int) $childRows->sum(fn ($r) => (int) ($r['INV'] ?? 0)),
                'ov_l30' => (int) $childRows->sum(fn ($r) => (int) ($r['L30'] ?? 0)),
            ],
            'status' => 200,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'excel_file' => 'required|file',
        ]);

        $file = $request->file('excel_file');
        $parsed = $this->parseSheetFile($file->getPathname(), $file->getClientOriginalName());

        if (! empty($parsed['error'])) {
            return response()->json(['success' => false, 'message' => $parsed['error']], 422);
        }

        $saved = $this->upsertSheetRows($parsed['rows']);

        return response()->json([
            'success' => true,
            'message' => "Imported {$saved} Alibaba sheet price row(s). Product Id and SKU were kept the same as the file.",
            'imported' => $saved,
            'skipped' => $parsed['skipped'],
        ]);
    }

    public function export()
    {
        $rows = AlibabaSheetPrice::query()
            ->orderBy('sku')
            ->orderBy('product_id')
            ->get()
            ->map(fn (AlibabaSheetPrice $row) => $this->sheetRowArray($row))
            ->all();

        return $this->downloadXlsx(
            'Alibaba_Analytics_Export_' . date('Y-m-d') . '.xlsx',
            $rows
        );
    }

    public function downloadSample()
    {
        $rows = AlibabaSheetPrice::query()
            ->orderBy('sku')
            ->orderBy('product_id')
            ->limit(10)
            ->get()
            ->map(fn (AlibabaSheetPrice $row) => $this->sheetRowArray($row))
            ->all();

        if ($rows === []) {
            $rows = [
                ['10000043347472', 'XLR 20 PAIR', 'Active', '9.17', '44', 'Bulk'],
                ['10000043326840', 'CAPO RED 4Pk', 'Active', '3.18', '25', 'Bulk'],
                ['10000044189326', 'PARENT SPEAKON ADP 2PCS', 'Active', '6.49', '1149', 'Manual'],
            ];
        }

        return $this->downloadXlsx('Alibaba_Analytics_Sample.xlsx', $rows);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, ProductMaster>
     */
    protected function productMasterByNormalizedSku(array $skus): array
    {
        $upper = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            $skus
        ))));
        if ($upper === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($upper), '?'));
        $map = [];
        ProductMaster::query()
            ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $upper)
            ->get(['sku', 'parent'])
            ->each(function (ProductMaster $pm) use (&$map) {
                $map[strtoupper(trim((string) $pm->sku))] = $pm;
            });

        return $map;
    }

    /**
     * Insert parent summary rows after each Product Master parent group.
     * Parent INV / OV L30 / Dil match /bestbuy-pricing: Dil = OV L30 ÷ INV.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function insertAlibabaParentRows(array $rows): array
    {
        $result = [];
        $group = [];
        $currentParent = null;

        foreach ($rows as $row) {
            $parent = trim((string) ($row['Parent'] ?? ''));
            $parent = $parent !== '' ? $parent : null;

            if ($parent === null) {
                if ($group !== []) {
                    foreach ($group as $child) {
                        $result[] = $child;
                    }
                    $result[] = $this->buildAlibabaParentRow((string) $currentParent, $group);
                    $group = [];
                    $currentParent = null;
                }
                $result[] = $row;
                continue;
            }

            if ($parent !== $currentParent) {
                if ($group !== []) {
                    foreach ($group as $child) {
                        $result[] = $child;
                    }
                    $result[] = $this->buildAlibabaParentRow((string) $currentParent, $group);
                    $group = [];
                }
                $currentParent = $parent;
            }
            $group[] = $row;
        }

        if ($group !== []) {
            foreach ($group as $child) {
                $result[] = $child;
            }
            $result[] = $this->buildAlibabaParentRow((string) $currentParent, $group);
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $childRows
     * @return array<string, mixed>
     */
    protected function buildAlibabaParentRow(string $parentName, array $childRows): array
    {
        $sumInv = 0;
        $sumOvL30 = 0;
        $sumSoh = 0;
        $seenSku = [];

        foreach ($childRows as $row) {
            $skuKey = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($skuKey !== '' && isset($seenSku[$skuKey])) {
                $sumSoh += (int) ($row['soh'] ?? 0);
                continue;
            }
            if ($skuKey !== '') {
                $seenSku[$skuKey] = true;
            }
            $sumInv += (int) ($row['INV'] ?? 0);
            $sumOvL30 += (int) ($row['L30'] ?? 0);
            $sumSoh += (int) ($row['soh'] ?? 0);
        }

        $dil = $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0.0;
        $key = 'PARENT '.$parentName;

        return [
            'Parent' => $key,
            'sku' => $key,
            '(Child) sku' => $key,
            'product_id' => '',
            'status' => '',
            'sku_price' => null,
            'soh' => $sumSoh,
            'inv_update' => '',
            'INV' => $sumInv,
            'L30' => $sumOvL30,
            'ov_l30' => $sumOvL30,
            'dil_percent' => $dil,
            'is_parent' => true,
            'is_parent_summary' => true,
            'is_parent_row' => true,
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, skipped: int, error: string|null}
     */
    public function parseSheetFile(string $path, string $originalName = ''): array
    {
        $extension = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
        $matrix = [];

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($path);
            $matrix = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } else {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                return ['rows' => [], 'skipped' => 0, 'error' => 'Could not read the uploaded file.'];
            }
            $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
            $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
            $first = $lines[0] ?? '';
            $delimiter = (substr_count($first, "\t") >= 2) ? "\t" : ',';
            foreach ($lines as $line) {
                if (trim((string) $line) === '') {
                    continue;
                }
                $matrix[] = str_getcsv($line, $delimiter);
            }
        }

        if ($matrix === []) {
            return ['rows' => [], 'skipped' => 0, 'error' => 'File is empty.'];
        }

        $headerRow = array_shift($matrix);
        $indexes = $this->resolveColumnIndexes($headerRow);
        if ($indexes['product_id'] === null || $indexes['sku'] === null) {
            $seen = implode(', ', array_slice(array_map(fn ($h) => $this->normalizeHeader((string) $h), $headerRow), 0, 12));

            return [
                'rows' => [],
                'skipped' => 0,
                'error' => 'Could not find Product Id and SKU columns. Expected: Product Id, SKU, Status, SKU Price.1, SOH, Inv Update. Seen: [' . $seen . '].',
            ];
        }

        $rows = [];
        $skipped = 0;
        foreach ($matrix as $row) {
            $productId = trim((string) ($row[$indexes['product_id']] ?? ''));
            $sku = (string) ($row[$indexes['sku']] ?? '');
            $sku = trim($sku);
            if ($productId === '' || $sku === '') {
                $skipped++;
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'sku' => $sku,
                'status' => $this->nullableString($row[$indexes['status']] ?? null),
                'sku_price' => $this->nullablePrice($row[$indexes['sku_price']] ?? null),
                'soh' => $this->nullableInt($row[$indexes['soh']] ?? null),
                'inv_update' => $this->nullableString($row[$indexes['inv_update']] ?? null),
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'error' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertSheetRows(array $rows): int
    {
        $saved = 0;

        foreach ($rows as $row) {
            $productId = trim((string) ($row['product_id'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($productId === '' || $sku === '') {
                continue;
            }

            AlibabaSheetPrice::updateOrCreate(
                ['product_id' => $productId],
                [
                    'sku' => $sku,
                    'status' => $row['status'] ?? null,
                    'sku_price' => $row['sku_price'] ?? null,
                    'soh' => $row['soh'] ?? null,
                    'inv_update' => $row['inv_update'] ?? null,
                ]
            );

            if (Schema::hasTable('alibaba_pricing_prices')) {
                $pricing = AlibabaPricingPrice::query()
                    ->where('sku', $sku)
                    ->orWhereRaw('UPPER(sku) = ?', [strtoupper($sku)])
                    ->first();
                if ($pricing) {
                    $pricing->fill([
                        'price' => $row['sku_price'] ?? null,
                        'ab_stock' => $row['soh'] ?? null,
                    ])->save();
                } else {
                    AlibabaPricingPrice::create([
                        'sku' => $sku,
                        'price' => $row['sku_price'] ?? null,
                        'ab_stock' => $row['soh'] ?? null,
                    ]);
                }
            }

            if (Schema::hasTable('alibaba_metrics')) {
                $metric = AlibabaMetric::query()
                    ->where('product_id', $productId)
                    ->where(function ($q) use ($sku) {
                        $q->where('sku', $sku)->orWhereRaw('UPPER(sku) = ?', [strtoupper($sku)]);
                    })
                    ->first();
                if ($metric) {
                    $metric->fill(['sku' => $sku, 'price' => $row['sku_price'] ?? null])->save();
                } else {
                    AlibabaMetric::create([
                        'product_id' => $productId,
                        'sku' => $sku,
                        'price' => $row['sku_price'] ?? null,
                    ]);
                }
            }

            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{product_id: int|null, sku: int|null, status: int|null, sku_price: int|null, soh: int|null, inv_update: int|null}
     */
    protected function resolveColumnIndexes(array $headerRow): array
    {
        $indexes = [
            'product_id' => null,
            'sku' => null,
            'status' => null,
            'sku_price' => null,
            'soh' => null,
            'inv_update' => null,
        ];

        foreach ($headerRow as $i => $header) {
            $key = $this->normalizeHeader((string) $header);
            if ($key === '') {
                continue;
            }

            if ($indexes['product_id'] === null && in_array($key, ['product_id', 'productid', 'id'], true)) {
                $indexes['product_id'] = (int) $i;
            } elseif ($indexes['sku'] === null && $key === 'sku') {
                $indexes['sku'] = (int) $i;
            } elseif ($indexes['status'] === null && $key === 'status') {
                $indexes['status'] = (int) $i;
            } elseif ($indexes['sku_price'] === null && in_array($key, ['sku_price_1', 'sku_price', 'price', 'skuprice', 'skuprice1'], true)) {
                $indexes['sku_price'] = (int) $i;
            } elseif ($indexes['soh'] === null && in_array($key, ['soh', 'stock', 'ab_stock', 'inv', 'inventory'], true)) {
                $indexes['soh'] = (int) $i;
            } elseif ($indexes['inv_update'] === null && in_array($key, ['inv_update', 'inventory_update', 'invupdate'], true)) {
                $indexes['inv_update'] = (int) $i;
            }
        }

        return $indexes;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace(['.', '-'], '_', $header);

        return trim((string) preg_replace('/[^a-z0-9_]+/', '_', $header), '_');
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }

    protected function nullablePrice(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_replace([',', '$'], '', $text);

        return is_numeric($text) ? round((float) $text, 2) : null;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }

        return is_numeric($text) ? (int) $text : null;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}
     */
    protected function sheetRowArray(AlibabaSheetPrice $row): array
    {
        return [
            (string) $row->product_id,
            (string) $row->sku,
            (string) ($row->status ?? ''),
            $row->sku_price !== null ? (string) $row->sku_price : '-',
            $row->soh !== null ? (string) $row->soh : '',
            (string) ($row->inv_update ?? ''),
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    protected function downloadXlsx(string $fileName, array $rows)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::SHEET_HEADERS, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $widths = ['A' => 22, 'B' => 36, 'C' => 12, 'D' => 14, 'E' => 10, 'F' => 14];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
