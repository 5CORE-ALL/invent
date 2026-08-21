<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\MacysPriceData;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
use App\Services\ChannelPromoPricingService;
use App\Services\PurchasingPowerApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchasingPowerController extends Controller
{
    public function pricingView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 65;

        return view('market-places.purchasing_power_tabulator_view', [
            'mode'         => $mode,
            'demo'         => $demo,
            'ppPercentage' => $percentage,
        ]);
    }

    public function dataJson(Request $request)
    {
        try {
            $response = $this->getViewData($request);
            $data = json_decode($response->getContent(), true);
            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Purchasing Power data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $ppMetrics    = PurchasingPowerProduct::whereIn('sku', $skus)->get()->keyBy(fn ($i) => strtoupper((string) $i->sku));
        $dataViews    = PurchasingPowerDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $amazonData   = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(fn($i) => strtoupper($i->sku));

        // Fallback only: Macy offers export (macys_price_data) — NOT the PP listed price.
        // Correct PP listed price/qty come from MCM OF21 → purchasing_power_products.
        $offerSheetBySku = MacysPriceData::query()
            ->where(function ($q) use ($skus) {
                $upper = array_values(array_unique(array_map(static fn ($s) => strtoupper((string) $s), $skus)));
                $q->whereIn(DB::raw('UPPER(sku)'), $upper)
                    ->orWhereIn(DB::raw('UPPER(offer_sku)'), $upper);
            })
            ->get()
            ->keyBy(function ($item) {
                $key = strtoupper(trim((string) ($item->offer_sku ?: $item->sku)));
                return $key;
            });

        // Sales qty from uploaded purchasing_power_sales (excluding Canceled)
        // Match by offer_sku (= product_masters.sku), NOT product_sku (which is Mirakl internal numeric ID)
        $salesQty = PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
            ->selectRaw('UPPER(offer_sku) as sku_upper, SUM(quantity) as total_qty')
            ->groupBy('sku_upper')
            ->pluck('total_qty', 'sku_upper');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.65;

        // STD PRC — amazon_data_view.STANDARD_PRICE (same source as /faire-pricing Rule)
        $normalizeSku = static function ($value) {
            $v = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', (string) $value);

            return strtoupper(preg_replace('/\s+/u', ' ', trim($v)) ?? '');
        };
        $amazonStandardPrices = AmazonDataView::all()
            ->keyBy(fn ($r) => $normalizeSku($r->sku))
            ->map(function ($r) {
                $val = is_array($r->value) ? $r->value : (json_decode((string) $r->value, true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? null;

                return (is_numeric($std) && floatval($std) > 0) ? round(floatval($std), 2) : 0;
            });

        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('purchasing_power', $skus);

        $result = [];

        foreach ($productMasters as $pm) {
            $sku     = strtoupper($pm->sku);
            $parent  = $pm->parent;

            $shopify   = $shopifyData->get($pm->sku);
            $ppMetric  = $ppMetrics[$sku] ?? $ppMetrics[strtoupper((string) $pm->sku)] ?? null;
            $amazon    = $amazonData[strtoupper($pm->sku)] ?? null;
            $offerSheet = $offerSheetBySku[$sku] ?? null;

            $row = [];
            $row['Parent']      = $parent;
            $row['(Child) sku'] = $pm->sku;

            $row['INV']  = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $row['L30']  = $shopify ? (int) ($shopify->quantity ?? 0) : 0;

            $row['PP L30']   = $salesQty[strtoupper($pm->sku)] ?? $ppMetric->m_l30 ?? 0;

            // Prc / PP Stock: Purchasing Power MCM OF21 listed price
            // (purchasingpowerus-prod.mirakl.net /api/offers → purchasing_power_products).
            // Do not prefer macys_price_data — that is Macy's sheet and shows wrong Prc (e.g. $20.38 vs $12.99).
            $mcmPrice = ($ppMetric && $ppMetric->price !== null && $ppMetric->price !== '')
                ? round((float) $ppMetric->price, 2)
                : null;
            $mcmStock = $ppMetric && $ppMetric->stock !== null
                ? (int) $ppMetric->stock
                : null;

            if ($mcmPrice !== null && $mcmPrice > 0) {
                $row['PP Price'] = $mcmPrice;
                $row['PP Price Source'] = 'PP MCM OF21 → purchasing_power_products.price';
            } elseif ($offerSheet && floatval($offerSheet->price ?? 0) > 0) {
                $row['PP Price'] = round(floatval($offerSheet->price), 2);
                $row['PP Price Source'] = 'Fallback: macys_price_data (Macy offers sheet)';
            } else {
                $row['PP Price'] = round(floatval($ppMetric->price ?? 0), 2);
                $row['PP Price Source'] = $ppMetric
                    ? 'PP MCM OF21 → purchasing_power_products.price'
                    : 'none';
            }

            if ($mcmStock !== null) {
                $row['PP INV'] = $mcmStock;
                $row['PP Stock Source'] = 'PP MCM OF21 → purchasing_power_products.stock';
            } elseif ($offerSheet && $offerSheet->quantity !== null) {
                $row['PP INV'] = (int) $offerSheet->quantity;
                $row['PP Stock Source'] = 'Fallback: macys_price_data.quantity (Macy offers sheet)';
            } else {
                $row['PP INV'] = 0;
                $row['PP Stock Source'] = 'none';
            }

            $row['A Price'] = $amazon ? floatval($amazon->price ?? 0) : null;

            // NR/REQ + SPRICE from PurchasingPowerDataView
            $row['nr_req']          = 'REQ';
            $row['NR']              = '';
            $row['Listed']          = null;
            $row['Live']            = null;
            $row['SPRICE']          = null;
            $row['has_custom_sprice'] = false;
            $row['SPRICE_STATUS']   = null;
            $row['B Link']          = '';
            $row['S Link']          = '';

            if (isset($dataViews[$pm->sku])) {
                $raw = $dataViews[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) {
                    $row['nr_req'] = $raw['nr_req'] ?? 'REQ';
                    $row['NR']     = $raw['NR']     ?? '';
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live']   = isset($raw['Live'])   ? filter_var($raw['Live'],   FILTER_VALIDATE_BOOLEAN) : null;
                    $row['B Link'] = $raw['buyer_link']  ?? '';
                    $row['S Link'] = $raw['seller_link'] ?? '';

                    if (isset($raw['SPRICE'])) {
                        $row['SPRICE']           = floatval($raw['SPRICE']);
                        $row['has_custom_sprice'] = true;
                        $row['SPRICE_STATUS']     = $raw['SPRICE_STATUS'] ?? 'saved';
                    } else {
                        $row['SPRICE'] = isset($dataViews[$pm->sku]) ? 0 : null;
                    }
                }
            }

            // LP / Ship from ProductMaster. Ship is excluded from margin formulas,
            // but Price Rule Apply uses Ship: SPRICE = (STD × (1 − Disc%)) − Ship.
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            $ship = 0;
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = floatval($v);
                }
                if (strtolower((string) $k) === 'ship') {
                    $ship = floatval($v);
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            if ($ship === 0 && isset($pm->ship)) {
                $ship = floatval($pm->ship);
            }

            $price           = floatval($row['PP Price'] ?? 0);
            $units_l30       = floatval($row['PP L30']   ?? 0);

            $row['PP Dil%']    = ($units_l30 && $row['INV'] > 0) ? round($units_l30 / $row['INV'], 2) : 0;
            $row['Total_pft']  = round(($price * $percentage - $lp) * $units_l30, 2);
            $row['Profit']     = $row['Total_pft'];
            $row['T_Sale_l30'] = round($price * $units_l30, 2);
            $row['Sales L30']  = $row['T_Sale_l30'];

            $gpft = $price > 0 ? (($price * $percentage - $lp) / $price) * 100 : 0;
            $row['GPFT%']  = round($gpft, 2);
            $row['PFT %']  = round($gpft, 2);
            $row['ROI%']   = round($lp > 0 ? (($price * $percentage - $lp) / $lp) * 100 : 0, 2);

            $row['percentage']          = $percentage;
            $row['LP_productmaster']    = $lp;
            $row['Ship_productmaster']  = round($ship, 2);
            $normSku = $normalizeSku($pm->sku);
            $row['standard_price'] = isset($amazonStandardPrices[$normSku])
                ? floatval($amazonStandardPrices[$normSku])
                : 0;
            $row['STANDARD_PRICE'] = ($row['standard_price'] ?? 0) > 0 ? $row['standard_price'] : null;

            // SPRICE metrics (Ship excluded from margin math)
            $sprice = $row['SPRICE'] ?? 0;
            $sgpft  = round($sprice > 0 ? (($sprice * $percentage - $lp) / $sprice) * 100 : 0, 2);
            $row['SGPFT'] = $sgpft;
            $row['SPFT']  = $sgpft;
            $row['SROI']  = round($lp > 0 ? (($sprice * $percentage - $lp) / $lp) * 100 : 0, 2);

            $row['image_path'] = $shopify?->image_src ?? ($values['image_path'] ?? ($pm->image_path ?? null));
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $pm->sku);

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Purchasing Power Data Fetched Successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function updateNrReq(Request $request)
    {
        $sku    = trim($request->input('sku'));
        $nrReq  = $request->input('nr_req');

        $dv       = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dv->value) ? $dv->value : (json_decode($dv->value, true) ?? []);
        $existing['nr_req'] = $nrReq;
        $dv->value = $existing;
        $dv->save();

        return response()->json(['success' => true, 'message' => 'NR/REQ updated']);
    }

    /** Save Buyer (B) / Seller (S) links for a SKU into purchasing_power_data_views.value JSON. */
    public function updateLinks(Request $request)
    {
        $validated = $request->validate([
            'sku'         => 'required|string',
            'buyer_link'  => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim($validated['sku']);

        $buyerLink  = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)) . ' must be a valid URL.',
                ], 422);
            }
        }

        $dv       = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dv->value) ? $dv->value : (json_decode($dv->value, true) ?? []);
        $existing['buyer_link']  = $buyerLink;
        $existing['seller_link'] = $sellerLink;
        $dv->value = $existing;
        $dv->save();

        return response()->json([
            'success'     => true,
            'message'     => 'Links saved.',
            'buyer_link'  => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    public function saveSpriceTabulator(Request $request)
    {
        try {
            $sku    = trim($request->input('sku'));
            $sprice = (float) $request->input('sprice');

            if (!$sku || $sprice === null) {
                return response()->json(['error' => 'SKU and SPRICE are required'], 400);
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 65)) : 65;
            $margin     = $percentage / 100;

            $pm = ProductMaster::where('sku', $sku)->first();
            $lp = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                }
                if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
            }

            $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp) / $sprice) * 100, 2) : 0;
            $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp) / $lp)     * 100, 2) : 0;

            // Same pattern as AliExpress
            $view   = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
            $stored = is_array($view->value) ? $view->value
                    : (json_decode($view->value, true) ?: []);

            $stored['SPRICE'] = $sprice;
            $stored['SGPFT']  = $sgpft;
            $stored['SPFT']   = $sgpft;
            $stored['SROI']   = $sroi;

            $view->value = $stored;
            $view->save();

            Log::info('PP SPRICE saved', ['sku' => $sku, 'sprice' => $sprice]);

            return response()->json([
                'success'            => true,
                'spft_percent'       => $sgpft,
                'sroi_percent'       => $sroi,
                'sgpft_percent'      => $sgpft,
                'price_push_success' => false,
                'price_push_message' => 'SPRICE saved. Use Push to send price to Purchasing Power (MCM PRI01).',
            ]);
        } catch (\Exception $e) {
            Log::error('PP SPRICE tabulator save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            if (empty($updates)) return response()->json(['error' => 'No updates provided'], 400);

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 65)) : 65;
            $margin     = $percentage / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku    = $update['sku']    ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) continue;

                $sprice = (float) $sprice;

                $pm = ProductMaster::where('sku', $sku)->first();
                $lp = 0;
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                    }
                    if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
                }

                // Same pattern as AliExpress
                $view   = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                        : (json_decode($view->value, true) ?: []);

                if ($sprice == 0) {
                    unset($stored['SPRICE'], $stored['SPFT'], $stored['SROI'], $stored['SGPFT']);
                } else {
                    $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp) / $sprice) * 100, 2) : 0;
                    $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp) / $lp)     * 100, 2) : 0;

                    $stored['SPRICE'] = $sprice;
                    $stored['SGPFT']  = $sgpft;
                    $stored['SPFT']   = $sgpft;
                    $stored['SROI']   = $sroi;
                }

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json([
                'success'                  => true,
                'updated'                  => $updatedCount,
                'message'                  => "Successfully saved {$updatedCount} SPRICE update(s)",
                'price_push_success_count' => 0,
                'price_push_failed_count'  => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('PP SPRICE batch save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePercentage(Request $request)
    {
        $percent = $request->input('percent');
        if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
            return response()->json(['status' => 400, 'message' => 'Invalid percentage'], 400);
        }

        MarketplacePercentage::updateOrCreate(
            ['marketplace' => 'Purchase'],
            ['percentage'  => $percent]
        );
        Cache::put('pp_marketplace_percentage', $percent, now()->addDays(30));

        return response()->json(['status' => 200, 'message' => 'Percentage updated', 'data' => ['percentage' => $percent]]);
    }

    public function getColumnVisibility(Request $request)
    {
        $key = 'pp_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        return response()->json(Cache::get($key, []));
    }

    public function setColumnVisibility(Request $request)
    {
        $key = 'pp_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        Cache::put($key, $request->input('visibility', []), now()->addDays(365));
        return response()->json(['success' => true]);
    }

    // ==================== SALES PAGE ====================

    public function salesView(Request $request)
    {
        $rawPct = MarketplacePercentage::where('marketplace', 'Purchase')->value('percentage');
        $ppMargin = ($rawPct !== null && (float) $rawPct > 0) ? (float) $rawPct : 65.0;

        return view('market-places.purchasing_power_sales_view', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'ppMargin' => $ppMargin,
        ]);
    }

    public function salesDataJson(Request $request)
    {
        try {
            $rawPct = MarketplacePercentage::where('marketplace', 'Purchase')->value('percentage');
            $percentage = ($rawPct !== null && (float) $rawPct > 0) ? (float) $rawPct : 65.0;

            $todayPst = \Carbon\Carbon::now('America/Los_Angeles');
            $l30Start = $todayPst->copy()->subDays(29)->startOfDay();
            $l30End = $todayPst->copy()->endOfDay();

            /** @var PurchasingPowerApiService $ppApi */
            $ppApi = app(PurchasingPowerApiService::class);
            $result = $ppApi->fetchOrders($l30Start, $l30End);
            $normalizedRows = collect($ppApi->flattenOrdersToLineRows($result['orders'] ?? []));
            $data = $this->mapPurchasingPowerSalesRows($normalizedRows, $percentage, 'pp_mcm_or11');

            return response()->json($data)
                ->header('X-PP-Sales-Source', 'pp_mcm_or11')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Throwable $e) {
            Log::error('PP salesDataJson failed: '.$e->getMessage(), [
                'exception' => get_class($e),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Failed to load Purchasing Power sales: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * L30 / L60 rollups from Purchasing Power MCM OR11 (not apicentral).
     */
    public function salesStats(Request $request)
    {
        $todayPst = \Carbon\Carbon::now('America/Los_Angeles');
        $l30Start = $todayPst->copy()->subDays(29)->startOfDay();
        $l30End = $todayPst->copy()->endOfDay();
        $l60Start = $todayPst->copy()->subDays(59)->startOfDay();
        $l60End = $todayPst->copy()->subDays(30)->endOfDay();

        try {
            /** @var PurchasingPowerApiService $ppApi */
            $ppApi = app(PurchasingPowerApiService::class);

            $aggregate = function (\Carbon\Carbon $start, \Carbon\Carbon $end) use ($ppApi): array {
                $result = $ppApi->fetchOrders($start, $end);
                $lines = $ppApi->flattenOrdersToLineRows($result['orders'] ?? []);
                $revenue = 0.0;
                $qty = 0;
                $orderIds = [];
                foreach ($lines as $line) {
                    $lineQty = max(0, (int) ($line->quantity ?? 0));
                    if ($lineQty <= 0) {
                        continue;
                    }
                    $unit = (float) ($line->unit_price ?? 0);
                    $amount = $line->amount !== null ? (float) $line->amount : ($unit * $lineQty);
                    $revenue += $amount;
                    $qty += $lineQty;
                    if (! empty($line->order_id)) {
                        $orderIds[(string) $line->order_id] = true;
                    } elseif (! empty($line->order_number)) {
                        $orderIds[(string) $line->order_number] = true;
                    }
                }

                return [
                    'revenue' => round($revenue, 2),
                    'qty' => $qty,
                    'orders' => count($orderIds),
                ];
            };

            $l30 = $aggregate($l30Start, $l30End);
            $l60 = $aggregate($l60Start, $l60End);
            $source = 'pp_mcm_or11';
        } catch (\Throwable $e) {
            Log::error('PP salesStats failed: '.$e->getMessage());

            return response()->json([
                'error' => true,
                'message' => 'Failed to load Purchasing Power sales stats: '.$e->getMessage(),
                'l30' => ['revenue' => 0, 'qty' => 0, 'orders' => 0],
                'l60' => ['revenue' => 0, 'qty' => 0, 'orders' => 0],
                'growth_pct' => 0,
                'source' => 'error',
            ], 500);
        }

        $growthPct = $l60['revenue'] > 0
            ? round((($l30['revenue'] - $l60['revenue']) / $l60['revenue']) * 100, 2)
            : 0.0;

        return response()->json([
            'l30' => $l30,
            'l60' => $l60,
            'growth_pct' => $growthPct,
            'source' => $source,
            'l30_window' => [$l30Start->toDateString(), $l30End->toDateString()],
            'l60_window' => [$l60Start->toDateString(), $l60End->toDateString()],
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mapPurchasingPowerSalesRows($rows, float $percentage, string $source = 'pp_mcm_or11')
    {
        $pct = $percentage / 100;
        $skus = $rows->pluck('sku')->filter()->map(fn ($sku) => trim((string) $sku))->unique()->values()->all();
        $productMasters = collect();
        if (! empty($skus)) {
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($pm) => strtoupper(trim((string) $pm->sku)));
        }

        return $rows->map(function ($r) use ($pct, $percentage, $productMasters, $source) {
            $skuKey = strtoupper(trim((string) ($r->sku ?? '')));
            $pm = $skuKey !== '' ? ($productMasters[$skuKey] ?? null) : null;

            // Ship intentionally excluded from Purchasing Power profit formulas.
            $lp = 0.0;
            if ($pm) {
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
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
            }

            $unitPrice = (float) ($r->unit_price ?? 0);
            $qty = max(0, (int) ($r->quantity ?? 0));
            $amount = $r->amount !== null && $r->amount !== ''
                ? (float) $r->amount
                : ($unitPrice * $qty);

            $pftEach = ($unitPrice * $pct) - $lp;
            $pft = round($pftEach * $qty, 2);
            $gpft = $unitPrice > 0 ? round(($pftEach / $unitPrice) * 100, 2) : 0;
            $cogs = round($lp * $qty, 2);
            $groi = $lp > 0 ? round(($pftEach / $lp) * 100, 2) : 0;

            $orderDate = '';
            if (! empty($r->order_date)) {
                try {
                    $orderDate = \Carbon\Carbon::parse($r->order_date)
                        ->timezone('America/Los_Angeles')
                        ->format('m/d/Y');
                } catch (\Throwable $e) {
                    $orderDate = '';
                }
            }

            return [
                'id' => $r->id ?? null,
                'date_created' => $orderDate,
                'order_number' => $r->order_number ?? null,
                'order_id' => $r->order_id ?? ($r->order_number ?? null),
                'status' => $r->status ?? '',
                'product_sku' => $r->sku,
                'mirakl_product_sku' => $r->mirakl_product_sku ?? null,
                'offer_sku' => $r->sku,
                'product_name' => $r->product_name ?? null,
                'quantity' => $qty,
                'unit_price' => round($unitPrice, 2),
                'amount' => round($amount, 2),
                'commission_rule' => $r->commission_rule ?? null,
                'commission' => round((float) ($r->commission ?? 0), 2),
                'amount_transferred' => round((float) ($r->amount_transferred ?? 0), 2),
                'shipping_company' => $r->shipping_company ?? null,
                'tracking_number' => $r->tracking_number ?? null,
                'tracking_url' => $r->tracking_url ?? null,
                'customer' => $r->customer ?? '',
                'city' => $r->city ?? null,
                'state' => $r->state ?? null,
                'country' => $r->country ?? null,
                'category_label' => $r->category_label ?? null,
                'lp' => round($lp, 2),
                'ship' => 0.0, // not used in PP formulas
                'cogs' => $cogs,
                'pft' => $pft,
                'gpft_pct' => $gpft,
                'groi_pct' => $groi,
                'margin_pct' => $percentage,
                '_source' => $source,
            ];
        });
    }
}
