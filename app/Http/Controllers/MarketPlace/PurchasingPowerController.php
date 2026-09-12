<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerListingStatus;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
use Carbon\Carbon;
use App\Services\ChannelPromoPricingService;
use App\Services\PurchasingPowerApiService;
use App\Support\MacysAmazonPriceCap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        $listingStatusData = [];
        if (Schema::hasTable('purchasing_power_listing_statuses')) {
            $listingStatusData = PurchasingPowerListingStatus::whereIn('sku', $skus)
                ->get()
                ->mapWithKeys(fn ($item) => [strtolower((string) $item->sku) => $item])
                ->all();
        }
        $ppFreshAfter = self::latestMcmFreshAfter();

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
            $row = [];
            $row['Parent']      = $parent;
            $row['(Child) sku'] = $pm->sku;

            $row['INV']  = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $row['L30']  = $shopify ? (int) ($shopify->quantity ?? 0) : 0;

            $row['PP L30']   = $salesQty[strtoupper($pm->sku)] ?? $ppMetric->m_l30 ?? 0;

            // Listed price is only a live PP MCM offer. Leftover product rows and
            // macys_price_data (Macy sheet) are not listed — show 0.
            $listingInactive = self::isListingMarkedInactive(
                $listingStatusData[strtolower((string) $pm->sku)] ?? null
            );
            $resolvedPrice = self::resolveListedPrice(
                $ppMetric,
                self::productInLatestMcm($ppMetric, $ppFreshAfter),
                $listingInactive
            );
            $row['PP Price'] = $resolvedPrice['price'];
            $row['PP Price Source'] = $resolvedPrice['source'];
            $row['is_missing_pp'] = $resolvedPrice['missing'];

            $mcmStock = $ppMetric && $ppMetric->stock !== null
                ? (int) $ppMetric->stock
                : null;
            $row['PP INV'] = $resolvedPrice['listed'] && $mcmStock !== null ? $mcmStock : 0;
            $row['PP Stock Source'] = $resolvedPrice['listed'] && $mcmStock !== null
                ? 'PP MCM OF21 → purchasing_power_products.stock'
                : 'none';

            $row['A Price'] = $amazon ? floatval($amazon->price ?? 0) : null;

            // NR/REQ + SPRICE from PurchasingPowerDataView
            $row['nr_req']          = 'REQ';
            $row['NR']              = '';
            $row['Listed']          = null;
            $row['Live']            = null;
            $row['SPRICE']          = null;
            $row['has_custom_sprice'] = false;
            $row['SPRICE_STATUS']   = null;
            $row['push_status']     = null;
            $row['SPRICE_PUSHED_VALUE'] = null;
            $row['SPRICE_STATUS_UPDATED_AT'] = null;
            $row['SPRICE_PUSHED_BY'] = null;
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
                        $row['SPRICE_STATUS'] = $raw['SPRICE_STATUS'] ?? null;
                    }
                    $row['push_status'] = $row['SPRICE_STATUS'];
                    $row['SPRICE_PUSHED_VALUE'] = isset($raw['SPRICE_PUSHED_VALUE'])
                        ? floatval($raw['SPRICE_PUSHED_VALUE'])
                        : null;
                    $row['SPRICE_STATUS_UPDATED_AT'] = $raw['SPRICE_STATUS_UPDATED_AT'] ?? $raw['SPRICE_PUSHED_AT'] ?? null;
                    $row['SPRICE_PUSHED_BY'] = $raw['SPRICE_PUSHED_BY'] ?? null;
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

    /**
     * @return array{listed: bool, price: float, source: string, missing: bool}
     */
    public static function resolveListedPrice($product, bool $inLatestMcm = true, bool $listingInactive = false): array
    {
        if ($listingInactive || ! $inLatestMcm) {
            return ['listed' => false, 'price' => 0.0, 'source' => '', 'missing' => true];
        }

        if (is_object($product) && isset($product->activated)
            && ! filter_var($product->activated, FILTER_VALIDATE_BOOLEAN)) {
            return ['listed' => false, 'price' => 0.0, 'source' => '', 'missing' => true];
        }

        if (is_object($product)) {
            $status = strtolower(trim((string) ($product->listing_status ?? '')));
            if (in_array($status, ['inactive', 'offline', 'disabled', 'ended', 'unpublished', '0', 'false'], true)) {
                return ['listed' => false, 'price' => 0.0, 'source' => '', 'missing' => true];
            }
        }

        $price = null;
        if (is_object($product)) {
            $raw = $product->price ?? null;
            if ($raw !== null && $raw !== '') {
                $price = floatval($raw);
            }
        } elseif (is_numeric($product)) {
            $price = floatval($product);
        }

        if ($price === null || $price <= 0) {
            return ['listed' => false, 'price' => 0.0, 'source' => '', 'missing' => true];
        }

        return [
            'listed' => true,
            'price' => $price,
            'source' => 'PP MCM OF21 → purchasing_power_products.price',
            'missing' => false,
        ];
    }

    public static function latestMcmFreshAfter(): ?Carbon
    {
        $last = PurchasingPowerProduct::query()->max('updated_at');
        if (! $last) {
            return null;
        }

        return Carbon::parse($last)->subMinutes(15);
    }

    public static function productInLatestMcm(?PurchasingPowerProduct $product, ?Carbon $freshAfter = null): bool
    {
        if (! $product) {
            return false;
        }
        $freshAfter = $freshAfter ?? self::latestMcmFreshAfter();
        if (! $freshAfter || ! $product->updated_at) {
            return false;
        }

        return $product->updated_at->gte($freshAfter);
    }

    public static function isListingMarkedInactive($listingStatus): bool
    {
        if (! $listingStatus) {
            return false;
        }
        $value = is_object($listingStatus)
            ? ($listingStatus->value ?? null)
            : $listingStatus;
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (! is_array($value)) {
            return false;
        }
        $flag = strtolower(trim((string) ($value['live_inactive'] ?? '')));

        return in_array($flag, ['inactive', 'offline', 'ended', 'disabled'], true);
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

            $skipPush = $request->boolean('skip_push') || $sprice <= 0;
            if ($sprice <= 0) {
                $this->persistPpPushStatus($sku, 'cleared', 0);
            } elseif ($skipPush) {
                $this->persistPpPushStatus($sku, 'applied', $sprice);
            }
            $pushResult = $skipPush
                ? ['success' => true, 'message' => 'Saved without marketplace push', 'skipped' => true]
                : $this->pushPriceToPurchasingPower($sku, $sprice);

            return response()->json([
                'success'            => true,
                'spft_percent'       => $sgpft,
                'sroi_percent'       => $sroi,
                'sgpft_percent'      => $sgpft,
                'price_push_success' => (bool) ($pushResult['success'] ?? false),
                'price_push_message' => (string) ($pushResult['message'] ?? ''),
                'price_push_status_code' => $pushResult['status_code'] ?? null,
                'price_push_skipped' => $skipPush,
                'push_status'        => $skipPush
                    ? ($sprice <= 0 ? 'cleared' : 'applied')
                    : ((($pushResult['success'] ?? false) === true) ? 'pushed' : 'error'),
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
            $pricePushQueue = [];
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
                    $stored['SPRICE_STATUS'] = 'cleared';
                    $stored['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
                } else {
                    $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp) / $sprice) * 100, 2) : 0;
                    $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp) / $lp)     * 100, 2) : 0;

                    $stored['SPRICE'] = $sprice;
                    $stored['SGPFT']  = $sgpft;
                    $stored['SPFT']   = $sgpft;
                    $stored['SROI']   = $sroi;
                    $stored['SPRICE_STATUS'] = 'applied';
                    $stored['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
                }

                $view->value = $stored;
                $view->save();
                $updatedCount++;
                if ($sprice > 0) {
                    $pricePushQueue[] = ['sku' => $sku, 'sprice' => $sprice];
                }
            }

            $skipPush = $request->boolean('skip_push');
            $pricePushSuccess = 0;
            $pricePushFailed = 0;
            $pricePushErrors = [];
            $pricePushResults = [];
            $singlePushResult = null;
            foreach ($skipPush ? [] : $pricePushQueue as $pushItem) {
                $pushResult = $this->pushPriceToPurchasingPower($pushItem['sku'], (float) $pushItem['sprice']);
                $ok = ($pushResult['success'] ?? false) === true;
                $pricePushResults[] = [
                    'sku' => $pushItem['sku'],
                    'success' => $ok,
                    'price' => (float) $pushItem['sprice'],
                    'message' => (string) ($pushResult['message'] ?? ''),
                ];
                if (count($pricePushQueue) === 1) {
                    $singlePushResult = $pushResult;
                }
                if ($ok) {
                    $pricePushSuccess++;
                } else {
                    $pricePushFailed++;
                    $pricePushErrors[] = $pushItem['sku'].': '.($pushResult['message'] ?? 'Price push failed');
                }
            }

            $response = [
                'success'                  => true,
                'updated'                  => $updatedCount,
                'message'                  => "Successfully saved {$updatedCount} SPRICE update(s)",
                'price_push_success_count' => $pricePushSuccess,
                'price_push_failed_count'  => $pricePushFailed,
                'price_push_skipped'       => $skipPush,
                'price_push_results'       => $pricePushResults,
            ];
            if ($pricePushErrors !== []) {
                $response['price_push_errors'] = $pricePushErrors;
            }
            if (is_array($singlePushResult)) {
                $response['price_push_success'] = (bool) ($singlePushResult['success'] ?? false);
                $response['price_push_message'] = (string) ($singlePushResult['message'] ?? '');
                $response['price_push_status_code'] = $singlePushResult['status_code'] ?? null;
            }

            return response()->json($response);
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

    public function pushPriceTabulator(Request $request)
    {
        $sku = strtoupper(trim((string) $request->input('sku', '')));
        $price = $request->input('price', $request->input('sprice'));
        if ($sku === '' || ! is_numeric($price) || (float) $price <= 0) {
            return response()->json(['success' => false, 'message' => 'SKU and price required'], 422);
        }

        $applied = MacysAmazonPriceCap::applyForSku($sku, (float) $price);
        $price = (float) $applied['price'];
        $result = $this->pushPriceToPurchasingPower($sku, $price);
        $message = (string) ($result['message'] ?? '');
        if (($result['success'] ?? false) && ($applied['capped'] ?? false)) {
            $message = trim($message.' (raised to Amazon $'.number_format($price, 2).')');
        }

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $message,
            'status_code' => $result['status_code'] ?? null,
            'price' => $price,
            'amazon_price' => $applied['amazon_price'] ?? null,
            'capped' => (bool) ($applied['capped'] ?? false),
            'push_status' => (($result['success'] ?? false) === true) ? 'pushed' : 'error',
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * @return array{success: bool, message: string, status_code?: int|null}
     */
    private function pushPriceToPurchasingPower(string $sku, float $sprice): array
    {
        if ($sprice <= 0) {
            return ['success' => false, 'message' => 'Skipping push for non-positive price'];
        }

        try {
            $result = app(PurchasingPowerApiService::class)->updatePrice($sku, $sprice);
            $this->persistPpPushStatus(
                $sku,
                (($result['success'] ?? false) === true) ? 'pushed' : 'error',
                $sprice
            );

            return $result;
        } catch (\Throwable $e) {
            $this->persistPpPushStatus($sku, 'error', $sprice);
            Log::error('Purchasing Power price push call failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function persistPpPushStatus(string $sku, string $status, ?float $price = null): void
    {
        try {
            $skuKey = strtoupper(trim($sku));
            $dataView = PurchasingPowerDataView::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuKey])
                ->first()
                ?: PurchasingPowerDataView::firstOrNew(['sku' => $skuKey]);
            $existing = is_array($dataView->value)
                ? $dataView->value
                : (json_decode((string) ($dataView->value ?? ''), true) ?: []);
            if (! is_array($existing)) {
                $existing = [];
            }
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
            if ($price !== null) {
                $existing['SPRICE_PUSHED_VALUE'] = round((float) $price, 2);
            }
            if (auth()->check()) {
                $existing['SPRICE_PUSHED_BY'] = auth()->user()->name ?? auth()->user()->email;
                $existing['SPRICE_PUSHED_BY_ID'] = auth()->id();
            }
            $dataView->sku = $dataView->sku ?: $skuKey;
            $dataView->value = $existing;
            $dataView->save();
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power persist push status failed', [
                'sku' => $sku,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
