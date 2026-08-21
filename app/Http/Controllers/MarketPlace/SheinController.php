<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\SheinDataView;
use App\Models\SheinDailyData;
use App\Models\SheinDailyDataL60;
use App\Models\ShopifySku;
use App\Services\SheinShopifySalesService;
use App\Services\SheinApiService;
use App\Services\LmpSkuGroupService;
use App\Services\ChannelPromoPricingService;
use App\Models\AmazonChannelSummary;
use App\Models\AmazonDataView;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\Carbon;
class SheinController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Shein margin (0–100) from marketplace_percentages; default 100 when missing.
     */
    private function sheinMarketplaceMarginPercent(): float
    {
        $row = MarketplacePercentage::query()
            ->where('marketplace', 'Shein')
            ->first();

        if (! $row || $row->percentage === null || $row->percentage === '') {
            return 100.0;
        }

        return (float) $row->percentage;
    }

    /**
     * LP and ship from product_master.Values (keys lp, ship); optional model attributes as fallback.
     *
     * @return array{lp: float, ship: float}
     */
    private function lpAndShipFromProductMaster(?ProductMaster $pm): array
    {
        if (! $pm) {
            return ['lp' => 0.0, 'ship' => 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        if (isset($values['lp'])) {
            $lp = (float) $values['lp'];
        } else {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = 0.0;
        if (isset($values['ship'])) {
            $ship = (float) $values['ship'];
        } else {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'ship') {
                    $ship = (float) $v;
                    break;
                }
            }
        }
        if ($ship === 0.0 && isset($pm->ship)) {
            $ship = (float) $pm->ship;
        }

        return ['lp' => $lp, 'ship' => $ship];
    }

    /**
     * Robust SKU normalization for cross-table joins.
     *
     * Folds non-breaking spaces (NBSP / narrow NBSP / \xA0) to regular spaces,
     * strips invalid UTF-8, collapses any internal whitespace runs, then uppercases.
     * Without this, `shein_pricing_prices.sku` rarely matches `product_masters.sku`
     * because Excel/Shein CSV exports leak NBSP and double-spaces, and LP/Ship
     * silently fall back to 0. Mirrors AliexpressController::normalizeAeSkuExact.
     */
    private function normalizeSheinSkuExact(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
    }

    /**
     * Key product_master rows by normalized SKU using a base Collection (not Eloquent\Collection) for safe key lookups.
     */
    private function productMasterByNormalizedSku(): SupportCollection
    {
        $pm = new ProductMaster;
        if (! Schema::hasTable($pm->getTable())) {
            return new SupportCollection;
        }

        return SupportCollection::make(
            ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->all()
        )->keyBy(fn(ProductMaster $r) => $this->normalizeSheinSkuExact((string) $r->sku));
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        // Flatten properly
        $nrValue = is_array($nr) && isset($nr['NR']) ? $nr['NR'] : $nr;

        $dataView = SheinDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        // Save correctly
        $value['NR'] = $nrValue;

        $dataView->value = $value;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => $dataView
        ]);
    }

    /**
     * Sync L30/L60 orders from Shein API into shein_daily_data / shein_daily_data_l60.
     */
    public function syncOrdersFromApi(Request $request, SheinApiService $sheinApi)
    {
        try {
            $target = strtolower((string) $request->input('target', 'l30')) === 'l60' ? 'l60' : 'l30';
            $days = (int) $request->input('days', $target === 'l60' ? 60 : 30);
            $result = $sheinApi->syncOrdersToDailyData($days, $target);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Shein syncOrdersFromApi failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Orders API sync failed: '.$e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Sync price/stock from Shein API into shein_pricing_prices.
     */
    public function syncPricingFromApi(SheinApiService $sheinApi)
    {
        try {
            $result = $sheinApi->syncPricingPricesFromApi();

            return response()->json($result, ($result['success'] ?? false) ? 200 : 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Shein syncPricingFromApi failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Pricing API sync failed: '.$e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Get L60 sales statistics from shein_daily_data_l60 (Shein API sync).
     */
    public function getL60Sales(Request $request)
    {
        try {
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $rows = SheinDailyDataL60::query()
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->get();

            $totalOrders = 0;
            $totalQuantity = 0;
            $totalSales = 0.0;
            foreach ($rows as $row) {
                $orderNum = trim((string) ($row->order_number ?? ''));
                $sellerSku = trim((string) ($row->seller_sku ?? ''));
                if ($orderNum === '' && $sellerSku === '') {
                    continue;
                }
                $totalOrders++;
                $quantity = max(1, (int) ($row->quantity ?? 0));
                $productPrice = (float) ($row->product_price ?? 0);
                // Sales = Product Price × qty (Seller Hub GMV)
                $lineRevenue = $productPrice * $quantity;
                $totalQuantity += $quantity;
                $totalSales += $lineRevenue;
            }

            $totals = [
                'total_sales' => round($totalSales, 2),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity,
            ];

            return response()->json([
                'success' => true,
                'data' => $totals,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Shein L60 sales from shein_daily_data_l60: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily data for Shein tabulator view.
     * Source: Shein Open API sync → shein_daily_data (NOT Shopify / apicentral).
     */
    public function getDailyData(Request $request)
    {
        try {
            $productMasters = $this->productMasterByNormalizedSku();
            $normalizeSku = fn ($v) => $this->normalizeSheinSkuExact((string) $v);

            $data = SheinDailyData::query()
                ->orderByDesc('order_processed_on')
                ->orderByDesc('id')
                ->get()
                ->map(function ($item) use ($productMasters, $normalizeSku) {
                    $key = $item->seller_sku ? $normalizeSku($item->seller_sku) : '';
                    $pm = $key !== '' ? $productMasters->get($key) : null;
                    if (! $pm instanceof ProductMaster) {
                        $pm = null;
                    }
                    $resolved = $this->lpAndShipFromProductMaster($pm);
                    $row = $item->toArray();
                    $row['lp'] = $resolved['lp'];
                    $row['ship'] = $resolved['ship'];
                    // Ensure dates are plain strings for Tabulator
                    foreach (['order_processed_on', 'collection_deadline', 'requested_shipping_time', 'delivery_deadline', 'delivery_time'] as $dateField) {
                        if (! empty($row[$dateField]) && ! is_string($row[$dateField])) {
                            try {
                                $row[$dateField] = Carbon::parse($row[$dateField])->format('Y-m-d H:i:s');
                            } catch (\Throwable $e) {
                                $row[$dateField] = (string) $row[$dateField];
                            }
                        }
                    }

                    return $row;
                })
                ->values()
                ->all();

            Log::info('Shein daily data fetched from shein_daily_data (API sync)', [
                'result_count' => count($data),
            ]);

            return response()->json([
                'data' => $data,
                'source' => 'shein_api',
                'marketplace_margin_decimal' => SheinShopifySalesService::sheinMarginDecimal(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Shein daily data: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Show Shein tabulator view
     */
    public function sheinTabulatorView()
    {
        return view('market-places.shein_tabulator_view');
    }

    /**
     * Save column visibility preferences
     */
    public function saveSheinColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility', []);
            $userId = auth()->id() ?? 'guest';
            
            cache()->put("shein_column_visibility_{$userId}", $visibility, now()->addYear());
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get column visibility preferences
     */
    public function getSheinColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = cache()->get("shein_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }

    // =========================================================================
    // SHEIN PRICING PAGE  (mirrors AliExpress pricing page exactly)
    // =========================================================================

    public function sheinBadgeChartData(\Illuminate\Http\Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'avg_gpft');
            $days = max(0, (int) $request->input('days', 30));

            $validMetrics = [
                'total_pft', 'total_sales', 'avg_gpft', 'avg_roi',
                'total_al30', 'avg_dil', 'total_cogs', 'missing_count', 'map_count', 'nmap_count',
                'total_sku', 'zero_sold', 'more_sold',
            ];
            if (!in_array($metric, $validMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $query = AmazonChannelSummary::where('channel', 'shein')
                ->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }
            $rows = $query->get(['snapshot_date', 'summary_data']);

            $data = [];
            foreach ($rows as $row) {
                $sd    = is_array($row->summary_data)
                       ? $row->summary_data
                       : (json_decode($row->summary_data ?? '{}', true) ?: []);
                $value = (float) ($sd[$metric] ?? 0);
                $data[] = [
                    'date'  => optional($row->snapshot_date)->format('M d'),
                    'value' => $value,
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Shein badge chart data error: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    public function sheinPricingView()
    {
        return view('market-places.shein_pricing_view');
    }

    /**
     * Strip invalid UTF-8 from a string (legacy DB / CSV bytes mis-labeled as UTF-8).
     */
    private function sanitizeUtf8String(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

        return $clean !== false ? $clean : '';
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    private function sanitizeUtf8Recursive($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $key = is_string($k) ? $this->sanitizeUtf8String($k) : $k;
                $out[$key] = $this->sanitizeUtf8Recursive($v);
            }

            return $out;
        }
        if (is_string($data)) {
            return $this->sanitizeUtf8String($data);
        }

        return $data;
    }

    public function getSheinPricingData(Request $request)
    {
        try {
            $normalizeSku = fn($v) => $this->normalizeSheinSkuExact((string) $v);

            // ── 1. All uploaded prices (base SKU list)
            $pricingRows  = \App\Models\SheinPricingPrice::all();
            $pricingBySku = $pricingRows->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 2. Product master → LP / Ship (Support Collection keyed by normalized SKU)
            $pmTable = (new ProductMaster)->getTable();
            $productMasterBySku = new SupportCollection();
            if (Schema::hasTable($pmTable)) {
                $productMasterBySku = SupportCollection::make(
                    ProductMaster::query()
                        ->whereNotNull('sku')->where('sku', '!=', '')
                        ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                        ->get()
                        ->all()
                )->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            // ── 3. Shein sales → al30 / sales from API-synced shein_daily_data
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $salesAgg = new SupportCollection();
            SheinDailyData::query()
                ->whereNotNull('seller_sku')->where('seller_sku', '!=', '')
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->get(['seller_sku', 'quantity', 'product_price', 'estimated_merchandise_revenue'])
                ->each(function ($row) use ($salesAgg, $normalizeSku) {
                    $key = $normalizeSku($row->seller_sku);
                    if ($key === '') {
                        return;
                    }
                    $qty = max(1, (int) ($row->quantity ?? 0));
                    $price = (float) ($row->product_price ?? 0);
                    // Sales = Product Price × qty (Seller Hub GMV)
                    $rev = $price * $qty;
                    $existing = $salesAgg->get($key);
                    if ($existing) {
                        $existing->al30 += $qty;
                        $existing->sales += $rev;
                    } else {
                        $salesAgg->put($key, (object) [
                            'al30' => $qty,
                            'sales' => $rev,
                        ]);
                    }
                });

            // ── 4. Shopify → INV / OV L30
            // Load full tables and key in PHP — SQL UPPER(TRIM(sku)) does not fold NBSP / multi-space variants.
            $shopifyBySku = ShopifySku::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5. SPRICE from shein_data_views
            $viewMetaBySku = SheinDataView::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5b. Buyer / Seller links from shein_listing_statuses
            $linksBySku = \App\Models\SheinListingStatus::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5c. LMP competitor prices/links from shein_lmp
            $lmpBySku = new SupportCollection();
            if (Schema::hasTable('shein_lmp')) {
                $lmpBySku = SupportCollection::make(\App\Models\SheinLmp::all()->all())
                    ->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            $allNormalizedSkus = collect(array_merge(
                $pricingBySku->keys()->all(),
                $productMasterBySku->keys()->all()
            ))->unique()->values();

            $sheinPmSkus = collect($productMasterBySku->map(fn ($pm) => $pm->sku ?? '')->all())
                ->merge($pricingBySku->map(fn ($pr) => $pr->sku ?? '')->all())
                ->filter()
                ->unique()
                ->values()
                ->all();
            $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('shein', $sheinPmSkus);
            $amazonStandardPrices = [];
            foreach (AmazonDataView::whereIn('sku', $sheinPmSkus)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? null;
                if (is_numeric($std) && (float) $std > 0) {
                    $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
                }
            }

            // Sku Link LMP — same shared lmp_sku_links groups as /ebay-tabulator-view
            $lmpGroupService = new LmpSkuGroupService();
            try {
                $prepSkus = [];
                foreach ($productMasterBySku as $pm) {
                    if ($pm && trim((string) ($pm->sku ?? '')) !== '') {
                        $prepSkus[] = (string) $pm->sku;
                    }
                }
                foreach ($pricingBySku as $pr) {
                    if ($pr && trim((string) ($pr->sku ?? '')) !== '') {
                        $prepSkus[] = (string) $pr->sku;
                    }
                }
                $lmpGroupService->prepareForSkus($prepSkus);
            } catch (\Throwable $e) {
                Log::warning('LmpSkuGroupService prepare failed (Shein): ' . $e->getMessage());
            }

            // ── 6. Margin from marketplace_percentages
            $percentage = $this->sheinMarketplaceMarginPercent();
            $margin = $percentage / 100;

            // ── 7. Build rows
            $rows = [];
            foreach ($allNormalizedSkus as $normalizedSku) {
                $priceRow   = $pricingBySku->get($normalizedSku);
                $price      = $priceRow ? (float) $priceRow->price              : 0;
                $origPrice  = $priceRow ? (float) ($priceRow->original_price      ?? 0) : 0;
                $spOffer    = $priceRow ? (float) ($priceRow->special_offer_price  ?? 0) : 0;
                $sheinStock = $priceRow ? (int)   ($priceRow->shein_stock          ?? 0) : 0;

                $productMaster = $productMasterBySku->get($normalizedSku);
                if (! $productMaster instanceof ProductMaster) {
                    $productMaster = null;
                }
                $resolved = $this->lpAndShipFromProductMaster($productMaster);
                $lp   = $resolved['lp'];
                $ship = $resolved['ship'];

                $sale  = $salesAgg->get($normalizedSku);
                $al30  = $sale ? (float) $sale->al30 : 0;
                // Actual L30 revenue from API-synced shein_daily_data.
                // Fall back to theoretical al30 × special_offer only when qty exists but revenue missing.
                $sales = $sale ? (float) ($sale->sales ?? 0) : 0;
                if ($sales <= 0 && $al30 > 0 && $spOffer > 0) {
                    $sales = $al30 * $spOffer;
                }

                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv      ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src      ?? null) : null;

                $metaRecord = $viewMetaBySku->get($normalizedSku);
                $meta       = $metaRecord ? ($metaRecord->value ?? []) : [];
                if (! is_array($meta)) {
                    $meta = [];
                }
                $nr         = $this->resolveSheinNrFromMeta($meta, $productMaster !== null);
                $sprice     = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;

                // Use special_offer_price only for all calculations
                $calcPrice  = $spOffer;
                $profit = ($calcPrice * $margin) - $lp - $ship;
                $gpft   = $calcPrice > 0 ? ($profit / $calcPrice) * 100 : 0;
                $groi   = $lp        > 0 ? ($profit / $lp)         * 100 : 0;
                $sgpft  = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi   = ($sprice > 0 && $lp > 0) ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;

                $displaySku = $productMaster?->sku ?? ($priceRow->sku ?? $normalizedSku);
                $isMissingShein = ! $priceRow || $spOffer <= 0;

                if ($isMissingShein) {
                    $mapValue = '';
                } else {
                    $adiff = abs($inv - $sheinStock);
                    $mapValue = $this->sheinInvWithinMapTolerance((float) $inv, (float) $sheinStock)
                        ? 'Map'
                        : 'N Map|' . (int) round($adiff);
                }

                // Buyer / Seller links
                $linkRecord = $linksBySku->get($normalizedSku);
                $linkVal = $linkRecord
                    ? (is_array($linkRecord->value) ? $linkRecord->value : (json_decode($linkRecord->value, true) ?: []))
                    : [];
                $buyerLink  = $linkVal['buyer_link']  ?? '';
                $sellerLink = $linkVal['seller_link'] ?? '';
                // NR/REQ status — prefer shein_listing_statuses.nr_req (same source as listing-shein),
                // fall back to meta-derived value, then INV-based default.
                $nrReq = $linkVal['nr_req'] ?? $nr ?? ($inv > 0 ? 'REQ' : 'NR');

                // LMP competitor entries merged across Sku Link LMP group (same as ebay-tabulator-view)
                $linkedLmpSkus = $this->sheinLinkedLmpSkusFor($lmpGroupService, (string) $displaySku);
                $lmpEntries = [];
                $seenLmp = [];
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedNorm = $normalizeSku($linkedSku);
                    foreach ($this->sheinLmpEntriesFrom($lmpBySku->get($linkedNorm)) as $entry) {
                        $dedupeKey = ((string) ($entry['price'] ?? '')) . '|' . strtoupper(trim((string) ($entry['link'] ?? '')));
                        if (isset($seenLmp[$dedupeKey])) {
                            continue;
                        }
                        $seenLmp[$dedupeKey] = true;
                        $entry['source_sku'] = $linkedSku;
                        $lmpEntries[] = $entry;
                    }
                }
                $lmpPrice = null;
                $lmpLink  = null;
                foreach ($lmpEntries as $entry) {
                    if ($lmpPrice === null || $entry['price'] < $lmpPrice) {
                        $lmpPrice = $entry['price'];
                        $lmpLink  = $entry['link'];
                    }
                }

                // Std Prc — shared amazon_data_view.STANDARD_PRICE; inherit from Sku Link LMP siblings
                $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $displaySku))] ?? null;
                if ($stdPrc === null && ! empty($linkedLmpSkus)) {
                    foreach ($linkedLmpSkus as $linkedSku) {
                        $linkedKey = strtoupper(trim((string) $linkedSku));
                        if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                            $stdPrc = $amazonStandardPrices[$linkedKey];
                            break;
                        }
                    }
                }

                $row = [
                    'sku'          => trim((string) $displaySku),
                    'parent'       => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent'    => false,
                    'image'        => $imageSrc,
                    'B Link'       => $buyerLink,
                    'S Link'       => $sellerLink,
                    'NR'           => $nr,
                    'nr_req'       => $nrReq,
                    'is_missing_shein' => $isMissingShein,
                    'missing'      => $isMissingShein ? 'M' : '',
                    'map'          => $mapValue,
                    'gpft'         => round($gpft,  2),
                    'groi'         => round($groi,  2),
                    'profit'       => round($profit, 2),
                    'sales'        => round($sales,  2),
                    'al30'         => (int) round($al30),
                    'lp'           => round($lp,   2),
                    'ship'         => round($ship,  2),
                    'sprice'       => round($sprice, 2),
                    'sgpft'        => round($sgpft, 2),
                    'sroi'         => round($sroi,  2),
                    '_margin'      => round($margin, 4),
                    'inv'          => $inv,
                    'shein_stock'      => $sheinStock,
                    'original_price'   => round($origPrice, 2),
                    'special_offer'    => round($spOffer,   2),
                    'calc_price'       => round($calcPrice, 2),
                    'ov_l30'       => $ovL30,
                    'dil_percent'  => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                    'lmp_price'    => $lmpPrice,
                    'lmp_link'     => $lmpLink,
                    'lmp_entries'  => $lmpEntries,
                    'linked_lmp_skus' => $linkedLmpSkus,
                    'STANDARD_PRICE' => $stdPrc,
                ];
                $rows[] = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $displaySku);
            }

            // Sort by parent groups then by SKU
            usort($rows, static function ($a, $b) {
                $pa = (string) ($a['parent'] ?? '');
                $pb = (string) ($b['parent'] ?? '');
                if ($pa === '' && $pb === '') return strnatcasecmp($a['sku'], $b['sku']);
                if ($pa === '') return 1;
                if ($pb === '') return -1;
                $cmp = strnatcasecmp($pa, $pb);
                return $cmp !== 0 ? $cmp : strnatcasecmp($a['sku'], $b['sku']);
            });

            $rows = $this->insertSheinParentRows($rows);
            $rows = $this->sanitizeUtf8Recursive($rows);

            $salesPage = SheinShopifySalesService::computeSalesPageTotals();
            $this->saveSheinPricingSnapshot($rows, $salesPage);

            $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE;
            if (defined('JSON_UNESCAPED_UNICODE')) {
                $jsonFlags |= JSON_UNESCAPED_UNICODE;
            }

            // Wrap rows + sales-page totals so pricing badges match /shein-tabulator
            return response()->json([
                'data' => $rows,
                'sales_page' => $salesPage,
            ], 200, [], $jsonFlags);
        } catch (\Exception $e) {
            Log::error('Shein pricing data error: ' . $e->getMessage());
            $msg = $this->sanitizeUtf8String($e->getMessage());

            return response()->json(['error' => $msg], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    private function insertSheinParentRows(array $rows): array
    {
        $result = []; $group = []; $currentParent = null;
        foreach ($rows as $row) {
            $p = $row['parent'] ?? null;
            $p = ($p !== null && $p !== '') ? (string) $p : null;
            if ($p === null) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildSheinParentRow($currentParent, $group);
                    $group = []; $currentParent = null;
                }
                $result[] = $row;
                continue;
            }
            if ($p !== $currentParent) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildSheinParentRow($currentParent, $group);
                    $group = [];
                }
                $currentParent = $p;
            }
            $group[] = $row;
        }
        if (!empty($group)) {
            foreach ($group as $r) $result[] = $r;
            $result[] = $this->buildSheinParentRow($currentParent, $group);
        }
        return $result;
    }

    /**
     * NR for map/missing rules — mirrors Amazon NRL → NR (REQ vs NR).
     *
     * @param  array<string, mixed>  $meta
     */
    private function resolveSheinNrFromMeta(array $meta, bool $hasProductMaster): ?string
    {
        $nrl = strtoupper(trim((string) ($meta['NRL'] ?? '')));
        if ($nrl === 'NRL') {
            return 'NR';
        }
        if ($nrl === 'REQ') {
            return 'REQ';
        }

        $nr = $meta['NR'] ?? $meta['NRP'] ?? null;
        if (is_bool($nr)) {
            return $nr ? 'NR' : ($hasProductMaster ? 'REQ' : null);
        }
        $nrOut = strtoupper(trim((string) $nr));
        if ($nrOut === 'NR' || $nrOut === 'NRL') {
            return 'NR';
        }
        if ($nrOut === 'REQ' || $nrOut === 'TRUE' || $nrOut === '1') {
            return $nrOut === 'REQ' ? 'REQ' : ($hasProductMaster ? 'REQ' : null);
        }

        return $hasProductMaster ? 'REQ' : null;
    }

    /** INV vs Shein stock = Map if diff ≤ 3 units OR ≤ 3% of Shopify INV (amazon INV vs INV_AMZ). */
    private function sheinInvWithinMapTolerance(float $inv, float $sheinStock): bool
    {
        if ($inv <= 0) {
            return true;
        }
        $diff = abs($inv - $sheinStock);
        if ($diff <= 3.0) {
            return true;
        }

        return $diff <= ($inv * 0.03);
    }

    /**
     * N Map SKU rows from /shein-pricing tabular data — same rules as countSheinPricingBadgeTotals.
     * Used by /map-issues/channel/shein (same pattern as TikTok nmapSkuRowsFromTabular).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float}>
     */
    public static function nmapSkuRowsFromPricing(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $nrValue = strtoupper(trim((string) (($row['nr_req'] ?? '') ?: ($row['NR'] ?? ''))));
            $isMissingShein = (bool) ($row['is_missing_shein'] ?? false)
                || strtoupper(trim((string) ($row['missing'] ?? ''))) === 'M';
            $rowPrice = (float) ($row['special_offer'] ?? 0);
            $sheinStock = (float) ($row['shein_stock'] ?? 0);

            if ($inv <= 0 || $nrValue !== 'REQ') {
                continue;
            }
            if ($isMissingShein || $rowPrice <= 0) {
                continue;
            }
            if ($sheinStock <= 0) {
                continue;
            }

            $diff = abs($inv - $sheinStock);
            $within = $diff <= 3.0 || $diff <= ($inv * 0.03);
            if ($within) {
                continue;
            }

            $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $out[] = [
                'sku' => $sku,
                'channel_sku' => $sku,
                'inv' => $inv,
                'channel_inv' => $sheinStock,
                'diff' => $diff,
            ];
        }

        return $out;
    }

    /**
     * Map / Miss / NMap — same rules as shein_pricing_view badges (ebay2-aligned):
     * Map/NMap only when listed + both INV and Shein stock > 0.
     */
    public static function countSheinPricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $nrValue = strtoupper(trim((string) (($row['nr_req'] ?? '') ?: ($row['NR'] ?? ''))));
            $isMissingShein = (bool) ($row['is_missing_shein'] ?? false)
                || strtoupper(trim((string) ($row['missing'] ?? ''))) === 'M';
            $rowPrice = (float) ($row['special_offer'] ?? 0);
            $sheinStock = (float) ($row['shein_stock'] ?? 0);

            if ($inv <= 0 || $nrValue !== 'REQ') {
                continue;
            }

            if ($isMissingShein || $rowPrice <= 0) {
                $miss++;
                continue;
            }

            // Both sides need stock (same as sheinRowIsListedForMap)
            if ($sheinStock <= 0) {
                continue;
            }

            $diff = abs($inv - $sheinStock);
            $within = $diff <= 3.0 || $diff <= ($inv * 0.03);
            if ($within) {
                $map++;
            } else {
                $nmap++;
            }
        }

        return [
            'map' => $map,
            'miss' => $miss,
            'nmap' => $nmap,
            'total_views' => 0,
        ];
    }

    /**
     * Persist daily summary for badge charts.
     * Sales / GPFT / GROI use /shein-tabulator sales_page totals; other counts use pricing rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $salesPage
     */
    private function saveSheinPricingSnapshot(array $rows, array $salesPage = []): void
    {
        try {
            $today = now()->toDateString();
            $children = collect($rows)->filter(fn ($r) => empty($r['is_parent']));
            if ($children->isEmpty()) {
                return;
            }

            $totalAl30 = 0.0;
            $zeroSold = 0;
            $moreSold = 0;
            $dilSum = 0.0;
            $dilCount = 0;
            $badgeTotals = self::countSheinPricingBadgeTotals($children);
            $missingCount = $badgeTotals['miss'];
            $mapCount = $badgeTotals['map'];
            $nmapCount = $badgeTotals['nmap'];

            foreach ($children as $row) {
                $inv = (float) ($row['inv'] ?? 0);
                $al30 = (float) ($row['al30'] ?? 0);
                $ovL30 = (float) ($row['ov_l30'] ?? 0);

                $totalAl30 += $al30;
                if ($al30 === 0.0) {
                    $zeroSold++;
                } else {
                    $moreSold++;
                }
                if ($inv > 0.0) {
                    $dilSum += ($ovL30 / $inv) * 100;
                    $dilCount++;
                }
            }

            if ($salesPage === []) {
                $salesPage = SheinShopifySalesService::computeSalesPageTotals();
            }

            $totalSku = $children->count();
            $avgDil = $dilCount > 0 ? $dilSum / $dilCount : 0.0;

            $summaryData = [
                'total_sku' => $totalSku,
                'total_sales' => round((float) ($salesPage['total_sales'] ?? 0), 2),
                'total_pft' => round((float) ($salesPage['total_pft'] ?? 0), 2),
                'total_cogs' => round((float) ($salesPage['total_cogs'] ?? 0), 2),
                'total_al30' => (int) ($salesPage['total_quantity'] ?? round($totalAl30)),
                'avg_gpft' => round((float) ($salesPage['pft_percentage'] ?? 0), 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round((float) ($salesPage['roi_percentage'] ?? 0), 2),
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $nmapCount,
                'zero_sold' => $zeroSold,
                'more_sold' => $moreSold,
                'calculated_at' => now()->toDateTimeString(),
            ];

            AmazonChannelSummary::updateOrCreate(
                ['channel' => 'shein', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved Shein pricing snapshot (sales-page Sales/GPFT/GROI)']
            );
        } catch (\Exception $e) {
            Log::error('Shein daily snapshot save failed: '.$e->getMessage());
        }
    }

    private function buildSheinParentRow(string $parentName, array $childRows): array
    {
        $sumInv = $sumOvL30 = $sumSheinStock = $sumAl30 = $sumSales = $sumProfit = 0;
        foreach ($childRows as $r) {
            $sumInv        += (float) ($r['inv']         ?? 0);
            $sumOvL30      += (float) ($r['ov_l30']       ?? 0);
            $sumSheinStock += (float) ($r['shein_stock']  ?? 0);
            $sumAl30       += (float) ($r['al30']         ?? 0);
            $sumSales      += (float) ($r['sales']        ?? 0);
            $sumProfit     += (float) ($r['al30'] ?? 0) * (float) ($r['profit'] ?? 0);
        }
        $key = 'PARENT ' . $parentName;
        return [
            'sku'         => $key,  'parent' => $key,  'is_parent' => true,
            'image'       => null,  'price'  => '-',   'missing'   => '-',
            'map'         => '-',   'gpft'   => $sumSales > 0 ? round(($sumProfit / $sumSales) * 100, 2) : 0,
            'groi'        => '-',   'profit' => round($sumProfit, 2),
            'sales'       => round($sumSales, 2),       'al30'      => (int) round($sumAl30),
            'lp'          => '-',   'ship'   => '-',   'sprice'    => '-',
            'sgpft'       => '-',   'sroi'   => '-',   '_margin'   => '-',
            'inv'         => (int) $sumInv,  'shein_stock' => (int) $sumSheinStock,
            'ov_l30'      => (int) $sumOvL30,
            'dil_percent' => $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0,
            'lmp_price'   => null, 'lmp_link' => null, 'lmp_entries' => [],
            'linked_lmp_skus' => [],
        ];
    }

    /**
     * Sku Link LMP group for a Shein row — same shared service as /ebay-tabulator-view.
     *
     * @return list<string>
     */
    private function sheinLinkedLmpSkusFor(LmpSkuGroupService $lmpGroupService, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        try {
            $group = $lmpGroupService->groupContaining($sku);
        } catch (\Throwable $e) {
            $group = [];
        }

        $members = $group !== [] ? $group : [$sku];
        $seen = [];
        $out = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $display;
        }

        return $out;
    }

    public function saveSheinSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }
            $margin = $this->sheinMarketplaceMarginPercent() / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku    = $update['sku']    ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) continue;
                $sprice = (float) $sprice;

                $n = $this->normalizeSheinSkuExact((string) $sku);
                $pm = null;
                if (Schema::hasTable((new ProductMaster)->getTable())) {
                    // SQL UPPER(TRIM) won't fold NBSP / multi-space variants — match in PHP.
                    $pm = ProductMaster::query()
                        ->whereNotNull('sku')->where('sku', '!=', '')
                        ->get()
                        ->first(fn ($r) => $this->normalizeSheinSkuExact((string) $r->sku) === $n);
                }
                $resolved = $this->lpAndShipFromProductMaster($pm instanceof ProductMaster ? $pm : null);
                $lp   = $resolved['lp'];
                $ship = $resolved['ship'];

                $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

                $view   = SheinDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
                $stored['SPRICE'] = $sprice;
                $stored['SGPFT']  = $sgpft;
                $stored['SROI']   = $sroi;
                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }
            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Exception $e) {
            Log::error('Shein SPRICE save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save buyer / seller links for a SKU into shein_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = \App\Models\SheinListingStatus::where('sku', $sku)->first();
        $existing = $status
            ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?: []))
            : [];

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        \App\Models\SheinListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    /**
     * Build the LMP competitor entries (slot, price, link) from a shein_lmp row.
     * Only non-empty price slots are returned.
     *
     * @return array<int, array{slot:int, price:float, link:string|null}>
     */
    private function sheinLmpEntriesFrom($lmpRow): array
    {
        $entries = [];
        if (! $lmpRow) {
            return $entries;
        }
        for ($i = 1; $i <= 4; $i++) {
            $p = $lmpRow->{'price_' . $i};
            $u = $lmpRow->{'url_' . $i};
            if ($p !== null && (float) $p > 0) {
                $entries[] = [
                    'slot'  => $i,
                    'price' => round((float) $p, 2),
                    'link'  => $u ?: null,
                ];
            }
        }
        return $entries;
    }

    /** Locate an existing shein_lmp row by normalized SKU. */
    private function findSheinLmpRow(string $normalizedSku)
    {
        if (! Schema::hasTable('shein_lmp')) {
            return null;
        }
        return \App\Models\SheinLmp::all()
            ->first(fn($r) => $this->normalizeSheinSkuExact((string) $r->sku) === $normalizedSku);
    }

    /**
     * Add a competitor LMP (price + link) into the next free slot for a SKU.
     * Creates the shein_lmp row if it does not exist yet.
     */
    public function saveLmpEntry(Request $request)
    {
        $sku   = trim((string) $request->input('sku'));
        $price = $request->input('price');
        $link  = trim((string) $request->input('link', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if (! is_numeric($price) || (float) $price <= 0) {
            return response()->json(['success' => false, 'message' => 'A valid price greater than 0 is required'], 422);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Invalid product link URL'], 422);
        }
        if (! Schema::hasTable('shein_lmp')) {
            return response()->json(['success' => false, 'message' => 'shein_lmp table does not exist'], 500);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized) ?? new \App\Models\SheinLmp(['sku' => $sku]);

        // Find the next empty slot (price_1 … price_4).
        $slot = null;
        for ($i = 1; $i <= 4; $i++) {
            if ($row->{'price_' . $i} === null) {
                $slot = $i;
                break;
            }
        }
        if ($slot === null) {
            return response()->json(['success' => false, 'message' => 'Maximum of 4 LMP entries reached for this SKU'], 422);
        }

        $row->{'price_' . $slot} = round((float) $price, 2);
        $row->{'url_' . $slot}   = $link !== '' ? $link : null;
        $row->is_not_found       = false;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP added',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }

    /**
     * Update an existing competitor LMP slot (price + link) for a SKU.
     */
    public function updateLmpEntry(Request $request)
    {
        $sku   = trim((string) $request->input('sku'));
        $slot  = (int) $request->input('slot');
        $price = $request->input('price');
        $link  = trim((string) $request->input('link', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if ($slot < 1 || $slot > 4) {
            return response()->json(['success' => false, 'message' => 'Invalid slot'], 422);
        }
        if (! is_numeric($price) || (float) $price <= 0) {
            return response()->json(['success' => false, 'message' => 'A valid price greater than 0 is required'], 422);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Invalid product link URL'], 422);
        }
        if (! Schema::hasTable('shein_lmp')) {
            return response()->json(['success' => false, 'message' => 'shein_lmp table does not exist'], 500);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'No LMP data found for this SKU'], 404);
        }
        if ($row->{'price_' . $slot} === null) {
            return response()->json(['success' => false, 'message' => 'LMP slot is empty'], 404);
        }

        $row->{'price_' . $slot} = round((float) $price, 2);
        $row->{'url_' . $slot}   = $link !== '' ? $link : null;
        $row->is_not_found       = false;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP updated',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }

    /** Remove a single competitor LMP slot for a SKU. */
    public function deleteLmpEntry(Request $request)
    {
        $sku  = trim((string) $request->input('sku'));
        $slot = (int) $request->input('slot');

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if ($slot < 1 || $slot > 4) {
            return response()->json(['success' => false, 'message' => 'Invalid slot'], 422);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'No LMP data found for this SKU'], 404);
        }

        $row->{'price_' . $slot} = null;
        $row->{'url_' . $slot}   = null;

        $hasPrice = false;
        for ($i = 1; $i <= 4; $i++) {
            if ($row->{'price_' . $i} !== null) {
                $hasPrice = true;
                break;
            }
        }
        $row->is_not_found = ! $hasPrice;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP removed',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }
}
