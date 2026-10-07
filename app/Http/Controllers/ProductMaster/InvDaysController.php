<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonOrder;
use App\Models\ForecastAnalysisHistory;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Inv Days": every CP Master SKU from the same product table as Inv<30 Days,
 * with Days Exp = (INV / OVL30) * 30, sorted highest to lowest.
 */
class InvDaysController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('inv-days', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $pushAtBySku = $this->latestShopifyPushAtBySku();
            $lastSaleBySku = $this->lastSaleDateBySku();
            $ovl30BySku = $this->ovl30BySku();
            $amazonSheets = $this->amazonSheetsByLookupKey();
            $clearanceBySku = $this->clearanceBySku();
            $nrpBySku = $this->forecastNrpBySku();
            $mslBySku = $this->forecastMslBySku();

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) ($product['SKU'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }

                $inv = (float) ($product['shopify_inv'] ?? 0);
                $ovl30 = $this->ovl30ForSku($sku, $ovl30BySku, $product);
                $salePrice = $this->amazonSalePrice($sku, $amazonSheets);
                $skuKey = $this->skuKey($sku);
                $clearance = $clearanceBySku[$skuKey] ?? null;
                $pushAt = $pushAtBySku[$skuKey] ?? null;
                $ageEnd = $inv <= 0
                    ? ($lastSaleBySku[$skuKey] ?? null)
                    : Carbon::now('America/New_York')->toDateTimeString();

                $rows[] = [
                    'id' => $product['id'] ?? null,
                    'image' => $product['image_path'] ?? null,
                    'parent' => $product['Parent'] ?? '',
                    'sku' => $sku,
                    'inv' => $inv,
                    'inv_value' => $salePrice !== null ? (int) round($salePrice * $inv) : null,
                    'ovl30' => $ovl30,
                    'dil' => InvUnder30DaysController::dilPercent($inv, $ovl30),
                    'age_days' => $this->wholeDaysBetween($pushAt, $ageEnd),
                    'days_exp' => ($inv <= 0 && $ovl30 <= 0)
                        ? null
                        : (InvUnder30DaysController::daysExp($inv, $ovl30) ?? 99999),
                    'clearance' => $clearance['value'] ?? 'NO',
                    'clearance_has_history' => $clearance !== null,
                    'msl' => $mslBySku[$this->forecastSkuKey($sku)] ?? 0,
                    'nr' => $nrpBySku[$this->forecastSkuKey($sku)] ?? 'REQ',
                ];
            }

            usort($rows, static function (array $a, array $b): int {
                $ad = $a['days_exp'];
                $bd = $b['days_exp'];
                // Missing Days Exp is stored as 99999 and sorts as that number.
                if ($ad === null && $bd === null) {
                    return 0;
                }
                if ($ad === null) {
                    return -1;
                }
                if ($bd === null) {
                    return 1;
                }

                return $bd <=> $ad;
            });

            return response()->json([
                'status' => 200,
                'data' => array_values($rows),
            ]);
        } catch (\Throwable $e) {
            Log::error('Inv Days data failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to load Inv Days data.',
                'data' => [],
            ], 500);
        }
    }

    public function toggleClearance(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '' || stripos($sku, 'PARENT') === 0) {
            return response()->json(['message' => 'SKU is required.'], 422);
        }

        [$name, $userId] = $this->clearanceActor($request);

        try {
            $saved = DB::transaction(function () use ($sku, $name, $userId) {
                $current = DB::table('inv_days_clearances')->where('sku_key', $this->skuKey($sku))->lockForUpdate()->first();
                $from = ($current && strtoupper((string) $current->value) === 'YES') ? 'YES' : 'NO';

                return $this->writeClearance($sku, $from === 'YES' ? 'NO' : 'YES', $name, $userId, $current);
            });
        } catch (\Throwable $e) {
            Log::error('Inv Days clearance toggle failed: '.$e->getMessage());

            return response()->json(['message' => 'Unable to save clearance.'], 500);
        }

        return response()->json([
            'status' => 200,
            'clearance' => $saved,
            'clearance_has_history' => true,
        ]);
    }

    public function bulkClearance(Request $request)
    {
        $value = strtoupper(trim((string) $request->input('value', '')));
        if (! in_array($value, ['YES', 'NO'], true)) {
            return response()->json(['message' => 'Choose Yes or NO.'], 422);
        }

        $skus = $request->input('skus', []);
        if (! is_array($skus)) {
            return response()->json(['message' => 'Select at least one row.'], 422);
        }

        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '' && stripos($sku, 'PARENT') !== 0)));

        if ($skus === []) {
            return response()->json(['message' => 'Select at least one row.'], 422);
        }

        [$name, $userId] = $this->clearanceActor($request);

        try {
            $updated = DB::transaction(function () use ($skus, $value, $name, $userId) {
                $byKey = [];
                foreach ($skus as $sku) {
                    $key = $this->skuKey($sku);
                    if ($key !== '') {
                        $byKey[$key] = $sku;
                    }
                }

                $existing = collect();
                foreach (array_chunk(array_keys($byKey), 500) as $chunk) {
                    $existing = $existing->merge(
                        DB::table('inv_days_clearances')->whereIn('sku_key', $chunk)->get()
                    );
                }
                $existing = $existing->keyBy('sku_key');

                $now = now();
                $inserts = [];
                $logs = [];
                $updateIds = [];
                foreach ($byKey as $key => $sku) {
                    $current = $existing->get($key);
                    $from = ($current && strtoupper((string) $current->value) === 'YES') ? 'YES' : 'NO';
                    if ($from === $value) {
                        continue;
                    }
                    if ($current) {
                        $updateIds[] = $current->id;
                    } else {
                        $inserts[] = [
                            'sku_key' => $key,
                            'sku' => $sku,
                            'value' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    $logs[] = [
                        'sku_key' => $key,
                        'sku' => $sku,
                        'from_value' => $from,
                        'to_value' => $value,
                        'changed_by' => $name,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($updateIds, 500) as $chunk) {
                    DB::table('inv_days_clearances')->whereIn('id', $chunk)->update([
                        'value' => $value,
                        'updated_at' => $now,
                    ]);
                }
                foreach (array_chunk($inserts, 400) as $chunk) {
                    DB::table('inv_days_clearances')->insert($chunk);
                }
                foreach (array_chunk($logs, 400) as $chunk) {
                    DB::table('inv_days_clearance_logs')->insert($chunk);
                }

                return count($logs);
            });
        } catch (\Throwable $e) {
            Log::error('Inv Days bulk clearance failed: '.$e->getMessage());

            return response()->json(['message' => 'Unable to save clearance.'], 500);
        }

        return response()->json([
            'status' => 200,
            'clearance' => $value,
            'updated' => $updated,
            'clearance_has_history' => true,
        ]);
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function clearanceActor(Request $request): array
    {
        $user = $request->user();
        $name = trim((string) ($user->name ?? ''));
        if ($name === '') {
            $name = trim((string) ($user->email ?? 'Unknown'));
        }

        return [$name, $user?->id];
    }

    private function writeClearance(string $sku, string $to, string $name, ?int $userId, ?object $current): string
    {
        $key = $this->skuKey($sku);
        $from = ($current && strtoupper((string) $current->value) === 'YES') ? 'YES' : 'NO';
        $now = now();

        if ($current) {
            DB::table('inv_days_clearances')->where('id', $current->id)->update([
                'sku' => $sku,
                'value' => $to,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('inv_days_clearances')->insert([
                'sku_key' => $key,
                'sku' => $sku,
                'value' => $to,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('inv_days_clearance_logs')->insert([
            'sku_key' => $key,
            'sku' => $sku,
            'from_value' => $from,
            'to_value' => $to,
            'changed_by' => $name,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $to;
    }

    public function clearanceYesSkus()
    {
        if (! Schema::hasTable('inv_days_clearances')) {
            return response()->json(['skus' => []]);
        }

        $skus = DB::table('inv_days_clearances')
            ->where('value', 'YES')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn ($sku) => $sku !== '' && stripos($sku, 'PARENT') !== 0)
            ->values();

        return response()->json(['skus' => $skus]);
    }

    public function clearanceHistory(Request $request)
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['message' => 'SKU is required.', 'data' => []], 422);
        }

        if (! Schema::hasTable('inv_days_clearance_logs')) {
            return response()->json(['status' => 200, 'sku' => $sku, 'data' => []]);
        }

        $rows = DB::table('inv_days_clearance_logs')
            ->where('sku_key', $this->skuKey($sku))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['from_value', 'to_value', 'changed_by', 'created_at']);

        $data = $rows->map(function ($row) {
            $at = $row->created_at;
            try {
                $at = $at ? Carbon::parse($at)->timezone('America/New_York')->format('j M Y, g:i A') : null;
            } catch (\Throwable $e) {
                $at = (string) $row->created_at;
            }

            return [
                'from_value' => $row->from_value,
                'to_value' => $row->to_value,
                'changed_by' => $row->changed_by,
                'created_at' => $at,
            ];
        })->values();

        return response()->json([
            'status' => 200,
            'sku' => $sku,
            'data' => $data,
        ]);
    }

    public function bulkNrp(Request $request)
    {
        $value = strtoupper(trim((string) $request->input('value', '')));
        if (! in_array($value, ['REQ', 'NR', 'LATER'], true)) {
            return response()->json(['message' => 'Choose REQ, NR, or LATER.'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['message' => 'Select at least one row.'], 422);
        }

        if (! Schema::hasTable('forecast_analysis')) {
            return response()->json(['message' => 'Forecast data is not available.'], 500);
        }

        $byKey = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                continue;
            }
            $key = $this->forecastSkuKey($sku);
            if ($key === '' || isset($byKey[$key])) {
                continue;
            }
            $byKey[$key] = [
                'sku' => $sku,
                'parent' => trim((string) ($item['parent'] ?? '')),
            ];
        }

        if ($byKey === []) {
            return response()->json(['message' => 'Select at least one row.'], 422);
        }

        $userName = trim((string) ($request->user()->name ?? ''));
        if ($userName === '') {
            $userName = trim((string) ($request->user()->email ?? 'N/A'));
        }

        try {
            $updated = DB::transaction(function () use ($byKey, $value, $userName) {
                $existing = DB::table('forecast_analysis')->whereNotNull('sku')->get(['id', 'sku', 'parent', 'nr', 'stage']);
                $grouped = [];
                foreach ($existing as $row) {
                    $key = $this->forecastSkuKey((string) $row->sku);
                    if ($key === '') {
                        continue;
                    }
                    $grouped[$key][] = $row;
                }

                $count = 0;
                $now = now();
                foreach ($byKey as $key => $item) {
                    $rows = $grouped[$key] ?? [];
                    $current = $this->pickedForecastNr($rows);
                    if ($rows === []) {
                        DB::table('forecast_analysis')->insert([
                            'sku' => $item['sku'],
                            'parent' => $item['parent'] !== '' ? $item['parent'] : null,
                            'nr' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        DB::table('forecast_analysis')
                            ->whereIn('id', array_map(static fn ($row) => $row->id, $rows))
                            ->update(['nr' => $value, 'updated_at' => $now]);
                    }

                    if ($current !== $value && Schema::hasTable('forecast_analysis_history')) {
                        ForecastAnalysisHistory::insert([
                            'sku' => $item['sku'],
                            'parent' => $item['parent'] !== '' ? $item['parent'] : null,
                            'field' => 'nr',
                            'old_value' => $current,
                            'new_value' => $value,
                            'updated_by' => $userName !== '' ? $userName : 'N/A',
                            'updated_at' => $now,
                        ]);
                    }
                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            Log::error('Inv Days bulk NRP failed: '.$e->getMessage());

            return response()->json(['message' => 'Unable to save NRP.'], 500);
        }

        return response()->json([
            'status' => 200,
            'nr' => $value,
            'updated' => $updated,
        ]);
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function pickedForecastNr(array $rows): string
    {
        $picked = null;
        foreach ($rows as $row) {
            if (trim((string) ($row->stage ?? '')) !== '') {
                $picked = $row;
                break;
            }
        }
        if (! $picked) {
            foreach ($rows as $row) {
                if (trim((string) ($row->nr ?? '')) !== '') {
                    $picked = $row;
                    break;
                }
            }
        }
        $picked = $picked ?? ($rows[0] ?? null);
        $nr = strtoupper(trim((string) ($picked->nr ?? '')));

        return in_array($nr, ['REQ', 'NR', 'LATER'], true) ? $nr : 'REQ';
    }

    /**
     * MSL from the same Forecast Analysis row builder (/forecast.analysis).
     *
     * @return array<string, int>
     */
    private function forecastMslBySku(): array
    {
        try {
            $rows = app(ForecastAnalysisController::class)->getForecastAnalysisSnapshotRows();
        } catch (\Throwable $e) {
            Log::warning('Inv Days MSL from forecast analysis failed: '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($rows as $item) {
            if ($item->is_parent ?? false) {
                continue;
            }
            $key = $this->forecastSkuKey((string) ($item->SKU ?? ''));
            if ($key === '') {
                continue;
            }
            $msl = $item->msl ?? 0;
            $map[$key] = is_numeric($msl) ? (int) round((float) $msl) : 0;
        }

        return $map;
    }

    /**
     * NRP from forecast_analysis.nr, matched the same way as /forecast.analysis.
     * REQ = green, NR (2BDC) = red, LATER = yellow. Missing rows display as REQ.
     *
     * @return array<string, string>
     */
    private function forecastNrpBySku(): array
    {
        if (! Schema::hasTable('forecast_analysis')) {
            return [];
        }

        $query = DB::table('forecast_analysis')->whereNotNull('sku');
        if (Schema::hasColumn('forecast_analysis', 'archived_at')) {
            $query->whereNull('archived_at');
        }

        $grouped = [];
        foreach ($query->get(['sku', 'nr', 'stage']) as $row) {
            $key = $this->forecastSkuKey((string) $row->sku);
            if ($key === '') {
                continue;
            }
            $grouped[$key][] = $row;
        }

        $map = [];
        foreach ($grouped as $key => $group) {
            $picked = null;
            foreach ($group as $row) {
                if (trim((string) ($row->stage ?? '')) !== '') {
                    $picked = $row;
                    break;
                }
            }
            if (! $picked) {
                foreach ($group as $row) {
                    if (trim((string) ($row->nr ?? '')) !== '') {
                        $picked = $row;
                        break;
                    }
                }
            }
            $picked = $picked ?? $group[0];
            $nr = strtoupper(trim((string) ($picked->nr ?? '')));
            if (! in_array($nr, ['REQ', 'NR', 'LATER'], true)) {
                $nr = 'REQ';
            }
            $map[$key] = $nr;
        }

        return $map;
    }

    private function forecastSkuKey(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku) ?? $sku;

        return trim($sku);
    }

    /**
     * @return array<string, array{value: string}>
     */
    private function clearanceBySku(): array
    {
        if (! Schema::hasTable('inv_days_clearances')) {
            return [];
        }

        $map = [];
        foreach (DB::table('inv_days_clearances')->get(['sku_key', 'value']) as $row) {
            $key = (string) $row->sku_key;
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'value' => strtoupper((string) $row->value) === 'YES' ? 'YES' : 'NO',
            ];
        }

        return $map;
    }

    /**
     * Amazon listing sale price from amazon_datsheets.price, matched the same way as the Amazon page.
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AmazonDatasheet>>  $amazonSheets
     */
    private function amazonSalePrice(string $sku, $amazonSheets): ?float
    {
        $key = AmazonDatasheet::normalizeSkuForLookup($sku);
        if ($key === '') {
            return null;
        }

        $sheet = AmazonDatasheet::pickBestForProductSku($sku, $amazonSheets->get($key));
        if (! $sheet || ! is_numeric($sheet->price)) {
            return null;
        }

        $price = (float) $sheet->price;

        return $price > 0 ? $price : null;
    }

    /**
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, AmazonDatasheet>>
     */
    private function amazonSheetsByLookupKey()
    {
        return AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['id', 'sku', 'price', 'updated_at'])
            ->groupBy(fn ($row) => AmazonDatasheet::normalizeSkuForLookup((string) $row->sku));
    }

    /**
     * Overall L30 units. Prefer Shopify order lines (shopify_raw_orders), which include
     * Amazon orders pushed into Shopify. shopify_skus.quantity is only a fallback when
     * that order table is missing — the inventory sync can store 0 after a partial fetch.
     *
     * @return array<string, int>|null
     */
    private function ovl30BySku(): ?array
    {
        try {
            [$start, $end] = AmazonOrder::dailySalesL30Window(
                \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS
            );

            return ShopifySku::soldUnitsByNormalizedSku($start, $end);
        } catch (\Throwable $e) {
            Log::warning('Inv Days OVL30 from Shopify orders failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, int>|null  $ovl30BySku
     */
    private function ovl30ForSku(string $sku, ?array $ovl30BySku, array $product): float
    {
        if ($ovl30BySku === null) {
            return (float) ($product['shopify_quantity'] ?? 0);
        }

        return (float) ShopifySku::soldUnitsForSku($sku, $ovl30BySku);
    }

    /**
     * Push time and last-sale time used to compute Inv Days age.
     *
     * @return array{push: array<string, string>, last_sale: array<string, string>}
     */
    public function ageDaySources(): array
    {
        return [
            'push' => $this->latestShopifyPushAtBySku(),
            'last_sale' => $this->lastSaleDateBySku(),
        ];
    }

    /**
     * Same age clock as /amazon-tabulator-view, keyed by normalized SKU.
     * Pages that do not send age_days still apply Age Disc from this map.
     *
     * @return array<string, int>
     */
    public function ageDayMap(): array
    {
        return Cache::remember('inv_days_age_map_v1', 120, function () {
            $sources = $this->cachedAgeSources();
            $out = [];
            ShopifySku::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select(['id', 'sku', 'inv'])
                ->chunkById(2000, function ($rows) use (&$out, $sources) {
                    foreach ($rows as $row) {
                        $days = $this->ageDays(
                            (string) $row->sku,
                            (float) ($row->inv ?? 0),
                            $sources['push'],
                            $sources['last_sale']
                        );
                        if ($days === null) {
                            continue;
                        }
                        $out[$this->skuKey((string) $row->sku)] = $days;
                    }
                });

            return $out;
        });
    }

    public function ageMap()
    {
        try {
            return response()->json([
                'success' => true,
                'age_days' => $this->ageDayMap(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Age day map failed: '.$e->getMessage());

            return response()->json(['success' => false, 'age_days' => []], 500);
        }
    }

    /**
     * Amazon Dil and CVR inputs for every analytics page.
     * Each value is [A L30, Sess30, units L60, sessions L60, OV L30, Shopify INV].
     */
    public function amazonStdMap()
    {
        try {
            return response()->json([
                'success' => true,
                'metrics' => $this->amazonStdMetricMap(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Amazon std metric map failed: '.$e->getMessage());

            return response()->json(['success' => false, 'metrics' => []], 500);
        }
    }

    /**
     * @return array{a30:float,s30:float,a60:float,s60:float,ov:float,inv:float}|null
     */
    public function amazonStdMetricFor(string $sku): ?array
    {
        $hit = $this->amazonStdMetricMap()[$this->skuKey($sku)] ?? null;
        if (! is_array($hit) || count($hit) < 6) {
            return null;
        }

        return [
            'a30' => (float) $hit[0],
            's30' => (float) $hit[1],
            'a60' => (float) $hit[2],
            's60' => (float) $hit[3],
            'ov' => (float) $hit[4],
            'inv' => (float) $hit[5],
        ];
    }

    /**
     * Same Dil and CVR inputs as /amazon-tabulator-view, keyed by normalized SKU.
     *
     * @return array<string, array{0:float,1:float,2:float,3:float,4:float,5:float}>
     */
    public function amazonStdMetricMap(): array
    {
        return Cache::remember('inv_days_amazon_std_map_v1', 120, function () {
            $sheets = AmazonDatasheet::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['id', 'sku', 'sessions_l30', 'sessions_l60', 'units_ordered_l60', 'updated_at'])
                ->groupBy(fn ($row) => AmazonDatasheet::normalizeSkuForLookup((string) $row->sku));

            $l30Units = [];
            $pushedMissing = [];
            try {
                [$start, $end] = AmazonOrder::dailySalesL30Window(
                    \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS
                );
                $l30Units = AmazonOrder::unitsSoldBySkuForWindow($start, $end);
                $pushedMissing = AmazonOrder::unitsPushedMissingFromShopifyRaw($start, $end);
            } catch (\Throwable $e) {
                Log::warning('Amazon std metric map: L30 units failed', ['error' => $e->getMessage()]);
            }

            $out = [];
            ShopifySku::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select(['id', 'sku', 'inv', 'quantity'])
                ->chunkById(2000, function ($rows) use (&$out, $sheets, $l30Units, $pushedMissing) {
                    foreach ($rows as $row) {
                        $sku = (string) $row->sku;
                        $lookup = AmazonDatasheet::normalizeSkuForLookup($sku);
                        $sheet = AmazonDatasheet::pickBestForProductSku($sku, $sheets->get($lookup) ?? []);
                        $sheetSku = $sheet?->sku ?? null;
                        $a30 = AmazonOrder::unitsSoldForProductSku($sku, $l30Units, $sheetSku);
                        $productCompact = ShopifySku::compactSkuForLookup($sku);
                        $sheetCompact = ShopifySku::compactSkuForLookup($sheetSku !== null ? (string) $sheetSku : '');
                        $ov = (float) ($row->quantity ?? 0);
                        if ($sheetCompact !== '' && $sheetCompact !== $productCompact) {
                            $ov += ShopifySku::ovL30SoldForSku((string) $sheetSku);
                            $ov += (int) ($pushedMissing[$sheetCompact] ?? 0);
                        }
                        $ov += (int) ($pushedMissing[$productCompact] ?? 0);
                        $out[$this->skuKey($sku)] = [
                            (float) $a30,
                            (float) ($sheet?->sessions_l30 ?? 0),
                            (float) ($sheet?->units_ordered_l60 ?? 0),
                            (float) ($sheet?->sessions_l60 ?? 0),
                            $ov,
                            (float) ($row->inv ?? 0),
                        ];
                    }
                });

            return $out;
        });
    }

    /**
     * Age days for one SKU. Sources are loaded once per process.
     */
    public function ageDaysFor(string $sku, float $inv): ?int
    {
        $sources = $this->cachedAgeSources();

        return $this->ageDays($sku, $inv, $sources['push'], $sources['last_sale']);
    }

    /**
     * @return array{push: array<string, string>, last_sale: array<string, string>}
     */
    private function cachedAgeSources(): array
    {
        static $sources = null;
        if ($sources === null) {
            try {
                $sources = $this->ageDaySources();
            } catch (\Throwable $e) {
                $sources = ['push' => [], 'last_sale' => []];
            }
        }

        return $sources;
    }

    /**
     * Whole days since the latest Shopify push. INV 0 stops the clock at the last sale.
     */
    public function ageDays(string $sku, float $inv, array $pushBySku, array $lastSaleBySku): ?int
    {
        $key = $this->skuKey($sku);
        $end = $inv <= 0
            ? ($lastSaleBySku[$key] ?? null)
            : Carbon::now('America/New_York')->toDateTimeString();

        return $this->wholeDaysBetween($pushBySku[$key] ?? null, $end);
    }

    /**
     * Latest successful Shopify push time per SKU (inventory_warehouse.push_status = success).
     *
     * @return array<string, string>
     */
    private function latestShopifyPushAtBySku(): array
    {
        $dates = [];

        if (! Schema::hasTable('inventory_warehouse')) {
            return [];
        }

        $pushes = DB::table('inventory_warehouse')
            ->where('push_status', 'success')
            ->whereNotNull('our_sku')
            ->where('our_sku', '!=', '')
            ->get(['our_sku', 'updated_at', 'created_at']);

        foreach ($pushes as $row) {
            $this->keepLatestDate($dates, (string) $row->our_sku, $row->updated_at ?: $row->created_at);
        }

        return $dates;
    }

    /**
     * Last paid Shopify sale date per SKU. This is when on-hand stock finished
     * for a SKU whose INV is now 0.
     *
     * @return array<string, string>
     */
    private function lastSaleDateBySku(): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }

        $rows = DB::table('shopify_raw_orders')
            ->whereNotNull('order_date')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($query) {
                $query->whereNull('financial_status')
                    ->orWhereNotIn('financial_status', ['refunded', 'voided']);
            })
            ->groupBy('sku')
            ->selectRaw('sku, MAX(order_date) as last_sale')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = $this->skuKey((string) $row->sku);
            $date = (string) $row->last_sale;
            if ($key === '' || $date === '') {
                continue;
            }
            if (! isset($map[$key]) || $date > $map[$key]) {
                $map[$key] = $date;
            }
        }

        return $map;
    }

    /**
     * Whole days from the latest Shopify push to $endAt.
     * Blank when either date is missing, or the end is before the push.
     */
    private function wholeDaysBetween(mixed $startAt, mixed $endAt): ?int
    {
        if ($startAt === null || $startAt === '' || $endAt === null || $endAt === '') {
            return null;
        }

        try {
            $start = Carbon::parse($startAt)->timezone('America/New_York')->startOfDay();
            $end = Carbon::parse($endAt)->timezone('America/New_York')->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        if ($start->greaterThan($end)) {
            return null;
        }

        return (int) abs($start->diffInDays($end));
    }

    private function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return strtolower($sku);
    }

    /**
     * @param  array<string, string>  $dates
     */
    private function keepLatestDate(array &$dates, string $sku, mixed $at): void
    {
        $key = $this->skuKey($sku);
        if ($key === '' || $at === null || $at === '') {
            return;
        }

        $at = (string) $at;
        if (! isset($dates[$key]) || $at > $dates[$key]) {
            $dates[$key] = $at;
        }
    }
}
