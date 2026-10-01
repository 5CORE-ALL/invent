<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\AlibabaSheetPrice;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Http\Controllers\Sales\AlibabaSalesController;
use App\Services\AlibabaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
class AlibabaAnalyticsController extends Controller
{
    private const SYNC_CACHE = 'alibaba_analytics_api_sync';

    public function index(): View
    {
        $margin = MarketplacePercentage::takeHomeDecimal('Alibaba');

        return view('market-places.alibaba_analytics', [
            'marginPercent' => round($margin * 100, 2),
        ]);
    }

    public function data(): JsonResponse
    {
        $sheetRows = AlibabaSheetPrice::query()
            ->orderBy('sku')
            ->orderBy('product_id')
            ->get();

        $namesByProduct = $this->productNamesByProductId($sheetRows->pluck('product_id')->filter()->all());
        $displaySkus = [];
        foreach ($sheetRows as $row) {
            $displaySkus[] = $this->skuWithPieceCount((string) $row->sku, $namesByProduct[trim((string) $row->product_id)] ?? '');
        }
        $skus = array_values(array_unique(array_filter(array_merge(
            $sheetRows->pluck('sku')->filter()->all(),
            $displaySkus
        ))));
        $shopifyData = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);
        $pmByNorm = $this->productMasterByNormalizedSku($skus);
        $margin = MarketplacePercentage::takeHomeDecimal('Alibaba');
        $l30 = app(AlibabaSalesController::class)->l30SkuTotals();
        $salesUsed = [];

        $children = [];
        foreach ($sheetRows as $row) {
            $storedSku = trim((string) $row->sku);
            $productId = trim((string) $row->product_id);
            $sku = $this->skuWithPieceCount($storedSku, $namesByProduct[$productId] ?? '');
            $skuKey = strtoupper($sku);
            $pm = $pmByNorm[$skuKey] ?? $pmByNorm[strtoupper($storedSku)] ?? null;
            $shopify = $shopifyData->get($sku) ?? $shopifyData->get($storedSku);
            $inv = (int) ($shopify->inv ?? 0);
            $ovL30 = (int) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0;
            $parent = trim((string) ($pm->parent ?? ''));
            $price = $row->sku_price !== null ? (float) $row->sku_price : 0.0;
            $lp = $pm ? (float) ($pm->unitLandedPrice() ?? 0) : 0.0;
            $bucket = $l30[$skuKey] ?? ['qty' => 0, 'sales' => 0.0];
            if ($skuKey !== '' && isset($salesUsed[$skuKey])) {
                $abL30 = 0;
                $sales = 0.0;
            } else {
                if ($skuKey !== '') {
                    $salesUsed[$skuKey] = true;
                }
                $abL30 = (int) ($bucket['qty'] ?? 0);
                $sales = round((float) ($bucket['sales'] ?? 0), 2);
            }
            $metrics = $this->priceMetrics($price, $lp, $margin);
            $cvr = $ovL30 > 0 ? round(($abL30 / $ovL30) * 100, 2) : 0.0;
            $image = $this->productImage($pm, $shopify);

            $children[] = [
                'Parent' => $parent,
                'parent' => $parent,
                'sku' => $sku,
                '(Child) sku' => $sku,
                'image_path' => $image,
                'image' => $image,
                'product_id' => $productId,
                'status' => $row->status,
                'sku_price' => $row->sku_price !== null ? (float) $row->sku_price : null,
                'soh' => $row->soh !== null ? (int) $row->soh : null,
                'inv_update' => $row->inv_update,
                'INV' => $inv,
                'inv' => $inv,
                'L30' => $ovL30,
                'ov_l30' => $ovL30,
                'dil_percent' => $dil,
                'al30' => $abL30,
                'AB L30' => $abL30,
                'price' => $price,
                'gpft' => $metrics['gpft'],
                'groi' => $metrics['roi'],
                'GPFT%' => $metrics['gpft'],
                'ROI%' => $metrics['roi'],
                'profit' => $metrics['profit_each'],
                'sales' => $sales,
                'lp' => round($lp, 2),
                'cvr' => $cvr,
                '_margin' => $margin,
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

    public function sync(Request $request, AlibabaApiService $api): JsonResponse
    {
        @set_time_limit(180);

        $page = max(1, (int) $request->input('page', 1));
        $result = $this->syncApiPage($api, $page, $request->boolean('reset') || $page === 1);
        $status = ! empty($result['success']) ? 200 : 422;

        return response()->json($result, $status);
    }

    /**
     * One page of /alibaba/icbu/product/list plus product detail. Used by the page and the scheduler.
     *
     * @return array{success: bool, message: string, page: int, page_size?: int, saved?: int, total_item?: int|null, synced?: int, done: bool}
     */
    public function syncApiPage(AlibabaApiService $api, int $page, bool $reset): array
    {
        $page = max(1, $page);
        $pageSize = 8;
        if ($reset || $page === 1) {
            Cache::put(self::SYNC_CACHE, ['ids' => []], now()->addHours(6));
        }

        $list = $api->listIcbuProducts($page, $pageSize);
        if (empty($list['success'])) {
            return [
                'success' => false,
                'message' => $list['message'] ?? 'Alibaba product list failed.',
                'page' => $page,
                'done' => true,
            ];
        }

        $state = Cache::get(self::SYNC_CACHE, ['ids' => []]);
        $ids = is_array($state['ids'] ?? null) ? $state['ids'] : [];
        $saved = 0;

        foreach ($list['products'] as $brief) {
            if (! is_array($brief)) {
                continue;
            }
            $productId = trim((string) ($brief['id'] ?? $brief['product_id'] ?? ''));
            $row = $api->icbuAnalyticsRow($productId);
            if ($row === null) {
                continue;
            }

            AlibabaSheetPrice::updateOrCreate(
                ['product_id' => $row['product_id']],
                [
                    'sku' => $row['sku'],
                    'status' => $row['status'],
                    'sku_price' => $row['sku_price'],
                    'soh' => $row['soh'],
                    'inv_update' => null,
                ]
            );

            if (Schema::hasTable('alibaba_metrics')) {
                AlibabaMetric::updateOrCreate(
                    ['sku' => $row['sku']],
                    array_filter([
                        'product_id' => $row['product_id'],
                        'price' => $row['sku_price'],
                    ], static fn ($value) => $value !== null)
                );
            }
            if (Schema::hasTable('alibaba_pricing_prices') && $row['sku_price'] !== null) {
                AlibabaPricingPrice::updateOrCreate(
                    ['sku' => $row['sku']],
                    array_filter([
                        'price' => $row['sku_price'],
                        'ab_stock' => $row['soh'],
                    ], static fn ($value) => $value !== null)
                );
            }

            $ids[] = $row['product_id'];
            $saved++;
        }

        $ids = array_values(array_unique($ids));
        $total = $list['total_item'];
        $fetched = count($list['products']);
        $done = $fetched === 0 || $fetched < $pageSize || ($total !== null && ($page * $pageSize) >= $total);

        if ($done && $ids !== []) {
            AlibabaSheetPrice::query()->whereNotIn('product_id', $ids)->delete();
        }

        Cache::put(self::SYNC_CACHE, ['ids' => $ids], now()->addHours(6));

        return [
            'success' => true,
            'page' => $page,
            'page_size' => $pageSize,
            'saved' => $saved,
            'total_item' => $total,
            'synced' => count($ids),
            'done' => $done,
            'message' => $done
                ? 'Loaded '.count($ids).' products from the Alibaba API.'
                : 'API page '.$page.' saved '.$saved.' product(s).',
        ];
    }

    /**
     * @param  array<int, mixed>  $productIds
     * @return array<string, string>
     */
    protected function productNamesByProductId(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $productIds
        ))));
        if ($ids === [] || ! Schema::hasTable('alibaba_metrics')) {
            return [];
        }

        $names = [];
        foreach (AlibabaMetric::query()->whereIn('product_id', $ids)->get(['product_id', 'sku', 'product_name']) as $metric) {
            $id = trim((string) $metric->product_id);
            $text = trim((string) ($metric->product_name ?? ''));
            $sku = trim((string) ($metric->sku ?? ''));
            $names[$id] = trim($text.' '.$sku);
        }

        return $names;
    }

    protected function skuWithPieceCount(string $sku, string $hint): string
    {
        $sku = trim($sku);
        if ($sku === '' || preg_match('/\b\d+\s*pcs?\b/i', $sku) === 1) {
            return $sku;
        }
        if (preg_match('/\b(\d+)\s*(?:pcs?|pieces?)\b/i', $hint, $match) !== 1) {
            return $sku;
        }

        return trim($sku.' '.$match[1].'PCS');
    }

    /**
     * Unit profit = (price × Alibaba margin) − LP. Ship is not subtracted.
     *
     * @return array{profit_each: float, gpft: float, roi: float}
     */
    protected function priceMetrics(float $price, float $lp, float $margin): array
    {
        $profitEach = ($price * $margin) - $lp;

        return [
            'profit_each' => round($profitEach, 2),
            'gpft' => $price > 0 ? round(($profitEach / $price) * 100, 2) : 0.0,
            'roi' => $lp > 0 ? round(($profitEach / $lp) * 100, 2) : 0.0,
        ];
    }

    protected function productImage(?ProductMaster $pm, mixed $shopify): ?string
    {
        $shopifyImage = is_object($shopify) ? ($shopify->image_src ?? null) : null;
        if (is_string($shopifyImage) && trim($shopifyImage) !== '') {
            return $shopifyImage;
        }
        if (! $pm) {
            return null;
        }
        $values = $this->productValues($pm);
        $path = $values['image_path'] ?? ($pm->image_path ?? null);

        return is_string($path) && trim($path) !== '' ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function productValues(ProductMaster $pm): array
    {
        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

        return is_array($values) ? $values : [];
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
            ->get()
            ->each(function (ProductMaster $pm) use (&$map) {
                $map[strtoupper(trim((string) $pm->sku))] = $pm;
            });

        return $map;
    }

    /**
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
        $sumAbL30 = 0;
        $sumSales = 0.0;
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
            $sumAbL30 += (int) ($row['al30'] ?? 0);
            $sumSales += (float) ($row['sales'] ?? 0);
        }

        $dil = $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0.0;
        $key = 'PARENT '.$parentName;

        return [
            'Parent' => $key,
            'parent' => $parentName,
            'sku' => $key,
            '(Child) sku' => $key,
            'image_path' => null,
            'image' => null,
            'product_id' => '',
            'status' => '',
            'sku_price' => null,
            'soh' => $sumSoh,
            'inv_update' => '',
            'INV' => $sumInv,
            'inv' => $sumInv,
            'L30' => $sumOvL30,
            'ov_l30' => $sumOvL30,
            'dil_percent' => $dil,
            'al30' => $sumAbL30,
            'AB L30' => $sumAbL30,
            'price' => null,
            'gpft' => null,
            'groi' => null,
            'GPFT%' => null,
            'ROI%' => null,
            'profit' => null,
            'sales' => round($sumSales, 2),
            'lp' => null,
            'cvr' => 0,
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

}
