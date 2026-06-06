<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
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
        $ppMetrics    = PurchasingPowerProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $dataViews    = PurchasingPowerDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $amazonData   = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(fn($i) => strtoupper($i->sku));

        // Sales qty from uploaded purchasing_power_sales (excluding Canceled)
        // Match by offer_sku (= product_masters.sku), NOT product_sku (which is Mirakl internal numeric ID)
        $salesQty = PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
            ->selectRaw('UPPER(offer_sku) as sku_upper, SUM(quantity) as total_qty')
            ->groupBy('sku_upper')
            ->pluck('total_qty', 'sku_upper');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.65;

        $result = [];

        foreach ($productMasters as $pm) {
            $sku     = strtoupper($pm->sku);
            $parent  = $pm->parent;

            $shopify   = $shopifyData->get($pm->sku);
            $ppMetric  = $ppMetrics[$pm->sku] ?? null;
            $amazon    = $amazonData[strtoupper($pm->sku)] ?? null;

            $row = [];
            $row['Parent']      = $parent;
            $row['(Child) sku'] = $pm->sku;

            $row['INV']  = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $row['L30']  = $shopify ? (int) ($shopify->quantity ?? 0) : 0;

            $row['PP L30']   = $salesQty[strtoupper($pm->sku)] ?? $ppMetric->m_l30 ?? 0;
            $row['PP Price'] = $ppMetric->price ?? 0;
            $row['PP INV']   = $ppMetric->stock ?? 0;

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

            // LP / Ship from ProductMaster
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') { $lp = floatval($v); break; }
            }
            if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $price           = floatval($row['PP Price'] ?? 0);
            $units_l30       = floatval($row['PP L30']   ?? 0);

            $row['PP Dil%']    = ($units_l30 && $row['INV'] > 0) ? round($units_l30 / $row['INV'], 2) : 0;
            $row['Total_pft']  = round(($price * $percentage - $lp - $ship) * $units_l30, 2);
            $row['Profit']     = $row['Total_pft'];
            $row['T_Sale_l30'] = round($price * $units_l30, 2);
            $row['Sales L30']  = $row['T_Sale_l30'];

            $gpft = $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0;
            $row['GPFT%']  = round($gpft, 2);
            $row['PFT %']  = round($gpft, 2);
            $row['ROI%']   = round($lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);

            $row['percentage']          = $percentage;
            $row['LP_productmaster']    = $lp;
            $row['Ship_productmaster']  = $ship;

            // SPRICE metrics
            $sprice = $row['SPRICE'] ?? 0;
            $sgpft  = round($sprice > 0 ? (($sprice * $percentage - $lp - $ship) / $sprice) * 100 : 0, 2);
            $row['SGPFT'] = $sgpft;
            $row['SPFT']  = $sgpft;
            $row['SROI']  = round($lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);

            $row['image_path'] = $shopify?->image_src ?? ($values['image_path'] ?? ($pm->image_path ?? null));

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
            $ship = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                }
                if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
            }

            $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
            $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

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
                'price_push_message' => 'No API push configured for Purchasing Power',
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
                $ship = 0;
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                    }
                    if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
                }

                // Same pattern as AliExpress
                $view   = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                        : (json_decode($view->value, true) ?: []);

                if ($sprice == 0) {
                    unset($stored['SPRICE'], $stored['SPFT'], $stored['SROI'], $stored['SGPFT']);
                } else {
                    $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                    $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

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
        // Sourced from `apicentral.shopify_order_items` so this page stays in sync with the
        // Shopify Orders dashboard and the all-marketplace-master Purchasing Power row.
        // Identification mirrors the shopify-orders page: source_name / tags contain
        // "purchasing power".
        //
        // Window: last 30 calendar days in Pacific time, INCLUSIVE — exactly matches the
        // L30 window used by getPurchasingPowerChannelData() / computePurchasingPowerMetricsFromShopify()
        // on the all-marketplace-master page so the totals on both pages match.
        $todayPst   = \Carbon\Carbon::now('America/Los_Angeles');
        $l30Start   = $todayPst->copy()->subDays(29)->startOfDay();
        $l30End     = $todayPst->copy()->endOfDay();

        $rows = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->whereBetween('order_date', [$l30Start, $l30End])
            ->where(function ($q) {
                $q->where('source_name', 'LIKE', '%purchasing power%')
                  ->orWhere('source_name', 'LIKE', '%purchasingpower%')
                  ->orWhere('tags', 'LIKE', '%Purchasing Power%')
                  ->orWhere('tags', 'LIKE', '%PurchasingPower%');
            })
            ->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $rawPct = MarketplacePercentage::where('marketplace', 'Purchase')->value('percentage');
        $percentage = ($rawPct !== null && (float) $rawPct > 0) ? (float) $rawPct : 65.0;
        $pct = $percentage / 100;

        $skus = $rows->pluck('sku')->filter()->map(fn ($sku) => trim((string) $sku))->unique()->values()->all();
        $productMasters = collect();
        if (!empty($skus)) {
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($pm) => strtoupper(trim((string) $pm->sku)));
        }

        $data = $rows->map(function ($r) use ($pct, $percentage, $productMasters) {
            $skuKey = strtoupper(trim((string) ($r->sku ?? '')));
            $pm = $skuKey !== '' ? ($productMasters[$skuKey] ?? null) : null;

            $lp = 0.0;
            $ship = 0.0;
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
                    if (isset($values['ship'])) {
                        $ship = (float) $values['ship'];
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
                if ($ship === 0.0 && isset($pm->ship)) {
                    $ship = (float) $pm->ship;
                }
            }

            $unitPrice = (float) ($r->price ?? 0);
            $qty       = max(0, (int) ($r->quantity ?? 0));
            $amount    = $unitPrice * $qty;
            $pftEach   = ($unitPrice * $pct) - $lp - $ship;
            $pft       = round($pftEach * $qty, 2);
            $gpft      = $unitPrice > 0 ? round(($pftEach / $unitPrice) * 100, 2) : 0;
            $cogs      = round($lp * $qty, 2);
            $groi      = $lp > 0 ? round(($pftEach / $lp) * 100, 2) : 0;

            $orderDate = null;
            if (!empty($r->order_date)) {
                try {
                    $orderDate = \Carbon\Carbon::parse($r->order_date)
                        ->timezone('America/Los_Angeles')
                        ->format('m/d/Y');
                } catch (\Throwable $e) {
                    $orderDate = '';
                }
            }

            $status = $r->financial_status ?: ($r->fulfillment_status ?: '');

            return [
                'id'                   => $r->id ?? null,
                'date_created'         => $orderDate ?: '',
                'order_number'         => $r->order_number,
                'order_id'             => $r->order_id ?? ($r->order_number ?? null),
                'status'               => $status,
                'product_sku'          => $r->sku,
                'mirakl_product_sku'   => null,
                'offer_sku'            => $r->sku,
                'product_name'         => $r->product_title ?? null,
                'quantity'             => $qty,
                'unit_price'           => round($unitPrice, 2),
                'amount'               => round($amount, 2),
                'commission_rule'      => null,
                'commission'           => 0.0,
                'amount_transferred'   => 0.0,
                'shipping_company'     => $r->tracking_company ?? null,
                'tracking_number'      => $r->tracking_number ?? null,
                'tracking_url'         => null,
                'customer'             => $r->customer_name ?? '',
                'city'                 => $r->shipping_city ?? null,
                'state'                => $r->shipping_province ?? null,
                'country'              => $r->shipping_country ?? null,
                'category_label'       => null,
                'lp'                   => round($lp, 2),
                'ship'                 => round($ship, 2),
                'cogs'                 => $cogs,
                'pft'                  => $pft,
                'gpft_pct'             => $gpft,
                'groi_pct'             => $groi,
                'margin_pct'           => $percentage,
            ];
        });

        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Returns L30 and L60 sales rollups for the Purchasing Power page badges. Same
     * windows / identification as `getPurchasingPowerChannelData()` on
     * /all-marketplace-master, so the two pages always agree.
     */
    public function salesStats(Request $request)
    {
        $ppWhere = function ($q) {
            $q->where('source_name', 'LIKE', '%purchasing power%')
              ->orWhere('source_name', 'LIKE', '%purchasingpower%')
              ->orWhere('tags', 'LIKE', '%Purchasing Power%')
              ->orWhere('tags', 'LIKE', '%PurchasingPower%');
        };

        $todayPst  = \Carbon\Carbon::now('America/Los_Angeles');
        $l30Start  = $todayPst->copy()->subDays(29)->startOfDay();
        $l30End    = $todayPst->copy()->endOfDay();
        $l60Start  = $todayPst->copy()->subDays(59)->startOfDay();
        $l60End    = $todayPst->copy()->subDays(30)->endOfDay();

        $aggregate = function (\Carbon\Carbon $start, \Carbon\Carbon $end) use ($ppWhere) {
            $row = DB::connection('apicentral')
                ->table('shopify_order_items')
                ->whereBetween('order_date', [$start, $end])
                ->where($ppWhere)
                ->where('quantity', '>', 0)
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue, COALESCE(SUM(quantity), 0) as qty, COUNT(DISTINCT order_number) as orders')
                ->first();
            return [
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'qty'     => (int) ($row->qty ?? 0),
                'orders'  => (int) ($row->orders ?? 0),
            ];
        };

        $l30 = $aggregate($l30Start, $l30End);
        $l60 = $aggregate($l60Start, $l60End);
        $growthPct = $l60['revenue'] > 0
            ? round((($l30['revenue'] - $l60['revenue']) / $l60['revenue']) * 100, 2)
            : 0.0;

        return response()->json([
            'l30' => $l30,
            'l60' => $l60,
            'growth_pct' => $growthPct,
            'l30_window' => [$l30Start->toDateString(), $l30End->toDateString()],
            'l60_window' => [$l60Start->toDateString(), $l60End->toDateString()],
        ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
