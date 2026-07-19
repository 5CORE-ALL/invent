<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ReverbProduct;
use App\Models\AmazonDatasheet;
use App\Models\MacyProduct;
use App\Models\TemuMetric;
use App\Models\TemuDailyData;
use App\Models\TemuPricing;
use App\Models\EbayMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayTwoDataView;
use App\Models\EbayThreeDataView;
use App\Models\EbayPriorityReport;
use App\Models\Ebay3PriorityReport;
use App\Models\Ebay3GeneralReport;
use App\Models\AmazonDataView;
use App\Models\TemuDataView;
use App\Models\Temu2Pricing;
use App\Models\Temu2DailyData;
use App\Models\Temu2DataView;
use App\Models\DobaDataView;
use App\Models\TikTokDataView;
use App\Models\BestbuyUSADataView;
use App\Models\MacyDataView;
use App\Models\ReverbViewData;
use App\Models\Shopifyb2cDataView;
use App\Models\ShopifyB2BDataView;
use App\Models\TiendamiaProduct;
use App\Models\TiendamiaDataView;
use App\Models\DobaMetric;
use App\Models\WalmartPriceData;
use App\Models\WalmartOrderData;
use App\Models\WalmartListingViewsData;
use App\Models\WalmartCampaignReport;
use App\Models\WalmartDataView;
use App\Models\TikTokProduct;
use App\Models\ChannelMaster;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyPriceData;
use App\Models\ShopifyB2CDailyData;
use App\Models\ViewsPullData;
use App\Models\TemuViewData;
use App\Models\Temu2ViewData;
use App\Models\TemuAdData;
use App\Models\TemuLmp;
use App\Models\MarketplacePercentage;
use App\Services\LmpSkuGroupService;
use App\Models\MarketplaceDailyMetric;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSkuCompetitor;
use App\Models\GoogleSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Models\CvrRemark;
use App\Models\AmazonListingStatus;
use App\Models\EbayListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\EbayThreeListingStatus;
use App\Models\DobaListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\TiktokShopListingStatus;
use App\Models\ShopifyB2CListingStatus;
use App\Models\MacysListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\TemuListingStatus;
use App\Models\BestbuyUSAListingStatus;
use App\Models\TiendamiaListingStatus;
use App\Models\JungleScoutProductData;
use App\Models\PricingMasterDailySnapshotSku;
use App\Models\PricingMasterDailySnapshot;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaMonthlySale;
use App\Models\FbaReportsMaster;
use App\Models\FbaManualData;
use Carbon\Carbon;
use App\Services\AmazonSpApiService;
use App\Services\FbaManualDataService;
use App\Services\FbaInventoryService;
use App\Services\DobaApiService;
use App\Services\WalmartService;
use App\Services\ReverbApiService;
use App\Services\EbayApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayThreeApiService;
use App\Services\BestBuyApiService;
use App\Services\MacysApiService;
use App\Support\TemuGoodsIdHelper;

class CvrMasterController extends Controller
{
    /**
     * Display CVR Master tabulator view
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        return view("market-places.cvr_master_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
        ]);
    }

    /**
     * Display Master Analytics CVR view (uses same data as CVR Master)
     */
    public function pricingMasterCvrView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        return view("market-places.pricing_master_cvr_view", [
            "mode" => $mode,
            "demo" => $demo,
        ]);
    }

    /**
     * Get CVR Master data as JSON for tabulator
     * Fetches data from ProductMaster and ShopifySku
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCvrDataJson(Request $request)
    {
        try {
            // Fetch all product master records
            $productMasterRows = ProductMaster::all();

            // Get all unique SKUs from product master (excluding PARENT rows)
            $skus = $productMasterRows
                ->filter(function ($item) {
                    return stripos($item->sku, 'PARENT') === false;
                })
                ->pluck("sku")
                ->toArray();

            // Fetch shopify data for these SKUs (for inventory and overall L30)
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            // Fetch Amazon data for GPFT/AD/PFT calculations
            $amazonDatasheets = AmazonDatasheet::whereIn("sku", $skus)->get()->keyBy("sku");
            
            // Fetch Amazon SP Campaign Reports for ad spend (L30)
            $amazonSpCampaigns = DB::table('amazon_sp_campaign_reports')
                ->selectRaw('
                    campaignName,
                    MAX(spend) as spend,
                    SUM(sales30d) as sales30d
                ')
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->groupBy('campaignName')
                ->get()
                ->keyBy('campaignName');
            
            Log::info('CVR Master - Amazon Data fetched', [
                'amazon_datasheets' => $amazonDatasheets->count(),
                'amazon_campaigns' => $amazonSpCampaigns->count()
            ]);

            // Normalize SKU function (matching WalmartSheetUploadController)
            $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));
            $normalizedSkus = collect($skus)->map($normalizeSku)->values()->all();
            
            // Fetch Walmart data (matching WalmartSheetUploadController)
            $walmartPriceData = WalmartPriceData::whereIn('sku', $skus)->get()->keyBy('sku');
            $walmartViewsData = WalmartListingViewsData::whereIn("sku", $skus)->get()->keyBy("sku");
            $walmartDataView = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');

            // Amazon SPRICE from amazon_data_view (same as modal)
            $amazonDataViewBySku = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');

            Log::info('CVR Master - Walmart Data fetched', [
                'price_data' => $walmartPriceData->count(),
                'views_data' => $walmartViewsData->count(),
                'data_view' => $walmartDataView->count()
            ]);

            // Fetch Walmart campaign data (L30)
            $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')
                ->whereIn('campaignName', $normalizedSkus)
                ->get()
                ->keyBy(fn($item) => $normalizeSku($item->campaignName));
            
            Log::info('CVR Master - Walmart Campaigns fetched', [
                'total_campaigns' => $walmartCampaignReportsL30->count(),
                'campaign_skus' => $walmartCampaignReportsL30->keys()->take(10)->toArray()
            ]);

            // Fetch Walmart order data for L30 totals
            $walmartOrderTotals = WalmartOrderData::whereIn('sku', $skus)
                ->where('status', '!=', 'Canceled')
                ->selectRaw('sku, SUM(qty) as total_qty, SUM(item_cost) as total_revenue')
                ->groupBy('sku')
                ->get()
                ->keyBy('sku');
            
            Log::info('CVR Master - Walmart Orders fetched', [
                'total_orders' => $walmartOrderTotals->count(),
                'sample_skus' => $walmartOrderTotals->keys()->take(10)->toArray()
            ]);

            // Get TikTok percentage from MarketplacePercentage (default 80%)
            $tiktokMarketplace = MarketplacePercentage::where('marketplace', 'TikTok')->first();
            $tiktokPercentage = $tiktokMarketplace ? ($tiktokMarketplace->percentage / 100) : 0.80;
            
            // Fetch TikTok product data
            $tiktokProducts = TikTokProduct::whereIn('sku', array_map('strtoupper', $skus))
                ->get()
                ->keyBy(function($item) {
                    return strtoupper($item->sku);
                });
            
            Log::info('CVR Master - TikTok Data fetched', [
                'tiktok_products' => $tiktokProducts->count(),
                'tiktok_percentage' => $tiktokPercentage * 100 . '%'
            ]);

            // Get BestBuy percentage from MarketplacePercentage (default 80%)
            $bestbuyMarketplace = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
            $bestbuyPercentage = $bestbuyMarketplace ? ($bestbuyMarketplace->percentage / 100) : 0.80;
            
            // Fetch BestBuy product data
            $bestbuyProducts = BestbuyUsaProduct::whereIn('sku', $skus)->get()->keyBy('sku');
            $bestbuyPriceData = BestbuyPriceData::whereIn('sku', $skus)->get()->keyBy('sku');
            
            Log::info('CVR Master - BestBuy Data fetched', [
                'bestbuy_products' => $bestbuyProducts->count(),
                'bestbuy_price_data' => $bestbuyPriceData->count(),
                'bestbuy_percentage' => $bestbuyPercentage * 100 . '%'
            ]);

            // Get Shopify B2C percentage from MarketplacePercentage (default 100%)
            $shopifyB2CMarketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            $shopifyB2CPercentage = $shopifyB2CMarketplace ? ($shopifyB2CMarketplace->percentage / 100) : 1.00;
            
            Log::info('CVR Master - Shopify B2C Data fetched', [
                'shopifyb2c_percentage' => $shopifyB2CPercentage * 100 . '%'
            ]);

            // Get Macy's percentage from MarketplacePercentage (default 80%)
            $macyMarketplace = MarketplacePercentage::where('marketplace', 'Macys')->first();
            $macyPercentage = $macyMarketplace ? ($macyMarketplace->percentage / 100) : 0.80;
            
            // Fetch Macy's product data
            $macyProducts = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');
            
            Log::info('CVR Master - Macy Data fetched', [
                'macy_products' => $macyProducts->count(),
                'macy_percentage' => $macyPercentage * 100 . '%'
            ]);

            // Get Reverb percentage from MarketplacePercentage (default 85%)
            $reverbMarketplace = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $reverbPercentage = $reverbMarketplace ? ($reverbMarketplace->percentage / 100) : 0.85;
            
            // Fetch Reverb product data
            $reverbProducts = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');
            
            Log::info('CVR Master - Reverb Data fetched', [
                'reverb_products' => $reverbProducts->count(),
                'reverb_percentage' => $reverbPercentage * 100 . '%'
            ]);

            // Get Doba percentage from MarketplacePercentage (default 100%)
            $dobaMarketplace = MarketplacePercentage::where('marketplace', 'Doba')->first();
            $dobaPercentage = $dobaMarketplace ? ($dobaMarketplace->percentage / 100) : 1.00;
            
            // FetchDoba product data
            $dobaMetrics = DobaMetric::whereIn('sku', $skus)->get()->keyBy('sku');
            
            Log::info('CVR Master - Doba Data fetched', [
                'doba_metrics' => $dobaMetrics->count(),
                'doba_percentage' => $dobaPercentage * 100 . '%'
            ]);

            // Fetch Temu data for GPFT/AD/PFT calculations
            // Match /temu-decrease: map pricing onto ProductMaster SKUs via normalized SKU
            $temuPricingsAll = TemuPricing::query()->get(['sku', 'base_price', 'goods_id', 'quantity']);
            $temuPricingByProductSku = $this->buildTemuPricingMapForProductSkus($temuPricingsAll, $skus);
            $temuPricings = collect($temuPricingByProductSku)->filter(); // keyed by PM sku
            $temuDailySales = TemuDailyData::whereIn('contribution_sku', $skus)
                ->selectRaw('contribution_sku as sku, SUM(quantity_purchased) as temu_l30')
                ->groupBy('contribution_sku')
                ->get()
                ->keyBy('sku');
            
            // Get Temu percentage (Temu marketplace uses 96%)
            $temuMarketplace = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $temuPercentage = $temuMarketplace ? ($temuMarketplace->percentage / 100) : 0.96;

            // Temu views = SUM(product_clicks) by goods_id — same source as /temu-decrease
            // Index by both raw and normalized goods_id so joins don't miss type/format variants
            $temuViewByGoodsId = [];
            if (Schema::hasTable('temu_view_data')) {
                foreach (
                    TemuViewData::selectRaw('goods_id, SUM(product_clicks) as product_clicks')
                        ->groupBy('goods_id')
                        ->get() as $r
                ) {
                    $clicks = (int) ($r->product_clicks ?? 0);
                    $rawGid = (string) ($r->goods_id ?? '');
                    if ($rawGid !== '') {
                        $temuViewByGoodsId[$rawGid] = $clicks;
                    }
                    $nk = TemuGoodsIdHelper::normalizeKey($r->goods_id);
                    if ($nk) {
                        $temuViewByGoodsId[$nk] = $clicks;
                    }
                }
            }
            
            Log::info('CVR Master - Temu Data fetched', [
                'temu_pricings' => $temuPricings->count(),
                'temu_view_goods_ids' => count($temuViewByGoodsId),
                'temu_percentage' => $temuPercentage * 100 . '%'
            ]);

            $temu2L30ByProductSku = array_fill_keys($skus, 0);
            $temu2PricingByProductSku = array_fill_keys($skus, null);
            $temu2ViewByGoodsId = [];
            if (Schema::hasTable('temu2_view_data')) {
                foreach (
                    Temu2ViewData::selectRaw('goods_id, SUM(product_clicks) as product_clicks')
                        ->groupBy('goods_id')
                        ->get() as $r
                ) {
                    $clicks = (int) ($r->product_clicks ?? 0);
                    $rawGid = (string) ($r->goods_id ?? '');
                    if ($rawGid !== '') {
                        $temu2ViewByGoodsId[$rawGid] = $clicks;
                    }
                    $nk = TemuGoodsIdHelper::normalizeKey($r->goods_id);
                    if ($nk) {
                        $temu2ViewByGoodsId[$nk] = $clicks;
                    }
                }
            }
            if (Schema::hasTable('temu2_pricing') && Schema::hasTable('temu2_daily_data')) {
                try {
                    $temu2L30ByProductSku = $this->buildTemu2L30ByProductSkusMap($skus, true);
                    $temu2PricingsAll = Temu2Pricing::query()->get(['sku', 'base_price', 'goods_id']);
                    $temu2PricingByProductSku = $this->buildTemu2PricingMapForProductSkus($temu2PricingsAll, $skus);
                    Log::info('CVR Master - Temu 2 Data fetched', [
                        'temu2_pricing_rows'   => $temu2PricingsAll->count(),
                        'temu2_skus_w_l30'     => count(array_filter($temu2L30ByProductSku, fn ($q) => (int) $q > 0)),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('CVR Master - Temu 2 batch load skipped: ' . $e->getMessage());
                    $temu2L30ByProductSku = array_fill_keys($skus, 0);
                    $temu2PricingByProductSku = array_fill_keys($skus, null);
                }
            } else {
                Log::info('CVR Master - temu2_pricing / temu2_daily_data not found; Temu 2 CVR fields skipped. Run: php artisan migrate (see 2026_04_20_000001_create_temu2_pricing_and_temu2_data_view_tables, 2026_03_14_100000_create_temu2_daily_data_table).');
            }

            // Fetch eBay data (eBay 1, 2, 3)
            $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
            $ebay2Metrics = Ebay2Metric::whereIn('sku', $skus)->get()->keyBy('sku');
            $ebay3Metrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
            
            // Get eBay percentages (default 80% for all eBay stores)
            $ebay1Marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();
            $ebay1Percentage = $ebay1Marketplace ? ($ebay1Marketplace->percentage / 100) : 0.80;
            
            $ebay2Marketplace = MarketplacePercentage::where('marketplace', 'Ebay2')->first();
            $ebay2Percentage = $ebay2Marketplace ? ($ebay2Marketplace->percentage / 100) : 0.80;
            
            $ebay3Marketplace = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
            $ebay3Percentage = $ebay3Marketplace ? ($ebay3Marketplace->percentage / 100) : 0.80;
            
            Log::info('CVR Master - eBay Data fetched', [
                'ebay1_metrics' => $ebayMetrics->count(),
                'ebay2_metrics' => $ebay2Metrics->count(),
                'ebay3_metrics' => $ebay3Metrics->count()
            ]);

            // Get Shein percentage from MarketplacePercentage (default 100%)
            $sheinMarketplace = MarketplacePercentage::where('marketplace', 'Shein')->first();
            $sheinPercentage = $sheinMarketplace ? ($sheinMarketplace->percentage / 100) : 1.00;

            // Fetch Shein pricing data from shein_pricing_prices (same source as Shein pricing page)
            // Price = special_offer_price (falls back to price); L30 from shein_daily_data by seller_sku
            $sheinPricings = collect();
            $sheinDailySales = collect();
            try {
                $sheinPricings = \App\Models\SheinPricingPrice::whereIn('sku', $skus)->get()->keyBy('sku');
                $sheinDailySales = \App\Models\SheinDailyData::whereIn('seller_sku', $skus)
                    ->selectRaw('seller_sku as sku, SUM(COALESCE(quantity, 0)) AS shein_l30')
                    ->groupBy('seller_sku')
                    ->get()
                    ->keyBy('sku');
                Log::info('CVR Master - Shein Data fetched', [
                    'shein_pricings'    => $sheinPricings->count(),
                    'shein_daily_sales' => $sheinDailySales->count(),
                    'shein_percentage'  => $sheinPercentage * 100 . '%',
                ]);
            } catch (\Exception $e) {
                Log::warning('CVR Master - Shein data fetch skipped: ' . $e->getMessage());
            }

            // Get Purchasing Power percentage (default 70%)
            $ppMarketplace = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $ppPercentage = $ppMarketplace ? ($ppMarketplace->percentage / 100) : 0.70;

            // Fetch PP pricing data + L30 sales (same source as Purchasing Power pricing page)
            $ppProducts = collect();
            $ppSalesQty = collect();
            try {
                $ppProducts = \App\Models\PurchasingPowerProduct::whereIn('sku', $skus)
                    ->get()->keyBy('sku');
                $ppSalesQty = \App\Models\PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
                    ->selectRaw('UPPER(offer_sku) as sku_upper, SUM(quantity) as total_qty')
                    ->whereIn(DB::raw('UPPER(offer_sku)'), array_map('strtoupper', $skus))
                    ->groupBy('sku_upper')
                    ->pluck('total_qty', 'sku_upper');
                Log::info('CVR Master - Purchasing Power Data fetched', [
                    'pp_products'  => $ppProducts->count(),
                    'pp_sales'     => $ppSalesQty->count(),
                    'pp_percentage'=> $ppPercentage * 100 . '%',
                ]);
            } catch (\Exception $e) {
                Log::warning('CVR Master - Purchasing Power data fetch skipped: ' . $e->getMessage());
            }
            $aeMarketplace = MarketplacePercentage::where('marketplace', 'Aliexpress')
                ->orWhere('marketplace', 'AliExpress')->first();
            $aePercentage = $aeMarketplace ? ($aeMarketplace->percentage / 100) : 1.00;

            // Fetch AliExpress pricing + L30 data (same source as AliExpress pricing page)
            $aePricings = collect();
            $aeDailySales = collect();
            try {
                $aePricings = \App\Models\AliexpressPricingPrice::whereIn('sku', $skus)
                    ->get()->keyBy('sku');
                $aeDailySales = \App\Models\AliexpressDailyData::query()
                    ->selectRaw('sku_code, SUM(COALESCE(quantity, 0)) AS ae_l30, SUM(COALESCE(order_amount, 0)) AS ae_sales')
                    ->whereIn('sku_code', $skus)
                    ->groupBy('sku_code')
                    ->get()
                    ->keyBy('sku_code');
                Log::info('CVR Master - AliExpress Data fetched', [
                    'ae_pricings'    => $aePricings->count(),
                    'ae_daily_sales' => $aeDailySales->count(),
                    'ae_percentage'  => $aePercentage * 100 . '%',
                ]);
            } catch (\Exception $e) {
                Log::warning('CVR Master - AliExpress data fetch skipped: ' . $e->getMessage());
            }

            // Amazon marketplace percentage (for Amz PFT / ROI)
            $amazonMarketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            $amazonPercentage = $amazonMarketplace ? ($amazonMarketplace->percentage / 100) : 0.80;

            // Fetch Amazon LMP data from amazon_sku_competitors
            $amazonLmpLookup = collect();
            $amazonLmpCountLookup = collect();
            $googleLmpLookup = collect();
            $googleLmpCountLookup = collect();
            try {
                $amazonLmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
                $amazonLmpRecords = $amazonLmpLookups['details'];
                $amazonLmpLookup = $amazonLmpLookups['lowest'];
                $amazonLmpCountLookup = $amazonLmpRecords->map(fn ($items) => $items->count());

                $googleLmpLookups = GoogleSkuCompetitor::buildGroupedLookup('google');
                $googleLmpRecords = $googleLmpLookups['details'];
                $googleLmpLookup = $googleLmpLookups['lowest'];
                $googleLmpCountLookup = $googleLmpRecords->map(fn ($items) => $items->count());
            } catch (\Exception $e) {
                Log::warning('Could not fetch Amazon LMP: ' . $e->getMessage());
            }

            // Fetch eBay LMP data from ebay_sku_competitors
            $ebayLmpLookup = collect();
            $ebayLmpCountLookup = collect();
            try {
                $ebayLmpRecords = EbaySkuCompetitor::where('marketplace', 'ebay')
                    ->where(function ($q) {
                        $q->where('total_price', '>', 0)
                          ->orWhere('price', '>', 0);
                    })
                    ->orderByRaw('COALESCE(total_price, price + COALESCE(shipping_cost, 0)) ASC')
                    ->get()
                    ->groupBy(function ($item) {
                        return strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
                    });
                $ebayLmpLookup = $ebayLmpRecords->map(function ($items) {
                    $lowest = $items->sortBy(function ($i) {
                        $total = floatval($i->total_price ?? 0);
                        if ($total <= 0) {
                            $total = floatval($i->price ?? 0) + floatval($i->shipping_cost ?? 0);
                        }
                        return $total;
                    })->first();
                    return $lowest;
                });
                $ebayLmpCountLookup = $ebayLmpRecords->map(fn ($items) => $items->count());
            } catch (\Exception $e) {
                Log::warning('Could not fetch eBay LMP: ' . $e->getMessage());
            }

            // Temu / Temu 2 LMP — same source as /temu-decrease and /temu2-decrease (temu_lmp)
            $temuLmpByNormalizedSku = [];
            $temuLmpSkuGroupService = null;
            try {
                if (Schema::hasTable('temu_lmp')) {
                    foreach (TemuLmp::all() as $temuLmpRow) {
                        $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
                    }
                    $temuLmpSkuGroupService = app(LmpSkuGroupService::class);
                    $temuLmpSkuGroupService->prepareForSkus($skus);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch Temu LMP: ' . $e->getMessage());
            }

            // Fetch latest remarks for all SKUs in one query
            $latestRemarks = CvrRemark::whereIn('sku', $skus)
                ->select('sku', 'remark', 'is_solved', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('sku')
                ->map(function($remarks) {
                    return $remarks->first(); // Get latest remark for each SKU
                });
            
            Log::info('CVR Master - Latest Remarks fetched', [
                'total_remarks' => $latestRemarks->count()
            ]);

            // Jungle Scout data for rating/reviews (SKU and ASIN lookup)
            // Order by updated_at DESC so the most recently synced record is checked first
            $allJungleScoutData = JungleScoutProductData::orderBy('updated_at', 'desc')->get();
            $jungleScoutBySku = $allJungleScoutData
                ->filter(fn ($item) => !empty($item->sku))
                ->groupBy(fn ($item) => strtoupper(trim($item->sku)))
                ->map(function ($group) {
                    return [
                        'all_data' => $group->map(function ($item) {
                            $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                            return is_array($data) ? $data : [];
                        })->values()->toArray()
                    ];
                });
            $jungleScoutByAsin = $allJungleScoutData
                ->filter(fn ($item) => !empty($item->asin))
                ->groupBy(fn ($item) => strtoupper(trim($item->asin)))
                ->map(function ($group) {
                    return [
                        'all_data' => $group->map(function ($item) {
                            $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                            return is_array($data) ? $data : [];
                        })->values()->toArray()
                    ];
                });

            // FBA L30: same as FBA Dispatch — resolve ProductMaster SKU → fba_table row, then fba_monthly_sales.l30_units by listing analytics key
            $fbaInventoryResolver = null;
            $fbaMonthlyByListingKey = collect();
            try {
                $fbaTableFbaRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get();
                $fbaInventoryResolver = FbaInventoryService::fromFbaRows($fbaTableFbaRows);
                $fbaMonthlyByListingKey = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                    ->get()
                    ->keyBy(function ($item) {
                        return FbaInventoryService::sellerSkuToAnalyticsListingKey($item->seller_sku ?? '');
                    });
            } catch (\Exception $e) {
                Log::warning('CVR Master - FBA monthly / resolver batch load failed: ' . $e->getMessage());
                $fbaInventoryResolver = $fbaInventoryResolver ?? FbaInventoryService::fromFbaRows(collect());
            }

            // Process data (skip PARENT rows from database)
            $result = [];

            foreach ($productMasterRows as $productMaster) {
                $sku = $productMaster->sku;

                // Skip database PARENT rows (we'll create synthetic ones later)
                if (stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $parent = $productMaster->parent ?? '';

                // Add values from product_master
                $values = $productMaster->Values ?: [];
                
                // Image path - check shopify first, then product master Values, then product master direct field
                $imagePath = null;

                $inventory = 0;
                $overallL30 = 0;

                // Add data from shopify_skus if available
                if (isset($shopifyData[$sku])) {
                    $shopifyItem = $shopifyData[$sku];
                    $inventory = $shopifyItem->inv ?? 0;
                    $overallL30 = $shopifyItem->quantity ?? 0;
                    // Get image from shopify if available
                    $imagePath = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
                } else {
                    // Fallback to product master for image
                    $imagePath = $values["image_path"] ?? ($productMaster->image_path ?? null);
                }

                // Calculate DIL% (Overall L30 / INV * 100)
                $dilPercent = $inventory > 0 ? round(($overallL30 / $inventory) * 100, 2) : 0;

                $fbaL30Units = 0;
                if ($fbaInventoryResolver !== null) {
                    $fbaResolved = $fbaInventoryResolver->resolve($sku);
                    if ($fbaResolved) {
                        $listingKey = FbaInventoryService::sellerSkuToAnalyticsListingKey($fbaResolved->seller_sku ?? '');
                        if ($listingKey !== '') {
                            $fbaMonthlyRow = $fbaMonthlyByListingKey->get($listingKey);
                            $fbaL30Units = $fbaMonthlyRow ? (int) ($fbaMonthlyRow->l30_units ?? 0) : 0;
                        }
                    }
                }
                $ovL30PlusFba = (int) $overallL30 + $fbaL30Units;

                // Get LP and Ship from ProductMaster Values
                $lp = 0;
                $ship = 0;
                $actWt = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") $lp = floatval($v);
                        if (strtolower($k) === "ship") $ship = floatval($v);
                        if (strtolower($k) === "wt_act") $actWt = floatval($v);
                    }
                }

                // Get Walmart views and CVR data from walmart_listing_views_data
                $walmartViews = 0;
                $walmartCVR = 0;
                if (isset($walmartViewsData[$sku])) {
                    $walmartItem = $walmartViewsData[$sku];
                    $walmartViews = $walmartItem->page_views ?? 0;
                    $walmartCVR = $walmartItem->conversion_rate ?? 0;
                }

                // Get Walmart PRICE - priority: WalmartDataView (sprice) > WalmartPriceData
                $walmartPrice = 0;
                $dataView = $walmartDataView->get($sku);
                if ($dataView && isset($dataView->value['sprice']) && $dataView->value['sprice'] > 0) {
                    // Use saved sprice from walmart_data_view (user-edited price)
                    $walmartPrice = floatval($dataView->value['sprice']);
                } else {
                    // Fallback to original walmart price
                    $priceItem = $walmartPriceData->get($sku);
                    if ($priceItem) {
                        $walmartPrice = floatval($priceItem->price ?? $priceItem->comparison_price ?? 0);
                    }
                }

                // Get Walmart campaign ad spend (normalize SKU for matching)
                $normalizedSku = $normalizeSku($sku);
                $walmartAdSpend = 0;
                $campaignL30 = $walmartCampaignReportsL30->get($normalizedSku);
                if ($campaignL30) {
                    $walmartAdSpend = floatval($campaignL30->spend ?? 0);
                }

                // Get Walmart L30 sales from order data
                $walmartL30Qty = 0;
                $walmartRevenue = 0;
                $orders = $walmartOrderTotals->get($sku);
                if ($orders) {
                    $walmartL30Qty = intval($orders->total_qty ?? 0);
                    $walmartRevenue = floatval($orders->total_revenue ?? 0);
                }

                // Calculate W L30 Sales Amount (SPRICE × Qty)
                $wL30 = $walmartPrice * $walmartL30Qty;

                // Calculate Walmart GPFT% (Gross Profit % BEFORE ads)
                // Formula: ((price × 0.80 - ship - lp) / price) × 100
                $walmartGPFT = $walmartPrice > 0 ? ((($walmartPrice * 0.80 - $ship - $lp) / $walmartPrice) * 100) : 0;

                // Calculate Walmart AD% (Ad Spend / Sales Revenue)
                $walmartAD = 0;
                if ($wL30 > 0) {
                    $walmartAD = ($walmartAdSpend / $wL30) * 100;
                } elseif ($walmartAdSpend > 0) {
                    $walmartAD = 100; // If there's spend but no sales
                }

                // Calculate Walmart PFT% (Net Profit % AFTER ads)
                // Formula: GPFT% - AD%
                $walmartPFT = $walmartGPFT - $walmartAD;

                // Log calculations for SKUs with Walmart data
                if ($walmartPrice > 0 || $walmartL30Qty > 0 || $walmartAdSpend > 0) {
                    Log::info("CVR Master - WM Calculations", [
                        'sku' => $sku,
                        'normalized_sku' => $normalizedSku,
                        'wm_price' => $walmartPrice,
                        'lp' => $lp,
                        'ship' => $ship,
                        'qty' => $walmartL30Qty,
                        'w_l30' => $wL30,
                        'ad_spend' => $walmartAdSpend,
                        'gpft' => round($walmartGPFT, 2),
                        'ad_percent' => round($walmartAD, 2),
                        'pft' => round($walmartPFT, 2)
                    ]);
                }

                // Get TikTok data
                $tiktokProduct = $tiktokProducts->get(strtoupper($sku));
                $tiktokPrice = $tiktokProduct ? floatval($tiktokProduct->price ?? 0) : 0;
                
                // Calculate TikTok GPFT% = ((price × percentage - lp - ship) / price) × 100
                $tiktokGPFT = $tiktokPrice > 0 ? ((($tiktokPrice * $tiktokPercentage - $lp - $ship) / $tiktokPrice) * 100) : 0;
                
                // TikTok PFT% = GPFT% (no ads for TikTok)
                $tiktokPFT = $tiktokGPFT;

                // Get BestBuy data
                $bestbuyProduct = $bestbuyProducts->get($sku);
                $bestbuyPriceItem = $bestbuyPriceData->get($sku);
                
                // Price: BestbuyPriceData takes priority, fallback to BestbuyUsaProduct
                $bbPrice = $bestbuyPriceItem ? floatval($bestbuyPriceItem->price ?? 0) : floatval($bestbuyProduct->price ?? 0);
                
                // Calculate BestBuy GPFT% = ((price × percentage - ship - lp) / price) × 100
                $bbGPFT = $bbPrice > 0 ? ((($bbPrice * $bestbuyPercentage - $lp - $ship) / $bbPrice) * 100) : 0;
                
                // BestBuy PFT% = GPFT% (no ads for BestBuy)
                $bbPFT = $bbGPFT;

                // Get Shopify B2C data - uses overall_l30 from shopify_skus (already fetched)
                // Price from shopify_skus table
                $sb2cPrice = isset($shopifyData[$sku]) ? floatval($shopifyData[$sku]->price ?? 0) : 0;
                
                // Calculate Shopify B2C GPFT% = ((price × percentage - ship - lp) / price) × 100
                // Shopify B2C uses 100% (no marketplace commission)
                $sb2cGPFT = $sb2cPrice > 0 ? ((($sb2cPrice * $shopifyB2CPercentage - $lp - $ship) / $sb2cPrice) * 100) : 0;
                
                // Shopify B2C PFT% = GPFT% (no ads)
                $sb2cPFT = $sb2cGPFT;

                // Get Macy's data
                $macyProduct = $macyProducts->get($sku);
                $macyPrice = $macyProduct ? floatval($macyProduct->price ?? 0) : 0;
                
                // Calculate Macy's GPFT% = ((price × percentage - ship - lp) / price) × 100
                $macyGPFT = $macyPrice > 0 ? ((($macyPrice * $macyPercentage - $lp - $ship) / $macyPrice) * 100) : 0;
                
                // Macy's PFT% = GPFT% (no ads for Macy's)
                $macyPFT = $macyGPFT;

                // Get Reverb data
                $reverbProduct = $reverbProducts->get($sku);
                $reverbPrice = $reverbProduct ? floatval($reverbProduct->price ?? 0) : 0;
                
                // Calculate Reverb GPFT% = ((price × percentage - ship - lp) / price) × 100
                $reverbGPFT = $reverbPrice > 0 ? ((($reverbPrice * $reverbPercentage - $lp - $ship) / $reverbPrice) * 100) : 0;
                
                // Reverb PFT% = GPFT% (no ads for Reverb)
                $reverbPFT = $reverbGPFT;

                // === EBAY 1 CALCULATIONS ===
                $ebay1Metric = $ebayMetrics->get($sku);
                $ebay1Price = $ebay1Metric ? floatval($ebay1Metric->ebay_price ?? 0) : 0;
                
                // eBay 1 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay1GPFT = $ebay1Price > 0 ? ((($ebay1Price * $ebay1Percentage - $lp - $ship) / $ebay1Price) * 100) : 0;
                
                // eBay 1 PFT% = GPFT% (no ads)
                $ebay1PFT = $ebay1GPFT;
                
                // === EBAY 2 CALCULATIONS ===
                $ebay2Metric = $ebay2Metrics->get($sku);
                $ebay2Price = $ebay2Metric ? floatval($ebay2Metric->ebay_price ?? 0) : 0;
                
                // eBay 2 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay2GPFT = $ebay2Price > 0 ? ((($ebay2Price * $ebay2Percentage - $lp - $ship) / $ebay2Price) * 100) : 0;
                
                // eBay 2 PFT% = GPFT% (no ads)
                $ebay2PFT = $ebay2GPFT;
                
                // === EBAY 3 CALCULATIONS ===
                $ebay3Metric = $ebay3Metrics->get($sku);
                $ebay3Price = $ebay3Metric ? floatval($ebay3Metric->ebay_price ?? 0) : 0;
                
                // eBay 3 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay3GPFT = $ebay3Price > 0 ? ((($ebay3Price * $ebay3Percentage - $lp - $ship) / $ebay3Price) * 100) : 0;
                
                // eBay 3 PFT% = GPFT% (no ads)
                $ebay3PFT = $ebay3GPFT;

                // Get Doba data
                $dobaMetric = $dobaMetrics->get($sku);
                $dobaPrice = $dobaMetric ? floatval($dobaMetric->anticipated_income ?? 0) : 0;
                
                // Calculate Doba GPFT% = ((price × percentage - ship - lp) / price) × 100
                // Doba uses 100% (no marketplace commission)
                $dobaGPFT = $dobaPrice > 0 ? ((($dobaPrice * $dobaPercentage - $lp - $ship) / $dobaPrice) * 100) : 0;
                
                // Doba PFT% = GPFT% (no ads for Doba)
                $dobaPFT = $dobaGPFT;
                
                // Log Doba calculations for debugging
                if ($dobaPrice > 0) {
                    Log::info("CVR Master - Doba Calculations", [
                        'sku' => $sku,
                        'doba_price' => $dobaPrice,
                        'doba_percentage' => $dobaPercentage,
                        'lp' => $lp,
                        'ship' => $ship,
                        'gpft' => round($dobaGPFT, 2),
                        'pft' => round($dobaPFT, 2)
                    ]);
                }

                // === AMAZON CALCULATIONS ===
                $amazonSheet = $amazonDatasheets->get($sku);
                $amazonPrice = $amazonSheet ? floatval($amazonSheet->price ?? 0) : 0;
                $amazonL30 = $amazonSheet ? intval($amazonSheet->units_ordered_l30 ?? 0) : 0;
                
                // Amazon GPFT% = (price × marketplace_percentage - ship - lp) / price × 100
                $amazonGPFT = $amazonPrice > 0 ? ((($amazonPrice * $amazonPercentage - $ship - $lp) / $amazonPrice) * 100) : 0;
                // Amazon PFT% (gross from Amazon price only, using marketplace %)
                $amzPft = $amazonPrice > 0 ? round((($amazonPrice * $amazonPercentage - $lp - $ship) / $amazonPrice) * 100, 2) : null;
                // Amazon ROI% = ((price × percentage - lp - ship) / lp) × 100 when lp > 0
                $amzRoi = ($lp > 0 && $amazonPrice > 0) ? round((($amazonPrice * $amazonPercentage - $lp - $ship) / $lp) * 100, 2) : null;

                // Get Amazon ad spend
                $amazonCampaign = $amazonSpCampaigns->get($sku);
                $amazonAdSpend = $amazonCampaign ? floatval($amazonCampaign->spend ?? 0) : 0;
                $amazonRevenue = $amazonPrice * $amazonL30;
                $amazonAD = $amazonRevenue > 0 ? ($amazonAdSpend / $amazonRevenue) * 100 : 0;
                
                // Amazon PFT% = GPFT% - AD%
                $amazonPFT = $amazonGPFT - $amazonAD;

                // Rating/reviews and LQS from Jungle Scout
                // Priority: 1) exact ASIN match, 2) SKU group, 3) ASIN from data id (legacy fallback)
                $rating = null;
                $reviews = null;
                $listingQualityScore = null;

                // Build a candidate list: ASIN-exact match first, then all SKU entries
                $jsEntries = [];
                if ($amazonSheet && !empty($amazonSheet->asin)) {
                    $asinKey = strtoupper(trim($amazonSheet->asin));
                    $asinData = $jungleScoutByAsin->get($asinKey);
                    if ($asinData && !empty($asinData['all_data'])) {
                        foreach ($asinData['all_data'] as $entry) {
                            $jsEntries[] = $entry;
                        }
                    }
                }
                $skuData = $jungleScoutBySku->get($sku);
                if ($skuData && !empty($skuData['all_data'])) {
                    foreach ($skuData['all_data'] as $entry) {
                        $jsEntries[] = $entry;
                    }
                }

                // Pick the best entry: first one with a positive rating (most recent due to DESC ordering)
                foreach ($jsEntries as $jsEntry) {
                    if (isset($jsEntry['rating']) && $jsEntry['rating'] > 0) {
                        $rating = (float) $jsEntry['rating'];
                        $reviews = isset($jsEntry['reviews']) ? (int) $jsEntry['reviews'] : null;
                        if (isset($jsEntry['listing_quality_score']) && $jsEntry['listing_quality_score'] !== '' && $jsEntry['listing_quality_score'] !== null) {
                            $listingQualityScore = is_numeric($jsEntry['listing_quality_score'])
                                ? (float) $jsEntry['listing_quality_score']
                                : null; // discard non-numeric LQS values
                        }
                        break;
                    }
                }
                // If no rated entry, still try to get a numeric LQS from any entry
                if ($listingQualityScore === null) {
                    foreach ($jsEntries as $jsEntry) {
                        if (isset($jsEntry['listing_quality_score']) && is_numeric($jsEntry['listing_quality_score']) && $jsEntry['listing_quality_score'] !== '') {
                            $listingQualityScore = (float) $jsEntry['listing_quality_score'];
                            break;
                        }
                    }
                }

                // === TEMU CALCULATIONS ===
                $temuPricing = $temuPricings->get($sku);
                $temuBasePrice = $temuPricing ? floatval($temuPricing->base_price ?? 0) : 0;
                $temuPrice = $temuBasePrice > 0 ? ($temuBasePrice <= 26.99 ? $temuBasePrice + 2.99 : $temuBasePrice) : 0;
                
                $temuSales = $temuDailySales->get($sku);
                $temuL30 = $temuSales ? intval($temuSales->temu_l30 ?? 0) : 0;
                
                // Temu GPFT% = (price × percentage - lp - temu_ship) / price × 100
                $temuShip = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                    }
                }
                $temuGPFT = $temuPrice > 0 ? ((($temuPrice * $temuPercentage - $lp - $temuShip) / $temuPrice) * 100) : 0;
                
                // Get Temu ad spend
                $goodsId = $temuPricing ? ($temuPricing->goods_id ?? null) : null;
                $temuAdSpend = 0;
                if ($goodsId) {
                    $temuAdData = TemuAdData::where('goods_id', $goodsId)->first();
                    $temuAdSpend = $temuAdData ? floatval($temuAdData->spend ?? 0) : 0;
                }
                
                $temuRevenue = $temuPrice * $temuL30;
                if ($temuAdSpend > 0 && $temuL30 == 0) {
                    $temuAD = 100;
                } else {
                    $temuAD = $temuRevenue > 0 ? ($temuAdSpend / $temuRevenue) * 100 : 0;
                }
                
                // Temu PFT%
                if ($temuAD == 100) {
                    $temuPFT = $temuGPFT;
                } else {
                    $temuPFT = $temuGPFT - $temuAD;
                }

                // === TEMU 2 CALCULATIONS (same price/GPFT as Temu; no ad % in rollups) ===
                $temu2Pricing = $temu2PricingByProductSku[$sku] ?? null;
                $temu2BasePrice = $temu2Pricing ? floatval($temu2Pricing->base_price ?? 0) : 0;
                $temu2Price = $temu2BasePrice > 0 ? ($temu2BasePrice <= 26.99 ? $temu2BasePrice + 2.99 : $temu2BasePrice) : 0;
                $temu2L30 = (int) ($temu2L30ByProductSku[$sku] ?? 0);
                $temu2GPFT = $temu2Price > 0 ? ((($temu2Price * $temuPercentage - $lp - $temuShip) / $temu2Price) * 100) : 0;
                $temu2PFT = $temu2GPFT;
                $temu2Views = 0;
                if ($temu2Pricing) {
                    $temu2Views = $this->lookupTemuViewsByGoodsId(
                        $temu2Pricing->goods_id ?? null,
                        $temu2ViewByGoodsId
                    ) ?? 0;
                }

                // === SHEIN CALCULATIONS ===
                // Price = special_offer_price (same logic as Shein pricing page)
                $sheinPricing = $sheinPricings->get($sku);
                $sheinSpOffer = $sheinPricing ? floatval($sheinPricing->special_offer_price ?? 0) : 0;
                $sheinPrice   = $sheinSpOffer > 0 ? $sheinSpOffer : ($sheinPricing ? floatval($sheinPricing->price ?? 0) : 0);
                $sheinViews   = 0; // shein_pricing_prices doesn't track views
                $sheinSaleRow = $sheinDailySales->get($sku);
                $sheinL30     = $sheinSaleRow ? intval($sheinSaleRow->shein_l30 ?? 0) : 0;
                $sheinShip    = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'shein_ship') $sheinShip = floatval($v);
                    }
                }
                $sheinGPFT = $sheinPrice > 0 ? ((($sheinPrice * $sheinPercentage - $lp - $sheinShip) / $sheinPrice) * 100) : 0;
                $sheinPFT  = $sheinGPFT; // No ads for Shein

                // === ALIEXPRESS CALCULATIONS ===
                // Price from aliexpress_pricing_prices; L30 from aliexpress_daily_data by sku_code
                $aePricing  = $aePricings->get($sku);
                $aePrice    = $aePricing ? floatval($aePricing->price ?? 0) : 0;
                $aeSaleRow  = $aeDailySales->get($sku);
                $aeL30      = $aeSaleRow ? intval($aeSaleRow->ae_l30 ?? 0) : 0;
                $aeGPFT     = $aePrice > 0 ? ((($aePrice * $aePercentage - $lp - $ship) / $aePrice) * 100) : 0;
                $aePFT      = $aeGPFT; // No ads for AliExpress

                // === PURCHASING POWER CALCULATIONS ===
                $ppProduct = $ppProducts->get($sku);
                $ppPrice   = $ppProduct ? floatval($ppProduct->price ?? 0) : 0;
                $ppSaleRow = $ppSalesQty->get(strtoupper($sku));
                $ppL30     = $ppSaleRow ? intval($ppSaleRow) : ($ppProduct ? intval($ppProduct->m_l30 ?? 0) : 0);
                $ppGPFT    = $ppPrice > 0 ? ((($ppPrice * $ppPercentage - $lp - $ship) / $ppPrice) * 100) : 0;
                $ppPFT     = $ppGPFT; // No ads for Purchasing Power

                // Calculate aggregated metrics across all marketplaces
                
                // Get views from all marketplaces
                $amazonViews = $amazonSheet ? intval($amazonSheet->sessions_l30 ?? 0) : 0;
                $ebay1Views = $ebay1Metric ? intval($ebay1Metric->views ?? 0) : 0;
                $ebay2Views = $ebay2Metric ? intval($ebay2Metric->views ?? 0) : 0;
                $ebay3Views = $ebay3Metric ? intval($ebay3Metric->views ?? 0) : 0;
                // Temu views = product_clicks from temu_view_data (same as /temu-decrease)
                $temuViews = 0;
                if ($temuPricing) {
                    $temuViews = $this->lookupTemuViewsByGoodsId(
                        $temuPricing->goods_id ?? null,
                        $temuViewByGoodsId
                    ) ?? 0;
                }
                $tiktokViews = $tiktokProduct ? intval($tiktokProduct->views ?? 0) : 0;
                $bbViews = 0; // BestBuy doesn't track views
                // Shopify L30 product page sessions (same shopify_skus.views as /shopify-b2c-pricing)
                $sb2cViews = isset($shopifyData[$sku]) ? intval($shopifyData[$sku]->views ?? 0) : 0;
                $macyViews = $macyProduct ? intval($macyProduct->views ?? 0) : 0;
                $reverbViews = $reverbProduct ? intval($reverbProduct->views ?? 0) : 0;
                $dobaViews = $dobaMetric ? intval($dobaMetric->impressions ?? 0) : 0;
                
                // Total Views (sum of all marketplace views)
                $totalViews = $amazonViews + $ebay1Views + $ebay2Views + $ebay3Views + $temuViews + $temu2Views
                              + $walmartViews + $tiktokViews + $bbViews + $sb2cViews
                              + $macyViews + $reverbViews + $dobaViews + $sheinViews; // AliExpress has no views tracked
                // Get L30 from all marketplaces
                $ebay1L30 = $ebay1Metric ? intval($ebay1Metric->ebay_l30 ?? 0) : 0;
                $ebay2L30 = $ebay2Metric ? intval($ebay2Metric->ebay_l30 ?? 0) : 0;
                $ebay3L30 = $ebay3Metric ? intval($ebay3Metric->ebay_l30 ?? 0) : 0;
                $walmartL30 = $walmartOrderTotals->get($sku) ? intval($walmartOrderTotals->get($sku)->total_qty ?? 0) : 0;
                $tiktokL30 = 0; // TikTok L30 would need ShipHub query (skip for performance)
                $bbL30 = $bestbuyProduct ? intval($bestbuyProduct->m_l30 ?? 0) : 0;
                $sb2cL30 = 0; // Shopify B2C L30 is in overall_l30 (already counted)
                $macyL30 = $macyProduct ? intval($macyProduct->m_l30 ?? 0) : 0;
                $reverbL30 = $reverbProduct ? intval($reverbProduct->r_l30 ?? 0) : 0;
                $dobaL30 = $dobaMetric ? intval($dobaMetric->quantity_l30 ?? 0) : 0;
                
                // Total L30 across all marketplaces
                $totalL30 = $amazonL30 + $ebay1L30 + $ebay2L30 + $ebay3L30 + $temuL30 + $temu2L30
                           + $walmartL30 + $tiktokL30 + $bbL30 + $sb2cL30
                           + $macyL30 + $reverbL30 + $dobaL30 + $sheinL30 + $aeL30 + $ppL30;
                
                // Calculate Avg CVR using CVR formula: (Total L30 / Total Views) × 100
                $avgCVR = $totalViews > 0 ? round(($totalL30 / $totalViews) * 100, 2) : 0;
                
                // Collect all prices (non-zero)
                $prices = [];
                if ($amazonPrice > 0) $prices[] = $amazonPrice;
                if ($ebay1Price > 0) $prices[] = $ebay1Price;
                if ($ebay2Price > 0) $prices[] = $ebay2Price;
                if ($ebay3Price > 0) $prices[] = $ebay3Price;
                if ($temuPrice > 0) $prices[] = $temuPrice;
                if ($temu2Price > 0) $prices[] = $temu2Price;
                if ($walmartPrice > 0) $prices[] = $walmartPrice;
                if ($tiktokPrice > 0) $prices[] = $tiktokPrice;
                if ($bbPrice > 0) $prices[] = $bbPrice;
                if ($sb2cPrice > 0) $prices[] = $sb2cPrice;
                if ($macyPrice > 0) $prices[] = $macyPrice;
                if ($reverbPrice > 0) $prices[] = $reverbPrice;
                if ($dobaPrice > 0) $prices[] = $dobaPrice;
                if ($sheinPrice > 0) $prices[] = $sheinPrice;
                if ($aePrice > 0) $prices[] = $aePrice;
                if ($ppPrice > 0) $prices[] = $ppPrice;
                
                // Collect all GPFT values (non-zero or negative)
                $gpftValues = [];
                if ($amazonPrice > 0) $gpftValues[] = $amazonGPFT;
                if ($ebay1Price > 0) $gpftValues[] = $ebay1GPFT;
                if ($ebay2Price > 0) $gpftValues[] = $ebay2GPFT;
                if ($ebay3Price > 0) $gpftValues[] = $ebay3GPFT;
                if ($temuPrice > 0) $gpftValues[] = $temuGPFT;
                if ($temu2Price > 0) $gpftValues[] = $temu2GPFT;
                if ($walmartPrice > 0) $gpftValues[] = $walmartGPFT;
                if ($tiktokPrice > 0) $gpftValues[] = $tiktokGPFT;
                if ($bbPrice > 0) $gpftValues[] = $bbGPFT;
                if ($sb2cPrice > 0) $gpftValues[] = $sb2cGPFT;
                if ($macyPrice > 0) $gpftValues[] = $macyGPFT;
                if ($reverbPrice > 0) $gpftValues[] = $reverbGPFT;
                if ($dobaPrice > 0) $gpftValues[] = $dobaGPFT;
                if ($sheinPrice > 0) $gpftValues[] = $sheinGPFT;
                if ($aePrice > 0) $gpftValues[] = $aeGPFT;
                if ($ppPrice > 0) $gpftValues[] = $ppGPFT;
                
                // Sales-weighted Ads%: (Σ ad spend $) ÷ (Σ sales $) × 100
                // for marketplaces with ads (Amazon, Temu, Walmart) that have sales
                $totalAdsAmount = 0.0;
                $totalAdSalesAmount = 0.0;
                if ($amazonRevenue > 0) {
                    $totalAdsAmount += $amazonAdSpend;
                    $totalAdSalesAmount += $amazonRevenue;
                }
                if ($temuRevenue > 0) {
                    $totalAdsAmount += $temuAdSpend;
                    $totalAdSalesAmount += $temuRevenue;
                }
                if ($wL30 > 0) {
                    $totalAdsAmount += $walmartAdSpend;
                    $totalAdSalesAmount += $wL30;
                }

                // Collect all PFT values
                $pftValues = [];
                if ($amazonPrice > 0) $pftValues[] = $amazonPFT;
                if ($ebay1Price > 0) $pftValues[] = $ebay1PFT;
                if ($ebay2Price > 0) $pftValues[] = $ebay2PFT;
                if ($ebay3Price > 0) $pftValues[] = $ebay3PFT;
                if ($temuPrice > 0) $pftValues[] = $temuPFT;
                if ($temu2Price > 0) $pftValues[] = $temu2PFT;
                if ($walmartPrice > 0) $pftValues[] = $walmartPFT;
                if ($tiktokPrice > 0) $pftValues[] = $tiktokPFT;
                if ($bbPrice > 0) $pftValues[] = $bbPFT;
                if ($sb2cPrice > 0) $pftValues[] = $sb2cPFT;
                if ($macyPrice > 0) $pftValues[] = $macyPFT;
                if ($reverbPrice > 0) $pftValues[] = $reverbPFT;
                if ($dobaPrice > 0) $pftValues[] = $dobaPFT;
                if ($sheinPrice > 0) $pftValues[] = $sheinPFT;
                if ($aePrice > 0) $pftValues[] = $aePFT;
                if ($ppPrice > 0) $pftValues[] = $ppPFT;
                
                // Collect GROI% / NROI% per listed channel:
                // GROI = GPFT% × price / LP ; NROI = NPFT% × price / LP (after ads)
                $roiValues = [];
                $nroiValues = [];
                if ($lp > 0) {
                    if ($amazonPrice > 0) {
                        $roiValues[] = ($amazonGPFT * $amazonPrice) / $lp;
                        $nroiValues[] = ($amazonPFT * $amazonPrice) / $lp;
                    }
                    if ($ebay1Price > 0) {
                        $roiValues[] = ($ebay1GPFT * $ebay1Price) / $lp;
                        $nroiValues[] = ($ebay1PFT * $ebay1Price) / $lp;
                    }
                    if ($ebay2Price > 0) {
                        $roiValues[] = ($ebay2GPFT * $ebay2Price) / $lp;
                        $nroiValues[] = ($ebay2PFT * $ebay2Price) / $lp;
                    }
                    if ($ebay3Price > 0) {
                        $roiValues[] = ($ebay3GPFT * $ebay3Price) / $lp;
                        $nroiValues[] = ($ebay3PFT * $ebay3Price) / $lp;
                    }
                    if ($temuPrice > 0) {
                        $roiValues[] = ($temuGPFT * $temuPrice) / $lp;
                        $nroiValues[] = ($temuPFT * $temuPrice) / $lp;
                    }
                    if ($temu2Price > 0) {
                        $roiValues[] = ($temu2GPFT * $temu2Price) / $lp;
                        $nroiValues[] = ($temu2PFT * $temu2Price) / $lp;
                    }
                    if ($walmartPrice > 0) {
                        $roiValues[] = ($walmartGPFT * $walmartPrice) / $lp;
                        $nroiValues[] = ($walmartPFT * $walmartPrice) / $lp;
                    }
                    if ($tiktokPrice > 0) {
                        $roiValues[] = ($tiktokGPFT * $tiktokPrice) / $lp;
                        $nroiValues[] = ($tiktokPFT * $tiktokPrice) / $lp;
                    }
                    if ($bbPrice > 0) {
                        $roiValues[] = ($bbGPFT * $bbPrice) / $lp;
                        $nroiValues[] = ($bbPFT * $bbPrice) / $lp;
                    }
                    if ($sb2cPrice > 0) {
                        $roiValues[] = ($sb2cGPFT * $sb2cPrice) / $lp;
                        $nroiValues[] = ($sb2cPFT * $sb2cPrice) / $lp;
                    }
                    if ($macyPrice > 0) {
                        $roiValues[] = ($macyGPFT * $macyPrice) / $lp;
                        $nroiValues[] = ($macyPFT * $macyPrice) / $lp;
                    }
                    if ($reverbPrice > 0) {
                        $roiValues[] = ($reverbGPFT * $reverbPrice) / $lp;
                        $nroiValues[] = ($reverbPFT * $reverbPrice) / $lp;
                    }
                    if ($dobaPrice > 0) {
                        $roiValues[] = ($dobaGPFT * $dobaPrice) / $lp;
                        $nroiValues[] = ($dobaPFT * $dobaPrice) / $lp;
                    }
                    if ($sheinPrice > 0) {
                        $roiValues[] = ($sheinGPFT * $sheinPrice) / $lp;
                        $nroiValues[] = ($sheinPFT * $sheinPrice) / $lp;
                    }
                    if ($aePrice > 0) {
                        $roiValues[] = ($aeGPFT * $aePrice) / $lp;
                        $nroiValues[] = ($aePFT * $aePrice) / $lp;
                    }
                    if ($ppPrice > 0) {
                        $roiValues[] = ($ppGPFT * $ppPrice) / $lp;
                        $nroiValues[] = ($ppPFT * $ppPrice) / $lp;
                    }
                }

                // Calculate averages
                $avgPrice = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
                $avgGPFT = count($gpftValues) > 0 ? round(array_sum($gpftValues) / count($gpftValues), 2) : 0;
                $avgAD = $totalAdSalesAmount > 0
                    ? round(($totalAdsAmount / $totalAdSalesAmount) * 100, 2)
                    : 0;
                $avgPFT = count($pftValues) > 0 ? round(array_sum($pftValues) / count($pftValues), 2) : 0;
                $avgRoi = count($roiValues) > 0 ? round(array_sum($roiValues) / count($roiValues), 2) : 0;
                $avgNroi = count($nroiValues) > 0 ? round(array_sum($nroiValues) / count($nroiValues), 2) : 0;

                // Get latest remark for this SKU
                $latestRemark = $latestRemarks->get($sku);
                $remarkText = $latestRemark ? $latestRemark->remark : null;
                $remarkSolved = $latestRemark ? $latestRemark->is_solved : false;

                // Amazon LMP and eBay LMP
                $skuLookupKey = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
                $amazonLmp = $amazonLmpLookup->get($skuLookupKey);
                $ebayLmp = $ebayLmpLookup->get($skuLookupKey);
                $googleLmp = $googleLmpLookup->get($skuLookupKey);
                $amazonLmpPrice = ($amazonLmp && isset($amazonLmp->price) && is_numeric($amazonLmp->price))
                    ? round(floatval($amazonLmp->price), 2) : null;
                $amazonLmpLink = ($amazonLmp && !empty($amazonLmp->product_link)) ? $amazonLmp->product_link : null;
                $ebayLmpTotal = null;
                $ebayLmpLink = null;
                if ($ebayLmp) {
                    $t = floatval($ebayLmp->total_price ?? 0);
                    if ($t <= 0) {
                        $t = floatval($ebayLmp->price ?? 0) + floatval($ebayLmp->shipping_cost ?? 0);
                    }
                    $ebayLmpTotal = $t > 0 ? round($t, 2) : null;
                    $ebayLmpLink = !empty($ebayLmp->product_link) ? $ebayLmp->product_link : null;
                }
                $ebayLmpPrice = $ebayLmpTotal;
                $amazonLmpCount = $amazonLmpCountLookup->get($skuLookupKey) ?? 0;
                $ebayLmpCount = $ebayLmpCountLookup->get($skuLookupKey) ?? 0;
                $googleLmpPrice = ($googleLmp && isset($googleLmp->price) && is_numeric($googleLmp->price))
                    ? round(floatval($googleLmp->price), 2) : null;
                $googleLmpLink = ($googleLmp && !empty($googleLmp->product_link)) ? $googleLmp->product_link : null;
                $googleLmpCount = $googleLmpCountLookup->get($skuLookupKey) ?? 0;

                $temuLmpResolved = $this->resolveTemuLmpForSku(
                    $sku,
                    $temuLmpByNormalizedSku,
                    $temuLmpSkuGroupService
                );
                $temuLmpPrice = $temuLmpResolved['price'];
                $temuLmpLink = $temuLmpResolved['link'];
                $temuLmpCount = $temuLmpResolved['count'];

                $amazonDataViewRow = $amazonDataViewBySku->get($sku);
                $amazonSprice = null;
                $amazonSgpft = null;
                $amazonSpft = null;
                $amazonSroi = null;
                if ($amazonDataViewRow && $amazonDataViewRow->value) {
                    $avVal = is_array($amazonDataViewRow->value) ? $amazonDataViewRow->value : (json_decode($amazonDataViewRow->value ?? '{}', true) ?? []);
                    $spr = $avVal['SPRICE'] ?? null;
                    $amazonSprice = ($spr !== null && $spr !== '' && floatval($spr) > 0) ? round(floatval($spr), 2) : null;
                    if (isset($avVal['SGPFT'])) $amazonSgpft = round(floatval($avVal['SGPFT']), 2);
                    if (isset($avVal['SPFT'])) $amazonSpft = round(floatval($avVal['SPFT']), 2);
                    if (isset($avVal['SROI'])) $amazonSroi = round(floatval($avVal['SROI']), 2);
                }

                // Determine if any channel is missing a listing (price = 0 / null)
                // eBay2 is excluded from the check if SKU weight > 0.75 LB
                // Walmart excluded – removed from this page
                $missingChannelPrices = [
                    $amazonPrice, $ebay1Price, $ebay3Price, $temuPrice, $temu2Price,
                    $tiktokPrice, $bbPrice, $sb2cPrice,
                    $macyPrice, $reverbPrice, $dobaPrice, $sheinPrice, $aePrice,
                ];
                if ($actWt <= 0.75) {
                    $missingChannelPrices[] = $ebay2Price;
                }
                $missingL = in_array(true, array_map(fn($p) => floatval($p) <= 0, $missingChannelPrices));

                $result[] = (object) [
                    "sku" => $sku,
                    "parent" => $parent,
                    "image_path" => $imagePath,
                    "inventory" => $inventory,
                    "amazon_price" => $amazonPrice > 0 ? round($amazonPrice, 2) : null,
                    "amazon_sprice" => $amazonSprice,
                    "amazon_sgpft" => $amazonSgpft,
                    "amazon_spft" => $amazonSpft,
                    "amazon_sroi" => $amazonSroi,
                    "amazon_lp" => $lp,
                    "amazon_ship" => $ship,
                    "amazon_ad" => round($amazonAD, 2),
                    "amazon_margin" => 0.80,
                    "amazon_l30" => $amazonL30,
                    "shein_l30" => $sheinL30,
                    "ae_l30"    => $aeL30,
                    "pp_l30"    => $ppL30,
                    "amz_pft" => $amzPft,
                    "amz_roi" => $amzRoi,
                    "overall_l30" => $overallL30,
                    "fba_l30" => $fbaL30Units,
                    "ov_l30_plus_fba" => $ovL30PlusFba,
                    "m_l30" => $totalL30,
                    "dil_percent" => $dilPercent,
                    "total_views" => $totalViews,
                    "avg_cvr" => $avgCVR,
                    "avg_price" => $avgPrice,
                    "avg_roi" => $avgRoi,
                    "avg_nroi" => $avgNroi,
                    "avg_gpft" => $avgGPFT,
                    "avg_ad" => $avgAD,
                    "avg_pft" => $avgPFT,
                    "amazon_lmp_price" => $amazonLmpPrice,
                    "ebay_lmp_price" => $ebayLmpPrice,
                    "google_lmp_price" => $googleLmpPrice,
                    "temu_lmp_price" => $temuLmpPrice,
                    "amazon_lmp_link" => $amazonLmpLink,
                    "ebay_lmp_link" => $ebayLmpLink,
                    "google_lmp_link" => $googleLmpLink,
                    "temu_lmp_link" => $temuLmpLink,
                    "amazon_lmp_count" => $amazonLmpCount,
                    "ebay_lmp_count" => $ebayLmpCount,
                    "google_lmp_count" => $googleLmpCount,
                    "temu_lmp_count" => $temuLmpCount,
                    "rating" => $rating,
                    "reviews" => $reviews,
                    "listing_quality_score" => $listingQualityScore,
                    "latest_remark" => $remarkText,
                    "remark_solved" => $remarkSolved,
                    "missing_l" => $missingL,
                ];
            }

            // Group by parent and create synthetic parent rows (like Amazon)
            $groupedByParent = collect($result)->groupBy('parent');
            $finalResult = [];
            $slNo = 1;

            foreach ($groupedByParent as $parent => $rows) {
                // Add child rows first
                foreach ($rows as $row) {
                    $row->{'SL No.'} = $slNo++;
                    $row->is_parent_summary = false;
                    $finalResult[] = $row;
                }

                // Skip creating parent row if parent is empty
                if (empty($parent)) {
                    continue;
                }

                // Create synthetic parent summary row (placed BELOW children)
                $amazonLmpVals = $rows->pluck('amazon_lmp_price')->filter(fn ($v) => $v !== null && $v > 0);
                $ebayLmpVals = $rows->pluck('ebay_lmp_price')->filter(fn ($v) => $v !== null && $v > 0);
                $googleLmpVals = $rows->pluck('google_lmp_price')->filter(fn ($v) => $v !== null && $v > 0);
                $temuLmpVals = $rows->pluck('temu_lmp_price')->filter(fn ($v) => $v !== null && $v > 0);
                $amazonPriceVals = $rows->pluck('amazon_price')->filter(fn ($v) => $v !== null && $v > 0);
                $amazonSpriceVals = $rows->pluck('amazon_sprice')->filter(fn ($v) => $v !== null && $v > 0);
                $amazonSgpftVals = $rows->pluck('amazon_sgpft')->filter(fn ($v) => $v !== null);
                $amazonSpftVals = $rows->pluck('amazon_spft')->filter(fn ($v) => $v !== null);
                $amazonSroiVals = $rows->pluck('amazon_sroi')->filter(fn ($v) => $v !== null);
                $parentRow = [
                    'SL No.' => $slNo++,
                    'sku' => 'PARENT ' . $parent,
                    'parent' => $parent,
                    'image_path' => null,
                    'inventory' => $rows->sum('inventory'),
                    'amazon_price' => $amazonPriceVals->isNotEmpty() ? round($amazonPriceVals->avg(), 2) : null,
                    'amazon_sprice' => $amazonSpriceVals->isNotEmpty() ? round($amazonSpriceVals->avg(), 2) : null,
                    'amazon_sgpft' => $amazonSgpftVals->isNotEmpty() ? round($amazonSgpftVals->avg(), 2) : null,
                    'amazon_spft' => $amazonSpftVals->isNotEmpty() ? round($amazonSpftVals->avg(), 2) : null,
                    'amazon_sroi' => $amazonSroiVals->isNotEmpty() ? round($amazonSroiVals->avg(), 2) : null,
                    'amazon_lp' => null,
                    'amazon_ship' => null,
                    'amazon_ad' => null,
                    'amazon_margin' => 0.80,
                    'amazon_l30' => null,
                    'shein_l30' => $rows->sum('shein_l30'),
                    'ae_l30'    => $rows->sum('ae_l30'),
                    'pp_l30'    => $rows->sum('pp_l30'),
                    'amz_pft' => $rows->filter(fn ($r) => isset($r->amz_pft) && $r->amz_pft !== null)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->amz_pft) && $r->amz_pft !== null)->avg('amz_pft'), 2) : null,
                    'amz_roi' => $rows->filter(fn ($r) => isset($r->amz_roi) && $r->amz_roi !== null)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->amz_roi) && $r->amz_roi !== null)->avg('amz_roi'), 2) : null,
                    'overall_l30' => $rows->sum('overall_l30'),
                    'fba_l30' => $rows->sum('fba_l30'),
                    'ov_l30_plus_fba' => $rows->sum('ov_l30_plus_fba'),
                    'm_l30' => $rows->sum('m_l30'),
                    'dil_percent' => 0, // Calculate after
                    'total_views' => $rows->sum('total_views'),
                    'avg_cvr' => $rows->count() > 0 ? round($rows->avg('avg_cvr'), 2) : 0,
                    'avg_price' => $rows->count() > 0 ? round($rows->avg('avg_price'), 2) : 0,
                    'avg_roi' => $rows->filter(fn ($r) => isset($r->avg_roi) && $r->avg_roi !== null)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->avg_roi) && $r->avg_roi !== null)->avg('avg_roi'), 2) : 0,
                    'avg_nroi' => $rows->filter(fn ($r) => isset($r->avg_nroi) && $r->avg_nroi !== null)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->avg_nroi) && $r->avg_nroi !== null)->avg('avg_nroi'), 2) : 0,
                    'avg_gpft' => $rows->count() > 0 ? round($rows->avg('avg_gpft'), 2) : 0,
                    // Sales-weighted Ads% across children: Σ(sales × avg_ad%) ÷ Σ sales
                    'avg_ad' => (function () use ($rows) {
                        $adsAmt = 0.0;
                        $salesAmt = 0.0;
                        foreach ($rows as $r) {
                            $sales = floatval($r->avg_price ?? 0) * floatval($r->overall_l30 ?? 0);
                            if ($sales <= 0) {
                                continue;
                            }
                            $salesAmt += $sales;
                            $adsAmt += $sales * (floatval($r->avg_ad ?? 0) / 100);
                        }
                        return $salesAmt > 0 ? round(($adsAmt / $salesAmt) * 100, 2) : 0;
                    })(),
                    'avg_pft' => $rows->count() > 0 ? round($rows->avg('avg_pft'), 2) : 0,
                    'amazon_lmp_price' => $amazonLmpVals->isNotEmpty() ? round($amazonLmpVals->avg(), 2) : null,
                    'ebay_lmp_price' => $ebayLmpVals->isNotEmpty() ? round($ebayLmpVals->avg(), 2) : null,
                    'google_lmp_price' => $googleLmpVals->isNotEmpty() ? round($googleLmpVals->avg(), 2) : null,
                    'temu_lmp_price' => $temuLmpVals->isNotEmpty() ? round($temuLmpVals->avg(), 2) : null,
                    'amazon_lmp_link' => null,
                    'ebay_lmp_link' => null,
                    'temu_lmp_link' => null,
                    'amazon_lmp_count' => $rows->sum('amazon_lmp_count'),
                    'ebay_lmp_count' => $rows->sum('ebay_lmp_count'),
                    'temu_lmp_count' => $rows->sum('temu_lmp_count'),
                    'rating' => $rows->filter(fn ($r) => isset($r->rating) && $r->rating > 0)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->rating) && $r->rating > 0)->avg('rating'), 1) : null,
                    'reviews' => $rows->sum('reviews'),
                    'listing_quality_score' => $rows->filter(fn ($r) => isset($r->listing_quality_score) && is_numeric($r->listing_quality_score) && $r->listing_quality_score > 0)->isNotEmpty()
                        ? round($rows->filter(fn ($r) => isset($r->listing_quality_score) && is_numeric($r->listing_quality_score) && $r->listing_quality_score > 0)->avg('listing_quality_score'), 1) : null,
                    'is_parent_summary' => true,
                ];

                // Calculate parent DIL%
                $parentInv = $parentRow['inventory'];
                $parentL30 = $parentRow['overall_l30'];
                $parentRow['dil_percent'] = $parentInv > 0 ? round(($parentL30 / $parentInv) * 100, 2) : 0;
                // Parent missing_l = true if any child has missing listings
                $parentRow['missing_l'] = $rows->contains(fn ($r) => isset($r->missing_l) && $r->missing_l === true);

                $finalResult[] = (object) $parentRow;
            }

            // Log summary
            $wmDataCount = collect($finalResult)->filter(function($row) {
                return ($row->wm_views ?? 0) > 0 || ($row->wm_gpft ?? 0) != 0;
            })->count();
            
            Log::info('CVR Master - Final Results Summary', [
                'total_rows' => count($finalResult),
                'rows_with_wm_data' => $wmDataCount,
                'sample_wm_data' => collect($finalResult)->filter(function($row) {
                    return ($row->wm_views ?? 0) > 0;
                })->take(3)->map(function($row) {
                    return [
                        'sku' => $row->sku,
                        'wm_views' => $row->wm_views ?? 0,
                        'wm_cvr' => $row->wm_cvr ?? 0,
                        'wm_gpft' => $row->wm_gpft ?? 0,
                        'wm_ad' => $row->wm_ad ?? 0,
                        'wm_pft' => $row->wm_pft ?? 0,
                    ];
                })->values()->toArray()
            ]);

            // Auto-save SKU-wise daily snapshot on refresh (for Inv, OV L30, Price, CVR graphs per SKU)
            try {
                $childRows = collect($finalResult)->filter(fn($r) => empty($r->is_parent_summary));
                $today = now('America/Los_Angeles')->toDateString();
                $saved = 0;
                foreach ($childRows as $row) {
                    $raw = is_string($row->sku ?? null) ? $row->sku : (string) ($row->sku ?? '');
                    $sku = preg_replace('/\s+/', ' ', trim($raw));
                    if ($sku === '') continue;
                    PricingMasterDailySnapshotSku::updateOrCreate(
                        ['snapshot_date' => $today, 'sku' => $sku],
                        [
                            'inventory' => (int) ($row->inventory ?? 0),
                            'overall_l30' => (int) ($row->overall_l30 ?? 0),
                            'avg_price' => isset($row->avg_price) && $row->avg_price > 0 ? round((float) $row->avg_price, 2) : null,
                            'avg_cvr' => isset($row->avg_cvr) && $row->avg_cvr !== null ? round((float) $row->avg_cvr, 2) : null,
                            'dil_percent' => isset($row->dil_percent) && $row->dil_percent !== null ? round((float) $row->dil_percent, 2) : null,
                            'amazon_price' => isset($row->amazon_price) && $row->amazon_price > 0 ? round((float) $row->amazon_price, 2) : null,
                            'rating' => isset($row->rating) && $row->rating > 0 ? round((float) $row->rating, 2) : null,
                            'total_views' => (int) ($row->total_views ?? 0),
                        ]
                    );
                    $saved++;
                }
                if ($saved > 0) {
                    Log::info('Master Analytics SKU snapshot saved', ['date' => $today, 'count' => $saved]);
                    // Also save daily totals for aggregate chart (Total INV, OV L30, etc.)
                    $totalInv = $childRows->sum(fn($r) => (int) ($r->inventory ?? 0));
                    $totalOvL30 = $childRows->sum(fn($r) => (int) ($r->overall_l30 ?? 0));
                    $avgPrice = $childRows->filter(fn($r) => isset($r->avg_price) && $r->avg_price > 0)->isNotEmpty()
                        ? round($childRows->filter(fn($r) => isset($r->avg_price) && $r->avg_price > 0)->avg('avg_price'), 2) : null;
                    $totalViews = $childRows->sum(fn($r) => (int) ($r->total_views ?? 0));
                    $avgCvr = $totalViews > 0 && $totalOvL30 > 0
                        ? round(($totalOvL30 / $totalViews) * 100, 2) : null;
                    PricingMasterDailySnapshot::updateOrCreate(
                        ['snapshot_date' => $today],
                        [
                            'total_inv' => $totalInv,
                            'total_ov_l30' => $totalOvL30,
                            'avg_price' => $avgPrice,
                            'avg_cvr' => $avgCvr,
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Master Analytics SKU daily snapshot save failed: ' . $e->getMessage());
            }

            return response()->json($finalResult);
            
        } catch (\Exception $e) {
            Log::error('Error fetching CVR data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch CVR data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Persist pricing-master-cvr Tabulator column visibility in channel_tabulator_column_settings.
     */
    public function saveColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility', []);
            if (!is_array($visibility)) {
                return response()->json(['error' => 'visibility must be an array'], 422);
            }

            $normalized = [];
            foreach ($visibility as $key => $val) {
                $field = trim((string) $key);
                if ($field === '' || strlen($field) > 190) {
                    continue;
                }
                $normalized[$field] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }

            ChannelTabulatorColumnSetting::updateOrCreate(
                ['channel_name' => 'pricing_master_cvr'],
                ['visibility' => $normalized]
            );

            // Keep session in sync for any older clients still reading it
            session(['cvr_master_column_visibility' => $normalized]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save column visibility'], 500);
        }
    }

    /**
     * Load pricing-master-cvr column visibility from channel_tabulator_column_settings.
     */
    public function getColumnVisibility(Request $request)
    {
        try {
            $row = ChannelTabulatorColumnSetting::where('channel_name', 'pricing_master_cvr')->first();
            $visibility = ($row && is_array($row->visibility)) ? $row->visibility : [];

            // One-time fallback: migrate legacy session settings into DB if empty
            if ($visibility === []) {
                $sessionVisibility = session('cvr_master_column_visibility', []);
                if (is_array($sessionVisibility) && $sessionVisibility !== []) {
                    $visibility = $sessionVisibility;
                    ChannelTabulatorColumnSetting::updateOrCreate(
                        ['channel_name' => 'pricing_master_cvr'],
                        ['visibility' => $visibility]
                    );
                }
            }

            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get column visibility'], 500);
        }
    }

    /**
     * Get marketplace breakdown data for specific SKU
     * Used for the OV L30 modal breakdown
     * SKU via query param to support slashes (e.g. 1/4M-3/8M Camera Screw 5Pcs)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBreakdownData(Request $request)
    {
        try {
            $sku = $request->get('sku', '');
            if ($sku === '') {
                return response()->json(['error' => 'SKU required'], 400);
            }
            $breakdownData = [];

            Log::info('Fetching breakdown data for SKU: ' . $sku);
            
            // Channel Ads% — same metric as /all-marketplace-master (Total Ad Spend / L30 Sales × 100).
            // Prefer channel_master_calculated_data (AMM fast-path cache); fall back to marketplace_daily_metrics.
            $channelAdsData = [];

            $resolveAdsPercent = function ($channelLabel, $ads, $tacos) {
                $norm = strtolower(preg_replace('/\s+/', '', (string) $channelLabel));
                // Match AMM formatter: Walmart / TopDawg prefer TACOS % when Ads% is empty.
                // Shopify/Shopify B2C use live AMM Shopify Ads% overlay below (not stale TACOS).
                if (in_array($norm, ['walmart', 'topdawg'], true)) {
                    if ($tacos !== null && $tacos !== '') {
                        return round((float) $tacos, 2);
                    }
                    if ($ads !== null && $ads !== '') {
                        return round((float) $ads, 2);
                    }
                    return 0.0;
                }
                if ($ads !== null && $ads !== '') {
                    return round((float) $ads, 2);
                }
                if ($tacos !== null && $tacos !== '') {
                    return round((float) $tacos, 2);
                }
                return 0.0;
            };

            $dailyMetrics = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
                ->groupBy('channel')
                ->get();

            foreach ($dailyMetrics as $row) {
                $metric = MarketplaceDailyMetric::where('channel', $row->channel)
                    ->where('date', $row->max_date)
                    ->first();

                if ($metric) {
                    $key = strtolower(trim($metric->channel));
                    $channelAdsData[$key] = $resolveAdsPercent(
                        $metric->channel,
                        $metric->ads_percentage,
                        $metric->tacos_percentage
                    );
                }
            }

            foreach (ChannelMasterCalculatedData::select('channel', 'ads_percentage', 'tacos_percentage')->get() as $channel) {
                $key = strtolower(trim($channel->channel));
                $channelAdsData[$key] = $resolveAdsPercent(
                    $channel->channel,
                    $channel->ads_percentage,
                    $channel->tacos_percentage
                );
            }

            // Shopify Ads% on /all-marketplace-master is live (Spend ÷ Sales), overlaid on the
            // "Shopify" channel — not marketplace_daily_metrics "Shopify B2C" TACOS (~7.5%).
            // Master Analytics SB2C must use that same AMM Shopify Ads%.
            try {
                $shopifySnap = app(ChannelMasterController::class)->getShopifyDirectL30Snapshot();
                $shopifyAdsPct = round((float) ($shopifySnap['tcos_pct'] ?? 0), 2);
                if ($shopifyAdsPct > 0) {
                    $channelAdsData['shopify'] = $shopifyAdsPct;
                    $channelAdsData['shopify b2c'] = $shopifyAdsPct;
                    $channelAdsData['shopifyb2c'] = $shopifyAdsPct;
                }
            } catch (\Throwable $e) {
                Log::warning('Master Analytics: Shopify live Ads% overlay failed: ' . $e->getMessage());
            }

            // Helper: Ads% for a breakdown marketplace (maps to AMM / daily-metrics channel names)
            $getChannelAdsPercent = function ($channelName) use ($channelAdsData) {
                $channelMap = [
                    'amazon' => 'amazon',
                    'fba' => 'amazon',
                    'ebay' => 'ebay',
                    'ebay1' => 'ebay',
                    'ebaytwo' => 'ebay 2',
                    'ebay2' => 'ebay 2',
                    'ebaythree' => 'ebay 3',
                    'ebay3' => 'ebay 3',
                    'doba' => 'doba',
                    'tiktok' => 'tiktok',
                    // SB2C ↔ AMM "Shopify" Ads% (live overlay above)
                    'sb2c' => 'shopify',
                    'shopifyb2c' => 'shopify',
                    'shopify' => 'shopify',
                    'sb2b' => 'shopify b2b',
                    'shopifyb2b' => 'shopify b2b',
                    'macy' => 'macys',
                    'macys' => 'macys',
                    'reverb' => 'reverb',
                    'temu' => 'temu',
                    'temu2' => 'temu 2',
                    'bestbuy' => 'best buy usa',
                    'tiendamia' => 'tiendamia',
                    'shein' => 'shein',
                    'aliexpress' => 'aliexpress',
                    'purchasingpower' => 'purchasing power',
                    'ppower' => 'purchasing power',
                    'purchase' => 'purchasing power',
                    'walmart' => 'walmart',
                ];

                $mappedName = $channelMap[strtolower(trim($channelName))] ?? strtolower(trim($channelName));
                // Prefer AMM Shopify key; fall back to shopify b2c if overlay missing
                if ($mappedName === 'shopify') {
                    return $channelAdsData['shopify']
                        ?? $channelAdsData['shopify b2c']
                        ?? $channelAdsData['shopifyb2c']
                        ?? 0;
                }
                return $channelAdsData[$mappedName] ?? 0;
            };

            // Backward-compatible alias used by a few NPFT calculations below
            $getChannelTACOS = $getChannelAdsPercent;

            // Helper to get buyer_link and seller_link from any marketplace listing_statuses table (same structure: sku, value JSON)
            $getListingLinks = function($modelClass, $sku) {
                if (!$sku || $sku === 'Not Listed') {
                    return [null, null];
                }
                $row = $modelClass::where('sku', $sku)->first();
                $val = $row && is_array($row->value) ? $row->value : [];
                return [$val['buyer_link'] ?? null, $val['seller_link'] ?? null];
            };

            // First, get the full SKU from ProductMaster (in case shortened SKU is passed)
            $productMaster = ProductMaster::where('sku', $sku)
                ->orWhere('sku', 'LIKE', $sku . '%')
                ->first();
            
            // Use the full SKU from ProductMaster if found
            $fullSku = $productMaster ? $productMaster->sku : $sku;
            
            if ($fullSku !== $sku) {
                Log::info('Found full SKU in ProductMaster: ' . $fullSku . ' (from: ' . $sku . ')');
            }

            // Fetch channel LMP lookups for breakdown LMP column (Amazon / eBay / Google / Temu)
            $amazonLmpLookup = collect();
            $ebayLmpLookup = collect();
            $googleLmpLookup = collect();
            $temuLmpByNormalizedSku = [];
            $temuLmpSkuGroupService = null;
            try {
                $amazonLmpLookup = AmazonSkuCompetitor::buildGroupedLookup('amazon')['lowest'];
                $ebayLmpLookup = EbaySkuCompetitor::buildGroupedLookup('ebay')['lowest'];
                $googleLmpLookup = GoogleSkuCompetitor::buildGroupedLookup('google')['lowest'];
            } catch (\Exception $e) {
                Log::warning('Could not fetch LMP lookups in breakdown: ' . $e->getMessage());
            }
            try {
                if (Schema::hasTable('temu_lmp')) {
                    foreach (TemuLmp::all() as $temuLmpRow) {
                        $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
                    }
                    $temuLmpSkuGroupService = app(LmpSkuGroupService::class);
                    $temuLmpSkuGroupService->prepareForSkus([$fullSku, $sku]);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch Temu LMP in breakdown: ' . $e->getMessage());
            }

            $skuLookupKey = strtoupper(preg_replace('/\s+/', ' ', trim($fullSku ?? $sku)));
            $resolveAmazonLmpPrice = function (?string $lookupSku = null) use ($amazonLmpLookup) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($lookupSku ?? ''))));
                if ($key === '') {
                    return null;
                }
                $lmp = $amazonLmpLookup->get($key);
                if ($lmp && isset($lmp->price) && is_numeric($lmp->price) && floatval($lmp->price) > 0) {
                    return round(floatval($lmp->price), 2);
                }
                return null;
            };
            $resolveEbayLmpPrice = function (?string $lookupSku = null) use ($ebayLmpLookup) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($lookupSku ?? ''))));
                if ($key === '') {
                    return null;
                }
                $lmp = $ebayLmpLookup->get($key);
                if (!$lmp) {
                    return null;
                }
                $total = floatval($lmp->total_price ?? 0);
                if ($total <= 0) {
                    $total = floatval($lmp->price ?? 0) + floatval($lmp->shipping_cost ?? 0);
                }
                return $total > 0 ? round($total, 2) : null;
            };
            $resolveGoogleLmpPrice = function (?string $lookupSku = null) use ($googleLmpLookup) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($lookupSku ?? ''))));
                if ($key === '') {
                    return null;
                }
                $lmp = $googleLmpLookup->get($key);
                if ($lmp && isset($lmp->price) && is_numeric($lmp->price) && floatval($lmp->price) > 0) {
                    return round(floatval($lmp->price), 2);
                }
                return null;
            };
            $resolveTemuLmpPrice = function (?string $lookupSku = null) use ($temuLmpByNormalizedSku, $temuLmpSkuGroupService) {
                $resolved = $this->resolveTemuLmpForSku(
                    (string) ($lookupSku ?? ''),
                    $temuLmpByNormalizedSku,
                    $temuLmpSkuGroupService
                );
                return $resolved['price'];
            };

            // Get LP and Ship from ProductMaster for profit calculations
            $values = $productMaster ? ($productMaster->Values ?: []) : [];
            $lp = 0;
            $ship = 0;
            $temuShip = 0;
            $actWt = 0;
            
            if ($values) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") $lp = floatval($v);
                    if (strtolower($k) === "ship") $ship = floatval($v);
                    if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                    if (strtolower($k) === "wt_act") $actWt = floatval($v);
                }
            }

            // Fetch views data from views_pull_data for marketplaces that use it
            $viewsPullData = ViewsPullData::where('sku', $fullSku)->first();

            // Fetch Amazon data (using full SKU)
            $amazonData = AmazonDatasheet::where('sku', $fullSku)->first();
            
            // Amazon margin from marketplace_percentages
            $amazonMarketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            $amazonPercentage = $amazonMarketplace ? ($amazonMarketplace->percentage / 100) : 0.80;
            
            // Calculate Amazon GPFT% (line 1887-1890: (price × 0.80 - ship - lp) / price × 100)
            $amazonPrice = $amazonData ? ($amazonData->price ?? 0) : 0;
            $amazonL30 = $amazonData ? ($amazonData->units_ordered_l30 ?? 0) : 0; // CORRECT field name!
            $amazonGPFT = $amazonPrice > 0 ? (($amazonPrice * 0.80 - $ship - $lp) / $amazonPrice) * 100 : 0;
            
            Log::info('Amazon GPFT calc - Price: ' . $amazonPrice . ', L30: ' . $amazonL30 . ', LP: ' . $lp . ', Ship: ' . $ship . ', GPFT%: ' . $amazonGPFT);
            
            // Get Amazon ad spend (line 1877-1878)
            $amazonAdSpend = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where('campaignName', 'LIKE', '%' . $fullSku . '%')
                ->sum('cost');
            
            // Get Amazon ad sales from campaigns (line 1864-1865, 1879)
            $amazonAdSales = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where('campaignName', 'LIKE', '%' . $fullSku . '%')
                ->sum('sales30d');
            
            Log::info('Amazon AD data - SKU: ' . $fullSku . ', Ad Spend: ' . $amazonAdSpend . ', Ad Sales (from campaigns): ' . $amazonAdSales);
            
            // Calculate Amazon AD% (line 1881-1885: AD_Spend / (price × A_L30) × 100)
            // Amazon uses units_ordered (A_L30), but if 0, calculate using spend/price ratio
            $amazonTotalRevenue = $amazonPrice * $amazonL30;
            
            // If no regular sales but has ad spend, calculate AD% from ad sales
            if ($amazonL30 == 0 && $amazonAdSales > 0) {
                $amazonTotalRevenue = $amazonPrice * $amazonAdSales;
            }
            
            $amazonAD = $amazonTotalRevenue > 0 ? ($amazonAdSpend / $amazonTotalRevenue) * 100 : 0;
            
            Log::info('Amazon AD% calculation - L30: ' . $amazonL30 . ', Ad Sales: ' . $amazonAdSales . ', Total Revenue: ' . $amazonTotalRevenue . ', AD Spend: ' . $amazonAdSpend . ', AD%: ' . $amazonAD);
            
            // If ad spend exists but no sales, show 100% AD%
            if ($amazonAdSpend > 0 && $amazonTotalRevenue == 0) {
                $amazonAD = 100;
            }
            
            // Amazon NPFT% - If no sales, NPFT = GPFT
            $amazonNPFT = $amazonL30 == 0 ? $amazonGPFT : ($amazonGPFT - $amazonAD);
            
            // Get Amazon suggested data from amazon_data_view
            $amazonDataView = AmazonDataView::where('sku', $fullSku)->first();
            $amazonSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $amazonPushedBy = null;
            $amazonPushedAt = null;
            if ($amazonDataView) {
                $val = is_array($amazonDataView->value) ? $amazonDataView->value : 
                       (is_string($amazonDataView->value) ? json_decode($amazonDataView->value, true) : []);
                if (is_array($val)) {
                    $amazonSuggested = [
                        'sprice' => $val['SPRICE'] ?? 0,
                        'sgpft' => $val['SGPFT'] ?? 0,
                        'sroi' => $val['SROI'] ?? 0,
                        'spft' => $val['SPFT'] ?? 0,
                    ];
                    // Get pushed by information
                    $amazonPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $amazonPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    // Format pushed at timestamp
                    if ($amazonPushedAt) {
                        try {
                            $amazonPushedAt = Carbon::parse($amazonPushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $amazonPushedAt = null;
                        }
                    }
                }
            }
            
            [$amazonBuyerLink, $amazonSellerLink] = $getListingLinks(AmazonListingStatus::class, $fullSku);
            
            $breakdownData[] = [
                'marketplace' => 'Amazon',
                'sku' => $amazonData ? $fullSku : 'Not Listed',
                'price' => $amazonPrice,
                'views' => $amazonData ? intval($amazonData->sessions_l30 ?? 0) : null,
                'l30' => $amazonL30,
                'gpft' => $amazonGPFT,
                'ad' => $amazonAD,
                'npft' => $amazonNPFT,
                'is_listed' => $amazonData ? true : false,
                'sprice' => $amazonSuggested['sprice'],
                'sgpft' => $amazonSuggested['sgpft'],
                'sroi' => $amazonSuggested['sroi'],
                'spft' => $amazonSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => 0.80,
                'pushed_by' => $amazonPushedBy,
                'pushed_at' => $amazonPushedAt,
                'buyer_link' => $amazonBuyerLink,
                'seller_link' => $amazonSellerLink,
            ];

            // Get parent SKU for eBay 3 campaigns
            $parentSku = $productMaster->parent ?? $fullSku;
            
            // Fetch eBay campaigns
            $ebay12Campaigns = EbayPriorityReport::where('report_range', 'L30')
                ->whereIn('channels', ['ebay1', 'ebay2'])
                ->where('campaign_name', 'LIKE', '%' . $fullSku . '%')
                ->get();
            $ebay3Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
                ->where(function($q) use ($parentSku) {
                    $q->where('campaign_name', 'LIKE', '%' . $parentSku . '%')
                      ->orWhere('campaign_name', 'LIKE', '%PARENT ' . $parentSku . '%');
                })->get();
            
            // eBay 1
            $ebayData = EbayMetric::where('sku', $fullSku)->first();
            $ebay1Marketplace = MarketplacePercentage::where('marketplace', 'Ebay1')->first()
                ?? MarketplacePercentage::where('marketplace', 'Ebay')->first();
            $ebay1Margin = $ebay1Marketplace ? ($ebay1Marketplace->percentage / 100) : 0.85;
            $ebay1Price = $ebayData->ebay_price ?? 0;
            $ebay1L30 = $ebayData->ebay_l30 ?? 0;
            $ebay1GPFT = $ebay1Price > 0 ? (($ebay1Price * $ebay1Margin - $ship - $lp) / $ebay1Price) * 100 : 0;
            $ebay1AD = 0;
            $ebay1NPFT = $ebay1L30 == 0 ? $ebay1GPFT : ($ebay1GPFT - $ebay1AD);
            
            $ebayDataView = EbayDataView::where('sku', $fullSku)->first();
            $ebay1Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $ebay1PushedBy = null;
            $ebay1PushedAt = null;
            if ($ebayDataView) {
                $val = is_array($ebayDataView->value) ? $ebayDataView->value : json_decode($ebayDataView->value, true);
                if (is_array($val)) {
                    $ebay1Suggested = ['sprice' => $val['SPRICE'] ?? 0, 'sgpft' => $val['SGPFT'] ?? 0,
                                       'sroi' => $val['SROI'] ?? 0, 'spft' => $val['SPFT'] ?? 0];
                    $ebay1PushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $ebay1PushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    if ($ebay1PushedAt) {
                        try {
                            $ebay1PushedAt = Carbon::parse($ebay1PushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $ebay1PushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Ebay1',
                'sku' => $ebayData ? $fullSku : 'Not Listed',
                'price' => $ebay1Price,
                'views' => $ebayData ? intval($ebayData->views ?? 0) : null,
                'l30' => $ebay1L30,
                'gpft' => $ebay1GPFT,
                'ad' => $ebay1AD,
                'tacos_ch' => $getChannelTACOS('Ebay1'),
                'npft' => $ebay1NPFT,
                'is_listed' => $ebayData ? true : false,
                'sprice' => $ebay1Suggested['sprice'],
                'sgpft' => $ebay1Suggested['sgpft'],
                'sroi' => $ebay1Suggested['sroi'],
                'spft' => $ebay1Suggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $ebay1Margin,
                'pushed_by' => $ebay1PushedBy,
                'pushed_at' => $ebay1PushedAt,
                'buyer_link' => ($ebay1Links = $getListingLinks(EbayListingStatus::class, $fullSku))[0],
                'seller_link' => $ebay1Links[1],
            ];

            // Fetch eBay 2 data from Ebay2Metric model (using full SKU)
            // Check exact match first
            $ebay2Data = Ebay2Metric::where('sku', $fullSku)->first();
            
            // If not found, check for variations (OPEN BOX, USED, etc.)
            if (!$ebay2Data) {
                $ebay2Data = Ebay2Metric::where('sku', 'LIKE', '%' . $fullSku . '%')
                    ->orWhere('sku', 'LIKE', 'OPEN BOX ' . $fullSku . '%')
                    ->orWhere('sku', 'LIKE', 'USED ' . $fullSku . '%')
                    ->first();
            }
            
            // eBay 2
            $ebay2Marketplace = MarketplacePercentage::where('marketplace', 'Ebay2')->first()
                ?? MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
            $ebay2Margin = $ebay2Marketplace ? ($ebay2Marketplace->percentage / 100) : 0.85;
            $ebay2Price = $ebay2Data->ebay_price ?? 0;
            $ebay2L30 = $ebay2Data->ebay_l30 ?? 0;
            // Same normal ship as eBay 1
            $ebay2GPFT = $ebay2Price > 0 ? (($ebay2Price * $ebay2Margin - $lp - $ship) / $ebay2Price) * 100 : 0;
            $ebay2AD = 0;
            $ebay2NPFT = $ebay2L30 == 0 ? $ebay2GPFT : ($ebay2GPFT - $ebay2AD);
            
            $ebay2DataView = $ebay2Data ? EbayTwoDataView::where('sku', $ebay2Data->sku)->first() : null;
            $ebay2Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($ebay2DataView) {
                $val = is_array($ebay2DataView->value) ? $ebay2DataView->value : json_decode($ebay2DataView->value, true);
                if (is_array($val)) {
                    $ebay2Suggested = ['sprice' => $val['SPRICE'] ?? 0, 'sgpft' => $val['SGPFT'] ?? 0,
                                       'sroi' => $val['SROI'] ?? 0, 'spft' => $val['SPFT'] ?? 0];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Ebay2',
                'sku' => $ebay2Data ? $ebay2Data->sku : 'Not Listed',
                'price' => $ebay2Price,
                'views' => $ebay2Data ? intval($ebay2Data->views ?? 0) : null,
                'l30' => $ebay2L30,
                'gpft' => $ebay2GPFT,
                'ad' => $ebay2AD,
                'npft' => $ebay2NPFT,
                'is_listed' => $ebay2Data ? true : false,
                'sprice' => $ebay2Suggested['sprice'],
                'sgpft' => $ebay2Suggested['sgpft'],
                'sroi' => $ebay2Suggested['sroi'],
                'spft' => $ebay2Suggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $ebay2Margin,
                'act_wt' => $actWt,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($ebay2Links = $getListingLinks(EbayTwoListingStatus::class, $ebay2Data ? $ebay2Data->sku : null))[0],
                'seller_link' => $ebay2Links[1],
            ];

            // eBay 3
            $ebay3Data = Ebay3Metric::where('sku', $fullSku)->first();
            $ebay3Marketplace = MarketplacePercentage::where('marketplace', 'Ebay3')->first()
                ?? MarketplacePercentage::where('marketplace', 'EbayThree')->first();
            $ebay3Margin = $ebay3Marketplace ? ($ebay3Marketplace->percentage / 100) : 0.85;
            $ebay3Price = $ebay3Data->ebay_price ?? 0;
            $ebay3L30 = $ebay3Data->ebay_l30 ?? 0;
            $ebay3GPFT = $ebay3Price > 0 ? (($ebay3Price * $ebay3Margin - $ship - $lp) / $ebay3Price) * 100 : 0;
            
            // eBay 3 AD% using parent SKU campaigns
            $ebay3AD = 0;
            $ebay3Campaign = $ebay3Campaigns->first();
            if ($ebay3Campaign) {
                $spend = (float) str_replace(['USD ', ','], '', $ebay3Campaign->cpc_ad_fees_payout_currency ?? '0');
                $revenue = $ebay3Price * $ebay3L30;
                if ($spend > 0 && $revenue == 0) $ebay3AD = 100;
                else $ebay3AD = $revenue > 0 ? ($spend / $revenue) * 100 : 0;
            }
            $ebay3NPFT = $ebay3L30 == 0 ? $ebay3GPFT : ($ebay3GPFT - $ebay3AD);
            
            $ebay3DataView = $ebay3Data ? EbayThreeDataView::where('sku', $fullSku)->first() : null;
            $ebay3Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($ebay3DataView) {
                $val = is_array($ebay3DataView->value) ? $ebay3DataView->value : json_decode($ebay3DataView->value, true);
                if (is_array($val)) {
                    $ebay3Suggested = ['sprice' => $val['SPRICE'] ?? 0, 'sgpft' => $val['SGPFT'] ?? 0,
                                       'sroi' => $val['SROI'] ?? 0, 'spft' => $val['SPFT'] ?? 0];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Ebay3',
                'sku' => $ebay3Data ? $fullSku : 'Not Listed',
                'price' => $ebay3Price,
                'views' => $ebay3Data ? intval($ebay3Data->views ?? 0) : null,
                'l30' => $ebay3L30,
                'gpft' => $ebay3GPFT,
                'ad' => $ebay3AD,
                'npft' => $ebay3NPFT,
                'is_listed' => $ebay3Data ? true : false,
                'sprice' => $ebay3Suggested['sprice'],
                'sgpft' => $ebay3Suggested['sgpft'],
                'sroi' => $ebay3Suggested['sroi'],
                'spft' => $ebay3Suggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $ebay3Margin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($ebay3Links = $getListingLinks(EbayThreeListingStatus::class, $fullSku))[0],
                'seller_link' => $ebay3Links[1],
            ];

            // Fetch Temu data (with SKU normalization matching TemuController)
            // Use full SKU for Temu
            // Normalize full SKU for Temu
            $normalizedSku = strtoupper(trim($fullSku));
            $normalizedSku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $normalizedSku);
            $normalizedSku = preg_replace('/\s+/', ' ', $normalizedSku);
            
            Log::info('Temu lookup - Full SKU: ' . $fullSku . ', Normalized: ' . $normalizedSku);
            
            // Get Temu pricing - check full SKU and normalized
            $temuPricing = TemuPricing::where(function($query) use ($fullSku, $normalizedSku) {
                $query->where('sku', $fullSku)
                      ->orWhere('sku', $normalizedSku);
            })->first();
            
            if ($temuPricing) {
                Log::info('Temu pricing found - SKU in DB: ' . $temuPricing->sku . ', base_price: ' . $temuPricing->base_price);
            } else {
                Log::warning('No Temu pricing found for SKU: ' . $fullSku . ' or normalized: ' . $normalizedSku);
            }
            
            // Get Temu sales - check full SKU and normalized
            $temuSales = TemuDailyData::where(function($query) use ($fullSku, $normalizedSku) {
                $query->where('contribution_sku', $fullSku)
                      ->orWhere('contribution_sku', $normalizedSku);
            })->selectRaw('SUM(quantity_purchased) as temu_l30')->first();
            
            $temuL30Value = $temuSales ? ($temuSales->temu_l30 ?? 0) : 0;
            
            Log::info('Temu sales L30: ' . $temuL30Value);
            
            // Calculate Temu Price (matching TemuController)
            $basePrice = $temuPricing ? ($temuPricing->base_price ?? 0) : 0;
            if ($basePrice > 0) {
                $temuPrice = $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice;
            } else {
                $temuPrice = 0;
            }
            
            $hasTemuData = $temuPricing && $basePrice > 0;
            
            // Fetch Temu data with EXACT logic from TemuController
            // Get goods_id from temu_pricing for view and ad data
            $goodsId = $temuPricing ? ($temuPricing->goods_id ?? null) : null;
            
            // Get Temu percentage from marketplace_percentages table
            $temuMarketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            if (!$temuMarketplaceData) {
                // Try case-insensitive
                $temuMarketplaceData = MarketplacePercentage::whereRaw('LOWER(marketplace) = ?', ['temu'])->first();
            }
            $temuPercentage = $temuMarketplaceData && $temuMarketplaceData->percentage ? ($temuMarketplaceData->percentage / 100) : 0.96;
            
            Log::info('Temu Marketplace % - Found: ' . ($temuMarketplaceData ? 'Yes' : 'No') . ', percentage: ' . ($temuMarketplaceData->percentage ?? 'NULL') . ', Final: ' . $temuPercentage);
            
            // Temu views = product_clicks (same as /temu-decrease Views column); null → N/A
            $temuViews = $this->resolveTemuProductClicks($goodsId);
            if (
                ($temuViews === null || $temuViews === 0)
                && $viewsPullData
                && $viewsPullData->temu !== null
                && $viewsPullData->temu !== ''
            ) {
                $temuViews = intval($viewsPullData->temu);
            }
            
            // Get ad spend by goods_id (matching line 1606-1612)
            $temuAdSpend = 0;
            if ($goodsId) {
                $temuAdData = TemuAdData::where('goods_id', $goodsId)->first();
                $temuAdSpend = $temuAdData ? ($temuAdData->spend ?? 0) : 0;
            }
            
            // Calculate Temu GPFT% (CORRECT formula from line 1630)
            $temuGPFT = $temuPrice > 0 ? (($temuPrice * $temuPercentage - $lp - $temuShip) / $temuPrice) * 100 : 0;
            
            Log::info('Temu GPFT DEBUG - Price: ' . $temuPrice . ', Percentage: ' . $temuPercentage . ', LP: ' . $lp . ', temuShip: ' . $temuShip);
            Log::info('Temu GPFT CALC - Revenue: ' . ($temuPrice * $temuPercentage) . ' - Costs: ' . ($lp + $temuShip) . ' = Profit: ' . ($temuPrice * $temuPercentage - $lp - $temuShip));
            Log::info('Temu GPFT Result: ' . $temuGPFT . '%');
            
            // Calculate ADS% (matching line 1636-1643)
            $temuRevenue = $temuPrice * $temuL30Value;
            if ($temuAdSpend > 0 && $temuL30Value == 0) {
                $temuADS = 100;
            } else {
                $temuADS = $temuRevenue > 0 ? ($temuAdSpend / $temuRevenue) * 100 : 0;
            }
            
            Log::info('Temu ADS calculation - Spend: ' . $temuAdSpend . ', Revenue: ' . $temuRevenue . ', ADS%: ' . $temuADS);
            
            // Calculate NPFT% (matching line 1645-1651)
            if ($temuADS == 100) {
                $temuNPFT = $temuGPFT;
            } else {
                $temuNPFT = $temuGPFT - $temuADS;
            }
            
            Log::info('Temu NPFT%: ' . $temuNPFT);
            
            // NOTE: Temu is added later with enhanced suggested data (line ~1518)

            // Fetch Doba data from doba_metrics table (using full SKU)
            $dobaMetric = DobaMetric::where('sku', $fullSku)->first();
            
            // Get Doba percentage from MarketplacePercentage
            $dobaMarketplace = MarketplacePercentage::where('marketplace', 'Doba')->first();
            $dobaPercentage = $dobaMarketplace ? ($dobaMarketplace->percentage / 100) : 1.00;
            
            $dobaPrice = $dobaMetric ? floatval($dobaMetric->anticipated_income ?? 0) : 0;
            
            // Calculate Doba GPFT% = ((price × percentage - ship - lp) / price) × 100
            $dobaGPFT = $dobaPrice > 0 ? ((($dobaPrice * $dobaPercentage - $lp - $ship) / $dobaPrice) * 100) : 0;
            
            // Doba doesn't have ads, so NPFT = GPFT
            $dobaNPFT = $dobaGPFT;
            
            Log::info("Breakdown - Doba for SKU: $fullSku", [
                'price' => $dobaPrice,
                'percentage' => $dobaPercentage,
                'lp' => $lp,
                'ship' => $ship,
                'gpft' => round($dobaGPFT, 2),
                'npft' => round($dobaNPFT, 2)
            ]);
            
            $hasDobaData = $dobaMetric && ($dobaMetric->quantity_l30 > 0 || $dobaPrice > 0);
            
            // Get Doba suggested data from doba_data_view
            $dobaDataView = DobaDataView::where('sku', $fullSku)->first();
            $dobaSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $dobaPushedBy = null;
            $dobaPushedAt = null;
            if ($dobaDataView) {
                $val = is_array($dobaDataView->value) ? $dobaDataView->value : json_decode($dobaDataView->value, true);
                if (is_array($val)) {
                    $dobaSuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                      'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                    // Get pushed by information
                    $dobaPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $dobaPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    // Format pushed at timestamp
                    if ($dobaPushedAt) {
                        try {
                            $dobaPushedAt = Carbon::parse($dobaPushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $dobaPushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Doba',
                'sku' => $hasDobaData ? $fullSku : 'Not Listed',
                'price' => $dobaPrice,
                'views' => $dobaMetric ? intval($dobaMetric->impressions ?? 0) : null,
                'l30' => $dobaMetric->quantity_l30 ?? 0,
                'gpft' => $dobaGPFT,
                'ad' => 0,
                'npft' => $dobaNPFT,
                'is_listed' => $hasDobaData,
                'sprice' => $dobaSuggested['sprice'],
                'sgpft' => $dobaSuggested['sgpft'],
                'sroi' => $dobaSuggested['sroi'],
                'spft' => $dobaSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $dobaPercentage,
                'pushed_by' => $dobaPushedBy,
                'pushed_at' => $dobaPushedAt,
                'buyer_link' => ($dobaLinks = $getListingLinks(DobaListingStatus::class, $fullSku))[0],
                'seller_link' => $dobaLinks[1],
            ];

            // Fetch TikTok data (matching TikTokPricingController)
            $tiktokData = TikTokProduct::where('sku', strtoupper($fullSku))->first();
            
            // Get TikTok percentage from MarketplacePercentage
            $tiktokMarketplace = MarketplacePercentage::where('marketplace', 'TikTok')->first();
            $tiktokPercentage = $tiktokMarketplace ? ($tiktokMarketplace->percentage / 100) : 0.80;
            
            // Get TikTok L30 from ShipHub orders (last 30 days)
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', 'tiktok')
                ->max('order_date');
            
            $tiktokL30 = 0;
            if ($latestDate) {
                $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
                $startDate = $latestDateCarbon->copy()->subDays(29); // 30 days total
                
                $tiktokL30 = DB::connection('shiphub')
                    ->table('orders as o')
                    ->join('order_items as i', 'o.id', '=', 'i.order_id')
                    ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
                    ->where('o.marketplace', 'tiktok')
                    ->where('i.sku', strtoupper($fullSku))
                    ->where(function($query) {
                        $query->where('o.order_status', '!=', 'Canceled')
                              ->where('o.order_status', '!=', 'Cancelled')
                              ->where('o.order_status', '!=', 'canceled')
                              ->where('o.order_status', '!=', 'cancelled')
                              ->orWhereNull('o.order_status');
                    })
                    ->sum('i.quantity_ordered');
            }
            
            $ttPrice = $tiktokData ? floatval($tiktokData->price ?? 0) : 0;
            
            // Calculate TikTok GPFT% = ((price × percentage - lp - ship) / price) × 100
            $ttGPFT = $ttPrice > 0 ? ((($ttPrice * $tiktokPercentage - $lp - $ship) / $ttPrice) * 100) : 0;
            
            // TikTok doesn't have ads, so NPFT = GPFT
            $ttNPFT = $tiktokL30 == 0 ? $ttGPFT : $ttGPFT;
            
            // Get TikTok suggested data from tiktok_data_view
            $tiktokDataView = TikTokDataView::where('sku', $fullSku)->first();
            $tiktokSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($tiktokDataView) {
                $val = is_array($tiktokDataView->value) ? $tiktokDataView->value : 
                       json_decode($tiktokDataView->value, true);
                if (is_array($val)) {
                    $tiktokSuggested = [
                        'sprice' => floatval($val['SPRICE'] ?? 0),
                        'sgpft' => floatval($val['SGPFT'] ?? 0),
                        'sroi' => floatval($val['SROI'] ?? 0),
                        'spft' => floatval($val['SPFT'] ?? 0)
                    ];
                }
            }
            
            $hasTikTokData = $tiktokData && ($ttPrice > 0 || $tiktokL30 > 0);
            
            $breakdownData[] = [
                'marketplace' => 'TikTok',
                'sku' => $hasTikTokData ? $fullSku : 'Not Listed',
                'price' => $ttPrice,
                'views' => $tiktokData ? intval($tiktokData->views ?? 0) : null,
                'l30' => $tiktokL30 ?? 0,
                'gpft' => $ttGPFT,
                'ad' => 0,
                'npft' => $ttNPFT,
                'is_listed' => $hasTikTokData,
                'sprice' => $tiktokSuggested['sprice'],
                'sgpft' => $tiktokSuggested['sgpft'],
                'sroi' => $tiktokSuggested['sroi'],
                'spft' => $tiktokSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $tiktokPercentage,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($tiktokLinks = $getListingLinks(TiktokShopListingStatus::class, $fullSku))[0],
                'seller_link' => $tiktokLinks[1],
            ];

            // Fetch BestBuy data (matching BestBuyPricingController)
            $bestbuyProduct = BestbuyUsaProduct::where('sku', $fullSku)->first();
            $bestbuyPrice = BestbuyPriceData::where('sku', $fullSku)->first();
            
            // Get BestBuy percentage
            $bestbuyMarketplace = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
            $bbPercentage = $bestbuyMarketplace ? ($bestbuyMarketplace->percentage / 100) : 0.80;
            
            // Price: BestbuyPriceData takes priority, fallback to BestbuyUsaProduct
            $bbPrice = $bestbuyPrice ? floatval($bestbuyPrice->price ?? 0) : floatval($bestbuyProduct->price ?? 0);
            
            // Calculate BestBuy GPFT% = ((price × percentage - ship - lp) / price) × 100
            $bbGPFT = $bbPrice > 0 ? ((($bbPrice * $bbPercentage - $lp - $ship) / $bbPrice) * 100) : 0;
            
            // BestBuy doesn't have ads, so NPFT = GPFT
            $bbNPFT = $bbGPFT;
            
            // NOTE: BestBuy is added later with enhanced suggested data (line ~1594)

            // Fetch Shopify B2C data
            // Price/views from shopify_skus, L30 from shopify_b2c_daily_data (count rows)
            $shopifySku = ShopifySku::where('sku', $fullSku)->first();
            if (!$shopifySku) {
                $byNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku([$fullSku]);
                $normKey = ShopifySku::normalizeSkuForShopifyLookup($fullSku);
                $shopifySku = ($normKey !== '' && isset($byNorm[$normKey])) ? $byNorm[$normKey] : null;
            }
            $sb2cPrice = $shopifySku ? floatval($shopifySku->price ?? 0) : 0;
            // Shopify L30 product page sessions; null when no shopify_skus row → N/A
            $sb2cViews = $shopifySku ? intval($shopifySku->views ?? 0) : null;
            
            // L30: Count rows in shopify_b2c_daily_data (not sum quantity)
            $sb2cL30 = ShopifyB2CDailyData::where('sku', $fullSku)->count();
            
            // Get Shopify B2C percentage
            $sb2cMarketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            $sb2cPercentage = $sb2cMarketplace ? ($sb2cMarketplace->percentage / 100) : 0.95;
            
            // Calculate Shopify B2C GPFT%
            $sb2cGPFT = $sb2cPrice > 0 ? ((($sb2cPrice * $sb2cPercentage - $lp - $ship) / $sb2cPrice) * 100) : 0;
            $sb2cNPFT = $sb2cL30 == 0 ? $sb2cGPFT : $sb2cGPFT;
            
            // Get suggested data
            $sb2cDataView = Shopifyb2cDataView::where('sku', $fullSku)->first();
            $sb2cSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $sb2cPushedBy = null;
            $sb2cPushedAt = null;
            if ($sb2cDataView) {
                $val = is_array($sb2cDataView->value) ? $sb2cDataView->value : json_decode($sb2cDataView->value, true);
                if (is_array($val)) {
                    $sb2cSuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                      'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                    // Get pushed by information
                    $sb2cPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $sb2cPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    // Format pushed at timestamp
                    if ($sb2cPushedAt) {
                        try {
                            $sb2cPushedAt = Carbon::parse($sb2cPushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $sb2cPushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Shopify',
                'sku' => $fullSku, // Always show SKU (from ProductMaster)
                'price' => $sb2cPrice,
                'views' => $sb2cViews,
                'l30' => $sb2cL30,
                'gpft' => $sb2cGPFT,
                'ad' => 0,
                'tacos_ch' => $getChannelTACOS('Shopify'),
                'npft' => $sb2cNPFT,
                'is_listed' => true, // Always true - never "Not Listed"
                'sprice' => $sb2cSuggested['sprice'],
                'sgpft' => $sb2cSuggested['sgpft'],
                'sroi' => $sb2cSuggested['sroi'],
                'spft' => $sb2cSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $sb2cPercentage,
                'pushed_by' => $sb2cPushedBy,
                'pushed_at' => $sb2cPushedAt,
                'buyer_link' => ($sb2cLinks = $getListingLinks(ShopifyB2CListingStatus::class, $fullSku))[0],
                'seller_link' => $sb2cLinks[1],
            ];

            // Add Shopify B2B - Same logic as B2C
            $sb2bPrice = $shopifySku ? floatval($shopifySku->b2b_price ?? 0) : 0;
            $sb2bL30 = DB::table('shopify_b2b_daily_data')->where('sku', $fullSku)->count();
            
            $sb2bMarketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2B')->first();
            $sb2bMargin = $sb2bMarketplace ? ($sb2bMarketplace->percentage / 100) : 0.95;
            
            $sb2bGPFT = $sb2bPrice > 0 ? (($sb2bPrice * $sb2bMargin - $lp - $ship) / $sb2bPrice) * 100 : 0;
            $sb2bNPFT = $sb2bL30 == 0 ? $sb2bGPFT : $sb2bGPFT;
            
            $sb2bDataView = ShopifyB2BDataView::where('sku', $fullSku)->first();
            $sb2bSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $sb2bPushedBy = null;
            $sb2bPushedAt = null;
            if ($sb2bDataView) {
                $val = is_array($sb2bDataView->value) ? $sb2bDataView->value : json_decode($sb2bDataView->value, true);
                if (is_array($val)) {
                    $sb2bSuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                      'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                    // Get pushed by information
                    $sb2bPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $sb2bPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    // Format pushed at timestamp
                    if ($sb2bPushedAt) {
                        try {
                            $sb2bPushedAt = Carbon::parse($sb2bPushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $sb2bPushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'SB2B',
                'sku' => $fullSku,
                'price' => $sb2bPrice,
                'views' => null, // B2B has no separate page-view metric
                'l30' => $sb2bL30,
                'gpft' => $sb2bGPFT,
                'ad' => 0,
                'tacos_ch' => $getChannelTACOS('ShopifyB2B'),
                'npft' => $sb2bNPFT,
                'is_listed' => true,
                'sprice' => $sb2bSuggested['sprice'],
                'sgpft' => $sb2bSuggested['sgpft'],
                'sroi' => $sb2bSuggested['sroi'],
                'spft' => $sb2bSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $sb2bMargin,
                'pushed_by' => $sb2bPushedBy,
                'pushed_at' => $sb2bPushedAt,
                'buyer_link' => null,
                'seller_link' => null,
            ];

            // Fetch Macy data from macy_products table (using full SKU)
            $macyProduct = MacyProduct::where('sku', $fullSku)->first();
            
            // Get Macy's percentage
            $macyMarketplace = MarketplacePercentage::where('marketplace', 'Macys')->first();
            $macyPercentage = $macyMarketplace ? ($macyMarketplace->percentage / 100) : 0.80;
            
            $macyPrice = $macyProduct ? floatval($macyProduct->price ?? 0) : 0;
            
            // Calculate Macy's GPFT% = ((price × percentage - ship - lp) / price) × 100
            $macyGPFT = $macyPrice > 0 ? ((($macyPrice * $macyPercentage - $lp - $ship) / $macyPrice) * 100) : 0;
            
            // Macy's doesn't have ads, NPFT = GPFT
            $macyL30 = $macyProduct->m_l30 ?? 0;
            $macyNPFT = $macyL30 == 0 ? $macyGPFT : $macyGPFT;
            
            // Get Macy suggested data from macy_data_view
            $macyDataView = MacyDataView::where('sku', $fullSku)->first();
            $macySuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($macyDataView) {
                $val = is_array($macyDataView->value) ? $macyDataView->value : json_decode($macyDataView->value, true);
                if (is_array($val)) {
                    $macySuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                      'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                }
            }
            
            $hasMacyData = $macyProduct && ($macyL30 > 0 || $macyPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'MACY',
                'sku' => $hasMacyData ? $fullSku : 'Not Listed',
                'price' => $macyPrice,
                'views' => $macyProduct ? intval($macyProduct->views ?? 0) : null,
                'l30' => $macyL30,
                'gpft' => $macyGPFT,
                'ad' => 0,
                'npft' => $macyNPFT,
                'is_listed' => $hasMacyData,
                'sprice' => $macySuggested['sprice'],
                'sgpft' => $macySuggested['sgpft'],
                'sroi' => $macySuggested['sroi'],
                'spft' => $macySuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $macyPercentage,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($macyLinks = $getListingLinks(MacysListingStatus::class, $fullSku))[0],
                'seller_link' => $macyLinks[1],
            ];

            // Fetch Reverb data from reverb_products table (using full SKU)
            $reverbProduct = ReverbProduct::where('sku', $fullSku)->first();
            
            // Get Reverb percentage
            $reverbMarketplace = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $reverbPercentage = $reverbMarketplace ? ($reverbMarketplace->percentage / 100) : 0.85;
            
            $rvPrice = $reverbProduct ? floatval($reverbProduct->price ?? 0) : 0;
            
            // Calculate Reverb GPFT% = ((price × percentage - ship - lp) / price) × 100
            $rvGPFT = $rvPrice > 0 ? ((($rvPrice * $reverbPercentage - $lp - $ship) / $rvPrice) * 100) : 0;
            
            // Reverb doesn't have ads, NPFT = GPFT
            $reverbL30 = $reverbProduct->r_l30 ?? 0;
            $rvNPFT = $reverbL30 == 0 ? $rvGPFT : $rvGPFT;
            
            // Get Reverb suggested data from reverb_view_data
            $reverbDataView = ReverbViewData::where('sku', $fullSku)->first();
            $reverbSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($reverbDataView) {
                $val = is_array($reverbDataView->values) ? $reverbDataView->values : 
                       json_decode($reverbDataView->values, true);
                if (is_array($val)) {
                    // Reverb stores SPFT/SROI with % symbols, need to strip them
                    $reverbSuggested = [
                        'sprice' => floatval($val['SPRICE'] ?? 0),
                        'sgpft' => floatval($val['SGPFT'] ?? 0),
                        'sroi' => floatval(str_replace('%', '', $val['SROI'] ?? '0')),
                        'spft' => floatval(str_replace('%', '', $val['SPFT'] ?? '0'))
                    ];
                }
            }
            
            $hasReverbData = $reverbProduct && ($reverbL30 > 0 || $rvPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'Reverb',
                'sku' => $hasReverbData ? $fullSku : 'Not Listed',
                'price' => $rvPrice,
                'views' => $reverbProduct ? intval($reverbProduct->views ?? 0) : null,
                'l30' => $reverbL30,
                'gpft' => $rvGPFT,
                'ad' => 0,
                'npft' => $rvNPFT,
                'is_listed' => $hasReverbData,
                'sprice' => $reverbSuggested['sprice'],
                'sgpft' => $reverbSuggested['sgpft'],
                'sroi' => $reverbSuggested['sroi'],
                'spft' => $reverbSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $reverbPercentage,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($reverbLinks = $getListingLinks(ReverbListingStatus::class, $fullSku))[0],
                'seller_link' => $reverbLinks[1],
            ];

            // Add Temu (Temu marketplace uses 96%) — pricing/views match /temu-decrease
            $temuMarketplace = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $temuMargin = $temuMarketplace ? ($temuMarketplace->percentage / 100) : 0.96;
            $temuPriceMapOne = $this->buildTemuPricingMapForProductSkus(
                TemuPricing::query()->get(['sku', 'base_price', 'goods_id', 'quantity']),
                [$fullSku]
            );
            $temuPricing = $temuPriceMapOne[$fullSku] ?? null;
            if (!$temuPricing) {
                // Keep earlier normalized lookup if map missed
                $temuPricing = TemuPricing::where(function ($query) use ($fullSku, $normalizedSku) {
                    $query->where('sku', $fullSku)->orWhere('sku', $normalizedSku);
                })->first();
            }
            $temuPrice = 0;
            if ($temuPricing) {
                $basePrice = $temuPricing->base_price ?? 0;
                $temuPrice = $basePrice > 0 ? ($basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice) : 0;
            }
            // Re-resolve views from this pricing row's goods_id (authoritative for the Temu row)
            $temuViews = $temuPricing
                ? $this->resolveTemuProductClicks($temuPricing->goods_id ?? null)
                : null;
            if (
                ($temuViews === null || $temuViews === 0)
                && $viewsPullData
                && $viewsPullData->temu !== null
                && $viewsPullData->temu !== ''
            ) {
                $temuViews = intval($viewsPullData->temu);
            }
            $temuL30 = TemuDailyData::where(function ($query) use ($fullSku, $normalizedSku) {
                $query->where('contribution_sku', $fullSku)
                      ->orWhere('contribution_sku', $normalizedSku);
            })->selectRaw('SUM(quantity_purchased) as l30')->value('l30') ?? 0;
            $temuGPFT = $temuPrice > 0 ? (($temuPrice * $temuMargin - $lp - $temuShip) / $temuPrice) * 100 : 0;
            $temuNPFT = $temuL30 == 0 ? $temuGPFT : $temuGPFT;
            
            $temuDataView = TemuDataView::where(function ($query) use ($fullSku, $normalizedSku) {
                $query->where('sku', $fullSku)->orWhere('sku', $normalizedSku);
            })->first();
            $temuSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($temuDataView) {
                $val = is_array($temuDataView->value) ? $temuDataView->value : json_decode($temuDataView->value, true);
                if (is_array($val)) {
                    $temuSuggested = ['sprice' => $val['SPRICE'] ?? 0, 'sgpft' => $val['SGPFT'] ?? 0,
                                      'sroi' => $val['SROI'] ?? 0, 'spft' => $val['SPFT'] ?? 0];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Temu',
                'sku' => $temuPricing ? $fullSku : 'Not Listed',
                'price' => $temuPrice,
                'views' => $temuViews,
                'l30' => $temuL30,
                'gpft' => $temuGPFT,
                'ad' => 0,
                'npft' => $temuNPFT,
                'is_listed' => $temuPricing ? true : false,
                'sprice' => $temuSuggested['sprice'],
                'sgpft' => $temuSuggested['sgpft'],
                'sroi' => $temuSuggested['sroi'],
                'spft' => $temuSuggested['spft'],
                'lp' => $lp,
                'ship' => $temuShip,
                'margin' => $temuMargin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($temuLinks = $getListingLinks(TemuListingStatus::class, $fullSku))[0],
                'seller_link' => $temuLinks[1],
            ];

            $temu2PricingRow = null;
            $temu2PriceBr = 0;
            $temu2L30Br = 0;
            $temu2GPFTBr = 0;
            $temu2NPFTBr = 0;
            $temu2ViewsBr = null; // null → N/A when no temu2_view_data
            $temu2DataView = null;
            $temu2Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $temu2Buyer = null;
            $temu2Seller = null;
            if (Schema::hasTable('temu2_pricing') && Schema::hasTable('temu2_daily_data')) {
                try {
                    $l30MapOne = $this->buildTemu2L30ByProductSkusMap([$fullSku], true);
                    $temu2L30Br = (int) ($l30MapOne[$fullSku] ?? 0);
                    $temu2AllPricing = Temu2Pricing::query()->get(['sku', 'base_price', 'goods_id']);
                    $t2PriceMap = $this->buildTemu2PricingMapForProductSkus($temu2AllPricing, [$fullSku]);
                    $temu2PricingRow = $t2PriceMap[$fullSku] ?? null;
                    if ($temu2PricingRow) {
                        $temu2BaseBr = $temu2PricingRow->base_price ?? 0;
                        $temu2PriceBr = $temu2BaseBr > 0 ? ($temu2BaseBr <= 26.99 ? $temu2BaseBr + 2.99 : $temu2BaseBr) : 0;
                    }
                    $temu2GPFTBr = $temu2PriceBr > 0 ? (($temu2PriceBr * $temuMargin - $lp - $temuShip) / $temu2PriceBr) * 100 : 0;
                    $temu2NPFTBr = $temu2L30Br == 0 ? $temu2GPFTBr : $temu2GPFTBr;
                    if ($temu2PricingRow) {
                        $temu2ViewsBr = $this->resolveTemuProductClicks(
                            $temu2PricingRow->goods_id ?? null,
                            true // Temu 2 view table
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('CVR breakdown Temu2 (pricing / daily): ' . $e->getMessage());
                }
            }
            if (Schema::hasTable('temu2_data_view')) {
                try {
                    $temu2DataView = Temu2DataView::whereIn('sku', array_filter([$fullSku, $normalizedSku]))->first();
                    if ($temu2DataView) {
                        $v2d = is_array($temu2DataView->value) ? $temu2DataView->value : json_decode($temu2DataView->value, true);
                        if (is_array($v2d)) {
                            $suggSp = $v2d['SPRICE'] ?? $v2d['sprice'] ?? 0;
                            $temu2Suggested = [
                                'sprice' => floatval($suggSp),
                                'sgpft' => floatval($v2d['SGPFT'] ?? $v2d['sgprft_percent'] ?? 0),
                                'sroi' => floatval($v2d['SROI'] ?? $v2d['sroi_percent'] ?? 0),
                                'spft' => floatval($v2d['SPFT'] ?? 0),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('CVR breakdown Temu2 (temu2_data_view): ' . $e->getMessage());
                }
            }
            if ($temu2DataView && is_array($temu2DataView->value)) {
                $temu2Buyer = $temu2DataView->value['buyer_link'] ?? null;
                $temu2Seller = $temu2DataView->value['seller_link'] ?? null;
            }
            $suggSpT2 = (float) ($temu2Suggested['sprice'] ?? 0);
            $temu2HasListSignal = ($temu2PricingRow && (float) ($temu2PricingRow->base_price ?? 0) > 0)
                || $temu2L30Br > 0
                || $suggSpT2 > 0.01;
            $breakdownData[] = [
                'marketplace' => 'Temu2',
                'sku' => $temu2HasListSignal ? $fullSku : 'Not Listed',
                'price' => $temu2PriceBr,
                'views' => $temu2ViewsBr,
                'l30' => $temu2L30Br,
                'gpft' => $temu2GPFTBr,
                'ad' => 0,
                'npft' => $temu2NPFTBr,
                'is_listed' => $temu2HasListSignal,
                'sprice' => $temu2Suggested['sprice'],
                'sgpft' => $temu2Suggested['sgpft'],
                'sroi' => $temu2Suggested['sroi'],
                'spft' => $temu2Suggested['spft'],
                'lp' => $lp,
                'ship' => $temuShip,
                'margin' => $temuMargin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => $temu2Buyer,
                'seller_link' => $temu2Seller,
            ];

            // NOTE: Macy is added earlier as 'MACY' with enhanced suggested data (line ~1500)

            // Add BestBuy
            $bestbuyProduct = BestbuyUsaProduct::where('sku', $fullSku)->first();
            $bestbuyMarketplace = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
            $bestbuyMargin = $bestbuyMarketplace ? ($bestbuyMarketplace->percentage / 100) : 0.80;
            $bestbuyPrice = $bestbuyProduct->price ?? 0;
            $bestbuyL30 = 0; // BestBuy L30 data source needed
            $bestbuyGPFT = $bestbuyPrice > 0 ? (($bestbuyPrice * $bestbuyMargin - $lp - $ship) / $bestbuyPrice) * 100 : 0;
            $bestbuyNPFT = $bestbuyL30 == 0 ? $bestbuyGPFT : $bestbuyGPFT;
            
            $bestbuyDataView = BestbuyUSADataView::where('sku', $fullSku)->first();
            $bestbuySuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($bestbuyDataView) {
                $val = is_array($bestbuyDataView->value) ? $bestbuyDataView->value : json_decode($bestbuyDataView->value, true);
                if (is_array($val)) {
                    $bestbuySuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                         'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'BestBuy',
                'sku' => $bestbuyProduct ? $fullSku : 'Not Listed',
                'price' => $bestbuyPrice,
                'views' => null, // BestBuy has no views metric
                'l30' => $bestbuyL30,
                'gpft' => $bestbuyGPFT,
                'ad' => 0,
                'npft' => $bestbuyNPFT,
                'is_listed' => $bestbuyProduct ? true : false,
                'sprice' => $bestbuySuggested['sprice'],
                'sgpft' => $bestbuySuggested['sgpft'],
                'sroi' => $bestbuySuggested['sroi'],
                'spft' => $bestbuySuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $bestbuyMargin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($bestbuyLinks = $getListingLinks(BestbuyUSAListingStatus::class, $fullSku))[0],
                'seller_link' => $bestbuyLinks[1],
            ];

            // Add Tiendamia
            $tiendamiaProduct = TiendamiaProduct::where('sku', $fullSku)->first();
            $tiendamiaMarketplace = MarketplacePercentage::where('marketplace', 'Tiendamia')->first();
            $tiendamiaMargin = $tiendamiaMarketplace ? ($tiendamiaMarketplace->percentage / 100) : 0.83;
            $tiendamiaPrice = $tiendamiaProduct->price ?? 0;
            $tiendamiaL30 = $tiendamiaProduct->m_l30 ?? 0;
            $tiendamiaGPFT = $tiendamiaPrice > 0 ? (($tiendamiaPrice * $tiendamiaMargin - $lp - $ship) / $tiendamiaPrice) * 100 : 0;
            $tiendamiaNPFT = $tiendamiaL30 == 0 ? $tiendamiaGPFT : $tiendamiaGPFT;
            
            $tiendamiaDataView = TiendamiaDataView::where('sku', $fullSku)->first();
            $tiendamiaSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($tiendamiaDataView) {
                $val = is_array($tiendamiaDataView->value) ? $tiendamiaDataView->value : 
                       json_decode($tiendamiaDataView->value, true);
                if (is_array($val)) {
                    $tiendamiaSuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                           'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Tiendamia',
                'sku' => $tiendamiaProduct ? $fullSku : 'Not Listed',
                'price' => $tiendamiaPrice,
                'views' => null, // Tiendamia has no views metric
                'l30' => $tiendamiaL30,
                'gpft' => $tiendamiaGPFT,
                'ad' => 0,
                'npft' => $tiendamiaNPFT,
                'is_listed' => $tiendamiaProduct ? true : false,
                'sprice' => $tiendamiaSuggested['sprice'],
                'sgpft' => $tiendamiaSuggested['sgpft'],
                'sroi' => $tiendamiaSuggested['sroi'],
                'spft' => $tiendamiaSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $tiendamiaMargin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => ($tiendamiaLinks = $getListingLinks(TiendamiaListingStatus::class, $fullSku))[0],
                'seller_link' => $tiendamiaLinks[1],
            ];

            // Add Shein
            $sheinMarketplacePerc = MarketplacePercentage::where('marketplace', 'Shein')->first();
            $sheinMarginBd = $sheinMarketplacePerc ? ($sheinMarketplacePerc->percentage / 100) : 1.00;
            $sheinPricingRowBd = null;
            $sheinL30Bd = 0;
            try {
                $sheinPricingRowBd = \App\Models\SheinPricingPrice::where('sku', $fullSku)->first();
                $sheinL30Bd = (int) (\App\Models\SheinDailyData::where('seller_sku', $fullSku)
                    ->selectRaw('SUM(COALESCE(quantity, 0)) as l30')
                    ->value('l30') ?? 0);
            } catch (\Exception $e) {
                Log::warning('Shein breakdown data fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            // Use special_offer_price for calculations (same as Shein pricing page)
            $sheinPriceBd = 0;
            if ($sheinPricingRowBd) {
                $sheinPriceBd = floatval($sheinPricingRowBd->special_offer_price ?? 0);
                if ($sheinPriceBd <= 0) $sheinPriceBd = floatval($sheinPricingRowBd->price ?? 0);
            }
            $sheinShipBd = 0;
            if ($values) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'shein_ship') $sheinShipBd = floatval($v);
                }
            }
            $sheinGPFTBd = $sheinPriceBd > 0 ? (($sheinPriceBd * $sheinMarginBd - $lp - $sheinShipBd) / $sheinPriceBd) * 100 : 0;
            $sheinNPFTBd = $sheinGPFTBd; // No ads
            $sheinSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $sheinBuyerLink = null;
            $sheinSellerLink = null;
            try {
                $sheinDataViewBd = \App\Models\SheinDataView::where('sku', $fullSku)->first();
                if ($sheinDataViewBd) {
                    $val = is_array($sheinDataViewBd->value) ? $sheinDataViewBd->value : json_decode($sheinDataViewBd->value, true);
                    if (is_array($val)) {
                        $sheinSuggestedBd = [
                            'sprice' => floatval($val['SPRICE'] ?? 0),
                            'sgpft'  => floatval($val['SGPFT'] ?? 0),
                            'sroi'   => floatval($val['SROI'] ?? 0),
                            'spft'   => floatval($val['SPFT'] ?? 0),
                        ];
                    }
                }
                [$sheinBuyerLink, $sheinSellerLink] = $getListingLinks(\App\Models\SheinListingStatus::class, $fullSku);
            } catch (\Exception $e) {
                Log::warning('Shein DataView/ListingStatus fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $hasSheinData = $sheinPricingRowBd && ($sheinPriceBd > 0 || $sheinL30Bd > 0);
            $sheinViewsBd = null;
            try {
                $sheinSheetBd = \App\Models\SheinSheetData::where('sku', $fullSku)->first();
                if ($sheinSheetBd && ($sheinSheetBd->views_clicks !== null && $sheinSheetBd->views_clicks !== '')) {
                    $sheinViewsBd = intval($sheinSheetBd->views_clicks);
                } else {
                    $sheinMetricBd = \App\Models\SheinMetric::where('sku', $fullSku)->first();
                    if ($sheinMetricBd && ($sheinMetricBd->views !== null && $sheinMetricBd->views !== '')) {
                        $sheinViewsBd = intval($sheinMetricBd->views);
                    }
                }
            } catch (\Exception $e) {
                // leave N/A
            }

            $breakdownData[] = [
                'marketplace' => 'Shein',
                'sku'         => $hasSheinData ? $fullSku : 'Not Listed',
                'price'       => round($sheinPriceBd, 2),
                'views'       => $sheinViewsBd,
                'l30'         => $sheinL30Bd,
                'gpft'        => round($sheinGPFTBd, 2),
                'ad'          => 0,
                'tacos_ch'    => $getChannelTACOS('Shein'),
                'npft'        => round($sheinNPFTBd, 2),
                'is_listed'   => $hasSheinData ? true : false,
                'sprice'      => $sheinSuggestedBd['sprice'],
                'sgpft'       => $sheinSuggestedBd['sgpft'],
                'sroi'        => $sheinSuggestedBd['sroi'],
                'spft'        => $sheinSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => $sheinShipBd,
                'margin'      => $sheinMarginBd,
                'pushed_by'   => null,
                'pushed_at'   => null,
                'buyer_link'  => $sheinBuyerLink,
                'seller_link' => $sheinSellerLink,
            ];

            // Add AliExpress
            $aeMarketplacePerc = MarketplacePercentage::where('marketplace', 'Aliexpress')
                ->orWhere('marketplace', 'AliExpress')->first();
            $aeMarginBd = $aeMarketplacePerc ? ($aeMarketplacePerc->percentage / 100) : 1.00;
            $aePricingRowBd = null;
            $aeL30Bd = 0;
            try {
                $aePricingRowBd = \App\Models\AliexpressPricingPrice::where('sku', $fullSku)->first();
                $aeL30Bd = (int) (\App\Models\AliexpressDailyData::where('sku_code', $fullSku)
                    ->selectRaw('SUM(COALESCE(quantity, 0)) as l30')
                    ->value('l30') ?? 0);
            } catch (\Exception $e) {
                Log::warning('AliExpress breakdown data fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $aePriceBd = $aePricingRowBd ? floatval($aePricingRowBd->price ?? 0) : 0;
            $aeGPFTBd  = $aePriceBd > 0 ? (($aePriceBd * $aeMarginBd - $lp - $ship) / $aePriceBd) * 100 : 0;
            $aeNPFTBd  = $aeGPFTBd; // No ads for AliExpress
            $aeSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $aeBuyerLink = null;
            $aeSellerLink = null;
            try {
                $aeDataViewBd = \App\Models\AliexpressDataView::where('sku', $fullSku)->first();
                if ($aeDataViewBd) {
                    $val = is_array($aeDataViewBd->value) ? $aeDataViewBd->value : json_decode($aeDataViewBd->value, true);
                    if (is_array($val)) {
                        $aeSuggestedBd = [
                            'sprice' => floatval($val['SPRICE'] ?? 0),
                            'sgpft'  => floatval($val['SGPFT'] ?? 0),
                            'sroi'   => floatval($val['SROI'] ?? 0),
                            'spft'   => floatval($val['SPFT'] ?? 0),
                        ];
                    }
                }
                [$aeBuyerLink, $aeSellerLink] = $getListingLinks(\App\Models\AliexpressListingStatus::class, $fullSku);
            } catch (\Exception $e) {
                Log::warning('AliExpress DataView/ListingStatus fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $hasAeData = $aePricingRowBd && ($aePriceBd > 0 || $aeL30Bd > 0);
            $aeViewsBd = null;
            try {
                $aeSheetBd = \App\Models\AliExpressSheetData::where('sku', $fullSku)->first();
                if ($aeSheetBd && ($aeSheetBd->views !== null && $aeSheetBd->views !== '')) {
                    $aeViewsBd = intval($aeSheetBd->views);
                }
            } catch (\Exception $e) {
                // leave N/A
            }

            $breakdownData[] = [
                'marketplace' => 'AliExpress',
                'sku'         => $hasAeData ? $fullSku : 'Not Listed',
                'price'       => round($aePriceBd, 2),
                'views'       => $aeViewsBd,
                'l30'         => $aeL30Bd,
                'gpft'        => round($aeGPFTBd, 2),
                'ad'          => 0,
                'tacos_ch'    => $getChannelTACOS('Aliexpress'),
                'npft'        => round($aeNPFTBd, 2),
                'is_listed'   => $hasAeData ? true : false,
                'sprice'      => $aeSuggestedBd['sprice'],
                'sgpft'       => $aeSuggestedBd['sgpft'],
                'sroi'        => $aeSuggestedBd['sroi'],
                'spft'        => $aeSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => $ship,
                'margin'      => $aeMarginBd,
                'pushed_by'   => null,
                'pushed_at'   => null,
                'buyer_link'  => $aeBuyerLink,
                'seller_link' => $aeSellerLink,
            ];

            // Add Purchasing Power
            $ppMarketplacePercBd = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $ppMarginBd = $ppMarketplacePercBd ? ($ppMarketplacePercBd->percentage / 100) : 0.70;
            $ppProductBd = null;
            $ppL30Bd = 0;
            try {
                $ppProductBd = \App\Models\PurchasingPowerProduct::where('sku', $fullSku)->first();
                $ppL30Bd = (int) (\App\Models\PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
                    ->whereRaw('UPPER(offer_sku) = ?', [strtoupper($fullSku)])
                    ->sum('quantity') ?? ($ppProductBd->m_l30 ?? 0));
            } catch (\Exception $e) {
                Log::warning('PP breakdown data fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $ppPriceBd = $ppProductBd ? floatval($ppProductBd->price ?? 0) : 0;
            $ppGPFTBd  = $ppPriceBd > 0 ? (($ppPriceBd * $ppMarginBd - $lp - $ship) / $ppPriceBd) * 100 : 0;
            $ppNPFTBd  = round($ppGPFTBd - $getChannelTACOS('Purchase'), 2);
            $ppSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            try {
                $ppDataViewBd = \App\Models\PurchasingPowerDataView::where('sku', $fullSku)->first();
                if ($ppDataViewBd) {
                    $val = is_array($ppDataViewBd->value) ? $ppDataViewBd->value : json_decode($ppDataViewBd->value, true);
                    if (is_array($val)) {
                        $ppSuggestedBd = [
                            'sprice' => floatval($val['SPRICE'] ?? 0),
                            'sgpft'  => floatval($val['SGPFT'] ?? 0),
                            'sroi'   => floatval($val['SROI']  ?? 0),
                            'spft'   => floatval($val['SPFT']  ?? 0),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('PP DataView fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $hasPpData = $ppProductBd && ($ppPriceBd > 0 || $ppL30Bd > 0);

            $breakdownData[] = [
                'marketplace' => 'PPower',
                'sku'         => $hasPpData ? $fullSku : 'Not Listed',
                'price'       => round($ppPriceBd, 2),
                'views'       => null, // Purchasing Power has no views metric
                'l30'         => $ppL30Bd,
                'gpft'        => round($ppGPFTBd, 2),
                'ad'          => 0,
                'tacos_ch'    => $getChannelTACOS('Purchase'),
                'npft'        => $ppNPFTBd,
                'is_listed'   => $hasPpData ? true : false,
                'sprice'      => $ppSuggestedBd['sprice'],
                'sgpft'       => $ppSuggestedBd['sgpft'],
                'sroi'        => $ppSuggestedBd['sroi'],
                'spft'        => $ppSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => $ship,
                'margin'      => $ppMarginBd,
                'pushed_by'   => null,
                'pushed_at'   => null,
                'buyer_link'  => null,
                'seller_link' => null,
            ];

            // FBA row – same structure as other marketplaces (only when SKU has FBA data)
            try {
                $baseSku = strtoupper(trim($fullSku));
                $fbaTableRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                    ->get()
                    ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                $fbaRow = $fbaTableRows->get($baseSku);
                if ($fbaRow) {
                    $fbaPriceData = FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                        ->get()
                        ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                    $fbaMonthlyData = FbaMonthlySale::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                        ->get()
                        ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                    $fbaReportsData = FbaReportsMaster::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                        ->get()
                        ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                    $fbaManualData = FbaManualData::all()->keyBy(fn($item) => strtoupper(trim((string)($item->sku ?? ''))));

                    $fbaPriceInfo = $fbaPriceData->get($baseSku);
                    $fbaMonthly = $fbaMonthlyData->get($baseSku);
                    $fbaReports = $fbaReportsData->get($baseSku);
                    $fbaSellerSku = trim((string)($fbaRow->seller_sku ?? ''));
                    $fbaManual = $fbaManualData->get(strtoupper($fbaSellerSku)) ?? $fbaManualData->get($baseSku);

                    $fbaPrice = $fbaPriceInfo ? floatval($fbaPriceInfo->price ?? 0) : 0;
                    $fbaL30 = $fbaMonthly ? ($fbaMonthly->l30_units ?? 0) : 0;
                    $fbaViews = ($fbaReports && $fbaReports->current_month_views !== null)
                        ? intval($fbaReports->current_month_views)
                        : null;

                    $sendCost = 0;
                    if ($fbaManual) {
                        $manualData = $fbaManual->data;
                        if (is_string($manualData)) {
                            $manualData = json_decode($manualData, true) ?? [];
                        }
                        if (is_array($manualData)) {
                            $shippingAmount = floatval($manualData['shipping_amount'] ?? 0);
                            $qtyInBox = floatval($manualData['quantity_in_each_box'] ?? 0);
                            if ($qtyInBox > 0) {
                                $sendCost = round($shippingAmount / $qtyInBox, 2);
                            }
                        }
                    }
                    $fbaFeeManual = 0;
                    if ($fbaManual && is_array($fbaManual->data ?? null)) {
                        $fbaFeeManual = floatval($fbaManual->data['fba_fee_manual'] ?? 0);
                    } elseif ($fbaManual && is_string($fbaManual->data ?? '')) {
                        $dec = json_decode($fbaManual->data, true);
                        $fbaFeeManual = is_array($dec) ? floatval($dec['fba_fee_manual'] ?? 0) : 0;
                    }
                    $fbaShip = app(FbaManualDataService::class)->calculateFbaShipCalculation(
                        $fbaRow->seller_sku ?? '',
                        $fbaFeeManual,
                        $sendCost
                    );

                    $amazonMarketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();
                    $fbaMargin = $amazonMarketplace ? ($amazonMarketplace->percentage / 100) : 0.80;
                    $fbaGPFT = $fbaPrice > 0 ? (($fbaPrice * $fbaMargin - $fbaShip - $lp) / $fbaPrice) * 100 : 0;
                    $fbaAD = 0;
                    $fbaNPFT = $fbaL30 == 0 ? $fbaGPFT : ($fbaGPFT - $fbaAD);

                    // FBA row: use SPRICE from FbaManualData (same as FBA page) so row shows saved/pushed value
                    $fbaSprice = 0;
                    if ($fbaManual) {
                        $dm = $fbaManual->data;
                        if (is_string($dm)) {
                            $dm = json_decode($dm, true) ?? [];
                        }
                        $dm = is_array($dm) ? $dm : [];
                        $fbaSprice = floatval($dm['s_price'] ?? $dm['S_Price'] ?? 0);
                    }
                    if ($fbaSprice > 0) {
                        $fbaSgpft = (($fbaSprice * $fbaMargin - $fbaShip - $lp) / $fbaSprice) * 100;
                        $fbaSpft = $fbaL30 == 0 ? $fbaSgpft : ($fbaSgpft - $fbaAD);
                        $fbaSroi = $lp > 0 ? (($fbaSprice * $fbaMargin - $lp - $fbaShip) / $lp) * 100 : 0;
                    } else {
                        $fbaSprice = $amazonSuggested['sprice'] ?? 0;
                        $fbaSgpft = $amazonSuggested['sgpft'] ?? 0;
                        $fbaSpft = $amazonSuggested['spft'] ?? 0;
                        $fbaSroi = $amazonSuggested['sroi'] ?? 0;
                    }

                    // Get LMP data for FBA SKU by matching base SKU (remove "FBA" suffix)
                    $baseSkuForLmp = strtoupper(preg_replace('/\s*FBA\s*/i', '', $fullSku));
                    $baseSkuForLmp = preg_replace('/\s+/', ' ', trim($baseSkuForLmp));
                    $fbaLmp = $amazonLmpLookup->get($baseSkuForLmp);
                    $fbaLmpPrice = ($fbaLmp && isset($fbaLmp->price) && is_numeric($fbaLmp->price))
                        ? round(floatval($fbaLmp->price), 2) : null;
                    $fbaLmpLink = ($fbaLmp && !empty($fbaLmp->product_link)) ? $fbaLmp->product_link : null;

                    $breakdownData[] = [
                        'marketplace' => 'FBA',
                        'sku' => $fullSku,
                        'price' => round($fbaPrice, 2),
                        'lmp_price' => $fbaLmpPrice,
                        'lmp_link' => $fbaLmpLink,
                        'views' => $fbaViews,
                        'l30' => $fbaL30,
                        'gpft' => round($fbaGPFT, 2),
                        'ad' => $fbaAD,
                        'tacos_ch' => 0,
                        'npft' => round($fbaNPFT, 2),
                        'is_listed' => true,
                        'sprice' => round($fbaSprice, 2),
                        'sgpft' => round($fbaSgpft, 2),
                        'sroi' => round($fbaSroi, 2),
                        'spft' => round($fbaSpft, 2),
                        'lp' => $lp,
                        'ship' => $fbaShip,
                        'margin' => $fbaMargin,
                        'pushed_by' => $amazonPushedBy ?? null,
                        'pushed_at' => $amazonPushedAt ?? null,
                        'buyer_link' => $amazonBuyerLink ?? null,
                        'seller_link' => $amazonSellerLink ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('FBA row skipped in breakdown for SKU ' . $fullSku . ': ' . $e->getMessage());
            }

            // Attach channel Ads%, NPFT = GPFT − Ads%, and channel-relevant LMP price
            $amazonLmpPrice = $resolveAmazonLmpPrice($fullSku);
            $ebayLmpPrice = $resolveEbayLmpPrice($fullSku);
            if ($ebayLmpPrice === null) {
                foreach (EbaySkuCompetitor::resolveLookupKeys($fullSku) as $ebayKey) {
                    $ebayLmpPrice = $resolveEbayLmpPrice($ebayKey);
                    if ($ebayLmpPrice !== null) {
                        break;
                    }
                }
            }
            $googleLmpPrice = $resolveGoogleLmpPrice($fullSku);
            $temuLmpPrice = $resolveTemuLmpPrice($fullSku);

            foreach ($breakdownData as &$row) {
                $adsPct = (float) $getChannelAdsPercent($row['marketplace'] ?? '');
                $gpftPct = (float) ($row['gpft'] ?? 0);
                $row['tacos_ch'] = $adsPct;
                $row['npft'] = round($gpftPct - $adsPct, 2);

                $mp = strtolower(trim((string) ($row['marketplace'] ?? '')));
                $lmpChannel = null;
                $lmpPrice = null;
                if (in_array($mp, ['amazon', 'fba'], true)) {
                    $lmpChannel = 'amazon';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveAmazonLmpPrice($rowSku) ?? $amazonLmpPrice;
                } elseif (in_array($mp, ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'], true)) {
                    $lmpChannel = 'ebay';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveEbayLmpPrice($rowSku);
                    if ($lmpPrice === null) {
                        foreach (EbaySkuCompetitor::resolveLookupKeys($rowSku, $fullSku) as $ebayKey) {
                            $lmpPrice = $resolveEbayLmpPrice($ebayKey);
                            if ($lmpPrice !== null) {
                                break;
                            }
                        }
                    }
                    if ($lmpPrice === null) {
                        $lmpPrice = $ebayLmpPrice;
                    }
                } elseif ($mp === 'google') {
                    $lmpChannel = 'google';
                    $lmpPrice = $googleLmpPrice;
                } elseif (in_array($mp, ['temu', 'temu2'], true)) {
                    // Same temu_lmp source used by /temu-decrease and /temu2-decrease
                    $lmpChannel = 'temu';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveTemuLmpPrice($rowSku) ?? $temuLmpPrice;
                }

                $row['lmp_channel'] = $lmpChannel;
                $row['lmp_price'] = $lmpPrice;
            }
            unset($row);

            Log::info('Total marketplaces: ' . count($breakdownData));

            return response()->json($breakdownData);
            
        } catch (\Exception $e) {
            Log::error('Error fetching breakdown data for SKU ' . $sku . ': ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch breakdown data'], 500);
        }
    }

    /**
     * Save a new remark for a SKU
     */
    public function saveRemark(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'remark' => 'required|string|max:200',
            ]);

            $remark = CvrRemark::create([
                'sku' => $request->sku,
                'remark' => $request->remark,
                'user_id' => auth()->id(),
                'is_solved' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Remark saved successfully',
                'remark' => $remark->load('user'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving CVR remark: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save remark'], 500);
        }
    }

    /**
     * Get remark history for a SKU
     */
    public function getRemarkHistory($sku)
    {
        try {
            $remarks = CvrRemark::where('sku', $sku)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($remark) {
                    return [
                        'id' => $remark->id,
                        'remark' => $remark->remark,
                        'user_name' => $remark->user ? $remark->user->name : 'Unknown',
                        'is_solved' => $remark->is_solved,
                        'created_at' => $remark->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json($remarks);
        } catch (\Exception $e) {
            Log::error('Error fetching remark history for SKU ' . $sku . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch remark history'], 500);
        }
    }

    /**
     * Get latest remark for a SKU
     */
    public function getLatestRemark($sku)
    {
        try {
            $remark = CvrRemark::where('sku', $sku)
                ->latest()
                ->first();

            if ($remark) {
                return response()->json([
                    'remark' => $remark->remark,
                    'user_name' => $remark->user ? $remark->user->name : 'Unknown',
                    'created_at' => $remark->created_at->format('Y-m-d H:i:s'),
                    'is_solved' => $remark->is_solved,
                ]);
            }

            return response()->json(null);
        } catch (\Exception $e) {
            Log::error('Error fetching latest remark for SKU ' . $sku . ': ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch latest remark'], 500);
        }
    }

    /**
     * Toggle solved status for a remark
     */
    public function toggleRemarkSolved(Request $request, $id)
    {
        try {
            $remark = CvrRemark::findOrFail($id);
            $remark->is_solved = !$remark->is_solved;
            $remark->save();

            return response()->json([
                'success' => true,
                'is_solved' => $remark->is_solved,
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling remark solved status: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update remark'], 500);
        }
    }

    /**
     * Get Amazon SPRICE table data (from amazon_data_view only)
     */
    public function getAmazonSpriceTableData(Request $request)
    {
        $rows = [];
        $amazonViews = AmazonDataView::orderBy('sku')->get();
        $amazonMarketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $defaultMargin = $amazonMarketplace ? ($amazonMarketplace->percentage / 100) : 0.80;

        foreach ($amazonViews as $av) {
            $val = is_array($av->value) ? $av->value : (json_decode($av->value ?? '{}', true) ?? []);
            $sprice = isset($val['SPRICE']) ? floatval($val['SPRICE']) : null;
            if ($sprice === null || $sprice <= 0) {
                continue;
            }
            $rows[] = [
                'sku' => $av->sku,
                'sprice' => round($sprice, 2),
                'sgpft' => isset($val['SGPFT']) ? round(floatval($val['SGPFT']), 2) : null,
                'spft' => isset($val['SPFT']) ? round(floatval($val['SPFT']), 2) : null,
                'sroi' => isset($val['SROI']) ? round(floatval($val['SROI']), 2) : null,
                'amazon_margin' => $defaultMargin,
                'avg_pft' => null,
                'updated_at' => $av->updated_at?->toDateTimeString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * Get Master Analytics chart data (Rolling L30) for Inv, OV L30, Price, CVR graphs.
     * Data is read from pricing_master_daily_snapshots_sku (SKU-wise, saved on page load/refresh).
     * When "parent" is provided, aggregates data for all SKUs under that parent by snapshot_date.
     */
    public function getPricingMasterChartData(Request $request)
    {
        $metric = strtolower(trim($request->input('metric', 'inv')));
        $days = (int) $request->input('days', 30);
        $skuRaw = $request->input('sku', '');
        $parentRaw = preg_replace('/\s+/', ' ', trim($request->input('parent', '')));
        $aggregate = filter_var($request->input('aggregate', false), FILTER_VALIDATE_BOOLEAN);
        $allowed = ['inv', 'ov_l30', 'price', 'cvr', 'dil', 'amz_price', 'rating', 'total_views'];
        if (!in_array($metric, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
        }

        $isParent = $parentRaw !== '';
        $isAggregate = $aggregate && $parentRaw === '' && trim($skuRaw) === '';

        // For aggregate chart, use daily totals table when metric has a direct column (correct totals like 180k)
        $aggregateUsesDailyTable = $isAggregate && in_array($metric, ['inv', 'ov_l30', 'price', 'cvr', 'dil'], true);

        if ($aggregateUsesDailyTable) {
            $query = PricingMasterDailySnapshot::orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $start = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $start);
            }
            $rows = $query->get();
            $data = $rows->map(function ($row) use ($metric) {
                $value = match ($metric) {
                    'inv' => (float) ($row->total_inv ?? 0),
                    'ov_l30' => (float) ($row->total_ov_l30 ?? 0),
                    'price' => $row->avg_price !== null ? (float) $row->avg_price : 0,
                    'cvr' => $row->avg_cvr !== null ? (float) $row->avg_cvr : 0,
                    'dil' => ($row->total_inv ?? 0) > 0
                        ? round(((float) ($row->total_ov_l30 ?? 0) / (float) $row->total_inv) * 100, 2)
                        : 0,
                    default => (float) ($row->total_inv ?? 0),
                };
                return [
                    'date' => Carbon::parse($row->snapshot_date)->format('M j'),
                    'value' => $value,
                ];
            })->values()->all();
            return response()->json(['success' => true, 'data' => $data]);
        }

        if ($isAggregate) {
            // Summary-level: aggregate all SKUs by snapshot_date (for total_views, rating, amz_price)
            $query = PricingMasterDailySnapshotSku::orderBy('snapshot_date', 'asc');
        } elseif ($isParent) {
            // Parent-level: get all SKUs for this parent from ProductMaster
            $skus = ProductMaster::where('parent', $parentRaw)->pluck('sku')->toArray();
            if (empty($skus)) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $query = PricingMasterDailySnapshotSku::whereIn('sku', $skus)->orderBy('snapshot_date', 'asc');
        } else {
            $sku = preg_replace('/\s+/', ' ', trim($skuRaw));
            if ($sku === '') {
                return response()->json(['success' => false, 'message' => 'SKU or parent is required for chart'], 400);
            }
            $skuNorm = strtolower($sku);
            $query = PricingMasterDailySnapshotSku::whereRaw('LOWER(TRIM(sku)) = ?', [$skuNorm])->orderBy('snapshot_date', 'asc');
        }

        if ($days > 0) {
            $start = now('America/Los_Angeles')->subDays($days)->toDateString();
            $query->where('snapshot_date', '>=', $start);
        }
        $rows = $query->get();

        if (($isParent || $isAggregate) && $rows->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($isParent || $isAggregate) {
            // Aggregate by snapshot_date
            $byDate = $rows->groupBy(function ($row) {
                return $row->snapshot_date->format('Y-m-d');
            });
            $data = collect($byDate)->map(function ($dateRows, $dateStr) use ($metric) {
                $invSum = $dateRows->sum('inventory');
                $l30Sum = $dateRows->sum('overall_l30');
                $viewsSum = $dateRows->sum('total_views');
                $value = match ($metric) {
                    'inv' => (float) $invSum,
                    'ov_l30' => (float) $l30Sum,
                    'total_views' => (float) $viewsSum,
                    'price' => (float) $dateRows->avg('avg_price'),
                    'cvr' => $viewsSum > 0 ? round(($l30Sum / $viewsSum) * 100, 2) : 0,
                    'dil' => $invSum > 0 ? round(($l30Sum / $invSum) * 100, 2) : 0,
                    'amz_price' => (float) $dateRows->avg('amazon_price'),
                    'rating' => (float) $dateRows->avg('rating'),
                    default => (float) $invSum,
                };
                return [
                    'date' => Carbon::parse($dateStr)->format('M j'),
                    'value' => $value,
                    '_key' => $dateStr,
                ];
            })->sortBy('_key')->map(fn ($d) => ['date' => $d['date'], 'value' => $d['value']])->values()->all();
        } else {
            $column = match ($metric) {
                'inv' => 'inventory',
                'ov_l30' => 'overall_l30',
                'price' => 'avg_price',
                'cvr' => 'avg_cvr',
                'dil' => 'dil_percent',
                'amz_price' => 'amazon_price',
                'rating' => 'rating',
                'total_views' => 'total_views',
                default => 'inventory',
            };
            $data = $rows->map(function ($row) use ($column) {
                $val = $row->{$column};
                $value = $val !== null ? (is_numeric($val) ? (float) $val : 0) : 0;
                return [
                    'date' => Carbon::parse($row->snapshot_date)->format('M j'),
                    'value' => $value,
                ];
            })->values()->all();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Save suggested pricing data (SPRICE, SGPFT, SPFT, SROI) to data_view tables
     */
    public function saveSuggestedData(Request $request)
    {
        try {
            $sku = trim($request->input('sku', ''));
            $marketplace = strtolower(trim($request->input('marketplace', '')));
            if (empty($sku) || empty($marketplace)) {
                return response()->json(['error' => 'SKU and marketplace are required'], 400);
            }

            // Resolve full SKU (same as getBreakdownData): use ProductMaster then Amazon datasheet for Amazon
            $productMaster = ProductMaster::where('sku', $sku)
                ->orWhere('sku', 'LIKE', $sku . '%')
                ->first();
            $fullSku = $productMaster ? $productMaster->sku : $sku;

            if ($marketplace === 'amazon') {
                // Use Amazon datasheet: prefer SKU as stored in amazon_datsheets for consistency
                $amazonData = AmazonDatasheet::where('sku', $fullSku)->first();
                $skuToUse = $amazonData ? $amazonData->sku : $fullSku;
            } else {
                $skuToUse = $fullSku;
            }

            $sprice = floatval($request->input('sprice', 0));
            $sgpft = floatval($request->input('sgpft', 0));
            $sroi = floatval($request->input('sroi', 0));
            $spft = floatval($request->input('spft', 0));
            $amazonMargin = $request->has('amazon_margin') ? floatval($request->input('amazon_margin')) : null;
            $avgPft = $request->has('avg_pft') ? floatval($request->input('avg_pft')) : null;

            // Determine which data_view table to use (use resolved SKU)
            if ($marketplace === 'amazon') {
                $dataView = AmazonDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'ebay' || $marketplace === 'ebay1') {
                $dataView = EbayDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'ebaytwo' || $marketplace === 'ebay2') {
                $dataView = EbayTwoDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'ebaythree' || $marketplace === 'ebay3') {
                $dataView = EbayThreeDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'temu') {
                $dataView = TemuDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'temu2') {
                if (!Schema::hasTable('temu2_data_view')) {
                    return response()->json(['error' => 'Temu 2 is not set up. Run migrations (temu2_data_view).'], 503);
                }
                $dataView = Temu2DataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'doba') {
                $dataView = DobaDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'walmart') {
                $dataView = WalmartDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'tiktok') {
                $dataView = TikTokDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'bestbuy') {
                $dataView = BestbuyUSADataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'macy') {
                $dataView = MacyDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'reverb') {
                $dataView = ReverbViewData::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'tiendamia') {
                $dataView = TiendamiaDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'shopifyb2c' || $marketplace === 'sb2c' || $marketplace === 'shopify') {
                $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'shopifyb2b' || $marketplace === 'sb2b') {
                $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'fba') {
                $dataView = null; // FBA uses FbaManualData, handled below
            } else {
                return response()->json(['error' => 'Marketplace not supported'], 400);
            }

            if ($marketplace === 'fba') {
                // FBA SPRICE is stored in fba_manual_data (same as FBA page). Resolve base SKU -> FBA seller SKU.
                $baseSku = strtoupper(trim($fullSku));
                $fbaTableRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                    ->get()
                    ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                $fbaRow = $fbaTableRows->get($baseSku);
                if (!$fbaRow || empty($fbaRow->seller_sku)) {
                    return response()->json(['error' => 'No FBA listing found for this SKU'], 400);
                }
                $fbaSellerSku = strtoupper(trim($fbaRow->seller_sku));
                $manual = FbaManualData::where('sku', $fbaSellerSku)->first();
                if (!$manual) {
                    $manual = new FbaManualData();
                    $manual->sku = $fbaSellerSku;
                    $manual->data = [];
                }
                $data = is_array($manual->data) ? $manual->data : [];
                $data['s_price'] = $sprice;
                $data['SPRICE_STATUS'] = 'applied'; // saved but not pushed
                $manual->data = $data;
                $manual->save();
                return response()->json(['success' => true]);
            }

            // Get existing value (Reverb uses 'values', others use 'value')
            if ($marketplace === 'reverb') {
                $value = is_array($dataView->values) ? $dataView->values : 
                         (is_string($dataView->values) ? json_decode($dataView->values, true) : []);
            } else {
                $value = is_array($dataView->value) ? $dataView->value : 
                         (is_string($dataView->value) ? json_decode($dataView->value, true) : []);
            }
            if (!is_array($value)) $value = [];
            
            // Update values (Walmart uses lowercase 'sprice', others use 'SPRICE')
            if ($marketplace === 'walmart') {
                $value['sprice'] = $sprice;  // Walmart uses lowercase
            } else {
                $value['SPRICE'] = $sprice;  // Others use uppercase
            }
            $value['SGPFT'] = $sgpft;
            $value['SROI'] = $sroi;
            $value['SPFT'] = $spft;
            
            // Remove lowercase duplicates (but not for Walmart which uses lowercase 'sprice');
            // Temu 2: also persist sprice + sgprft_percent + sroi_percent (same as Temu2 decrease / saveTemu2Sprice)
            if ($marketplace === 'walmart') {
                unset($value['SPRICE']);
            } elseif ($marketplace === 'temu2') {
                $value['sprice'] = $sprice;
                $value['sgprft_percent'] = round($sgpft, 2);
                $value['sroi_percent'] = round($sroi, 2);
            } else {
                unset($value['sprice'], $value['sgpft'], $value['sroi'], $value['spft']);
            }
            
            // Save to correct field (Reverb uses 'values', others use 'value')
            if ($marketplace === 'reverb') {
                $dataView->values = $value;
            } else {
                $dataView->value = $value;
            }
            $dataView->save();

            // When saving Amazon suggested price, also sync to FBA if this SKU has FBA (so FBA page shows same sprice)
            if ($marketplace === 'amazon' && $sprice > 0) {
                $baseSku = strtoupper(trim($fullSku));
                $fbaTableRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                    ->get()
                    ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
                $fbaRow = $fbaTableRows->get($baseSku);
                if ($fbaRow && !empty($fbaRow->seller_sku)) {
                    $fbaSellerSku = strtoupper(trim($fbaRow->seller_sku));
                    $fbaManual = FbaManualData::where('sku', $fbaSellerSku)->first();
                    if (!$fbaManual) {
                        $fbaManual = new FbaManualData();
                        $fbaManual->sku = $fbaSellerSku;
                        $fbaManual->data = [];
                    }
                    $fbaData = is_array($fbaManual->data) ? $fbaManual->data : [];
                    $fbaData['s_price'] = $sprice;
                    if (!isset($fbaData['SPRICE_STATUS']) || $fbaData['SPRICE_STATUS'] === '') {
                        $fbaData['SPRICE_STATUS'] = 'applied';
                    }
                    $fbaManual->data = $fbaData;
                    $fbaManual->save();
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving suggested data: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $message = config('app.debug') ? $e->getMessage() : 'Failed to save';
            return response()->json(['error' => $message], 500);
        }
    }

    /**
     * Push price to marketplace (Amazon or Doba) for a specific SKU
     * Similar to OverallAmazonController::applyAmazonPrice
     */
    public function pushPriceToAmazon(Request $request)
    {
        try {
            // Log incoming request for debugging
            Log::info('CVR Master - Push price request received', [
                'sku' => $request->input('sku'),
                'price' => $request->input('price'),
                'marketplace' => $request->input('marketplace'),
                'all_input' => $request->all()
            ]);

            // Validate request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'sku' => 'required|string',
                'price' => 'required|numeric|min:0.01|max:999999.99',
                'marketplace' => 'required|string'
            ]);

            if ($validator->fails()) {
                Log::warning('CVR Master - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray()
                ], 400);
            }

            $sku = strtoupper(trim($request->input('sku')));
            $price = round(floatval($request->input('price')), 2);
            $marketplace = strtolower($request->input('marketplace'));

            // Validate price
            if ($price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price must be greater than 0.'
                ], 400);
            }

            // Handle different marketplaces (API push where available)
            if ($marketplace === 'amazon') {
                return $this->pushToAmazon($sku, $price);
            } elseif ($marketplace === 'doba') {
                return $this->pushToDoba($sku, $price);
            } elseif ($marketplace === 'walmart') {
                return $this->pushToWalmart($sku, $price);
            } elseif ($marketplace === 'sb2c' || $marketplace === 'shopifyb2c' || $marketplace === 'shopify') {
                return $this->pushToShopifyB2C($sku, $price);
            } elseif ($marketplace === 'sb2b' || $marketplace === 'shopifyb2b') {
                return $this->pushToShopifyB2B($sku, $price);
            } elseif ($marketplace === 'pls' || $marketplace === 'prolightsounds') {
                return $this->pushToPls($sku, $price);
            } elseif ($marketplace === 'reverb') {
                return $this->pushToReverb($sku, $price);
            } elseif ($marketplace === 'fba') {
                return $this->pushToFba($sku, $price);
            } elseif ($marketplace === 'ebay' || $marketplace === 'ebay1') {
                return $this->pushToEbay($sku, $price);
            } elseif ($marketplace === 'ebay2' || $marketplace === 'ebaytwo') {
                return $this->pushToEbay2($sku, $price);
            } elseif ($marketplace === 'ebay3' || $marketplace === 'ebaythree') {
                return $this->pushToEbay3($sku, $price);
            } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
                return $this->pushToBestBuy($sku, $price);
            } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
                return $this->pushToMacy($sku, $price);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Price push is not available for this channel ($marketplace). Supported: Amazon, eBay1/2/3, Doba, Walmart, Shopify, SB2B, BestBuy, Macy, Reverb, FBA."
                ], 400);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('CVR Master - Validation exception', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('CVR Master - Exception in pushPriceToAmazon', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Push price to Amazon
     */
    private function pushToAmazon($sku, $price)
    {
        try {
            // Prefer exact seller MSKU from `amazon_datsheets` (same as OverallAmazonController::applyAmazonPrice);
            // CVR was passing strtoupper($sku) only, which can miss case/spacing and fail Listings API lookup.
            $resolved = \App\Models\AmazonDatasheet::resolveSellerMskuByProductKey(
                str_replace("\xc2\xa0", ' ', (string) $sku)
            );
            $apiSku = ($resolved !== null && $resolved !== '') ? $resolved : (string) $sku;

            // Push to Amazon using SP API
            $service = new AmazonSpApiService();
            $result = $service->updateAmazonPriceUS($apiSku, $price);

            // Check if the response indicates errors
            if (isset($result['errors']) && !empty($result['errors'])) {
                // Save error status to amazon_data_view
                $this->savePricePushStatus($sku, 'amazon', 'error', $price);
                
                Log::error('CVR Master - Amazon price push failed', [
                    'sku' => $sku,
                    'price' => $price,
                    'errors' => $result['errors']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to push price to Amazon.',
                    'errors' => $result['errors']
                ], 400);
            }

            // Save success status
            $this->savePricePushStatus($sku, 'amazon', 'pushed', $price);
            
            Log::info('CVR Master - Amazon price push successful', [
                'sku' => $sku,
                'price' => $price
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Price $" . number_format($price, 2) . " pushed to Amazon for SKU: $sku",
                'result' => $result
            ]);

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'amazon', 'error', $price);
            
            Log::error('CVR Master - Amazon push exception', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Amazon API error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Push price to Doba
     * Matches DobaController::pushPriceToDoba implementation
     */
    private function pushToDoba($sku, $price)
    {
        try {
            // Get item_id from doba_metrics table
            $dobaMetric = DobaMetric::where('sku', $sku)->first();
            
            if (!$dobaMetric || !$dobaMetric->item_id) {
                $this->savePricePushStatus($sku, 'doba', 'error', $price);
                
                Log::warning('CVR Master - Doba SKU not found', [
                    'sku' => $sku,
                    'price' => $price
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "Item ID not found for SKU: $sku. Please run Doba metrics fetch first.",
                    'errors' => [['message' => 'Item ID not found for this SKU']]
                ], 404);
            }

            $itemId = $dobaMetric->item_id;
            $selfPickPrice = $dobaMetric->self_pick_price ?? null; // Get self_pick_price from metric
            
            // Push to Doba using API (matching DobaController implementation)
            $dobaApiService = new DobaApiService();
            $priceResult = $dobaApiService->updateItemPrice($itemId, $price, $selfPickPrice);

            // Check if the response indicates real errors
            // Some API responses include an "errors" key even when empty.
            if (!empty($priceResult['errors'])) {
                $errorMessage = is_array($priceResult['errors'])
                    ? json_encode($priceResult['errors'])
                    : (string) $priceResult['errors'];

                // Save error status to doba_data_view
                $this->savePricePushStatus($sku, 'doba', 'error', $price);
                
                Log::warning('CVR Master - Doba price push failed', [
                    'sku' => $sku,
                    'item_id' => $itemId,
                    'price' => $price,
                    'self_pick_price' => $selfPickPrice,
                    'error' => $errorMessage
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Price update failed: ' . $errorMessage,
                    'errors' => [['message' => 'Price update: ' . $errorMessage]]
                ], 400);
            }

            // Only push to Doba API - do not update local doba_metrics (main table)
            $this->savePricePushStatus($sku, 'doba', 'pushed', $price);
            
            Log::info('CVR Master - Doba price push successful', [
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Price $" . number_format($price, 2) . " pushed to Doba successfully for SKU: $sku",
                'data' => [
                    'price_update' => $priceResult
                ]
            ]);

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'doba', 'error', $price);
            
            Log::error('CVR Master - Doba push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'API Exception: ' . $e->getMessage(),
                'errors' => [['message' => 'API Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to Walmart
     * Matches PricingMasterViewsController::pushPricewalmart implementation
     */
    private function pushToWalmart($sku, $price)
    {
        try {
            // Walmart uses SKU directly (no need to lookup item_id)
            // Verify SKU exists in walmart_api_data table
            $walmartSku = DB::connection('apicentral')
                ->table('walmart_api_data')
                ->where('sku', $sku)
                ->value('sku');
            
            if (!$walmartSku) {
                $this->savePricePushStatus($sku, 'walmart', 'error', $price);
                
                Log::warning('CVR Master - Walmart SKU not found', [
                    'sku' => $sku,
                    'price' => $price
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "SKU: $sku not found in Walmart API data.",
                    'errors' => [['message' => 'SKU not found in Walmart listings']]
                ], 404);
            }
            
            // Push to Walmart using API (matching PricingMasterViewsController)
            $walmartService = new WalmartService();
            $result = $walmartService->updatePrice($sku, $price);

            // Check if the response indicates errors
            if (isset($result['errors']) && !empty($result['errors'])) {
                // Save error status to walmart_data_view
                $this->savePricePushStatus($sku, 'walmart', 'error', $price);
                
                $reason = is_array($result['errors']) ? json_encode($result['errors']) : $result['errors'];
                
                Log::error('CVR Master - Walmart price push failed', [
                    'sku' => $sku,
                    'price' => $price,
                    'error' => $reason
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Price update failed: ' . $reason,
                    'errors' => [['message' => 'Price update: ' . $reason]]
                ], 400);
            }

            // Save success status
            $this->savePricePushStatus($sku, 'walmart', 'pushed', $price);
            
            Log::info('CVR Master - Walmart price push successful', [
                'sku' => $sku,
                'price' => $price
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Price $" . number_format($price, 2) . " pushed to Walmart successfully for SKU: $sku",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'walmart', 'error', $price);
            
            Log::error('CVR Master - Walmart push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Walmart API error: ' . $e->getMessage(),
                'errors' => [['message' => 'API Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to Shopify B2C
     * Matches PricingMasterViewsController Shopify implementation
     */
    private function pushToShopifyB2C($sku, $price)
    {
        try {
            // Get variant_id from shopify_skus — use normalized SKU match (spaces / NBSP) like mapByProductSkus
            $byNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku([$sku]);
            $k = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $shopifyRecord = ($k !== '' && isset($byNorm[$k])) ? $byNorm[$k] : ShopifySku::where('sku', $sku)->first();
            
            if (!$shopifyRecord) {
                $this->savePricePushStatus($sku, 'shopifyb2c', 'error', $price);
                
                Log::error('CVR Master - Shopify B2C SKU not found', [
                    'sku' => $sku
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "SKU: $sku not found in Shopify.",
                    'errors' => [['message' => 'SKU not found in Shopify listings']]
                ], 404);
            }

            $variantId = $shopifyRecord->variant_id;
            
            if (!$variantId) {
                $this->savePricePushStatus($sku, 'shopifyb2c', 'error', $price);
                
                Log::error('CVR Master - Shopify B2C Variant ID is null', [
                    'sku' => $sku,
                    'shopify_record' => $shopifyRecord
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "Variant ID not found for SKU: $sku",
                    'errors' => [['message' => 'Variant ID is null']]
                ], 404);
            }
            
            Log::info('CVR Master - Calling Shopify API to update B2C price', [
                'sku' => $sku,
                'variant_id' => $variantId,
                'price' => $price
            ]);

            // Push to Shopify using API
            $result = \App\Http\Controllers\UpdatePriceApiController::updateShopifyVariantPrice($variantId, $price);

            if ($result['status'] === 'success') {
                // Only push to Shopify API - do not update local shopify_skus.price (main table)
                $verifiedPrice = $result['verified_price'] ?? $price;
                $this->savePricePushStatus($sku, 'shopifyb2c', 'pushed', $verifiedPrice);
                
                Log::info('CVR Master - Shopify B2C price push successful', [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'price' => $verifiedPrice
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($verifiedPrice, 2) . " pushed to Shopify B2C successfully for SKU: $sku",
                    'data' => $result
                ]);
            } else {
                $reason = $result['message'] ?? 'API error';
                
                $this->savePricePushStatus($sku, 'shopifyb2c', 'error', $price);
                
                Log::error('CVR Master - Shopify B2C price push failed', [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'price' => $price,
                    'reason' => $reason,
                    'result' => $result
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Shopify B2C price update failed: ' . $reason,
                    'errors' => [['message' => $reason]]
                ], 400);
            }

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'shopifyb2c', 'error', $price);
            
            Log::error('CVR Master - Shopify B2C push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Shopify B2C API error: ' . $e->getMessage(),
                'errors' => [['message' => 'API Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to ProLightSounds (PLS) Shopify store
     */
    private function pushToPls($sku, $price)
    {
        try {
            // Get variant_id from shopify_catalog_variants for PLS store
            $plsVariant = \App\Models\ShopifyPlsVariant::where('sku', $sku)->first();

            if (!$plsVariant) {
                $this->savePricePushStatus($sku, 'pls', 'error', $price);

                Log::error('CVR Master - PLS SKU not found', [
                    'sku' => $sku
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "SKU: $sku not found in ProLightSounds.",
                    'errors' => [['message' => 'SKU not found in ProLightSounds listings']]
                ], 404);
            }

            $variantId = $plsVariant->shopify_variant_id;

            if (!$variantId) {
                $this->savePricePushStatus($sku, 'pls', 'error', $price);

                Log::error('CVR Master - PLS Variant ID is null', [
                    'sku' => $sku,
                    'pls_variant' => $plsVariant
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Variant ID not found for SKU: $sku",
                    'errors' => [['message' => 'Variant ID is null']]
                ], 404);
            }

            Log::info('CVR Master - Calling Shopify API to update PLS price', [
                'sku' => $sku,
                'variant_id' => $variantId,
                'price' => $price
            ]);

            // Push to ProLightSounds Shopify using UpdatePriceApiController
            $result = \App\Http\Controllers\UpdatePriceApiController::updateShopifyVariantPrice(
                $variantId, 
                $price,
                'pls'  // Pass store identifier
            );

            if ($result['status'] === 'success') {
                $verifiedPrice = $result['verified_price'] ?? $price;
                $this->savePricePushStatus($sku, 'pls', 'pushed', $verifiedPrice);

                Log::info('CVR Master - PLS price push successful', [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'price' => $verifiedPrice
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($verifiedPrice, 2) . " pushed to ProLightSounds successfully for SKU: $sku",
                    'data' => $result
                ]);
            } else {
                $reason = $result['message'] ?? 'API error';

                $this->savePricePushStatus($sku, 'pls', 'error', $price);

                Log::error('CVR Master - PLS price push failed', [
                    'sku' => $sku,
                    'variant_id' => $variantId,
                    'price' => $price,
                    'reason' => $reason,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'ProLightSounds price update failed: ' . $reason,
                    'errors' => [['message' => $reason]]
                ], 400);
            }

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'pls', 'error', $price);

            Log::error('CVR Master - PLS push exception', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ProLightSounds API error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Push price to Shopify B2B
     * Note: Shopify B2B pricing may require catalog-specific API (Shopify Plus)
     * For now, updates local b2b_price field
     */
    private function pushToShopifyB2B($sku, $price)
    {
        try {
            // Get record from shopify_skus table
            $shopifyRecord = ShopifySku::where('sku', $sku)->first();
            
            if (!$shopifyRecord) {
                $this->savePricePushStatus($sku, 'shopifyb2b', 'error', $price);
                
                Log::error('CVR Master - Shopify B2B SKU not found', [
                    'sku' => $sku
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "SKU: $sku not found in Shopify.",
                    'errors' => [['message' => 'SKU not found in Shopify listings']]
                ], 404);
            }
            
            // Update local b2b_price field
            // Note: Full B2B catalog API integration requires Shopify Plus
            try {
                DB::beginTransaction();
                
                $shopifyRecord->b2b_price = $price;
                $shopifyRecord->save();
                
                DB::commit();
                
                // Save success status
                $this->savePricePushStatus($sku, 'shopifyb2b', 'pushed', $price);
                
                Log::info('CVR Master - Shopify B2B price updated (local)', [
                    'sku' => $sku,
                    'b2b_price' => $price
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "B2B Price $" . number_format($price, 2) . " updated locally for SKU: $sku",
                    'note' => 'B2B price stored locally. Catalog API push requires Shopify Plus.'
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                $this->savePricePushStatus($sku, 'shopifyb2b', 'error', $price);
                
                Log::error('CVR Master - Shopify B2B local DB update failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update B2B price: ' . $e->getMessage(),
                    'errors' => [['message' => 'DB update failed']]
                ], 500);
            }

        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'shopifyb2b', 'error', $price);
            
            Log::error('CVR Master - Shopify B2B push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Shopify B2B error: ' . $e->getMessage(),
                'errors' => [['message' => 'Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to eBay 1 — same path as /push-ebay-price-tabulator (EbayController::pushEbayPrice).
     */
    private function pushToEbay($sku, $price)
    {
        try {
            $ebayMetric = EbayMetric::where('sku', $sku)->first();
            if (!$ebayMetric) {
                $ebayMetric = EbayMetric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
            }

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->savePricePushStatus($sku, 'ebay', 'error', $price);
                Log::warning('CVR Master - eBay item_id not found', ['sku' => $sku]);
                return response()->json([
                    'success' => false,
                    'message' => "eBay listing not found for SKU: $sku",
                    'errors' => [['message' => 'eBay listing not found for this SKU']]
                ], 404);
            }

            $ebayService = new EbayApiService();
            $result = $ebayService->reviseFixedPriceItem($ebayMetric->item_id, $price);

            if (isset($result['success']) && $result['success']) {
                $this->savePricePushStatus($sku, 'ebay', 'pushed', $price);
                Log::info('CVR Master - eBay price push successful', [
                    'sku' => $sku,
                    'price' => $price,
                    'item_id' => $ebayMetric->item_id,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($price, 2) . " pushed to eBay for SKU: $sku",
                    'result' => $result
                ]);
            }

            $isAccountRestricted = !empty($result['accountRestricted']);
            $this->savePricePushStatus($sku, 'ebay', $isAccountRestricted ? 'account_restricted' : 'error', $price);

            $errors = $result['errors'] ?? [['code' => 'UnknownError', 'message' => 'Failed to update price']];
            if (!is_array($errors)) {
                $errors = [$errors];
            }
            $firstMsg = is_array($errors[0] ?? null)
                ? ($errors[0]['message'] ?? $errors[0]['LongMessage'] ?? 'Failed to update eBay price')
                : (string) $errors[0];

            return response()->json([
                'success' => false,
                'message' => $firstMsg,
                'errors' => $errors,
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'ebay', 'error', $price);
            Log::error('CVR Master - eBay push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'eBay API error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push price to eBay 2 — same path as /push-ebay2-price (EbayTwoController::pushEbay2Price).
     */
    private function pushToEbay2($sku, $price)
    {
        try {
            $ebayMetric = Ebay2Metric::where('sku', $sku)->first();
            if (!$ebayMetric) {
                $ebayMetric = Ebay2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
            }
            if (!$ebayMetric) {
                $ebayMetric = Ebay2Metric::where('sku', 'LIKE', '%' . $sku . '%')->first();
            }

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->savePricePushStatus($sku, 'ebay2', 'error', $price);
                return response()->json([
                    'success' => false,
                    'message' => "eBay2 listing not found for SKU: $sku",
                ], 404);
            }

            $result = (new Ebay2ApiService())->reviseFixedPriceItem(
                itemId: $ebayMetric->item_id,
                price: $price
            );

            if (!empty($result['success'])) {
                $ebayMetric->ebay_price = $price;
                $ebayMetric->save();
                $this->savePricePushStatus($sku, 'ebay2', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($price, 2) . " pushed to eBay2 for SKU: $sku",
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($sku, 'ebay2', !empty($result['accountRestricted']) ? 'account_restricted' : 'error', $price);
            $errors = $result['errors'] ?? [];
            $message = $errors[0]['message'] ?? ($result['message'] ?? 'Failed to update price on eBay2');
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'ebay2', 'error', $price);
            Log::error('CVR Master - eBay2 push exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'eBay2 API error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Push price to eBay 3 — same path as /push-ebay3-price-tabulator.
     */
    private function pushToEbay3($sku, $price)
    {
        try {
            $ebayMetric = Ebay3Metric::where('sku', $sku)->first();
            if (!$ebayMetric) {
                $ebayMetric = Ebay3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
            }

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->savePricePushStatus($sku, 'ebay3', 'error', $price);
                return response()->json([
                    'success' => false,
                    'message' => "eBay3 listing not found for SKU: $sku",
                ], 404);
            }

            $result = (new EbayThreeApiService())->reviseFixedPriceItem(
                $ebayMetric->item_id,
                $price,
                null,
                $sku
            );

            if (!empty($result['success'])) {
                $this->savePricePushStatus($sku, 'ebay3', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($price, 2) . " pushed to eBay3 for SKU: $sku",
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($sku, 'ebay3', !empty($result['accountRestricted']) ? 'account_restricted' : 'error', $price);
            $errors = $result['errors'] ?? [['message' => 'Failed to update price']];
            $firstMsg = is_array($errors[0] ?? null)
                ? ($errors[0]['message'] ?? $errors[0]['LongMessage'] ?? 'Failed to update eBay3 price')
                : (string) ($errors[0] ?? 'Failed to update eBay3 price');
            return response()->json(['success' => false, 'message' => $firstMsg, 'errors' => $errors], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'ebay3', 'error', $price);
            Log::error('CVR Master - eBay3 push exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'eBay3 API error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Push price to Best Buy (Mirakl) via BestBuyApiService.
     */
    private function pushToBestBuy($sku, $price)
    {
        try {
            $result = app(BestBuyApiService::class)->updatePrice($sku, $price);
            if (!empty($result['success'])) {
                $this->savePricePushStatus($sku, 'bestbuy', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? ("Price $" . number_format($price, 2) . " pushed to BestBuy for SKU: $sku"),
                    'result' => $result,
                ]);
            }
            $this->savePricePushStatus($sku, 'bestbuy', 'error', $price);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to push price to BestBuy',
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'bestbuy', 'error', $price);
            Log::error('CVR Master - BestBuy push exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'BestBuy API error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Push price to Macy's (Mirakl) via MacysApiService.
     */
    private function pushToMacy($sku, $price)
    {
        try {
            $result = app(MacysApiService::class)->updatePrice($sku, $price);
            if (!empty($result['success'])) {
                $this->savePricePushStatus($sku, 'macy', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? ("Price $" . number_format($price, 2) . " pushed to Macy for SKU: $sku"),
                    'result' => $result,
                ]);
            }
            $this->savePricePushStatus($sku, 'macy', 'error', $price);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to push price to Macy',
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'macy', 'error', $price);
            Log::error('CVR Master - Macy push exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Macy API error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Push price to Reverb via ReverbApiService (uses reverb_products.reverb_listing_id when available).
     */
    private function pushToReverb($sku, $price)
    {
        try {
            $service = new ReverbApiService();
            $result = $service->updatePrice($sku, $price);

            if ($result['success']) {
                $this->savePricePushStatus($sku, 'reverb', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result
                ]);
            }

            $this->savePricePushStatus($sku, 'reverb', 'error', $price);
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'errors' => [['message' => $result['message']]]
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'reverb', 'error', $price);
            Log::error('CVR Master - Reverb push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Reverb error: ' . $e->getMessage(),
                'errors' => [['message' => $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to FBA (Amazon Fulfillment). Resolves base SKU to FBA seller SKU and calls SP API.
     */
    private function pushToFba($sku, $price)
    {
        $fbaSellerSku = null;
        try {
            $baseSku = strtoupper(trim($sku));
            $fbaTableRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                ->get()
                ->keyBy(fn($item) => strtoupper(trim(preg_replace('/\s*FBA\s*/i', '', (string)($item->seller_sku ?? '')))));
            $fbaRow = $fbaTableRows->get($baseSku);

            if (!$fbaRow || empty($fbaRow->seller_sku)) {
                return response()->json([
                    'success' => false,
                    'message' => "No FBA listing found for SKU: $sku"
                ], 400);
            }

            $fbaSellerSku = trim((string)$fbaRow->seller_sku);
            $service = new \App\Services\AmazonSpApiService();
            $result = $service->updateAmazonPriceUS($fbaSellerSku, $price);

            if (isset($result['errors']) && !empty($result['errors'])) {
                $this->saveFbaSpriceStatus($fbaSellerSku, 'error');
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to push price to FBA.',
                    'errors' => $result['errors']
                ], 400);
            }

            $this->saveFbaSpriceStatus($fbaSellerSku, 'pushed', $price);
            $this->savePricePushStatus($sku, 'amazon', 'pushed', $price);

            Log::info('CVR Master - FBA price push successful', [
                'sku' => $sku,
                'fba_seller_sku' => $fbaSellerSku,
                'price' => $price
            ]);

            return response()->json([
                'success' => true,
                'message' => "Price $" . number_format($price, 2) . " pushed to FBA for SKU: $sku",
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('CVR Master - FBA push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            if ($fbaSellerSku !== null) {
                $this->saveFbaSpriceStatus($fbaSellerSku, 'error');
            }
            return response()->json([
                'success' => false,
                'message' => 'FBA error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save FBA suggested price and push status to FbaManualData (keyed by FBA seller SKU).
     */
    private function saveFbaSpriceStatus($fbaSellerSku, $status, $pushedPrice = null)
    {
        try {
            $manual = FbaManualData::where('sku', strtoupper(trim($fbaSellerSku)))->first();
            if (!$manual) {
                $manual = new FbaManualData();
                $manual->sku = strtoupper(trim($fbaSellerSku));
                $manual->data = [];
            }
            $data = $manual->data ?? [];
            $data['SPRICE_STATUS'] = $status;
            $data['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            if ($pushedPrice !== null) {
                $data['s_price'] = $pushedPrice;
            }
            $manual->data = $data;
            $manual->save();
        } catch (\Exception $e) {
            Log::error('CVR Master - Failed to save FBA SPRICE status', [
                'sku' => $fbaSellerSku,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Save price push status to the appropriate data_view table
     */
    private function savePricePushStatus($sku, $marketplace, $status, $pushedPrice = null)
    {
        try {
            $dataView = null;
            
            if ($marketplace === 'amazon') {
                $dataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'doba') {
                $dataView = DobaDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'walmart') {
                $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebay' || $marketplace === 'ebay1') {
                $dataView = EbayDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebay2' || $marketplace === 'ebaytwo') {
                $dataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebay3' || $marketplace === 'ebaythree') {
                $dataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
                $dataView = BestbuyUSADataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
                $dataView = MacyDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'shopifyb2c' || $marketplace === 'sb2c' || $marketplace === 'shopify') {
                $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'shopifyb2b' || $marketplace === 'sb2b') {
                $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'reverb') {
                $dataView = ReverbViewData::firstOrNew(['sku' => $sku]);
            }
            
            if ($dataView) {
                // ReverbViewData uses 'values', others use 'value'
                if ($marketplace === 'reverb') {
                    $existing = is_array($dataView->values) ? $dataView->values : (json_decode($dataView->values ?? '{}', true) ?? []);
                } else {
                    $existing = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value ?? '{}', true) ?? []);
                }
                if (!is_array($existing)) $existing = [];
                
                // Save status
                $existing['SPRICE_STATUS'] = $status;
                $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
                
                // Save pushed by information
                if (auth()->check()) {
                    $existing['SPRICE_PUSHED_BY'] = auth()->user()->name ?? auth()->user()->email;
                    $existing['SPRICE_PUSHED_BY_ID'] = auth()->id();
                }
                $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
                
                // Save the pushed price
                if ($pushedPrice !== null) {
                    $existing['SPRICE_PUSHED_VALUE'] = $pushedPrice;
                }
                
                if ($marketplace === 'reverb') {
                    $dataView->values = $existing;
                } else {
                    $dataView->value = $existing;
                }
                $dataView->save();
            }
            
            Log::info('CVR Master - Price push status saved', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'status' => $status,
                'pushed_by' => auth()->check() ? auth()->user()->name : 'Unknown'
            ]);
        } catch (\Exception $e) {
            Log::error('CVR Master - Failed to save price push status', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk change price for selected SKUs across all marketplaces.
     * - Doba & Shopify Wholesale: 25% discount (price * 0.75)
     * - Shopify B2B: 25% discount + shipping deducted (price * 0.75 - ship)
     * - Others (Amazon, Walmart, Shopify B2C): Full price
     */
    public function bulkChangePrice(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'price' => 'required|numeric|min:0.01|max:999999.99',
            'skus' => 'required|array',
            'skus.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first()
            ], 400);
        }

        $basePrice = round(floatval($request->input('price')), 2);
        $skus = array_map(fn($s) => strtoupper(trim($s)), $request->input('skus', []));
        $skus = array_values(array_filter(array_unique($skus)));

        if (empty($skus)) {
            return response()->json(['success' => false, 'message' => 'No valid SKUs provided'], 400);
        }

        $pushableMarketplaces = ['amazon', 'doba', 'walmart', 'sb2c', 'shopify', 'sb2b', 'reverb'];
        $updated = 0;
        $errors = [];

        foreach ($skus as $sku) {
            $productMaster = ProductMaster::where('sku', $sku)->first();
            $values = $productMaster && $productMaster->Values
                ? (is_array($productMaster->Values) ? $productMaster->Values : json_decode($productMaster->Values ?? '{}', true) ?? [])
                : [];
            $ship = floatval($values['ship'] ?? $productMaster->ship ?? 0);

            $dobaPrice = round($basePrice * 0.75, 2);
            $sb2bPrice = max(0.01, round($basePrice * 0.75 - $ship, 2));

            foreach ($pushableMarketplaces as $mp) {
                $price = match ($mp) {
                    'doba' => $dobaPrice,
                    'sb2b' => $sb2bPrice,
                    default => $basePrice
                };

                try {
                    // Keep suggested SPRICE in sync with bulk pricing rules,
                    // so Doba/Shopify wholesale shows 25% reduced price in UI.
                    if (in_array($mp, ['doba', 'sb2b'])) {
                        $this->saveSpriceToView($sku, $mp, $price);
                    }

                    $req = Request::create('/cvr-master-push-price', 'POST', [
                        'sku' => $sku,
                        'price' => $price,
                        'marketplace' => $mp,
                        '_token' => $request->input('_token')
                    ]);
                    $req->setUserResolver($request->getUserResolver());
                    $response = $this->pushPriceToAmazon($req);
                    $data = json_decode($response->getContent(), true);
                    if ($data['success'] ?? false) {
                        $this->saveSpriceToView($sku, $mp, $price);
                        $updated++;
                    } else {
                        $errors[] = "{$sku}/{$mp}: " . ($data['message'] ?? 'Failed');
                    }
                } catch (\Exception $e) {
                    $errors[] = "{$sku}/{$mp}: " . $e->getMessage();
                    Log::error('Bulk change price error', ['sku' => $sku, 'mp' => $mp, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'errors' => array_slice($errors, 0, 10),
            'message' => "Price \${$basePrice} applied. " . count($skus) . " SKU(s) processed across marketplaces."
        ]);
    }

    private function saveSpriceToView($sku, $marketplace, $sprice)
    {
        $dataView = null;
        if ($marketplace === 'amazon') {
            $dataView = AmazonDataView::firstOrNew(['sku' => $sku]);
        } elseif ($marketplace === 'doba') {
            $dataView = DobaDataView::firstOrNew(['sku' => $sku]);
        } elseif ($marketplace === 'walmart') {
            $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
        } elseif ($marketplace === 'sb2c' || $marketplace === 'shopifyb2c' || $marketplace === 'shopify') {
            $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
        } elseif ($marketplace === 'sb2b' || $marketplace === 'shopifyb2b') {
            $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
        }

        if ($dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value ?? '{}', true) ?? []);
            if (!is_array($value)) $value = [];
            if ($marketplace === 'walmart') {
                $value['sprice'] = $sprice;
            } else {
                $value['SPRICE'] = $sprice;
            }
            $dataView->value = $value;
            $dataView->save();
        }
    }

    /**
     * Same as TemuController::buildTemuDecreaseDataResponse: normalize for Temu / Temu 2 SKU + order line matching
     */
    private static function normalizeTemuSkuForCvr(string $sku): string
    {
        $sku = str_replace("\xc2\xa0", ' ', (string) $sku);
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
        $sku = preg_replace('/\s+/', ' ', $sku);
        return $sku;
    }

    /**
     * Temu LMP entries from a temu_lmp row — same logic as TemuController::extractTemuLmpEntries.
     *
     * @return list<array{price: mixed, link: mixed}>
     */
    private function extractTemuLmpEntriesFromRow(?TemuLmp $temuLmpRow): array
    {
        if (!$temuLmpRow) {
            return [];
        }

        $entries = $temuLmpRow->lmp_entries;
        if (is_array($entries) && count($entries) > 0) {
            return $entries;
        }

        $lmpEntries = [];
        if ($temuLmpRow->lmp !== null || $temuLmpRow->lmp_link) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp, 'link' => $temuLmpRow->lmp_link];
        }
        if ($temuLmpRow->lmp_2 !== null || $temuLmpRow->lmp_link_2) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp_2, 'link' => $temuLmpRow->lmp_link_2];
        }

        return $lmpEntries;
    }

    /**
     * @param  list<array{price: mixed, link: mixed}>  $entries
     * @return list<array{price: mixed, link: mixed}>
     */
    private function dedupeTemuLmpEntriesList(array $entries): array
    {
        $seen = [];
        $out = [];
        foreach ($entries as $entry) {
            $price = $entry['price'] ?? null;
            $link = strtoupper(trim((string) ($entry['link'] ?? '')));
            $key = (string) $price . '|' . $link;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }
        usort($out, function ($a, $b) {
            $pa = ($a['price'] ?? null) !== null && $a['price'] !== '' ? (float) $a['price'] : PHP_FLOAT_MAX;
            $pb = ($b['price'] ?? null) !== null && $b['price'] !== '' ? (float) $b['price'] : PHP_FLOAT_MAX;
            return $pa <=> $pb;
        });
        return $out;
    }

    /**
     * Resolve Temu/Temu2 LMP for a SKU from temu_lmp (same source as /temu-decrease).
     *
     * @param  array<string, TemuLmp>  $temuLmpByNormalizedSku
     * @return array{price: float|null, link: string|null, count: int, entries: list<array{price: mixed, link: mixed}>}
     */
    private function resolveTemuLmpForSku(
        string $sku,
        array $temuLmpByNormalizedSku,
        ?LmpSkuGroupService $lmpSkuGroupService = null
    ): array {
        $empty = ['price' => null, 'link' => null, 'count' => 0, 'entries' => []];
        $sku = trim($sku);
        if ($sku === '' || $temuLmpByNormalizedSku === []) {
            return $empty;
        }

        $linkedSkus = [$sku];
        if ($lmpSkuGroupService) {
            try {
                $group = $lmpSkuGroupService->groupContaining($sku);
                if (is_array($group) && $group !== []) {
                    $linkedSkus = $group;
                }
            } catch (\Throwable $e) {
                // fall back to the single SKU
            }
        }

        $lmpEntries = [];
        foreach ($linkedSkus as $linkedSku) {
            $row = $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) $linkedSku)] ?? null;
            if ($row) {
                $lmpEntries = array_merge($lmpEntries, $this->extractTemuLmpEntriesFromRow($row));
            }
        }
        $lmpEntries = $this->dedupeTemuLmpEntriesList($lmpEntries);

        // L1 = lowest non-ignored entry (same as /temu-decrease)
        $prices = [];
        $l1Link = null;
        foreach ($lmpEntries as $entry) {
            if (!empty($entry['ignored'])) {
                continue;
            }
            $p = $entry['price'] ?? null;
            if ($p !== null && $p !== '' && floatval($p) > 0) {
                $prices[] = floatval($p);
                if ($l1Link === null) {
                    $l1Link = $entry['link'] ?? null;
                }
            }
        }
        if (count($prices) > 0) {
            $minPrice = min($prices);
            foreach ($lmpEntries as $entry) {
                if (!empty($entry['ignored'])) {
                    continue;
                }
                $p = $entry['price'] ?? null;
                if ($p !== null && $p !== '' && floatval($p) === (float) $minPrice) {
                    $l1Link = $entry['link'] ?? null;
                    break;
                }
            }
        }

        $ownRow = $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr($sku)] ?? null;
        $price = count($prices) > 0
            ? round(min($prices), 2)
            : ((empty($lmpEntries) && $ownRow && $ownRow->lmp !== null && floatval($ownRow->lmp) > 0) ? round(floatval($ownRow->lmp), 2) : null);
        $link = $l1Link ?? ($ownRow->lmp_link ?? null);

        return [
            'price' => $price,
            'link' => $link ? (string) $link : null,
            'count' => count($lmpEntries),
            'entries' => $lmpEntries,
        ];
    }

    /**
     * Temu LMP competitors for Master Analytics LMP drawer (from temu_lmp /temu-decrease).
     */
    public function getTemuLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->query('sku', ''));
            if ($sku === '') {
                return response()->json(['success' => false, 'error' => 'SKU required'], 400);
            }

            $temuLmpByNormalizedSku = [];
            if (Schema::hasTable('temu_lmp')) {
                foreach (TemuLmp::all() as $temuLmpRow) {
                    $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
                }
            }

            $groupService = null;
            try {
                $groupService = app(LmpSkuGroupService::class);
                $groupService->prepareForSkus([$sku]);
            } catch (\Throwable $e) {
                $groupService = null;
            }

            $resolved = $this->resolveTemuLmpForSku($sku, $temuLmpByNormalizedSku, $groupService);
            $competitors = [];
            foreach ($resolved['entries'] as $idx => $entry) {
                $price = isset($entry['price']) && $entry['price'] !== '' ? floatval($entry['price']) : 0;
                if ($price <= 0) {
                    continue;
                }
                $competitors[] = [
                    'id' => 'temu-' . ($idx + 1),
                    'price' => round($price, 2),
                    'product_link' => $entry['link'] ?? null,
                    'link' => $entry['link'] ?? null,
                    'product_title' => '',
                    'image' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'lmp' => $resolved['price'],
                'competitors' => $competitors,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu LMP data: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to fetch Temu LMP'], 500);
        }
    }

    /**
     * L30 from temu2_daily_data, rolled up to Product Master SKUs (same as Temu 2 decrease / temu2-decrease-data).
     *
     * @param  string[]  $productSkus
     * @return array<string, int> product sku => quantity L30
     */
    private function buildTemu2L30ByProductSkusMap(array $productSkus, bool $tablesOk): array
    {
        $out = array_fill_keys($productSkus, 0);
        if (!$tablesOk) {
            return $out;
        }
        if (!Schema::hasTable('temu2_daily_data')) {
            return $out;
        }
        $l30ByNormalizedPm = [];
        foreach ($productSkus as $s) {
            $l30ByNormalizedPm[self::normalizeTemuSkuForCvr($s)] = 0;
        }
        $noSpaceToNormalized = [];
        foreach (array_keys($l30ByNormalizedPm) as $nk) {
            $ns = str_replace(' ', '', $nk);
            if ($ns !== '') {
                $noSpaceToNormalized[$ns] = $nk;
            }
        }
        try {
            $groups = Temu2DailyData::query()
                ->selectRaw('contribution_sku, SUM(quantity_purchased) as t')
                ->whereNotNull('contribution_sku')
                ->where('contribution_sku', '!=', '')
                ->groupBy('contribution_sku')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('buildTemu2L30ByProductSkusMap: ' . $e->getMessage());
            return $out;
        }
        foreach ($groups as $row) {
            $raw = trim((string) ($row->contribution_sku ?? ''));
            if ($raw === '') {
                continue;
            }
            $n = self::normalizeTemuSkuForCvr($raw);
            $qty = (int) ($row->t ?? 0);
            if (isset($l30ByNormalizedPm[$n])) {
                $l30ByNormalizedPm[$n] += $qty;
            } else {
                $nNoSpace = str_replace(' ', '', $n);
                if (isset($noSpaceToNormalized[$nNoSpace])) {
                    $pk = $noSpaceToNormalized[$nNoSpace];
                    $l30ByNormalizedPm[$pk] += $qty;
                }
            }
        }
        foreach ($productSkus as $s) {
            $k = self::normalizeTemuSkuForCvr($s);
            $out[$s] = (int) ($l30ByNormalizedPm[$k] ?? 0);
        }
        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection|array  $temu2PricingAll
     * @param  string[]  $productSkus
     * @return array<string, \App\Models\Temu2Pricing|null>
     */
    private function buildTemu2PricingMapForProductSkus($temu2PricingAll, array $productSkus): array
    {
        return $this->buildTemuPricingMapForProductSkus($temu2PricingAll, $productSkus);
    }

    /**
     * Map Temu/Temu2 pricing rows onto ProductMaster SKUs (normalized + no-space), same as /temu-decrease.
     *
     * @param  \Illuminate\Support\Collection|array  $pricingAll
     * @param  string[]  $productSkus
     * @return array<string, mixed|null>
     */
    private function buildTemuPricingMapForProductSkus($pricingAll, array $productSkus): array
    {
        $out = array_fill_keys($productSkus, null);
        $byNorm = [];
        $byNoSpace = [];
        foreach ($pricingAll as $p) {
            $n = self::normalizeTemuSkuForCvr((string) ($p->sku ?? ''));
            if ($n === '') {
                continue;
            }
            if (!isset($byNorm[$n])) {
                $byNorm[$n] = $p;
            }
            $byNoSpace[str_replace(' ', '', $n)] = $p;
        }
        foreach ($productSkus as $s) {
            $ns = self::normalizeTemuSkuForCvr($s);
            if (isset($byNorm[$ns])) {
                $out[$s] = $byNorm[$ns];
            } else {
                $k = str_replace(' ', '', $ns);
                if (isset($byNoSpace[$k])) {
                    $out[$s] = $byNoSpace[$k];
                }
            }
        }
        return $out;
    }

    /**
     * Lookup pre-aggregated Temu product_clicks by goods_id (raw + normalized keys).
     *
     * @param  array<string, int>  $viewByGoodsId
     */
    private function lookupTemuViewsByGoodsId($goodsId, array $viewByGoodsId): ?int
    {
        if ($goodsId === null || $goodsId === '') {
            return null;
        }
        $raw = (string) $goodsId;
        if (array_key_exists($raw, $viewByGoodsId)) {
            return (int) $viewByGoodsId[$raw];
        }
        $nk = TemuGoodsIdHelper::normalizeKey($goodsId);
        if ($nk !== null && $nk !== '' && array_key_exists($nk, $viewByGoodsId)) {
            return (int) $viewByGoodsId[$nk];
        }
        // goods_id present but no view rows → 0 (same as /temu-decrease)
        return 0;
    }

    /**
     * Live SUM(product_clicks) for a goods_id — same metric as /temu-decrease Views.
     * Returns null when goods_id is missing (N/A), otherwise the click total (may be 0).
     */
    private function resolveTemuProductClicks($goodsId, bool $isTemu2 = false): ?int
    {
        if ($goodsId === null || $goodsId === '') {
            return null;
        }
        $tableOk = $isTemu2
            ? Schema::hasTable('temu2_view_data')
            : Schema::hasTable('temu_view_data');
        if (!$tableOk) {
            return null;
        }
        $model = $isTemu2 ? Temu2ViewData::class : TemuViewData::class;
        $nk = TemuGoodsIdHelper::normalizeKey($goodsId);
        $sum = (int) $model::where('goods_id', $goodsId)->sum('product_clicks');
        if ($sum === 0 && $nk && (string) $goodsId !== $nk) {
            $sum = (int) $model::where('goods_id', $nk)->sum('product_clicks');
        }
        return $sum;
    }

    /**
     * Display the Sold Master view
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function soldMasterView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        return view("market-places.sold_master_view", [
            "mode" => $mode,
            "demo" => $demo,
        ]);
    }
}
