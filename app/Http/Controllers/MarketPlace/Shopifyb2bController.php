<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\GoogleSkuCompetitor;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifyB2BDailyData;
use App\Models\ShopifyB2BDataView;
use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use App\Services\ChannelPromoPricingService;
use App\Services\StorePricePushService;
use App\Services\LmpSkuGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Shopifyb2bController extends Controller
{
    /**
     * Business Analytics tabulator (Shopify B2B; mirrors /shopify-b2c-pricing).
     * Sales from shopify_b2b_daily_data (same source as /shopify-b2b/daily-sales).
     * INV / L30 from shopify_skus (same as other marketplaces).
     * Price / views from FleetCart store_listing_prices (business5core.com).
     */
    public function shopifyB2bTabulatorView()
    {
        $snapshot = $this->getShopifyB2bL30Snapshot();

        return view('market-places.shopify_b2b_tabulator_view', [
            'shopifyB2bL30Sales' => (float) ($snapshot['l30_sales'] ?? 0),
            'shopifyB2bL30Orders' => (int) ($snapshot['l30_orders'] ?? 0),
            'shopifyB2bL30Qty' => (int) ($snapshot['qty'] ?? 0),
            'shopifyB2bTotalPft' => (float) ($snapshot['total_pft'] ?? 0),
            'shopifyB2bTotalCogs' => (float) ($snapshot['total_cogs'] ?? 0),
            'shopifyB2bTotalSpend' => 0.0,
            'shopifyB2bGpftPct' => (float) ($snapshot['gpft_pct'] ?? 0),
            'shopifyB2bGroiPct' => (float) ($snapshot['groi_pct'] ?? 0),
            'shopifyB2bTcosPct' => 0.0,
            'shopifyB2bNpftPct' => (float) ($snapshot['gpft_pct'] ?? 0),
            'shopifyB2bNroiPct' => (float) ($snapshot['groi_pct'] ?? 0),
        ]);
    }

    public function shopifyB2bDataJson()
    {
        $data = $this->getViewShopifyB2bTabularData();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * L30 sales snapshot from shopify_b2b_daily_data — same basis as /shopify-b2b/daily-sales.
     */
    private function getShopifyB2bL30Snapshot(): array
    {
        $orders = ShopifyB2BDailyData::where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->get();

        if ($orders->isEmpty()) {
            return [
                'l30_sales' => 0,
                'l30_orders' => 0,
                'qty' => 0,
                'total_pft' => 0,
                'total_cogs' => 0,
                'gpft_pct' => 0,
                'groi_pct' => 0,
            ];
        }

        $skus = $orders->pluck('sku')->unique()->filter()->values()->all();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper(trim((string) $item->sku));
        });

        $margin = $this->getShopifyB2bMargin();
        $totalSales = 0.0;
        $totalQty = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $orderIds = [];

        foreach ($orders as $order) {
            $sku = strtoupper(trim((string) ($order->sku ?? '')));
            $quantity = (float) ($order->quantity ?? 0);
            $price = (float) ($order->price ?? 0);
            $totalAmount = (float) ($order->total_amount ?? 0);

            $totalSales += $totalAmount;
            $totalQty += (int) $quantity;
            if (! empty($order->order_id)) {
                $orderIds[(string) $order->order_id] = true;
            }

            // B2B profit formulas exclude Ship: PFT = (Price × Margin − LP)
            $lp = 0.0;
            $pm = $productMasters[$sku] ?? null;
            if ($pm) {
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;
            $pftEach = ($price * $margin) - $lp;
            $totalPft += $pftEach * $quantity;
        }

        return [
            'l30_sales' => $totalSales,
            'l30_orders' => count($orderIds),
            'qty' => $totalQty,
            'total_pft' => $totalPft,
            'total_cogs' => $totalCogs,
            'gpft_pct' => $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0,
            'groi_pct' => $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0,
        ];
    }

    private function getShopifyB2bMargin(): float
    {
        $marketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2B')->first();

        return $marketplace ? ((float) $marketplace->percentage / 100) : 0.95;
    }

    public function getViewShopifyB2bTabularData()
    {
        $percentageValue = $this->getShopifyB2bMargin();

        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();

        $storeByNorm = [];
        if (Schema::hasTable('store_listing_prices')) {
            foreach (StoreListingPrice::query()->get() as $storeRow) {
                $storeKey = ShopifySku::normalizeSkuForShopifyLookup((string) $storeRow->sku);
                if ($storeKey === '') {
                    continue;
                }
                $existing = $storeByNorm[$storeKey] ?? null;
                if ($existing === null || ($storeRow->is_variant && ! $existing->is_variant)) {
                    $storeByNorm[$storeKey] = $storeRow;
                }
            }
        }

        // OV L30 — overall sold units from shopify_skus.quantity (same as /shopify-b2c-pricing)
        $shopifySkuMap = ShopifySku::mapByProductSkus($skus);

        // Same source as /shopify-b2b/daily-sales
        $shopifyB2BOrders = ShopifyB2BDailyData::whereIn('sku', $skus)
            ->where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');

        // PRMT%/CPN%/DSC%/Appr/Push Prc — shopify_b2b_promo_pricing (site-specific)
        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('shopify_b2b', $skus);

        // SPRICE / NR from shopifyb2b_data_view (same store as /pricing-master-cvr sb2b)
        $shopifyB2bViewData = ShopifyB2BDataView::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        // Std Prc — amazon_data_view.STANDARD_PRICE (same shared store as /amazon-tabulator-view)
        $amazonStandardPrices = [];
        foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
            $val = is_array($adv->value)
                ? $adv->value
                : (json_decode((string) ($adv->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
            }
        }

        $googleLmpDetails = collect();
        try {
            $googleLmpLookups = GoogleSkuCompetitor::buildGroupedLookup('google');
            $googleLmpDetails = $googleLmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('Shopify B2B Google LMP lookup failed: '.$e->getMessage());
        }

        $lmpGroupService = new LmpSkuGroupService();
        try {
            $lmpGroupService->prepareForSkus(array_values(array_filter(array_map(
                static fn ($s) => trim((string) $s),
                $skus
            ))));
        } catch (\Throwable $e) {
            Log::warning('LmpSkuGroupService prepare failed (Shopify B2B): '.$e->getMessage());
        }

        $channelAdsPct = 0.0;
        $processedItems = [];

        foreach ($productMasterRows as $sku => $productMaster) {
            $processedItem = [];
            $processedItem['(Child) sku'] = $sku;
            $processedItem['Parent'] = $productMaster->parent ?? null;

            $values = is_array($productMaster->Values)
                ? $productMaster->Values
                : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

            $lp = $values['lp'] ?? ($productMaster->lp ?? 0);
            $ship = $values['ship'] ?? ($productMaster->ship ?? 0);

            $processedItem['LP_productmaster'] = $lp;
            $processedItem['Ship_productmaster'] = $ship;

            $storeRow = $storeByNorm[ShopifySku::normalizeSkuForShopifyLookup((string) $sku)] ?? null;

            $shopifyItem = $shopifySkuMap->get($sku);
            $processedItem['INV'] = $shopifyItem ? (int) ($shopifyItem->inv ?? 0) : 0;
            $processedItem['L30'] = $shopifyItem ? (int) ($shopifyItem->quantity ?? 0) : 0;
            $processedItem['Price'] = $storeRow && $storeRow->selling_price !== null
                ? floatval($storeRow->selling_price)
                : 0;
            $processedItem['website_regular_price'] = $storeRow && $storeRow->price !== null
                ? floatval($storeRow->price)
                : null;
            $processedItem['website_special_price'] = $storeRow && $storeRow->special_price !== null
                ? floatval($storeRow->special_price)
                : null;
            $processedItem['Views'] = $storeRow && $storeRow->views !== null
                ? (int) $storeRow->views
                : 0;
            $processedItem['image_path'] = $storeRow?->base_image;

            $b2bOrder = $shopifyB2BOrders[$sku] ?? null;
            $processedItem['B2B L30'] = $b2bOrder ? $b2bOrder->total_quantity : 0;

            // NR/REQ stored in shopifyb2b_data_view (no separate listing-status table for B2B)
            $processedItem['nr_req'] = 'REQ';
            $processedItem['B Link'] = '';
            $processedItem['S Link'] = '';

            if (isset($shopifyB2bViewData[$sku])) {
                $viewArr = is_array($shopifyB2bViewData[$sku]->value)
                    ? $shopifyB2bViewData[$sku]->value
                    : (json_decode($shopifyB2bViewData[$sku]->value, true) ?? []);
                $rlNrl = $viewArr['rl_nrl'] ?? null;
                if (! $rlNrl && isset($viewArr['nr_req'])) {
                    $rlNrl = ($viewArr['nr_req'] === 'REQ') ? 'RL' : (($viewArr['nr_req'] === 'NR') ? 'NRL' : 'RL');
                }
                if ($rlNrl === 'RL') {
                    $processedItem['nr_req'] = 'REQ';
                } elseif ($rlNrl === 'NRL') {
                    $processedItem['nr_req'] = 'NR';
                }
                $processedItem['B Link'] = $viewArr['buyer_link'] ?? '';
            }
            $processedItem['S Link'] = ($storeRow && ! empty($storeRow->url)) ? $storeRow->url : '';

            $price = $processedItem['Price'];
            $b2bL30 = $processedItem['B2B L30'];
            $ovL30 = $processedItem['L30'];

            // B2B P&L excludes Ship (same as daily-sales / S PRC metrics). No channel ads.
            if ($price > 0) {
                $grossProfit = ($price * $percentageValue) - floatval($lp);
                $processedItem['GPFT%'] = ($grossProfit / $price) * 100;
                $processedItem['ROI%'] = $lp > 0 ? ($grossProfit / $lp) * 100 : 0;
                if ($b2bL30 > 0) {
                    $processedItem['Profit'] = $grossProfit * $b2bL30;
                    $processedItem['Sales L30'] = $price * $b2bL30;
                } else {
                    $processedItem['Profit'] = 0;
                    $processedItem['Sales L30'] = 0;
                }
            } else {
                $processedItem['GPFT%'] = 0;
                $processedItem['ROI%'] = 0;
                $processedItem['Profit'] = 0;
                $processedItem['Sales L30'] = 0;
            }

            $inv = $processedItem['INV'];
            $processedItem['DIL%'] = $inv > 0 ? ($ovL30 / $inv) * 100 : 0;

            $views = $processedItem['Views'];
            $processedItem['CVR%'] = $views > 0 ? ($b2bL30 / $views) * 100 : 0;

            $processedItem['googleSpent'] = 0;
            $salesL30 = $processedItem['Sales L30'];
            $processedItem['ADS%'] = 0;

            if ($price > 0 && floatval($lp) > 0) {
                $unitGross = ($price * $percentageValue) - floatval($lp);
                $adSpendUnit = $price * ($channelAdsPct / 100);
                $processedItem['NROI%'] = (($unitGross - $adSpendUnit) / floatval($lp)) * 100;
            } else {
                $processedItem['NROI%'] = 0;
            }

            $processedItem['SPRICE'] = 0;
            $processedItem['SGPFT'] = 0;
            $processedItem['SNPFT'] = 0;
            $processedItem['SROI'] = 0;
            $processedItem['SNROI'] = 0;
            $processedItem['SPRICE_STATUS'] = null;

            $savedSprice = null;
            if (isset($shopifyB2bViewData[$sku])) {
                $valuesArr = is_array($shopifyB2bViewData[$sku]->value)
                    ? $shopifyB2bViewData[$sku]->value
                    : (json_decode($shopifyB2bViewData[$sku]->value, true) ?: []);
                $processedItem['SPRICE_STATUS'] = $valuesArr['SPRICE_STATUS'] ?? null;
                $savedSprice = $valuesArr['SPRICE'] ?? null;
            }

            $processedItem['has_custom_sprice'] = is_numeric($savedSprice) && (float) $savedSprice > 0;

            // B2B: S PRC = saved value, else live website Price. Metrics exclude Ship.
            $sprice = (is_numeric($savedSprice) && (float) $savedSprice > 0)
                ? round((float) $savedSprice, 2)
                : ($price > 0 ? round((float) $price, 2) : 0);

            if ($sprice > 0) {
                $processedItem['SPRICE'] = $sprice;
                $sGross = ($sprice * $percentageValue) - floatval($lp);
                $processedItem['SGPFT'] = ($sGross / $sprice) * 100;
                $processedItem['SNPFT'] = (float) $processedItem['SGPFT'] - $channelAdsPct;
                $processedItem['SROI'] = floatval($lp) > 0 ? ($sGross / floatval($lp)) * 100 : 0;
                $adSpendUnit = $sprice * ($channelAdsPct / 100);
                $processedItem['SNROI'] = floatval($lp) > 0
                    ? (($sGross - $adSpendUnit) / floatval($lp)) * 100
                    : 0;
            }

            $linkedLmpSkus = $this->shopifyB2bLinkedLmpSkusFor($lmpGroupService, (string) $sku);
            $processedItem['linked_lmp_skus'] = $linkedLmpSkus;

            $stdPrc = $amazonStandardPrices[strtoupper(trim((string) $sku))] ?? null;
            if ($stdPrc === null) {
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedKey = strtoupper(trim((string) $linkedSku));
                    if ($linkedKey !== '' && isset($amazonStandardPrices[$linkedKey])) {
                        $stdPrc = $amazonStandardPrices[$linkedKey];
                        break;
                    }
                }
            }
            $processedItem['STANDARD_PRICE'] = $stdPrc;

            $processedItem = app(ChannelPromoPricingService::class)->applyToRow($processedItem, $promoMap, (string) $sku);

            $mergedLmpEntries = collect();
            $seenLmp = [];
            $skusForLmp = $linkedLmpSkus !== [] ? $linkedLmpSkus : [$sku];
            foreach ($skusForLmp as $linkedSku) {
                $linkedKey = GoogleSkuCompetitor::normalizeSkuKey((string) $linkedSku);
                $groupEntries = $googleLmpDetails->get($linkedKey);
                if (! $groupEntries instanceof \Illuminate\Support\Collection) {
                    continue;
                }
                foreach ($groupEntries as $comp) {
                    $dedupeKey = ((string) ($comp->id ?? '')).'|'
                        .((string) ($comp->product_id ?? '')).'|'
                        .strtoupper(trim((string) ($comp->source ?? ''))).'|'
                        .strtoupper(trim((string) ($comp->product_link ?? '')));
                    if (isset($seenLmp[$dedupeKey])) {
                        continue;
                    }
                    $seenLmp[$dedupeKey] = true;
                    $mergedLmpEntries->push($comp);
                }
            }
            $mergedLmpEntries = GoogleSkuCompetitor::sortCollectionByNumericPrice($mergedLmpEntries);
            $lowestLmp = GoogleSkuCompetitor::lowestFromCollection($mergedLmpEntries);

            $processedItem['lmp_price'] = ($lowestLmp && is_numeric($lowestLmp->price))
                ? round((float) $lowestLmp->price, 2)
                : null;
            $processedItem['lmp_link'] = $lowestLmp->product_link ?? null;
            $processedItem['lmp_source'] = $lowestLmp->source ?? null;
            $processedItem['lmp_title'] = $lowestLmp->product_title ?? null;
            $processedItem['lmp_entries'] = $mergedLmpEntries->map(static function ($comp) {
                return [
                    'id' => $comp->id,
                    'product_id' => $comp->product_id,
                    'source' => $comp->source,
                    'price' => isset($comp->price) ? round((float) $comp->price, 2) : null,
                    'link' => $comp->product_link,
                    'product_link' => $comp->product_link,
                    'title' => $comp->product_title,
                    'product_title' => $comp->product_title,
                    'image' => $comp->image,
                    'rating' => $comp->rating !== null ? (float) $comp->rating : null,
                    'reviews' => $comp->reviews !== null ? (int) $comp->reviews : null,
                ];
            })->values()->all();
            $processedItem['lmp_entries_total'] = $mergedLmpEntries->count();

            $processedItem['is_parent_summary'] = false;
            $processedItems[] = $processedItem;
        }

        $groupedByParent = collect($processedItems)->groupBy(function ($row) {
            return trim((string) ($row['Parent'] ?? ''));
        });

        $finalItems = [];
        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalItems[] = $row;
            }

            if ($parent === '') {
                continue;
            }

            $inv = (float) $rows->sum(fn ($r) => floatval($r['INV'] ?? 0));
            $ovL30 = (float) $rows->sum(fn ($r) => floatval($r['L30'] ?? 0));
            $b2bL30 = (float) $rows->sum(fn ($r) => floatval($r['B2B L30'] ?? 0));
            $views = (float) $rows->sum(fn ($r) => floatval($r['Views'] ?? 0));
            $profit = (float) $rows->sum(fn ($r) => floatval($r['Profit'] ?? 0));
            $sales = (float) $rows->sum(fn ($r) => floatval($r['Sales L30'] ?? 0));
            $adSpend = (float) $rows->sum(fn ($r) => floatval($r['googleSpent'] ?? 0));

            $childPrices = $rows->pluck('Price')->filter(fn ($p) => is_numeric($p) && $p > 0);
            $gpftVals = $rows->pluck('GPFT%')->filter(fn ($v) => is_numeric($v));
            $roiVals = $rows->pluck('ROI%')->filter(fn ($v) => is_numeric($v));
            $nroiVals = $rows->pluck('NROI%')->filter(fn ($v) => is_numeric($v));

            $hasReqChild = $rows->contains(fn ($r) => ($r['nr_req'] ?? '') === 'REQ');
            $imageRow = $rows->first(fn ($r) => ! empty($r['image_path']));
            $imagePath = is_array($imageRow) ? ($imageRow['image_path'] ?? null) : null;

            $finalItems[] = [
                '(Child) sku' => 'PARENT '.$parent,
                'Parent' => $parent,
                'is_parent_summary' => true,
                'LP_productmaster' => '',
                'Ship_productmaster' => '',
                'INV' => $inv,
                'L30' => $ovL30,
                'B2B L30' => $b2bL30,
                'Views' => $views,
                'Price' => $childPrices->count() > 0 ? round($childPrices->avg(), 2) : 0,
                'image_path' => $imagePath,
                'nr_req' => $hasReqChild ? 'REQ' : 'NR',
                'B Link' => '',
                'S Link' => '',
                'GPFT%' => $gpftVals->count() > 0 ? round($gpftVals->avg(), 2) : 0,
                'ROI%' => $roiVals->count() > 0 ? round($roiVals->avg(), 2) : 0,
                'NROI%' => $nroiVals->count() > 0 ? round($nroiVals->avg(), 2) : 0,
                'Profit' => round($profit, 2),
                'Sales L30' => round($sales, 2),
                'DIL%' => $inv > 0 ? ($ovL30 / $inv) * 100 : 0,
                'CVR%' => $views > 0 ? ($b2bL30 / $views) * 100 : 0,
                'googleSpent' => $adSpend,
                'ADS%' => $sales > 0 ? ($adSpend / $sales) * 100 : 0,
                'SPRICE' => 0,
                'SGPFT' => 0,
                'SNPFT' => 0,
                'SROI' => 0,
                'SNROI' => 0,
                'SPRICE_STATUS' => null,
                'linked_lmp_skus' => [],
                'lmp_price' => null,
                'lmp_link' => null,
                'lmp_source' => null,
                'lmp_title' => null,
                'lmp_entries' => [],
                'lmp_entries_total' => 0,
            ];
        }

        return $finalItems;
    }

    /**
     * @return list<string>
     */
    private function shopifyB2bLinkedLmpSkusFor(LmpSkuGroupService $lmpGroupService, string $sku): array
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

    /**
     * Save SPRICE into shopifyb2b_data_view — same store as /pricing-master-cvr sb2b.
     */
    public function saveSpriceToDatabase(Request $request)
    {
        $updates = $request->input('updates');

        if ($updates && is_array($updates)) {
            return $this->saveBulkSpriceUpdates($updates);
        }

        $sku = $request->input('sku');
        $sprice = $request->input('sprice');

        if (! $sku || $sprice === null) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $result = $this->calculateAndSaveSprice($sku, $sprice);

        if ($result['success']) {
            return response()->json([
                'message' => 'Data saved successfully.',
                'sgpft_percent' => $result['sgpft'],
                'snpft_percent' => $result['snpft'],
                'sroi_percent' => $result['sroi'],
                'snroi_percent' => $result['snroi'],
            ]);
        }

        return response()->json(['error' => $result['error']], 400);
    }

    /**
     * Push saved S PRC to business5core.com special/selling price.
     */
    public function pushSpriceToWebsite(Request $request, StorePricePushService $push)
    {
        $sku = trim((string) $request->input('sku'));
        $sprice = $request->input('sprice', $request->input('price'));
        if ($sku === '' || ! is_numeric($sprice) || (float) $sprice <= 0) {
            return response()->json(['success' => false, 'message' => 'SKU and S PRC > 0 are required.'], 400);
        }

        $result = $push->pushSprice($sku, (float) $sprice);
        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    private function saveBulkSpriceUpdates(array $updates)
    {
        $successCount = 0;
        $errors = [];

        foreach ($updates as $update) {
            $sku = $update['sku'] ?? null;
            $sprice = $update['sprice'] ?? null;

            if (! $sku || $sprice === null) {
                $errors[] = ['sku' => $sku, 'error' => 'SKU or sprice missing'];
                continue;
            }

            $result = $this->calculateAndSaveSprice($sku, $sprice);
            if ($result['success']) {
                $successCount++;
            } else {
                $errors[] = ['sku' => $sku, 'error' => $result['error']];
            }
        }

        return response()->json([
            'success' => true,
            'updated' => $successCount,
            'errors' => $errors,
            'message' => "Updated $successCount SKU(s)",
        ]);
    }

    private function calculateAndSaveSprice($sku, $sprice)
    {
        $productMaster = ProductMaster::where('sku', $sku)->first();
        if (! $productMaster) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        $values = is_array($productMaster->Values)
            ? $productMaster->Values
            : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

        $lp = $values['lp'] ?? ($productMaster->lp ?? 0);

        $percentage = $this->getShopifyB2bMargin();
        $sprice = floatval($sprice);
        // B2B SPRICE metrics exclude Ship (same as Target ROI% / daily-sales PFT)
        $grossProfit = ($sprice * $percentage) - floatval($lp);

        $sgpft = $sprice > 0 ? ($grossProfit / $sprice) * 100 : 0;
        $sroi = $lp > 0 ? ($grossProfit / $lp) * 100 : 0;
        $snpft = $sgpft; // B2B has no channel ads
        $snroi = floatval($lp) > 0 ? ($grossProfit / floatval($lp)) * 100 : 0;

        // Same table /pricing-master-cvr uses for sb2b
        $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        $merged = array_merge($existing, [
            'SPRICE' => $sprice,
            'SGPFT' => $sgpft,
            'SNPFT' => $snpft,
            'SPFT' => $snpft,
            'SROI' => $sroi,
            'SNROI' => $snroi,
        ]);

        $dataView->value = $merged;
        $dataView->save();

        Log::info('SPRICE saved to shopifyb2b_data_view', [
            'sku' => $sku,
            'sprice' => $sprice,
            'id' => $dataView->id,
        ]);

        return [
            'success' => true,
            'sgpft' => $sgpft,
            'snpft' => $snpft,
            'sroi' => $sroi,
            'snroi' => $snroi,
        ];
    }

    public function updateShopifyB2bListedLive(Request $request)
    {
        $sku = $request->input('sku');
        $nrReq = $request->input('nr_req');

        if (! $sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }

        $rlNrlValue = ($nrReq === 'REQ') ? 'RL' : 'NRL';

        $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
        $currentValue = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?? []);

        $currentValue['rl_nrl'] = $rlNrlValue;
        $currentValue['nr_req'] = $nrReq;

        $dataView->value = $currentValue;
        $dataView->save();

        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $cacheKey = "shopify_b2b_tabulator_column_visibility_{$userId}";
        $visibility = Cache::get($cacheKey, []);

        return response()->json(['visibility' => $visibility]);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $visibility = $request->input('visibility', []);
        $cacheKey = "shopify_b2b_tabulator_column_visibility_{$userId}";

        Cache::put($cacheKey, $visibility, now()->addDays(30));

        return response()->json(['success' => true]);
    }
}
