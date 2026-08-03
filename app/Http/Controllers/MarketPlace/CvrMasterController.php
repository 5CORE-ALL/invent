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
use App\Models\TopDawgProduct;
use App\Models\TopDawgDataView;
use App\Models\TopDawgOrderMetric;
use App\Models\AmazonDatasheet;
use App\Models\MacyProduct;
use App\Models\MacysPriceData;
use App\Models\TemuMetric;
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
use App\Models\AmzCvrAuditHistory;
use App\Models\TemuDataView;
use App\Models\Temu2Pricing;
use App\Models\Temu2Metric;
use App\Models\Temu2DailyData;
use App\Models\Temu2DataView;
use App\Models\DobaDataView;
use App\Models\TiktokShopDataView;
use App\Models\TiktokCampaignReport;
use App\Models\TiktokSkuCompetitor;
use App\Models\BestbuyUSADataView;
use App\Models\MacyDataView;
use App\Models\ReverbViewData;
use App\Models\Shopifyb2cDataView;
use App\Models\ShopifyB2BDataView;
use App\Models\TiendamiaProduct;
use App\Models\TiendamiaPriceUpload;
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
use App\Models\Temu2ViewData;
use App\Models\TemuViewData;
use App\Models\TemuCampaignReport;
use App\Models\Temu2CampaignReport;
use App\Models\TemuLmp;
use App\Models\MarketplacePercentage;
use App\Services\LmpSkuGroupService;
use App\Services\TemuShopifySalesService;
use App\Models\MarketplaceDailyMetric;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSkuCompetitor;
use App\Models\GoogleSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Models\BestbuySkuCompetitor;
use App\Models\MacySkuCompetitor;
use App\Models\ReverbSkuCompetitor;
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
use App\Services\TopDawgApiService;
use App\Services\TemuApiService;
use App\Services\Temu2ApiService;
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
     * Display Price Increase view (same logic / datatable as pricing-master-cvr)
     */
    public function priceIncreaseView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        return view("market-places.price_increase_view", [
            "mode" => $mode,
            "demo" => $demo,
        ]);
    }

    /**
     * Display WMPNM Dil view (copy of price-increase; same datatable /cvr-master-data-json)
     */
    public function wmpnmDilView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        return view("market-places.wmpnm_dil_view", [
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
            // Fetch product master (only columns needed for CVR table — avoid hydrating unused wide columns)
            $productMasterRows = ProductMaster::query()
                ->select(['id', 'sku', 'parent', 'Values'])
                ->get();

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
            $amazonDatasheets = AmazonDatasheet::whereIn("sku", $skus)
                ->select(['sku', 'asin', 'price', 'units_ordered_l30', 'sessions_l30'])
                ->get()
                ->keyBy("sku");
            
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

            // Amazon SPRICE / STANDARD_PRICE from amazon_data_view (same store as /amazon-tabulator-view)
            $amazonDataViewRows = AmazonDataView::whereIn('sku', $skus)->get();
            $amazonDataViewBySku = $amazonDataViewRows->keyBy('sku');
            $amazonDataViewBySkuUpper = $amazonDataViewRows->keyBy(fn ($r) => strtoupper(trim((string) $r->sku)));

            // Temu SPRICE from temu_data_view (same store as /temu-decrease)
            $temuDataViewRows = Schema::hasTable('temu_data_view')
                ? TemuDataView::whereIn('sku', $skus)->get()
                : collect();
            $temuDataViewBySku = $temuDataViewRows->keyBy('sku');
            $temuDataViewBySkuUpper = $temuDataViewRows->keyBy(fn ($r) => strtoupper(trim((string) $r->sku)));

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

            // TikTok — same sources/formulas as /tiktok-pricing
            // Margin: TiktokShop first, fallback TikTok (legacy); default 80%
            $tiktokMarketplace = MarketplacePercentage::where('marketplace', 'TiktokShop')
                ->orWhere('marketplace', 'TikTok')
                ->first();
            $tiktokPercentage = $tiktokMarketplace ? ($tiktokMarketplace->percentage / 100) : 0.80;

            $skusUpperTt = array_values(array_unique(array_map(
                static fn ($s) => strtoupper((string) $s),
                $skus
            )));

            // Products keyed UPPER(sku)
            $tiktokProducts = TikTokProduct::whereIn('sku', $skusUpperTt)
                ->get()
                ->keyBy(fn ($item) => strtoupper((string) $item->sku));

            // L30 from tiktok_orders (last 30 California calendar days) — same as /tiktok-pricing
            $tiktokL30BySku = [];
            try {
                $tiktokL30BySku = \App\Models\TiktokOrder::soldQtyL30($skusUpperTt, 30);
            } catch (\Throwable $e) {
                Log::warning('CVR Master TikTok L30 (tiktok_orders): ' . $e->getMessage());
            }

            // Campaign spend L30+L7 by campaign_name (= SKU) — same as /tiktok-pricing TACOS%
            $tiktokSpendBySku = [];
            try {
                if (Schema::hasTable('tiktok_campaign_reports')) {
                    foreach (['L30', 'L7'] as $ttRange) {
                        TiktokCampaignReport::where('report_range', $ttRange)
                            ->where('creative_type', 'Product card')
                            ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                            ->whereNotNull('product_id')->where('product_id', '!=', '')
                            ->selectRaw('UPPER(TRIM(campaign_name)) as sku_upper, SUM(cost) as total_cost')
                            ->groupBy(DB::raw('UPPER(TRIM(campaign_name))'))
                            ->get()
                            ->each(function ($row) use (&$tiktokSpendBySku) {
                                $k = (string) ($row->sku_upper ?? '');
                                if ($k === '') {
                                    return;
                                }
                                $tiktokSpendBySku[$k] = ($tiktokSpendBySku[$k] ?? 0) + (float) ($row->total_cost ?? 0);
                            });
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CVR Master TikTok campaign spend: ' . $e->getMessage());
            }

            // Shop data view overrides for views / may hold SPRICE (keyed normalized UPPER)
            $tiktokShopByNormSku = [];
            try {
                if (Schema::hasTable('tiktok_shop_data_views') && $skusUpperTt !== []) {
                    TiktokShopDataView::query()
                        ->whereIn(DB::raw('UPPER(TRIM(sku))'), $skusUpperTt)
                        ->get()
                        ->each(function ($row) use (&$tiktokShopByNormSku) {
                            $k = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $row->sku)));
                            if ($k !== '') {
                                $tiktokShopByNormSku[$k] = $row;
                            }
                        });
                }
            } catch (\Throwable $e) {
                Log::warning('CVR Master TikTok shop data views: ' . $e->getMessage());
            }

            Log::info('CVR Master - TikTok Data fetched', [
                'tiktok_products' => $tiktokProducts->count(),
                'tiktok_l30_skus' => count(array_filter($tiktokL30BySku, fn ($q) => (int) $q > 0)),
                'tiktok_spend_skus' => count($tiktokSpendBySku),
                'tiktok_percentage' => $tiktokPercentage * 100 . '%'
            ]);

            // Get BestBuy percentage from MarketplacePercentage (default 80%)
            $bestbuyMarketplace = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
            $bestbuyPercentage = $bestbuyMarketplace ? ($bestbuyMarketplace->percentage / 100) : 0.80;
            
            // Fetch BestBuy product + uploaded sheet (same as /bestbuy-pricing)
            // Sheet SKUs are stored UPPERCASE — key by upper so mixed-case PM SKUs match.
            $bestbuyProducts = BestbuyUsaProduct::whereIn('sku', $skus)->get()->keyBy('sku');
            $bestbuyPriceData = BestbuyPriceData::whereIn('sku', array_values(array_unique(array_merge(
                    $skus,
                    array_map('strtoupper', $skus)
                ))))
                ->get()
                ->keyBy(fn ($item) => strtoupper((string) $item->sku));

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

            // Same price source as /macys-pricing: macys_price_data sheet first, else macy_products.price
            $normalizeMacySku = static fn ($s) => strtoupper(trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $s))));
            $macyProducts = MacyProduct::whereIn('sku', $skus)->get()
                ->keyBy(fn ($m) => $normalizeMacySku($m->sku));
            $macyPriceSheet = MacysPriceData::whereIn('sku', array_values(array_unique(array_merge(
                    $skus,
                    array_map('strtoupper', $skus)
                ))))
                ->get()
                ->keyBy(fn ($m) => $normalizeMacySku($m->sku));

            Log::info('CVR Master - Macy Data fetched', [
                'macy_products' => $macyProducts->count(),
                'macy_price_sheet' => $macyPriceSheet->count(),
                'macy_percentage' => $macyPercentage * 100 . '%'
            ]);

            // Get Reverb percentage from MarketplacePercentage (default 85%) — same as /reverb-pricing
            $reverbMarketplace = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $reverbPercentage = $reverbMarketplace ? ((float) $reverbMarketplace->percentage / 100) : 0.85;

            // Channel Ads% (Bump ÷ L30 Sales) — same source as /reverb-pricing PFT% = GPFT% − Ads%
            $reverbChannelAdsPct = (float) (
                ChannelMasterCalculatedData::where('channel', 'Reverb')->value('ads_percentage')
                ?? ChannelMasterCalculatedData::where('channel', 'like', 'Reverb%')->value('ads_percentage')
                ?? 0
            );

            // Same normalized SKU lookup as /reverb-pricing (spacing / case / PCS drift)
            $reverbProductsByNorm = ReverbProduct::buildLookupByNormalizedSku($skus);

            Log::info('CVR Master - Reverb Data fetched', [
                'reverb_products' => count($reverbProductsByNorm),
                'reverb_percentage' => $reverbPercentage * 100 . '%'
            ]);

            // Get Doba percentage from MarketplacePercentage (same as /doba-tabulator; default 95%)
            $dobaMarketplace = MarketplacePercentage::where('marketplace', 'Doba')->first();
            $dobaPercentage = $dobaMarketplace ? ((float) $dobaMarketplace->percentage / 100) : 0.95;

            // Fetch Doba product data — key by normalized SKU like DobaController
            $normalizeDobaSku = static fn ($s) => strtoupper(trim((string) $s));
            $dobaMetrics = DobaMetric::whereIn('sku', $skus)->get()
                ->keyBy(fn ($m) => $normalizeDobaSku($m->sku));
            $missingDobaSkus = collect($skus)
                ->map($normalizeDobaSku)
                ->unique()
                ->reject(fn ($s) => $dobaMetrics->has($s))
                ->values();
            if ($missingDobaSkus->isNotEmpty()) {
                DobaMetric::query()
                    ->where(function ($q) use ($missingDobaSkus) {
                        foreach ($missingDobaSkus as $s) {
                            $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$s]);
                        }
                    })
                    ->get()
                    ->each(function ($m) use ($dobaMetrics, $normalizeDobaSku) {
                        $dobaMetrics->put($normalizeDobaSku($m->sku), $m);
                    });
            }

            Log::info('CVR Master - Doba Data fetched', [
                'doba_metrics' => $dobaMetrics->count(),
                'doba_percentage' => $dobaPercentage * 100 . '%'
            ]);

            // Fetch Temu data for GPFT/AD/PFT calculations
            // Pricing map onto ProductMaster SKUs via normalized SKU
            $temuPricingsAll = collect();
            if (Schema::hasTable('temu_metrics')) {
                $temuMetricCols = ['sku', 'base_price', 'goods_id', 'quantity'];
                if (Schema::hasColumn('temu_metrics', 'product_clicks_l30')) {
                    $temuMetricCols[] = 'product_clicks_l30';
                }
                if (Schema::hasColumn('temu_metrics', 'sku_id')) {
                    $temuMetricCols[] = 'sku_id';
                }
                $temuPricingsAll = TemuMetric::query()->get($temuMetricCols);
            }
            $temuPricingByProductSku = $this->buildTemuPricingMapForProductSkus($temuPricingsAll, $skus);
            $temuPricings = collect($temuPricingByProductSku)->filter(); // keyed by PM sku

            // L30 qty — same source as /temu-tabulator (/temu/daily-data → temu_orders)
            $temuL30ByProductSku = array_fill_keys($skus, 0);
            try {
                [$temuL30Start, $temuL30End] = TemuShopifySalesService::channelMasterL30Window();
                $temuOrderRows = TemuShopifySalesService::getOrdersTableRows($temuL30Start, $temuL30End);
                $temuNormToPmSku = [];
                $temuNoSpaceToPmSku = [];
                foreach ($skus as $pmSku) {
                    $n = self::normalizeTemuSkuForCvr((string) $pmSku);
                    $temuNormToPmSku[$n] = $pmSku;
                    $ns = str_replace(' ', '', $n);
                    if ($ns !== '') {
                        $temuNoSpaceToPmSku[$ns] = $pmSku;
                    }
                }
                foreach ($temuOrderRows as $orderRow) {
                    $raw = trim((string) ($orderRow['contribution_sku'] ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    $n = self::normalizeTemuSkuForCvr($raw);
                    $pmKey = $temuNormToPmSku[$n] ?? null;
                    if ($pmKey === null) {
                        $ns = str_replace(' ', '', $n);
                        $pmKey = $temuNoSpaceToPmSku[$ns] ?? null;
                    }
                    if ($pmKey === null) {
                        continue;
                    }
                    $temuL30ByProductSku[$pmKey] += (int) ($orderRow['quantity_purchased'] ?? 0);
                }
            } catch (\Throwable $e) {
                Log::warning('CVR Master Temu L30 (temu_orders / temu-tabulator): ' . $e->getMessage());
            }

            // Margin — marketplace_percentages Temu (temu-decrease / temu-tabulator)
            $temuPercentage = TemuShopifySalesService::temuMarginDecimal();

            // Temu views by goods_id — Seller Center sheet temu_view_data.product_clicks
            // (same as /temu-decrease). Fallback: Ads API temu_metrics.product_clicks_l30.
            $temuViewByGoodsId = [];
            if (Schema::hasTable('temu_view_data')) {
                TemuViewData::selectRaw('goods_id, SUM(product_clicks) as product_clicks')
                    ->whereNotNull('goods_id')
                    ->where('goods_id', '!=', '')
                    ->groupBy('goods_id')
                    ->get()
                    ->each(function ($row) use (&$temuViewByGoodsId) {
                        $key = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                        $clicks = (int) ($row->product_clicks ?? 0);
                        if ($key !== null && $key !== '') {
                            $temuViewByGoodsId[$key] = $clicks;
                        }
                        $rawGid = (string) ($row->goods_id ?? '');
                        if ($rawGid !== '') {
                            $temuViewByGoodsId[$rawGid] = $clicks;
                        }
                    });
            }
            if (Schema::hasTable('temu_metrics') && Schema::hasColumn('temu_metrics', 'product_clicks_l30')) {
                TemuMetric::query()
                    ->select('goods_id', 'product_clicks_l30')
                    ->whereNotNull('goods_id')
                    ->where('goods_id', '!=', '')
                    ->get()
                    ->each(function ($row) use (&$temuViewByGoodsId) {
                        $key = TemuGoodsIdHelper::normalizeKey($row->goods_id);
                        if ($key === '' || $key === null) {
                            return;
                        }
                        // Sheet wins when present; only fill missing goods from Ads API
                        if (isset($temuViewByGoodsId[$key])) {
                            return;
                        }
                        $clicks = (int) ($row->product_clicks_l30 ?? 0);
                        $temuViewByGoodsId[$key] = $clicks;
                        $rawGid = (string) ($row->goods_id ?? '');
                        if ($rawGid !== '' && ! isset($temuViewByGoodsId[$rawGid])) {
                            $temuViewByGoodsId[$rawGid] = $clicks;
                        }
                    });
            }

            // Temu Ads spend — ads sheet → temu_campaign_reports (same as /temu-decrease)
            [$temuSpendByGoodsId, $temuSpendBySku, $temuSpendBySkuLoose] = $this->loadTemuCampaignSpendIndexes('L30');
            // Aggregate Ads% (~2.6%) applied to every Temu row — same as /temu-decrease badgeAvgAds
            $temuAggregateAdsPercent = $this->resolveTemuAggregateAdsPercent('L30');

            Log::info('CVR Master - Temu Data fetched', [
                'temu_pricings' => $temuPricings->count(),
                'temu_view_goods_ids' => count($temuViewByGoodsId),
                'temu_campaign_spend_goods' => count($temuSpendByGoodsId),
                'temu_aggregate_ads_percent' => $temuAggregateAdsPercent,
                'temu_percentage' => $temuPercentage * 100 . '%'
            ]);

            $temu2L30ByProductSku = array_fill_keys($skus, 0);
            $temu2PricingByProductSku = array_fill_keys($skus, null);
            $temu2ViewByGoodsId = [];
            $temu2SpendByGoodsId = [];
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
            // L30 Ads spend from /temu2/ads upload (temu2_campaign_reports)
            if (Schema::hasTable('temu2_campaign_reports')) {
                try {
                    foreach (
                        Temu2CampaignReport::where('report_range', 'L30')
                            ->selectRaw('goods_id, COALESCE(SUM(spend), 0) as spend')
                            ->groupBy('goods_id')
                            ->get() as $r
                    ) {
                        $spend = round((float) ($r->spend ?? 0), 2);
                        $rawGid = (string) ($r->goods_id ?? '');
                        if ($rawGid !== '') {
                            $temu2SpendByGoodsId[$rawGid] = $spend;
                        }
                        $nk = TemuGoodsIdHelper::normalizeKey($r->goods_id);
                        if ($nk) {
                            $temu2SpendByGoodsId[$nk] = $spend;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('CVR Master - Temu 2 campaign spend load skipped: ' . $e->getMessage());
                }
            }
            if (Schema::hasTable('temu2_pricing') && Schema::hasTable('temu2_daily_data')) {
                try {
                    $temu2L30ByProductSku = $this->buildTemu2L30ByProductSkusMap($skus, true);
                    $temu2PricingsAll = Temu2Metric::query()->get(['sku', 'base_price', 'goods_id']);
                    $temu2PricingByProductSku = $this->buildTemu2PricingMapForProductSkus($temu2PricingsAll, $skus);
                    Log::info('CVR Master - Temu 2 Data fetched', [
                        'temu2_pricing_rows'   => $temu2PricingsAll->count(),
                        'temu2_skus_w_l30'     => count(array_filter($temu2L30ByProductSku, fn ($q) => (int) $q > 0)),
                        'temu2_spend_goods'    => count($temu2SpendByGoodsId),
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

            // eBay 2 / eBay 3 use fixed 85% take-home — same as their tabulator views
            $ebay2Percentage = 0.85;
            $ebay3Percentage = 0.85;

            // Channel Ads% — same sources as /ebay-tabulator-view, /ebay2-tabulator-view, /ebay3-tabulator-view
            // (PFT% = GPFT% − Ads% on every row)
            $channelMasterCtrl = app(ChannelMasterController::class);
            $ebay1ChannelAdsPct = (float) $channelMasterCtrl->getEbayMasterAdsPercent();
            $ebay2ChannelAdsPct = (float) $channelMasterCtrl->getEbaytwoMasterAdsPercent();
            $ebay3ChannelAdsPct = method_exists($channelMasterCtrl, 'getEbaythreeMasterAdsPercent')
                ? (float) $channelMasterCtrl->getEbaythreeMasterAdsPercent()
                : 0.0;
            
            Log::info('CVR Master - eBay Data fetched', [
                'ebay1_metrics' => $ebayMetrics->count(),
                'ebay2_metrics' => $ebay2Metrics->count(),
                'ebay3_metrics' => $ebay3Metrics->count()
            ]);

            // Shein — same sources/formulas as /shein-pricing (API, not sheet)
            // Price = special_offer_price only; LP/Ship from product_master; L30 from shein_daily_data
            // (exclude refund/return/cancel/closed/exchange); margin from marketplace_percentages; Ads% = 0
            $sheinMarketplace = MarketplacePercentage::where('marketplace', 'Shein')->first();
            $sheinPercentage = $sheinMarketplace ? ((float) $sheinMarketplace->percentage / 100) : 1.00;
            $sheinPricingsByNorm = [];
            $sheinL30ByNorm = [];
            try {
                if (Schema::hasTable('shein_pricing_prices')) {
                    foreach (\App\Models\SheinPricingPrice::all(['sku', 'price', 'special_offer_price', 'original_price', 'shein_stock']) as $row) {
                        $nk = $this->normalizeSheinSkuForCvr((string) ($row->sku ?? ''));
                        if ($nk !== '') {
                            $sheinPricingsByNorm[$nk] = $row;
                        }
                    }
                }
                if (Schema::hasTable('shein_daily_data')) {
                    $sheinL30ByNorm = $this->fetchSheinL30ByNormalizedSku();
                }
                Log::info('CVR Master - Shein Data fetched', [
                    'shein_pricings' => count($sheinPricingsByNorm),
                    'shein_l30_skus' => count($sheinL30ByNorm),
                    'shein_percentage' => ($sheinPercentage * 100) . '%',
                ]);
            } catch (\Exception $e) {
                Log::warning('CVR Master - Shein data fetch skipped: ' . $e->getMessage());
            }

            // TopDawg — same sources/formulas as /topdawg-pricing
            // Price/stock from topdawg_products (API); L30 from topdawg_order_metrics;
            // Margin from marketplace_percentages (TopDawg); no ship; Ads% = 0
            $tdMarketplace = MarketplacePercentage::where('marketplace', 'TopDawg')->first();
            $tdPercentage = $tdMarketplace ? ((float) $tdMarketplace->percentage / 100) : 0.0;
            $tdProductsByNorm = [];
            $tdOrderL30ByNorm = [];
            try {
                if (Schema::hasTable('topdawg_products')) {
                    $tdProductsByNorm = TopDawgProduct::buildLookupByNormalizedSku($skus);
                }
                if (Schema::hasTable('topdawg_order_metrics')) {
                    $tdOrderL30ByNorm = $this->fetchTopDawgL30OrderAggregatesBySku();
                }
                Log::info('CVR Master - TopDawg Data fetched', [
                    'topdawg_products' => count($tdProductsByNorm),
                    'topdawg_percentage' => ($tdPercentage * 100) . '%',
                ]);
            } catch (\Exception $e) {
                Log::warning('CVR Master - TopDawg data fetch skipped: ' . $e->getMessage());
            }

            // Purchasing Power — same sources/formulas as /purchasing-power-pricing
            // Margin default 65%; GPFT/ROI exclude ship; Ads% = 0
            $ppMarketplace = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $ppPercentage = $ppMarketplace ? ($ppMarketplace->percentage / 100) : 0.65;

            $ppProducts = collect();
            $ppSalesQty = collect();
            $ppOfferSheetBySku = collect();
            try {
                $ppProducts = \App\Models\PurchasingPowerProduct::whereIn('sku', $skus)
                    ->get()
                    ->keyBy(fn ($i) => strtoupper((string) $i->sku));
                $ppSalesQty = \App\Models\PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
                    ->selectRaw('UPPER(offer_sku) as sku_upper, SUM(quantity) as total_qty')
                    ->groupBy('sku_upper')
                    ->pluck('total_qty', 'sku_upper');
                // Fallback price only (same as /purchasing-power-pricing) — not preferred over MCM
                $ppSkuUpper = array_values(array_unique(array_map(
                    static fn ($s) => strtoupper((string) $s),
                    $skus
                )));
                $ppOfferSheetBySku = MacysPriceData::query()
                    ->where(function ($q) use ($ppSkuUpper) {
                        $q->whereIn(DB::raw('UPPER(sku)'), $ppSkuUpper)
                            ->orWhereIn(DB::raw('UPPER(offer_sku)'), $ppSkuUpper);
                    })
                    ->get()
                    ->keyBy(function ($item) {
                        return strtoupper(trim((string) ($item->offer_sku ?: $item->sku)));
                    });
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

            // Amazon / Google LMP — slim columns only (never full Eloquent + details collections)
            $amazonLmpLookup = collect();
            $amazonLmpCountLookup = collect();
            $googleLmpLookup = collect();
            $googleLmpCountLookup = collect();
            try {
                if (Schema::hasTable('amazon_sku_competitors')) {
                    $amazonGrouped = DB::table('amazon_sku_competitors')
                        ->select(['sku', 'price', 'product_link', 'ignored'])
                        ->where('marketplace', 'amazon')
                        ->whereRaw('CAST(price AS DECIMAL(10,2)) > 0')
                        ->get()
                        ->groupBy(fn ($item) => AmazonSkuCompetitor::normalizeSkuKey($item->sku));
                    $amazonLmpLookup = $amazonGrouped->map(fn ($items) => AmazonSkuCompetitor::lowestFromCollection($items));
                    $amazonLmpCountLookup = $amazonGrouped->map(fn ($items) => $items->count());
                    unset($amazonGrouped);
                }

                if (Schema::hasTable('google_sku_competitors')) {
                    $googleGrouped = DB::table('google_sku_competitors')
                        ->select(['sku', 'price', 'product_link', 'ignored'])
                        ->where('marketplace', 'google')
                        ->where('price', '>', 0)
                        ->get()
                        ->groupBy(fn ($item) => GoogleSkuCompetitor::normalizeSkuKey($item->sku));
                    $googleLmpLookup = $googleGrouped->map(fn ($items) => GoogleSkuCompetitor::lowestFromCollection($items));
                    $googleLmpCountLookup = $googleGrouped->map(fn ($items) => $items->count());
                    unset($googleGrouped);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch Amazon LMP: ' . $e->getMessage());
            }

            // eBay LMP — slim columns only
            $ebayLmpLookup = collect();
            $ebayLmpCountLookup = collect();
            try {
                if (Schema::hasTable('ebay_sku_competitors')) {
                    $ebayGrouped = DB::table('ebay_sku_competitors')
                        ->select(['sku', 'price', 'shipping_cost', 'total_price', 'product_link', 'ignored'])
                        ->where('marketplace', 'ebay')
                        ->where(function ($q) {
                            $q->where('total_price', '>', 0)
                              ->orWhere('price', '>', 0);
                        })
                        ->get()
                        ->groupBy(fn ($item) => EbaySkuCompetitor::normalizeSkuKey($item->sku));
                    $ebayLmpLookup = $ebayGrouped->map(function ($items) {
                        $active = $items->filter(fn ($i) => empty($i->ignored));
                        $pool = $active->isNotEmpty() ? $active : $items;
                        return $pool->sortBy(function ($i) {
                            $total = floatval($i->total_price ?? 0);
                            if ($total <= 0) {
                                $total = floatval($i->price ?? 0) + floatval($i->shipping_cost ?? 0);
                            }
                            return $total;
                        })->first();
                    });
                    $ebayLmpCountLookup = $ebayGrouped->map(fn ($items) => $items->count());
                    unset($ebayGrouped);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch eBay LMP: ' . $e->getMessage());
            }

            // Temu / Temu 2 LMP — slim columns only (never TemuLmp::all() full hydrate)
            $temuLmpByNormalizedSku = [];
            $temuLmpSkuGroupService = null;
            try {
                if (Schema::hasTable('temu_lmp')) {
                    foreach (
                        TemuLmp::query()
                            ->select(['id', 'sku', 'lmp', 'lmp_link', 'lmp_2', 'lmp_link_2', 'lmp_entries'])
                            ->get() as $temuLmpRow
                    ) {
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

            // Jungle Scout — only rows for our SKUs / ASINs (never full-table get of JSON blobs)
            $jungleScoutBySku = collect();
            $jungleScoutByAsin = collect();
            try {
                if (Schema::hasTable((new JungleScoutProductData)->getTable())) {
                    $asinList = $amazonDatasheets
                        ->pluck('asin')
                        ->filter(fn ($a) => !empty($a))
                        ->map(fn ($a) => strtoupper(trim((string) $a)))
                        ->unique()
                        ->values()
                        ->all();

                    $jsRows = collect();
                    $jsSeenIds = [];
                    $pullJsChunk = function (array $chunk, string $column) use (&$jsRows, &$jsSeenIds) {
                        if ($chunk === []) {
                            return;
                        }
                        foreach (
                            JungleScoutProductData::query()
                                ->select(['id', 'sku', 'asin', 'data', 'updated_at'])
                                ->whereIn($column, $chunk)
                                ->orderByDesc('updated_at')
                                ->get() as $row
                        ) {
                            $id = $row->id ?? null;
                            if ($id !== null) {
                                if (isset($jsSeenIds[$id])) {
                                    continue;
                                }
                                $jsSeenIds[$id] = true;
                            }
                            $jsRows->push($row);
                        }
                    };
                    foreach (array_chunk($skus, 400) as $chunk) {
                        $pullJsChunk($chunk, 'sku');
                    }
                    foreach (array_chunk($asinList, 400) as $chunk) {
                        $pullJsChunk($chunk, 'asin');
                    }

                    $mapJsGroup = function ($group) {
                        $sorted = $group->sortByDesc(function ($item) {
                            $ts = $item->updated_at ?? null;
                            return $ts ? strtotime((string) $ts) : 0;
                        });
                        return [
                            'all_data' => $sorted->map(function ($item) {
                                $data = is_array($item->data) ? $item->data : json_decode($item->data ?? '[]', true);
                                if (!is_array($data)) {
                                    return [];
                                }
                                // Keep only fields used by CVR (drop bulky Jungle Scout payload)
                                return [
                                    'rating' => $data['rating'] ?? null,
                                    'reviews' => $data['reviews'] ?? null,
                                    'listing_quality_score' => $data['listing_quality_score'] ?? null,
                                ];
                            })->values()->toArray(),
                        ];
                    };

                    $jungleScoutBySku = $jsRows
                        ->filter(fn ($item) => !empty($item->sku))
                        ->groupBy(fn ($item) => strtoupper(trim($item->sku)))
                        ->map($mapJsGroup);
                    $jungleScoutByAsin = $jsRows
                        ->filter(fn ($item) => !empty($item->asin))
                        ->groupBy(fn ($item) => strtoupper(trim($item->asin)))
                        ->map($mapJsGroup);
                    unset($jsRows, $jsSeenIds);
                }
            } catch (\Exception $e) {
                Log::warning('CVR Master - Jungle Scout scoped load failed: ' . $e->getMessage());
            }

            // FBA L30: same as FBA Dispatch — resolve ProductMaster SKU → fba_table row, then fba_monthly_sales.l30_units by listing analytics key
            $fbaInventoryResolver = null;
            $fbaMonthlyByListingKey = collect();
            try {
                $fbaTableFbaRows = FbaTable::query()
                    ->select(['id', 'seller_sku', 'quantity_available'])
                    ->whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                    ->get();
                $fbaInventoryResolver = FbaInventoryService::fromFbaRows($fbaTableFbaRows);
                $fbaMonthlyByListingKey = FbaMonthlySale::query()
                    ->select(['seller_sku', 'l30_units'])
                    ->whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
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

            $scalarShip = static function (array $vals, string $key) {
                if (! array_key_exists($key, $vals) || $vals[$key] === null || $vals[$key] === '') {
                    return null;
                }
                $v = $vals[$key];
                if (is_numeric($v)) {
                    return 0 + $v;
                }

                return trim((string) $v);
            };

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
                $ttShip = 0; // TikTok 1 ship (tt_ship only — same as /tiktok-pricing)
                $actWt = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") $lp = floatval($v);
                        if (strtolower($k) === "ship") $ship = floatval($v);
                        if (strtolower($k) === "tt_ship") $ttShip = floatval($v);
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

                // === TIKTOK (same as /tiktok-pricing) ===
                $skuUpperTt = strtoupper((string) $sku);
                $skuNormTt = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $sku)));
                $tiktokProduct = $tiktokProducts->get($skuUpperTt);
                $tiktokPrice = $tiktokProduct ? floatval($tiktokProduct->price ?? 0) : 0;

                // Views = video_views + ads_views + affl_views (shop data view can override)
                $ttVideoViews = $tiktokProduct ? intval($tiktokProduct->video_views ?? $tiktokProduct->views ?? 0) : 0;
                $ttAdsViews = $tiktokProduct ? intval($tiktokProduct->ads_views ?? 0) : 0;
                $ttAfflViews = $tiktokProduct ? intval($tiktokProduct->affl_views ?? 0) : 0;
                $ttShopRow = $tiktokShopByNormSku[$skuNormTt] ?? null;
                if ($ttShopRow) {
                    $ttVal = is_array($ttShopRow->value)
                        ? $ttShopRow->value
                        : (json_decode($ttShopRow->value ?? '{}', true) ?: []);
                    if (array_key_exists('video_views', $ttVal)) {
                        $ttVideoViews = intval($ttVal['video_views']);
                    }
                    if (array_key_exists('ads_views', $ttVal)) {
                        $ttAdsViews = intval($ttVal['ads_views']);
                    }
                    if (array_key_exists('affl_views', $ttVal)) {
                        $ttAfflViews = intval($ttVal['affl_views']);
                    }
                }

                // L30 from tiktok_orders
                $tiktokL30 = (int) ($tiktokL30BySku[$skuUpperTt] ?? 0);

                // GPFT% uses tt_ship only (no fallback to ship)
                $tiktokGPFT = $tiktokPrice > 0
                    ? ((($tiktokPrice * $tiktokPercentage - $lp - $ttShip) / $tiktokPrice) * 100)
                    : 0;

                // TACOS% = spend(L30+L7) / (L30 × Price) × 100; spend>0 & L30=0 → 100
                $tiktokAdSpend = (float) ($tiktokSpendBySku[$skuUpperTt] ?? 0);
                $tiktokRevenue = $tiktokPrice * $tiktokL30;
                if ($tiktokRevenue > 0) {
                    $tiktokAD = ($tiktokAdSpend / $tiktokRevenue) * 100;
                } else {
                    $tiktokAD = $tiktokAdSpend > 0 ? 100.0 : 0.0;
                }
                // PFT% = GPFT% − TACOS% (same as /tiktok-pricing)
                $tiktokPFT = $tiktokGPFT - $tiktokAD;

                // BestBuy — same as /bestbuy-pricing BB Price: sheet first, else product
                $bestbuyProduct = $bestbuyProducts->get($sku);
                $bestbuyPriceItem = $bestbuyPriceData->get(strtoupper((string) $sku));
                $bbPrice = $bestbuyPriceItem
                    ? floatval($bestbuyPriceItem->price ?? 0)
                    : ($bestbuyProduct ? floatval($bestbuyProduct->price ?? 0) : 0);

                // GPFT% = ((price × percentage − ship − lp) / price) × 100
                $bbGPFT = $bbPrice > 0
                    ? round((($bbPrice * $bestbuyPercentage - $lp - $ship) / $bbPrice) * 100, 2)
                    : 0;

                // BestBuy PFT% = GPFT% (no ads)
                $bbPFT = $bbGPFT;

                // Get Shopify B2C data - uses overall_l30 from shopify_skus (already fetched)
                // Price from shopify_skus table
                $sb2cPrice = isset($shopifyData[$sku]) ? floatval($shopifyData[$sku]->price ?? 0) : 0;
                
                // Calculate Shopify B2C GPFT% = ((price × percentage - ship - lp) / price) × 100
                // Shopify B2C uses 100% (no marketplace commission)
                $sb2cGPFT = $sb2cPrice > 0 ? ((($sb2cPrice * $shopifyB2CPercentage - $lp - $ship) / $sb2cPrice) * 100) : 0;
                
                // Shopify B2C PFT% = GPFT% (no ads)
                $sb2cPFT = $sb2cGPFT;

                // Macy's price — same as /macys-pricing MC Price: sheet first, else product
                $macySkuKey = $normalizeMacySku($sku);
                $macyProduct = $macyProducts->get($macySkuKey);
                $macySheetRow = $macyPriceSheet->get($macySkuKey);
                $macyPrice = $macySheetRow
                    ? floatval($macySheetRow->price ?? 0)
                    : ($macyProduct ? floatval($macyProduct->price ?? 0) : 0);

                // GPFT% = ((price × percentage − ship − lp) / price) × 100  (same as /macys-pricing)
                $macyGPFT = $macyPrice > 0
                    ? round((($macyPrice * $macyPercentage - $lp - $ship) / $macyPrice) * 100, 2)
                    : 0;

                // Macy's PFT% = GPFT% (no ads)
                $macyPFT = $macyGPFT;

                // Reverb — same as /reverb-pricing RV Price from reverb_products (normalized SKU)
                $reverbNormKey = ReverbProduct::normalizeSkuForLookup($sku);
                $reverbProduct = ($reverbNormKey !== '' && isset($reverbProductsByNorm[$reverbNormKey]))
                    ? $reverbProductsByNorm[$reverbNormKey]
                    : null;
                $reverbPrice = $reverbProduct ? floatval($reverbProduct->price ?? 0) : 0;

                // GPFT% = ((price × percentage − lp − ship) / price) × 100
                $reverbGPFT = $reverbPrice > 0
                    ? round((($reverbPrice * $reverbPercentage - $lp - $ship) / $reverbPrice) * 100, 2)
                    : 0;

                // Reverb PFT% = GPFT% − channel Ads% (same as /reverb-pricing)
                $reverbPFT = round($reverbGPFT - $reverbChannelAdsPct, 2);

                // === EBAY 1 CALCULATIONS ===
                $ebay1Metric = $ebayMetrics->get($sku);
                $ebay1Price = $ebay1Metric ? floatval($ebay1Metric->ebay_price ?? 0) : 0;
                
                // eBay 1 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay1GPFT = $ebay1Price > 0 ? ((($ebay1Price * $ebay1Percentage - $lp - $ship) / $ebay1Price) * 100) : 0;
                
                // eBay 1 PFT% = GPFT% − channel Ads% (same as /ebay-tabulator-view)
                $ebay1PFT = round($ebay1GPFT - $ebay1ChannelAdsPct, 2);
                
                // === EBAY 2 CALCULATIONS ===
                $ebay2Metric = $ebay2Metrics->get($sku);
                $ebay2Price = $ebay2Metric ? floatval($ebay2Metric->ebay_price ?? 0) : 0;
                
                // eBay 2 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay2GPFT = $ebay2Price > 0 ? ((($ebay2Price * $ebay2Percentage - $lp - $ship) / $ebay2Price) * 100) : 0;
                
                // eBay 2 PFT% = GPFT% − channel Ads% (same as /ebay2-tabulator-view)
                $ebay2PFT = round($ebay2GPFT - $ebay2ChannelAdsPct, 2);
                
                // === EBAY 3 CALCULATIONS ===
                $ebay3Metric = $ebay3Metrics->get($sku);
                $ebay3Price = $ebay3Metric ? floatval($ebay3Metric->ebay_price ?? 0) : 0;
                
                // eBay 3 GPFT% = ((price × percentage - ship - lp) / price) × 100
                $ebay3GPFT = $ebay3Price > 0 ? ((($ebay3Price * $ebay3Percentage - $lp - $ship) / $ebay3Price) * 100) : 0;
                
                // eBay 3 PFT% = GPFT% − channel Ads% (same as /ebay3-tabulator-view)
                $ebay3PFT = round($ebay3GPFT - $ebay3ChannelAdsPct, 2);

                // Get Doba data — same as /doba-tabulator: Price=anticipated_income, no ads
                $dobaMetric = $dobaMetrics->get($normalizeDobaSku($sku));
                $dobaPrice = $dobaMetric ? floatval($dobaMetric->anticipated_income ?? 0) : 0;

                // GPFT% = ((price × margin − LP − Ship) / price) × 100  (margin from MarketplacePercentage)
                $dobaGPFT = $dobaPrice > 0
                    ? round((($dobaPrice * $dobaPercentage - $lp - $ship) / $dobaPrice) * 100, 2)
                    : 0;

                // Doba PFT% = GPFT% (no ads — same as /doba-tabulator)
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

                // === TEMU CALCULATIONS (exact formulas from /temu-decrease) ===
                $temuPricing = $temuPricings->get($sku);
                $temuBasePrice = $temuPricing ? floatval($temuPricing->base_price ?? 0) : 0;
                // FB Prc: +$2.99 when base ≤ $26.99
                $temuPrice = $temuBasePrice > 0 ? ($temuBasePrice <= 26.99 ? $temuBasePrice + 2.99 : $temuBasePrice) : 0;

                $temuL30 = (int) ($temuL30ByProductSku[$sku] ?? 0);

                $temuShip = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                    }
                }
                // GPFT% = ((FB × margin − LP − temu_ship) / FB) × 100
                $temuGPFT = $temuPrice > 0
                    ? ((($temuPrice * $temuPercentage - $lp - $temuShip) / $temuPrice) * 100)
                    : 0;

                // Ads%: every row uses aggregate badge Ads% (~2.6%) — same as /temu-decrease formatter
                // Exception: spend > 0 and L30 = 0 → 100%
                $goodsId = $temuPricing ? ($temuPricing->goods_id ?? null) : null;
                $temuAdSpend = $this->lookupTemuCampaignSpend(
                    $goodsId,
                    $sku,
                    $temuSpendByGoodsId,
                    $temuSpendBySku,
                    $temuSpendBySkuLoose
                );
                $temuRevenue = $temuPrice * $temuL30;
                if ($temuAdSpend > 0 && $temuL30 == 0) {
                    $temuAD = 100;
                } else {
                    $temuAD = $temuAggregateAdsPercent;
                }
                // NPFT% = GPFT% − aggregate Ads% (skip subtract when Ads% = 100)
                $temuPFT = ($temuAD == 100) ? $temuGPFT : ($temuGPFT - $temuAD);

                // === TEMU 2 CALCULATIONS (same price/GPFT as /temu2-decrease; Ads% = 0) ===
                $temu2Pricing = $temu2PricingByProductSku[$sku] ?? null;
                $temu2BasePrice = $temu2Pricing ? floatval($temu2Pricing->base_price ?? 0) : 0;
                $temu2Price = $temu2BasePrice > 0 ? ($temu2BasePrice <= 26.99 ? $temu2BasePrice + 2.99 : $temu2BasePrice) : 0;
                $temu2L30 = (int) ($temu2L30ByProductSku[$sku] ?? 0);
                $temu2GPFT = $temu2Price > 0 ? ((($temu2Price * $temuPercentage - $lp - $temuShip) / $temu2Price) * 100) : 0;
                // Temu 2: Spend is display-only — no Ads% / NPFT-from-ads (same as /temu2-decrease)
                $temu2AdSpend = 0.0;
                $temu2Revenue = $temu2Price * $temu2L30;
                $temu2AD = 0;
                $temu2PFT = $temu2GPFT;
                $temu2Views = 0;
                if ($temu2Pricing) {
                    $temu2Views = $this->lookupTemuViewsByGoodsId(
                        $temu2Pricing->goods_id ?? null,
                        $temu2ViewByGoodsId
                    ) ?? 0;
                }

                // === SHEIN (same as /shein-pricing) ===
                // Calc price = special_offer_price only; ship = product_master ship; Ads% = 0
                $sheinNormKey = $this->normalizeSheinSkuForCvr((string) $sku);
                $sheinPricing = ($sheinNormKey !== '' && isset($sheinPricingsByNorm[$sheinNormKey]))
                    ? $sheinPricingsByNorm[$sheinNormKey]
                    : null;
                $sheinPrice = $sheinPricing ? floatval($sheinPricing->special_offer_price ?? 0) : 0;
                $sheinViews = 0; // /shein-pricing does not track views
                $sheinL30 = ($sheinNormKey !== '') ? (int) ($sheinL30ByNorm[$sheinNormKey] ?? 0) : 0;
                $sheinGPFT = $sheinPrice > 0
                    ? round((($sheinPrice * $sheinPercentage - $lp - $ship) / $sheinPrice) * 100, 2)
                    : 0;
                $sheinPFT = $sheinGPFT; // No ads

                // === ALIEXPRESS CALCULATIONS ===
                // Price from aliexpress_pricing_prices; L30 from aliexpress_daily_data by sku_code
                $aePricing  = $aePricings->get($sku);
                $aePrice    = $aePricing ? floatval($aePricing->price ?? 0) : 0;
                $aeSaleRow  = $aeDailySales->get($sku);
                $aeL30      = $aeSaleRow ? intval($aeSaleRow->ae_l30 ?? 0) : 0;
                $aeGPFT     = $aePrice > 0 ? ((($aePrice * $aePercentage - $lp - $ship) / $aePrice) * 100) : 0;
                $aePFT      = $aeGPFT; // No ads for AliExpress

                // === PURCHASING POWER (same as /purchasing-power-pricing) ===
                // Price: MCM OF21 → purchasing_power_products; fallback macys_price_data
                // GPFT/ROI: (price × margin − LP) / price|LP — ship excluded; Ads% = 0
                $ppSkuKey = strtoupper((string) $sku);
                $ppProduct = $ppProducts->get($ppSkuKey);
                $ppOfferSheet = $ppOfferSheetBySku->get($ppSkuKey);
                $mcmPrice = ($ppProduct && $ppProduct->price !== null && $ppProduct->price !== '')
                    ? floatval($ppProduct->price)
                    : 0.0;
                if ($mcmPrice > 0) {
                    $ppPrice = $mcmPrice;
                } elseif ($ppOfferSheet && floatval($ppOfferSheet->price ?? 0) > 0) {
                    $ppPrice = floatval($ppOfferSheet->price);
                } else {
                    $ppPrice = $ppProduct ? floatval($ppProduct->price ?? 0) : 0;
                }
                $ppSaleRow = $ppSalesQty->get($ppSkuKey);
                $ppL30 = $ppSaleRow !== null
                    ? intval($ppSaleRow)
                    : ($ppProduct ? intval($ppProduct->m_l30 ?? 0) : 0);
                $ppGPFT = $ppPrice > 0 ? ((($ppPrice * $ppPercentage - $lp) / $ppPrice) * 100) : 0;
                $ppPFT = $ppGPFT; // No ads

                // === TOPDAWG (same as /topdawg-pricing) ===
                // Price from topdawg_products; L30 from order_metrics; no ship; Ads% = 0
                $tdNormKey = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                $tdProduct = ($tdNormKey !== '' && isset($tdProductsByNorm[$tdNormKey]))
                    ? $tdProductsByNorm[$tdNormKey]
                    : null;
                $tdPrice = $tdProduct ? floatval($tdProduct->price ?? 0) : 0;
                $tdViews = $tdProduct ? intval($tdProduct->views ?? 0) : 0;
                if (!empty($tdOrderL30ByNorm)) {
                    $tdL30 = (int) (($tdOrderL30ByNorm[$tdNormKey]['qty'] ?? 0));
                } else {
                    $tdL30 = $tdProduct ? intval($tdProduct->r_l30 ?? 0) : 0;
                }
                $tdGPFT = $tdPrice > 0
                    ? round((($tdPrice * $tdPercentage - $lp) / $tdPrice) * 100, 2)
                    : 0;
                $tdPFT = $tdGPFT; // No ads

                // Calculate aggregated metrics across all marketplaces
                
                // Get views from all marketplaces
                $amazonViews = $amazonSheet ? intval($amazonSheet->sessions_l30 ?? 0) : 0;
                $ebay1Views = $ebay1Metric ? intval($ebay1Metric->views ?? 0) : 0;
                $ebay2Views = $ebay2Metric ? intval($ebay2Metric->views ?? 0) : 0;
                $ebay3Views = $ebay3Metric ? intval($ebay3Metric->views ?? 0) : 0;
                // Temu views = product_clicks_l30 from temu_metrics API (same as /temu-decrease)
                $temuViews = 0;
                if ($temuPricing) {
                    $temuViews = $this->lookupTemuViewsByGoodsId(
                        $temuPricing->goods_id ?? null,
                        $temuViewByGoodsId
                    ) ?? 0;
                    if ($temuViews === 0) {
                        $temuViews = (int) ($temuPricing->product_clicks_l30 ?? 0);
                    }
                }
                // TikTok views = video + ads + affl (same as /tiktok-pricing T Views)
                $tiktokViews = $ttVideoViews + $ttAdsViews + $ttAfflViews;
                $bbViews = 0; // BestBuy doesn't track views
                // Shopify L30 product page sessions (same shopify_skus.views as /shopify-b2c-pricing)
                $sb2cViews = isset($shopifyData[$sku]) ? intval($shopifyData[$sku]->views ?? 0) : 0;
                $macyViews = $macyProduct ? intval($macyProduct->views ?? 0) : 0;
                $reverbViews = $reverbProduct ? intval($reverbProduct->views ?? 0) : 0;
                $dobaViews = $dobaMetric ? intval($dobaMetric->impressions ?? 0) : 0;
                
                // Total Views (sum of all marketplace views) — Walmart excluded from this page
                $totalViews = $amazonViews + $ebay1Views + $ebay2Views + $ebay3Views + $temuViews + $temu2Views
                              + $tiktokViews + $bbViews + $sb2cViews
                              + $macyViews + $reverbViews + $dobaViews + $sheinViews + $tdViews; // AliExpress has no views tracked
                // Get L30 from all marketplaces
                $ebay1L30 = $ebay1Metric ? intval($ebay1Metric->ebay_l30 ?? 0) : 0;
                $ebay2L30 = $ebay2Metric ? intval($ebay2Metric->ebay_l30 ?? 0) : 0;
                $ebay3L30 = $ebay3Metric ? intval($ebay3Metric->ebay_l30 ?? 0) : 0;
                // Walmart / Tiendamia excluded from SW L30 on this page
                $walmartL30 = 0;
                // $tiktokL30 already set from tiktok_orders above
                $bbL30 = $bestbuyProduct ? intval($bestbuyProduct->m_l30 ?? 0) : 0;
                $sb2cL30 = 0; // Shopify B2C L30 is in overall_l30 (already counted)
                $macyL30 = $macyProduct ? intval($macyProduct->m_l30 ?? 0) : 0;
                $reverbL30 = $reverbProduct ? intval($reverbProduct->r_l30 ?? 0) : 0;
                $dobaL30 = $dobaMetric ? intval($dobaMetric->quantity_l30 ?? 0) : 0;
                
                // Total L30 across marketplaces (Walmart / Tiendamia excluded)
                $totalL30 = $amazonL30 + $ebay1L30 + $ebay2L30 + $ebay3L30 + $temuL30 + $temu2L30
                           + $tiktokL30 + $bbL30 + $sb2cL30
                           + $macyL30 + $reverbL30 + $dobaL30 + $sheinL30 + $aeL30 + $ppL30 + $tdL30;
                
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
                // Walmart excluded from this page
                if ($tiktokPrice > 0) $prices[] = $tiktokPrice;
                if ($bbPrice > 0) $prices[] = $bbPrice;
                if ($sb2cPrice > 0) $prices[] = $sb2cPrice;
                if ($macyPrice > 0) $prices[] = $macyPrice;
                if ($reverbPrice > 0) $prices[] = $reverbPrice;
                if ($dobaPrice > 0) $prices[] = $dobaPrice;
                if ($sheinPrice > 0) $prices[] = $sheinPrice;
                if ($aePrice > 0) $prices[] = $aePrice;
                if ($ppPrice > 0) $prices[] = $ppPrice;
                if ($tdPrice > 0) $prices[] = $tdPrice;
                
                // Collect all GPFT values (non-zero or negative)
                $gpftValues = [];
                if ($amazonPrice > 0) $gpftValues[] = $amazonGPFT;
                if ($ebay1Price > 0) $gpftValues[] = $ebay1GPFT;
                if ($ebay2Price > 0) $gpftValues[] = $ebay2GPFT;
                if ($ebay3Price > 0) $gpftValues[] = $ebay3GPFT;
                if ($temuPrice > 0) $gpftValues[] = $temuGPFT;
                if ($temu2Price > 0) $gpftValues[] = $temu2GPFT;
                // Walmart excluded from this page
                if ($tiktokPrice > 0) $gpftValues[] = $tiktokGPFT;
                if ($bbPrice > 0) $gpftValues[] = $bbGPFT;
                if ($sb2cPrice > 0) $gpftValues[] = $sb2cGPFT;
                if ($macyPrice > 0) $gpftValues[] = $macyGPFT;
                if ($reverbPrice > 0) $gpftValues[] = $reverbGPFT;
                if ($dobaPrice > 0) $gpftValues[] = $dobaGPFT;
                if ($sheinPrice > 0) $gpftValues[] = $sheinGPFT;
                if ($aePrice > 0) $gpftValues[] = $aeGPFT;
                if ($ppPrice > 0) $gpftValues[] = $ppGPFT;
                if ($tdPrice > 0) $gpftValues[] = $tdGPFT;
                
                // Sales-weighted Ads%: (Σ ad spend $) ÷ (Σ sales $) × 100
                // for marketplaces with ads (Amazon, Temu, Temu 2, TikTok) that have sales
                // Walmart excluded from this page
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
                if ($tiktokRevenue > 0) {
                    $totalAdsAmount += $tiktokAdSpend;
                    $totalAdSalesAmount += $tiktokRevenue;
                }
                if ($temu2Revenue > 0) {
                    $totalAdsAmount += $temu2AdSpend;
                    $totalAdSalesAmount += $temu2Revenue;
                }

                // Collect all PFT values
                $pftValues = [];
                if ($amazonPrice > 0) $pftValues[] = $amazonPFT;
                if ($ebay1Price > 0) $pftValues[] = $ebay1PFT;
                if ($ebay2Price > 0) $pftValues[] = $ebay2PFT;
                if ($ebay3Price > 0) $pftValues[] = $ebay3PFT;
                if ($temuPrice > 0) $pftValues[] = $temuPFT;
                if ($temu2Price > 0) $pftValues[] = $temu2PFT;
                // Walmart excluded from this page
                if ($tiktokPrice > 0) $pftValues[] = $tiktokPFT;
                if ($bbPrice > 0) $pftValues[] = $bbPFT;
                if ($sb2cPrice > 0) $pftValues[] = $sb2cPFT;
                if ($macyPrice > 0) $pftValues[] = $macyPFT;
                if ($reverbPrice > 0) $pftValues[] = $reverbPFT;
                if ($dobaPrice > 0) $pftValues[] = $dobaPFT;
                if ($sheinPrice > 0) $pftValues[] = $sheinPFT;
                if ($aePrice > 0) $pftValues[] = $aePFT;
                if ($ppPrice > 0) $pftValues[] = $ppPFT;
                if ($tdPrice > 0) $pftValues[] = $tdPFT;
                
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
                        // GROI% / NROI% — same as /temu-decrease: NROI = GROI − Ads% (skip when Ads%=100)
                        $temuGroi = (($temuPrice * $temuPercentage - $lp - $temuShip) / $lp) * 100;
                        $roiValues[] = $temuGroi;
                        $nroiValues[] = ($temuAD == 100) ? $temuGroi : ($temuGroi - $temuAD);
                    }
                    if ($temu2Price > 0) {
                        // Temu 2: no ads — NROI = GROI (same as /temu2-decrease)
                        $temu2Groi = (($temu2Price * $temuPercentage - $lp - $temuShip) / $lp) * 100;
                        $roiValues[] = $temu2Groi;
                        $nroiValues[] = $temu2Groi;
                    }
                    // Walmart excluded from this page
                    if ($tiktokPrice > 0) {
                        // GROI uses tt_ship; NROI = dollar-ads style via NPFT (GPFT − TACOS)
                        $tiktokGroi = (($tiktokPrice * $tiktokPercentage - $lp - $ttShip) / $lp) * 100;
                        $roiValues[] = $tiktokGroi;
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
                        // GROI% = (Price × Margin − LP − Ship) / LP × 100 — same as /shein-pricing
                        $sheinGroi = (($sheinPrice * $sheinPercentage - $lp - $ship) / $lp) * 100;
                        $roiValues[] = $sheinGroi;
                        $nroiValues[] = $sheinGroi; // Ads% = 0 → NROI = GROI
                    }
                    if ($aePrice > 0) {
                        $roiValues[] = ($aeGPFT * $aePrice) / $lp;
                        $nroiValues[] = ($aePFT * $aePrice) / $lp;
                    }
                    if ($ppPrice > 0) {
                        $roiValues[] = ($ppGPFT * $ppPrice) / $lp;
                        $nroiValues[] = ($ppPFT * $ppPrice) / $lp;
                    }
                    if ($tdPrice > 0) {
                        $roiValues[] = ($tdGPFT * $tdPrice) / $lp;
                        $nroiValues[] = ($tdPFT * $tdPrice) / $lp;
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
                // Direct Temu LMP (raw competitor price — no Recovery calculation)
                $temuLmpPrice = $temuLmpResolved['price'];
                $temuLmpLink = $temuLmpResolved['link'];
                $temuLmpCount = $temuLmpResolved['count'];

                $amazonDataViewRow = $amazonDataViewBySku->get($sku)
                    ?? $amazonDataViewBySkuUpper->get(strtoupper(trim((string) $sku)));
                $amazonSprice = null;
                $amazonStandardPrice = null;
                $amazonSgpft = null;
                $amazonSpft = null;
                $amazonSroi = null;
                if ($amazonDataViewRow && $amazonDataViewRow->value) {
                    $avVal = is_array($amazonDataViewRow->value) ? $amazonDataViewRow->value : (json_decode($amazonDataViewRow->value ?? '{}', true) ?? []);
                    $spr = $avVal['SPRICE'] ?? null;
                    $amazonSprice = ($spr !== null && $spr !== '' && floatval($spr) > 0) ? round(floatval($spr), 2) : null;
                    // Manual Standard Price (SP) — same field as /amazon-tabulator-view
                    $std = $avVal['STANDARD_PRICE'] ?? null;
                    $amazonStandardPrice = (is_numeric($std) && floatval($std) > 0) ? round(floatval($std), 2) : null;
                    if (isset($avVal['SGPFT'])) $amazonSgpft = round(floatval($avVal['SGPFT']), 2);
                    if (isset($avVal['SPFT'])) $amazonSpft = round(floatval($avVal['SPFT']), 2);
                    if (isset($avVal['SROI'])) $amazonSroi = round(floatval($avVal['SROI']), 2);
                }

                // Temu SPRICE — same store as /temu-decrease (temu_data_view.value.sprice / SPRICE)
                $temuDataViewRow = $temuDataViewBySku->get($sku)
                    ?? $temuDataViewBySkuUpper->get(strtoupper(trim((string) $sku)));
                $temuSprice = null;
                // Approx push-history count for outer Hist column (amazon/temu; Walmart excluded from this page)
                $pushHistoryCount = 0;
                foreach ([$amazonDataViewRow, $temuDataViewRow] as $dvHistRow) {
                    if (!$dvHistRow) {
                        continue;
                    }
                    $rawHist = $dvHistRow->value ?? null;
                    $valHist = is_array($rawHist)
                        ? $rawHist
                        : (is_string($rawHist) ? (json_decode($rawHist, true) ?: []) : []);
                    if (is_array($valHist) && $valHist !== []) {
                        $pushHistoryCount += count($this->extractPushMeta($valHist)['push_history']);
                    }
                }
                if ($temuDataViewRow && $temuDataViewRow->value) {
                    $tvVal = is_array($temuDataViewRow->value)
                        ? $temuDataViewRow->value
                        : (json_decode($temuDataViewRow->value ?? '{}', true) ?? []);
                    $tSpr = $tvVal['sprice'] ?? $tvVal['SPRICE'] ?? null;
                    $temuSprice = ($tSpr !== null && $tSpr !== '' && floatval($tSpr) > 0)
                        ? round(floatval($tSpr), 2)
                        : null;
                }
                $temuSkuId = $temuPricing ? ($temuPricing->sku_id ?? null) : null;

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

                // Shipping Master Label fields (same source as /sales-order-fulfillment Label column)
                $labelType = isset($values['label_type']) ? trim((string) $values['label_type']) : '';
                if ($labelType === '') {
                    $labelType = 'STD';
                }

                $result[] = (object) [
                    "sku" => $sku,
                    "parent" => $parent,
                    "image_path" => $imagePath,
                    "inventory" => $inventory,
                    "amazon_price" => $amazonPrice > 0 ? round($amazonPrice, 2) : null,
                    // eBay 1 our listing — used for blue 5 Core row in LMP (same as /ebay-tabulator-view)
                    "ebay1_price" => $ebay1Price > 0 ? round($ebay1Price, 2) : null,
                    "ebay1_item_id" => $ebay1Metric ? ($ebay1Metric->item_id ?? null) : null,
                    "amazon_sprice" => $amazonSprice,
                    "amazon_standard_price" => $amazonStandardPrice,
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
                    "td_l30"    => $tdL30,
                    "amz_pft" => $amzPft,
                    "amz_roi" => $amzRoi,
                    "overall_l30" => $overallL30,
                    "fba_l30" => $fbaL30Units,
                    "ov_l30_plus_fba" => $ovL30PlusFba,
                    "m_l30" => $totalL30,
                    "dil_percent" => $dilPercent,
                    "total_views" => $totalViews,
                    // Temu 1 Views — same source as /temu-decrease (temu_metrics.product_clicks_l30)
                    "temu_views" => (int) $temuViews,
                    // Temu SPRICE / push fields — same as /temu-decrease
                    "temu_base_price" => $temuBasePrice > 0 ? round($temuBasePrice, 2) : null,
                    "temu_price" => $temuPrice > 0 ? round($temuPrice, 2) : null,
                    "temu_sprice" => $temuSprice,
                    "temu_goods_id" => $goodsId,
                    "temu_sku_id" => $temuSkuId,
                    "temu_ship" => $temuShip > 0 ? round($temuShip, 2) : 0,
                    "temu_margin" => $temuPercentage,
                    "temu_push_status" => null,
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
                    "push_history_count" => $pushHistoryCount,
                    "label" => $labelType,
                    "label_qty" => $scalarShip($values, 'label_qty'),
                    "wt_act" => $scalarShip($values, 'wt_act'),
                    "l" => $scalarShip($values, 'l'),
                    "w" => $scalarShip($values, 'w'),
                    "h" => $scalarShip($values, 'h'),
                    "wt_decl" => $scalarShip($values, 'wt_decl'),
                    "l_decl" => $scalarShip($values, 'l_decl'),
                    "w_decl" => $scalarShip($values, 'w_decl'),
                    "h_decl" => $scalarShip($values, 'h_decl'),
                    // CP$ / FRG / LP — Product Master (Label details modal)
                    "cp" => $scalarShip($values, 'cp'),
                    "frght" => (function () use ($values, $scalarShip) {
                        $stored = $scalarShip($values, 'frght');
                        if ($stored !== null && $stored !== '' && is_numeric($stored)) {
                            return round((float) $stored, 2);
                        }
                        $l = $scalarShip($values, 'l');
                        $w = $scalarShip($values, 'w');
                        $h = $scalarShip($values, 'h');
                        if (! is_numeric($l) || ! is_numeric($w) || ! is_numeric($h)) {
                            return null;
                        }
                        $cbm = (((float) $l * 2.54) * ((float) $w * 2.54) * ((float) $h * 2.54)) / 1000000;

                        return round($cbm * 200, 2);
                    })(),
                    "lp" => $lp > 0 ? round($lp, 2) : $scalarShip($values, 'lp'),
                    "sku_image" => $imagePath,
                ];
            }

            // Audit history — same table/source as /amz-cvr-issues (amz_cvr_audit_histories)
            $auditBySku = $this->amzCvrAuditHistoryBySku(
                collect($result)->pluck('sku')->filter()->unique()->values()->all()
            );
            foreach ($result as &$rowObj) {
                $skuKey = trim((string) ($rowObj->sku ?? ''));
                $history = $auditBySku[$skuKey] ?? [];
                $rowObj->audit_history = $history;
                $rowObj->audit_history_latest = $history[0] ?? null;
                $rowObj->audit_history_ts = isset($history[0]['sort_ts']) ? (int) $history[0]['sort_ts'] : 0;
                $rowObj->audit_history_dates = array_values(array_unique(array_filter(array_map(
                    static fn ($h) => $h['date_key'] ?? null,
                    $history
                ))));
            }
            unset($rowObj);

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
                $amazonStandardPriceVals = $rows->pluck('amazon_standard_price')->filter(fn ($v) => $v !== null && $v > 0);
                $temuSpriceVals = $rows->pluck('temu_sprice')->filter(fn ($v) => $v !== null && $v > 0);
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
                    'amazon_standard_price' => $amazonStandardPriceVals->isNotEmpty() ? round($amazonStandardPriceVals->avg(), 2) : null,
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
                    'td_l30'    => $rows->sum('td_l30'),
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
                    'temu_views' => (int) $rows->sum('temu_views'),
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
                    'push_history_count' => 0,
                    'label' => null,
                    'label_qty' => null,
                    'wt_act' => null,
                    'l' => null,
                    'w' => null,
                    'h' => null,
                    'wt_decl' => null,
                    'l_decl' => null,
                    'w_decl' => null,
                    'h_decl' => null,
                    'cp' => null,
                    'frght' => null,
                    'lp' => null,
                    'sku_image' => null,
                    'audit_history' => [],
                    'audit_history_latest' => null,
                    'audit_history_ts' => 0,
                    'audit_history_dates' => [],
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

            // Auto-save SKU-wise daily snapshot on refresh (for Inv, OV L30, Price, CVR, NPFT, NROI graphs per SKU)
            try {
                $childRows = collect($finalResult)->filter(fn($r) => empty($r->is_parent_summary));
                $today = now('America/Los_Angeles')->toDateString();
                $saved = 0;
                foreach ($childRows as $row) {
                    $raw = is_string($row->sku ?? null) ? $row->sku : (string) ($row->sku ?? '');
                    $sku = preg_replace('/\s+/', ' ', trim($raw));
                    if ($sku === '') continue;
                    // inventory column is unsigned — clamp negatives so one bad SKU cannot abort the batch
                    $invClamped = max(0, (int) ($row->inventory ?? 0));
                    $snapshotPayload = [
                        'inventory' => $invClamped,
                        'overall_l30' => max(0, (int) ($row->overall_l30 ?? 0)),
                        'avg_price' => isset($row->avg_price) && $row->avg_price > 0 ? round((float) $row->avg_price, 2) : null,
                        'avg_cvr' => isset($row->avg_cvr) && $row->avg_cvr !== null ? round((float) $row->avg_cvr, 2) : null,
                        'dil_percent' => isset($row->dil_percent) && $row->dil_percent !== null ? round((float) $row->dil_percent, 2) : null,
                        'amazon_price' => isset($row->amazon_price) && $row->amazon_price > 0 ? round((float) $row->amazon_price, 2) : null,
                        'rating' => isset($row->rating) && $row->rating > 0 ? round((float) $row->rating, 2) : null,
                        'total_views' => max(0, (int) ($row->total_views ?? 0)),
                    ];
                    // Avg NPFT% / Avg NROI% (same fields as pricing-master-cvr main table / parent blue row)
                    if (Schema::hasColumn('pricing_master_daily_snapshots_sku', 'avg_pft')) {
                        $snapshotPayload['avg_pft'] = isset($row->avg_pft) && $row->avg_pft !== null
                            ? round((float) $row->avg_pft, 2) : null;
                    }
                    if (Schema::hasColumn('pricing_master_daily_snapshots_sku', 'avg_nroi')) {
                        $snapshotPayload['avg_nroi'] = isset($row->avg_nroi) && $row->avg_nroi !== null
                            ? round((float) $row->avg_nroi, 2) : null;
                    }
                    try {
                        PricingMasterDailySnapshotSku::updateOrCreate(
                            ['snapshot_date' => $today, 'sku' => $sku],
                            $snapshotPayload
                        );
                        $saved++;
                    } catch (\Throwable $rowEx) {
                        Log::warning('Master Analytics SKU snapshot row failed', [
                            'sku' => $sku,
                            'error' => $rowEx->getMessage(),
                        ]);
                    }
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
                    'topdawg' => 'topdawg',
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

            // Per-SKU LMP resolvers only (never load full competitor tables — OOM on 128MB)
            $lmpPriceCache = [];
            $cacheLmp = function (string $channel, string $key, $resolver) use (&$lmpPriceCache) {
                $ck = $channel . '|' . $key;
                if (array_key_exists($ck, $lmpPriceCache)) {
                    return $lmpPriceCache[$ck];
                }
                try {
                    $lmpPriceCache[$ck] = $resolver();
                } catch (\Throwable $e) {
                    $lmpPriceCache[$ck] = null;
                }
                return $lmpPriceCache[$ck];
            };

            $resolveAmazonLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = AmazonSkuCompetitor::normalizeSkuKey($lookupSku);
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('amazon', $key, function () use ($lookupSku) {
                    $lmp = AmazonSkuCompetitor::getLowestPriceForSku($lookupSku, 'amazon');
                    if ($lmp && isset($lmp->price) && is_numeric($lmp->price) && floatval($lmp->price) > 0) {
                        return round(floatval($lmp->price), 2);
                    }
                    return null;
                });
            };
            $resolveEbayLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = EbaySkuCompetitor::normalizeSkuKey($lookupSku);
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('ebay', $key, function () use ($lookupSku) {
                    $lmp = EbaySkuCompetitor::getLowestPriceForSku($lookupSku, 'ebay');
                    if (!$lmp) {
                        return null;
                    }
                    $total = floatval($lmp->total_price ?? 0);
                    if ($total <= 0) {
                        $total = floatval($lmp->price ?? 0) + floatval($lmp->shipping_cost ?? 0);
                    }
                    return $total > 0 ? round($total, 2) : null;
                });
            };
            $resolveGoogleLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($lookupSku ?? ''))));
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('google', $key, function () use ($lookupSku) {
                    $comps = GoogleSkuCompetitor::getCompetitorsForSku($lookupSku, 'google');
                    $active = $comps->filter(fn ($c) => empty($c->ignored));
                    $lmp = $active->isNotEmpty() ? $active->sortBy(fn ($c) => floatval($c->price ?? 0))->first() : null;
                    if ($lmp && isset($lmp->price) && is_numeric($lmp->price) && floatval($lmp->price) > 0) {
                        return round(floatval($lmp->price), 2);
                    }
                    return null;
                });
            };
            $resolveBestbuyLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = BestbuySkuCompetitor::normalizeSkuKey($lookupSku);
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('bestbuy', $key, function () use ($lookupSku) {
                    if (!Schema::hasTable('bestbuy_sku_competitors')) {
                        return null;
                    }
                    $lmp = BestbuySkuCompetitor::getLowestPriceForSku($lookupSku, 'bestbuy');
                    if (!$lmp) {
                        return null;
                    }
                    $total = floatval($lmp->total_price ?? 0);
                    if ($total <= 0) {
                        $total = floatval($lmp->price ?? 0) + floatval($lmp->shipping_cost ?? 0);
                    }
                    return $total > 0 ? round($total, 2) : null;
                });
            };
            $resolveMacyLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = MacySkuCompetitor::normalizeSkuKey($lookupSku);
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('macy', $key, function () use ($lookupSku) {
                    if (!Schema::hasTable('macy_sku_competitors')) {
                        return null;
                    }
                    $lmp = MacySkuCompetitor::getLowestPriceForSku($lookupSku, 'macy');
                    if (!$lmp) {
                        return null;
                    }
                    $total = floatval($lmp->total_price ?? 0);
                    if ($total <= 0) {
                        $total = floatval($lmp->price ?? 0) + floatval($lmp->shipping_cost ?? 0);
                    }
                    return $total > 0 ? round($total, 2) : null;
                });
            };
            $resolveReverbLmpPrice = function (?string $lookupSku = null) use ($cacheLmp) {
                $key = ReverbSkuCompetitor::normalizeSkuKey($lookupSku);
                if ($key === '') {
                    return null;
                }
                return $cacheLmp('reverb', $key, function () use ($lookupSku) {
                    if (!Schema::hasTable('reverb_sku_competitors')) {
                        return null;
                    }
                    $lmp = ReverbSkuCompetitor::getLowestPriceForSku($lookupSku, 'reverb');
                    if (!$lmp) {
                        return null;
                    }
                    $total = floatval($lmp->total_price ?? 0);
                    if ($total <= 0) {
                        $total = floatval($lmp->price ?? 0) + floatval($lmp->shipping_cost ?? 0);
                    }
                    return $total > 0 ? round($total, 2) : null;
                });
            };

            // Temu LMP: only load rows for this SKU's LMP group (never TemuLmp::all())
            $temuLmpByNormalizedSku = [];
            $temuLmpSkuGroupService = null;
            try {
                if (Schema::hasTable('temu_lmp')) {
                    $temuLmpSkuGroupService = app(LmpSkuGroupService::class);
                    $temuLmpSkuGroupService->prepareForSkus([$fullSku, $sku]);
                    $temuMemberSkus = [$fullSku, $sku];
                    try {
                        $group = $temuLmpSkuGroupService->groupContaining($fullSku);
                        if (!empty($group)) {
                            $temuMemberSkus = array_merge($temuMemberSkus, $group);
                        }
                    } catch (\Throwable $e) {
                        // keep seed SKUs
                    }
                    $temuMemberSkus = array_values(array_unique(array_filter(array_map(
                        fn ($s) => trim((string) $s),
                        $temuMemberSkus
                    ))));
                    if ($temuMemberSkus !== []) {
                        // Exact SKU match first
                        foreach (TemuLmp::whereIn('sku', $temuMemberSkus)->get() as $temuLmpRow) {
                            $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
                        }
                        // Normalized fallback only for missing members (bounded OR, not full table)
                        $missingNorms = [];
                        foreach ($temuMemberSkus as $memberSku) {
                            $n = self::normalizeTemuSkuForCvr($memberSku);
                            if ($n !== '' && !isset($temuLmpByNormalizedSku[$n])) {
                                $missingNorms[$n] = true;
                            }
                        }
                        if ($missingNorms !== []) {
                            $q = TemuLmp::query();
                            $q->where(function ($qq) use ($missingNorms) {
                                foreach (array_keys($missingNorms) as $n) {
                                    $qq->orWhereRaw(
                                        'UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(sku), CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?',
                                        [$n]
                                    );
                                }
                            });
                            foreach ($q->limit(50)->get() as $temuLmpRow) {
                                $temuLmpByNormalizedSku[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch Temu LMP in breakdown: ' . $e->getMessage());
            }

            $resolveTemuLmpPrice = function (?string $lookupSku = null) use ($temuLmpByNormalizedSku, $temuLmpSkuGroupService) {
                $resolved = $this->resolveTemuLmpForSku(
                    (string) ($lookupSku ?? ''),
                    $temuLmpByNormalizedSku,
                    $temuLmpSkuGroupService
                );
                // Direct Temu LMP (raw competitor price — no Recovery calculation)
                return $resolved['price'];
            };

            // TikTok LMP: query only linked SKUs (not full tiktok_sku_competitors table)
            $tiktokLmpSkuGroupService = null;
            try {
                $tiktokLmpSkuGroupService = app(LmpSkuGroupService::class);
                $tiktokLmpSkuGroupService->prepareForSkus([$fullSku, $sku]);
            } catch (\Exception $e) {
                Log::warning('Could not init TikTok LMP groups in breakdown: ' . $e->getMessage());
            }
            $resolveTiktokLmpPrice = function (?string $lookupSku = null) use ($tiktokLmpSkuGroupService, $cacheLmp) {
                $skuVal = trim((string) ($lookupSku ?? ''));
                if ($skuVal === '') {
                    return null;
                }
                $cacheKey = TiktokSkuCompetitor::normalizeSkuKey($skuVal);
                return $cacheLmp('tiktok', $cacheKey, function () use ($skuVal, $tiktokLmpSkuGroupService) {
                    $members = [$skuVal];
                    if ($tiktokLmpSkuGroupService) {
                        try {
                            $group = $tiktokLmpSkuGroupService->groupContaining($skuVal);
                            if (!empty($group)) {
                                $members = $group;
                            }
                        } catch (\Throwable $e) {
                            // keep single-SKU fallback
                        }
                    }
                    $merged = collect();
                    foreach ($members as $linkedSku) {
                        $found = TiktokSkuCompetitor::getCompetitorsForSku((string) $linkedSku, 'tiktok');
                        if ($found->isNotEmpty()) {
                            $merged = $merged->merge($found);
                        }
                    }
                    if ($merged->isEmpty()) {
                        return null;
                    }
                    $merged = TiktokSkuCompetitor::dedupeByProductId($merged);
                    $lowest = TiktokSkuCompetitor::lowestFromCollection($merged);
                    if (!$lowest || !is_numeric($lowest->price ?? null)) {
                        return null;
                    }
                    $landed = floatval($lowest->price) + floatval($lowest->shipping_cost ?? 0);
                    return $landed > 0 ? round($landed, 2) : null;
                });
            };

            // Get LP and Ship from ProductMaster for profit calculations
            $values = $productMaster ? ($productMaster->Values ?: []) : [];
            $lp = 0;
            $ship = 0;
            $temuShip = 0;
            $ttShip = 0;
            $actWt = 0;
            
            if ($values) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") $lp = floatval($v);
                    if (strtolower($k) === "ship") $ship = floatval($v);
                    if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                    if (strtolower($k) === "tt_ship") $ttShip = floatval($v);
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
            $amazonStandardPrice = null;
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
                    // STANDARD_PRICE — same as /amazon-tabulator-view SP column
                    $stdRaw = $val['STANDARD_PRICE'] ?? null;
                    if (is_numeric($stdRaw) && (float) $stdRaw > 0) {
                        $amazonStandardPrice = round((float) $stdRaw, 2);
                    }
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
                'standard_price' => $amazonStandardPrice,
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
            
            // eBay 1 — channel Ads% on every row (same as /ebay-tabulator-view AD% / PFT%)
            $ebayData = EbayMetric::where('sku', $fullSku)->first();
            $ebay1Marketplace = MarketplacePercentage::where('marketplace', 'Ebay1')->first()
                ?? MarketplacePercentage::where('marketplace', 'Ebay')->first();
            $ebay1Margin = $ebay1Marketplace ? ($ebay1Marketplace->percentage / 100) : 0.85;
            $ebay1Price = $ebayData->ebay_price ?? 0;
            $ebay1L30 = $ebayData->ebay_l30 ?? 0;
            $ebay1GPFT = $ebay1Price > 0 ? (($ebay1Price * $ebay1Margin - $ship - $lp) / $ebay1Price) * 100 : 0;
            $ebay1AD = (float) app(ChannelMasterController::class)->getEbayMasterAdsPercent();
            $ebay1NPFT = round($ebay1GPFT - $ebay1AD, 2);
            
            $ebayDataView = EbayDataView::where('sku', $fullSku)->first();
            $ebay1Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $ebay1PushedBy = null;
            $ebay1PushedAt = null;
            if ($ebayDataView) {
                $val = is_array($ebayDataView->value) ? $ebayDataView->value : json_decode($ebayDataView->value, true);
                if (is_array($val)) {
                    $ebay1Sgpft = floatval($val['SGPFT'] ?? 0);
                    $ebay1Sprice = floatval($val['SPRICE'] ?? 0);
                    $ebay1Suggested = [
                        'sprice' => $ebay1Sprice,
                        'sgpft' => $ebay1Sgpft,
                        'sroi' => floatval($val['SROI'] ?? 0),
                        // SPFT = SGPFT − Ads% (same as /ebay-tabulator-view)
                        'spft' => ($ebay1Sprice > 0 || $ebay1Sgpft != 0)
                            ? round($ebay1Sgpft - $ebay1AD, 2)
                            : floatval($val['SPFT'] ?? 0),
                    ];
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
                'tacos_ch' => $ebay1AD,
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
            
            // eBay 2 — fixed 85% margin + channel Ads% on every row (same as /ebay2-tabulator-view)
            $ebay2Margin = 0.85;
            $ebay2Price = $ebay2Data->ebay_price ?? 0;
            $ebay2L30 = $ebay2Data->ebay_l30 ?? 0;
            // Same normal ship as eBay 1 (Values['ship'], not ebay2_ship)
            $ebay2GPFT = $ebay2Price > 0
                ? round((($ebay2Price * $ebay2Margin - $lp - $ship) / $ebay2Price) * 100, 2)
                : 0;
            $ebay2AD = (float) app(ChannelMasterController::class)->getEbaytwoMasterAdsPercent();
            $ebay2NPFT = round($ebay2GPFT - $ebay2AD, 2);
            
            $ebay2DataView = $ebay2Data ? EbayTwoDataView::where('sku', $ebay2Data->sku)->first() : null;
            $ebay2Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($ebay2DataView) {
                $val = is_array($ebay2DataView->value) ? $ebay2DataView->value : json_decode($ebay2DataView->value, true);
                if (is_array($val)) {
                    $ebay2Sprice = floatval($val['SPRICE'] ?? 0);
                    $ebay2Sgpft = floatval($val['SGPFT'] ?? 0);
                    // Recalc SGPFT when SPRICE exists but SGPFT missing (pricing page does the same)
                    if ($ebay2Sprice > 0 && $ebay2Sgpft == 0) {
                        $ebay2Sgpft = round((($ebay2Sprice * $ebay2Margin - $lp - $ship) / $ebay2Sprice) * 100, 2);
                    }
                    $ebay2Suggested = [
                        'sprice' => $ebay2Sprice,
                        'sgpft' => $ebay2Sgpft,
                        'sroi' => floatval($val['SROI'] ?? 0),
                        // SPFT = SGPFT − Ads% (same as /ebay2-tabulator-view)
                        'spft' => $ebay2Sprice > 0 ? round($ebay2Sgpft - $ebay2AD, 2) : floatval($val['SPFT'] ?? 0),
                    ];
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
                'tacos_ch' => $ebay2AD,
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

            // eBay 3 — fixed 85% margin + channel Ads% on every row (same as /ebay3-tabulator-view)
            $ebay3SkuNorm = strtoupper(trim((string) $fullSku));
            $ebay3Data = Ebay3Metric::where('sku', $fullSku)->first()
                ?? Ebay3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [$ebay3SkuNorm])->first();
            $ebay3Margin = 0.85;
            $ebay3Price = $ebay3Data->ebay_price ?? 0;
            $ebay3L30 = $ebay3Data->ebay_l30 ?? 0;
            $ebay3GPFT = $ebay3Price > 0
                ? round((($ebay3Price * $ebay3Margin - $ship - $lp) / $ebay3Price) * 100, 2)
                : 0;
            $ebay3Ctrl = app(ChannelMasterController::class);
            $ebay3AD = method_exists($ebay3Ctrl, 'getEbaythreeMasterAdsPercent')
                ? (float) $ebay3Ctrl->getEbaythreeMasterAdsPercent()
                : 0.0;
            $ebay3NPFT = round($ebay3GPFT - $ebay3AD, 2);
            $ebay3ListedSku = $ebay3Data ? (string) ($ebay3Data->sku ?: $fullSku) : null;

            $ebay3DataView = null;
            if ($ebay3ListedSku) {
                $ebay3DataView = EbayThreeDataView::where('sku', $ebay3ListedSku)->first()
                    ?? EbayThreeDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($ebay3ListedSku))])->first()
                    ?? EbayThreeDataView::where('sku', $fullSku)->first();
            }
            $ebay3Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $ebay3PushedBy = null;
            $ebay3PushedAt = null;
            if ($ebay3DataView) {
                $val = is_array($ebay3DataView->value) ? $ebay3DataView->value : json_decode($ebay3DataView->value, true);
                if (is_array($val)) {
                    $ebay3Sprice = floatval($val['SPRICE'] ?? 0);
                    $ebay3Sgpft = floatval($val['SGPFT'] ?? 0);
                    // Recalc SGPFT when SPRICE exists but SGPFT missing (same as pricing page)
                    if ($ebay3Sprice > 0 && $ebay3Sgpft == 0) {
                        $ebay3Sgpft = round((($ebay3Sprice * $ebay3Margin - $lp - $ship) / $ebay3Sprice) * 100, 2);
                    }
                    $ebay3Suggested = [
                        'sprice' => $ebay3Sprice,
                        'sgpft' => $ebay3Sgpft,
                        'sroi' => floatval($val['SROI'] ?? 0),
                        // SPFT = SGPFT − Ads% (same as /ebay3-tabulator-view)
                        'spft' => $ebay3Sprice > 0 ? round($ebay3Sgpft - $ebay3AD, 2) : floatval($val['SPFT'] ?? 0),
                    ];
                    $ebay3PushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $ebay3PushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    if ($ebay3PushedAt) {
                        try {
                            $ebay3PushedAt = Carbon::parse($ebay3PushedAt)->format('jM');
                        } catch (\Exception $e) {
                            $ebay3PushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Ebay3',
                'sku' => $ebay3ListedSku ?: 'Not Listed',
                'price' => $ebay3Price,
                'views' => $ebay3Data ? intval($ebay3Data->views ?? 0) : null,
                'l30' => $ebay3L30,
                'gpft' => $ebay3GPFT,
                'ad' => $ebay3AD,
                'tacos_ch' => $ebay3AD,
                'npft' => $ebay3NPFT,
                'is_listed' => $ebay3Data ? true : false,
                'sprice' => $ebay3Suggested['sprice'],
                'sgpft' => $ebay3Suggested['sgpft'],
                'sroi' => $ebay3Suggested['sroi'],
                'spft' => $ebay3Suggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $ebay3Margin,
                'pushed_by' => $ebay3PushedBy,
                'pushed_at' => $ebay3PushedAt,
                'buyer_link' => ($ebay3Links = $getListingLinks(EbayThreeListingStatus::class, $ebay3ListedSku ?: $fullSku))[0],
                'seller_link' => $ebay3Links[1],
            ];

            // Temu breakdown row is built later (API metrics + ads sheet) — same as /temu-decrease

            // Doba — same as /doba-tabulator (with-ship):
            // Price = anticipated_income; margin from MarketplacePercentage (default 95%);
            // GPFT = NPFT (Ads% = 0); ship included in formulas.
            $dobaSkuNorm = strtoupper(trim((string) $fullSku));
            $dobaMetric = DobaMetric::where('sku', $fullSku)->first()
                ?? DobaMetric::whereRaw('UPPER(TRIM(sku)) = ?', [$dobaSkuNorm])->first();

            $dobaMarketplace = MarketplacePercentage::where('marketplace', 'Doba')->first();
            $dobaPercentage = $dobaMarketplace ? ((float) $dobaMarketplace->percentage / 100) : 0.95;

            $dobaPrice = $dobaMetric ? floatval($dobaMetric->anticipated_income ?? 0) : 0;

            // Same as DobaController PFT_percentage / ROI_percentage (with-ship)
            $dobaGPFT = $dobaPrice > 0
                ? round((($dobaPrice * $dobaPercentage - $lp - $ship) / $dobaPrice) * 100, 2)
                : 0;
            $dobaNPFT = $dobaGPFT; // no ads — same as /doba-tabulator
            
            $hasDobaData = (bool) $dobaMetric && ($dobaPrice > 0 || intval($dobaMetric->quantity_l30 ?? 0) > 0);
            
            // SPRICE from doba_data_view (with-ship table — same as /doba-tabulator)
            $dobaDataView = DobaDataView::where('sku', $fullSku)->first()
                ?? DobaDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$dobaSkuNorm])->first();
            $dobaSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $dobaPushedBy = null;
            $dobaPushedAt = null;
            if ($dobaDataView) {
                $val = is_array($dobaDataView->value) ? $dobaDataView->value : json_decode($dobaDataView->value, true);
                if (is_array($val)) {
                    $dobaSprice = floatval($val['SPRICE'] ?? 0);
                    // Live SGPFT/SPFT from SPRICE (same formula as /doba-tabulator frontend)
                    $dobaSgpft = $dobaSprice > 0
                        ? round((($dobaSprice * $dobaPercentage - $lp - $ship) / $dobaSprice) * 100, 2)
                        : 0;
                    $dobaSuggested = [
                        'sprice' => $dobaSprice,
                        'sgpft' => $dobaSgpft,
                        // SPFT = SGPFT (no ads)
                        'spft' => $dobaSgpft,
                        'sroi' => ($lp > 0 && $dobaSprice > 0)
                            ? round((($dobaSprice * $dobaPercentage - $lp - $ship) / $lp) * 100, 2)
                            : floatval($val['SROI'] ?? 0),
                    ];
                    $dobaPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $dobaPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
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
                'l30' => $dobaMetric ? intval($dobaMetric->quantity_l30 ?? 0) : 0,
                'gpft' => $dobaGPFT,
                'ad' => 0,
                'tacos_ch' => 0,
                'npft' => $dobaNPFT,
                // GROI/NROI = ROI_percentage from /doba-tabulator (no ads)
                'groi' => ($lp > 0 && $dobaPrice > 0)
                    ? round((($dobaPrice * $dobaPercentage - $lp - $ship) / $lp) * 100, 2)
                    : 0,
                'nroi' => ($lp > 0 && $dobaPrice > 0)
                    ? round((($dobaPrice * $dobaPercentage - $lp - $ship) / $lp) * 100, 2)
                    : 0,
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

            // Fetch TikTok data — same sources/formulas as /tiktok-pricing (TikTokPricingController)
            $tiktokData = TikTokProduct::where('sku', strtoupper($fullSku))->first();
            $skuNormTt = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $fullSku)));

            // Margin: TiktokShop first, fallback TikTok (legacy)
            $tiktokMarketplace = MarketplacePercentage::where('marketplace', 'TiktokShop')
                ->orWhere('marketplace', 'TikTok')
                ->first();
            $tiktokPercentage = $tiktokMarketplace ? ($tiktokMarketplace->percentage / 100) : 0.80;

            // L30 from tiktok_orders — last 30 California calendar days
            $soldMap = \App\Models\TiktokOrder::soldQtyL30([strtoupper($fullSku)], 30);
            $tiktokL30 = (int) ($soldMap[strtoupper($fullSku)] ?? 0);

            $ttPrice = $tiktokData ? floatval($tiktokData->price ?? 0) : 0;

            // T views = (video_views || views) + ads_views + affl_views; shop data view may override components
            $ttVideoViews = $tiktokData ? intval($tiktokData->video_views ?? $tiktokData->views ?? 0) : 0;
            $ttAdsViews = $tiktokData ? intval($tiktokData->ads_views ?? 0) : 0;
            $ttAfflViews = $tiktokData ? intval($tiktokData->affl_views ?? 0) : 0;

            // SPRICE from tiktok_shop_data_views (primary); fallback reverb_view_data — same as /tiktok-pricing
            $tiktokSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $ttShopRow = TiktokShopDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$skuNormTt])->first();
            $tiktokValArr = [];
            if ($ttShopRow) {
                $tiktokValArr = is_array($ttShopRow->value)
                    ? $ttShopRow->value
                    : (json_decode($ttShopRow->value ?? '{}', true) ?: []);
                if (array_key_exists('video_views', $tiktokValArr)) {
                    $ttVideoViews = intval($tiktokValArr['video_views']);
                }
                if (array_key_exists('ads_views', $tiktokValArr)) {
                    $ttAdsViews = intval($tiktokValArr['ads_views']);
                }
                if (array_key_exists('affl_views', $tiktokValArr)) {
                    $ttAfflViews = intval($tiktokValArr['affl_views']);
                }
                $tiktokSuggested = [
                    'sprice' => isset($tiktokValArr['SPRICE']) ? floatval($tiktokValArr['SPRICE']) : 0,
                    'sgpft' => isset($tiktokValArr['SGPFT']) ? floatval($tiktokValArr['SGPFT']) : 0,
                    'sroi' => isset($tiktokValArr['SROI']) ? floatval(str_replace('%', '', (string) $tiktokValArr['SROI'])) : 0,
                    'spft' => isset($tiktokValArr['SPFT']) ? floatval(str_replace('%', '', (string) $tiktokValArr['SPFT'])) : 0,
                ];
            }
            if (!$ttShopRow) {
                $reverbFallback = ReverbViewData::where('sku', $fullSku)->first();
                if ($reverbFallback) {
                    $rv = is_array($reverbFallback->values) ? $reverbFallback->values : [];
                    $tiktokSuggested = [
                        'sprice' => isset($rv['SPRICE']) ? floatval($rv['SPRICE']) : 0,
                        'sgpft' => isset($rv['SGPFT']) ? floatval($rv['SGPFT']) : 0,
                        'sroi' => isset($rv['SROI']) ? floatval(str_replace('%', '', (string) ($rv['SROI'] ?? 0))) : 0,
                        'spft' => isset($rv['SPFT']) ? floatval(str_replace('%', '', (string) ($rv['SPFT'] ?? 0))) : 0,
                    ];
                }
            }

            $ttViews = $ttVideoViews + $ttAdsViews + $ttAfflViews;

            // GPFT% uses tt_ship only (no fallback to ship) — same as TikTok 1 pricing
            $ttGPFT = $ttPrice > 0
                ? round((($ttPrice * $tiktokPercentage - $lp - $ttShip) / $ttPrice) * 100, 2)
                : 0;

            // SKU TACOS% from tiktok_campaign_reports (campaign_name = SKU), L30+L7 Product card — same as /tiktok-pricing
            $ttSpend = 0.0;
            try {
                $skuUpperTt = strtoupper(trim($fullSku));
                $ttSpendL30 = (float) TiktokCampaignReport::where('report_range', 'L30')
                    ->where('creative_type', 'Product card')
                    ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                    ->whereNotNull('product_id')->where('product_id', '!=', '')
                    ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpperTt])
                    ->sum('cost');
                $ttSpendL7 = (float) TiktokCampaignReport::where('report_range', 'L7')
                    ->where('creative_type', 'Product card')
                    ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                    ->whereNotNull('product_id')->where('product_id', '!=', '')
                    ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpperTt])
                    ->sum('cost');
                // Match /tiktok-pricing spend: L30 cost + L7 cost
                $ttSpend = $ttSpendL30 + $ttSpendL7;
            } catch (\Throwable $e) {
                Log::warning('TikTok breakdown TACOS fetch failed for ' . $fullSku . ': ' . $e->getMessage());
            }
            $ttSalesValue = $tiktokL30 * $ttPrice;
            $ttTacos = $ttSalesValue > 0
                ? round(($ttSpend / $ttSalesValue) * 100, 2)
                : ($ttSpend > 0 ? 100.0 : 0.0);
            $ttNPFT = round($ttGPFT - $ttTacos, 2);
            // SPFT = SGPFT − TACOS% (recomputed like /tiktok-pricing)
            if (($tiktokSuggested['sprice'] ?? 0) > 0 || ($tiktokSuggested['sgpft'] ?? 0) != 0) {
                $tiktokSuggested['spft'] = round(floatval($tiktokSuggested['sgpft']) - $ttTacos, 2);
            }

            // Buyer / Seller links — UPPER(TRIM) match like /tiktok-pricing
            $ttBuyerLink = null;
            $ttSellerLink = null;
            try {
                $ttLinkRow = TiktokShopListingStatus::whereRaw('UPPER(TRIM(sku)) = ?', [$skuNormTt])->first();
                $ttLinkVal = ($ttLinkRow && is_array($ttLinkRow->value))
                    ? $ttLinkRow->value
                    : ($ttLinkRow ? (json_decode($ttLinkRow->value, true) ?: []) : []);
                $ttBuyerLink = $ttLinkVal['buyer_link'] ?? null;
                $ttSellerLink = $ttLinkVal['seller_link'] ?? null;
            } catch (\Throwable $e) {
                // leave null
            }

            // Listed = present in tiktok_products (same Missing logic as /tiktok-pricing)
            $hasTikTokData = (bool) $tiktokData;

            $breakdownData[] = [
                'marketplace' => 'TikTok',
                'sku' => $hasTikTokData ? $fullSku : 'Not Listed',
                'price' => $ttPrice,
                'views' => $hasTikTokData ? $ttViews : null,
                'l30' => $tiktokL30,
                'gpft' => $ttGPFT,
                'ad' => $ttTacos,
                'tacos_ch' => $ttTacos,
                'npft' => $ttNPFT,
                'is_listed' => $hasTikTokData,
                'sprice' => $tiktokSuggested['sprice'],
                'sgpft' => $tiktokSuggested['sgpft'],
                'sroi' => $tiktokSuggested['sroi'],
                'spft' => $tiktokSuggested['spft'],
                'lp' => $lp,
                'ship' => $ttShip,
                'margin' => $tiktokPercentage,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => $ttBuyerLink,
                'seller_link' => $ttSellerLink,
            ];

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

            // Add Shopify B2B — L30 from shopify_b2b_daily_data (same as /shopify-b2b/daily-sales)
            $sb2bPrice = $shopifySku ? floatval($shopifySku->b2b_price ?? 0) : 0;
            $sb2bL30 = (int) (DB::table('shopify_b2b_daily_data')
                ->where('sku', $fullSku)
                ->where('period', 'l30')
                ->where('financial_status', '!=', 'refunded')
                ->sum('quantity') ?? 0);
            
            $sb2bMarketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2B')->first();
            $sb2bMargin = $sb2bMarketplace ? ($sb2bMarketplace->percentage / 100) : 0.95;
            
            // GPFT% / GROI% — include Ship (aligned with SPRICE = Price×0.75 − Ship)
            $sb2bGPFT = $sb2bPrice > 0 ? (($sb2bPrice * $sb2bMargin - $lp - $ship) / $sb2bPrice) * 100 : 0;
            $sb2bNPFT = $sb2bGPFT;

            // Always calculate SPRICE = (Price × 0.75) − Ship
            $sb2bCalcSprice = $sb2bPrice > 0
                ? max(0.01, round(($sb2bPrice * 0.75) - $ship, 2))
                : 0;
            
            $sb2bDataView = ShopifyB2BDataView::where('sku', $fullSku)->first();
            $sb2bSuggested = ['sprice' => $sb2bCalcSprice, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $sb2bPushedBy = null;
            $sb2bPushedAt = null;
            if ($sb2bDataView) {
                $val = is_array($sb2bDataView->value) ? $sb2bDataView->value : json_decode($sb2bDataView->value, true);
                if (is_array($val)) {
                    // Keep push metadata; SPRICE itself is always recalculated from Price/Ship
                    $sb2bSuggested['sgpft'] = floatval($val['SGPFT'] ?? 0);
                    $sb2bSuggested['sroi'] = floatval($val['SROI'] ?? 0);
                    $sb2bSuggested['spft'] = floatval($val['SPFT'] ?? 0);
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
            // Recalculate SGPFT/SROI from always-calculated SPRICE for modal display
            if ($sb2bCalcSprice > 0) {
                $sb2bGross = ($sb2bCalcSprice * $sb2bMargin) - $lp - $ship;
                $sb2bSuggested['sgpft'] = ($sb2bGross / $sb2bCalcSprice) * 100;
                $sb2bSuggested['spft'] = $sb2bSuggested['sgpft'];
                $sb2bSuggested['sroi'] = $lp > 0 ? ($sb2bGross / $lp) * 100 : 0;
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
                'sprice' => $sb2bCalcSprice,
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

            // Macy — same sources/formulas as /macys-pricing:
            // MC Price = macys_price_data.price (sheet) if present, else macy_products.price
            $macySkuNorm = strtoupper(trim(preg_replace('/\s+/u', ' ', str_replace("\u{00a0}", ' ', (string) $fullSku))));
            $macyProduct = MacyProduct::where('sku', $fullSku)->first()
                ?? MacyProduct::whereRaw('UPPER(TRIM(REPLACE(sku, CHAR(160), \' \'))) = ?', [$macySkuNorm])->first();
            $macySheetRow = MacysPriceData::where('sku', $fullSku)->first()
                ?? MacysPriceData::whereRaw('UPPER(TRIM(sku)) = ?', [$macySkuNorm])->first();

            $macyMarketplace = MarketplacePercentage::where('marketplace', 'Macys')->first();
            $macyPercentage = $macyMarketplace ? ((float) $macyMarketplace->percentage / 100) : 0.80;

            $macyPrice = $macySheetRow
                ? floatval($macySheetRow->price ?? 0)
                : ($macyProduct ? floatval($macyProduct->price ?? 0) : 0);

            $macyGPFT = $macyPrice > 0
                ? round((($macyPrice * $macyPercentage - $lp - $ship) / $macyPrice) * 100, 2)
                : 0;

            $macyL30 = $macyProduct ? intval($macyProduct->m_l30 ?? 0) : 0;
            $macyNPFT = $macyGPFT;

            // Get Macy suggested data from macy_data_view
            $macyDataView = MacyDataView::where('sku', $fullSku)->first()
                ?? MacyDataView::whereRaw('UPPER(TRIM(sku)) = ?', [$macySkuNorm])->first();
            $macySuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($macyDataView) {
                $val = is_array($macyDataView->value) ? $macyDataView->value : json_decode($macyDataView->value, true);
                if (is_array($val)) {
                    $macySuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                      'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                }
            }

            $hasMacyData = ($macyProduct || $macySheetRow) && ($macyL30 > 0 || $macyPrice > 0);
            
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

            // Reverb — same sources/formulas as /reverb-pricing:
            // RV Price = reverb_products.price via normalized SKU; PFT = GPFT − channel Ads%.
            $reverbNormKey = ReverbProduct::normalizeSkuForLookup($fullSku);
            $reverbLookup = ReverbProduct::buildLookupByNormalizedSku([$fullSku, $sku]);
            $reverbProduct = ($reverbNormKey !== '' && isset($reverbLookup[$reverbNormKey]))
                ? $reverbLookup[$reverbNormKey]
                : null;

            $reverbMarketplace = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $reverbPercentage = $reverbMarketplace ? ((float) $reverbMarketplace->percentage / 100) : 0.85;
            $reverbAdsPct = (float) $getChannelAdsPercent('Reverb');

            $rvPrice = $reverbProduct ? floatval($reverbProduct->price ?? 0) : 0;
            $rvGPFT = $rvPrice > 0
                ? round((($rvPrice * $reverbPercentage - $lp - $ship) / $rvPrice) * 100, 2)
                : 0;
            $reverbL30 = $reverbProduct ? intval($reverbProduct->r_l30 ?? 0) : 0;
            $rvNPFT = round($rvGPFT - $reverbAdsPct, 2);

            // SPRICE from reverb_view_data (normalized SKU, same as /reverb-pricing)
            $reverbViewLookup = ReverbProduct::buildModelLookupByNormalizedSku(ReverbViewData::class, [$fullSku, $sku]);
            $reverbDataView = ($reverbNormKey !== '' && isset($reverbViewLookup[$reverbNormKey]))
                ? $reverbViewLookup[$reverbNormKey]
                : null;
            $reverbSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $reverbViewVal = [];
            if ($reverbDataView) {
                $reverbViewVal = is_array($reverbDataView->values) ? $reverbDataView->values :
                       (json_decode($reverbDataView->values, true) ?: []);
                if (is_array($reverbViewVal)) {
                    $rvSgpft = floatval($reverbViewVal['SGPFT'] ?? 0);
                    $rvSprice = floatval($reverbViewVal['SPRICE'] ?? 0);
                    // SPFT = SGPFT − Ads% (same as /reverb-pricing); fall back to stored SPFT
                    $rvSpft = ($rvSprice > 0 || $rvSgpft != 0)
                        ? round($rvSgpft - $reverbAdsPct, 2)
                        : floatval(str_replace('%', '', $reverbViewVal['SPFT'] ?? '0'));
                    $reverbSuggested = [
                        'sprice' => $rvSprice,
                        'sgpft' => $rvSgpft,
                        'sroi' => floatval(str_replace('%', '', $reverbViewVal['SROI'] ?? '0')),
                        'spft' => $rvSpft,
                    ];
                }
            }

            $listingStateRv = strtolower(trim((string) ($reverbProduct->listing_state ?? '')));
            $isActiveReverbListing = $reverbProduct && (
                ($listingStateRv === '' && !empty($reverbProduct->reverb_listing_id))
                || in_array($listingStateRv, ['live', 'active'], true)
            );
            $spricePushedRv = (($reverbViewVal['SPRICE_STATUS'] ?? null) === 'pushed');
            $hasReverbData = $rvPrice > 0 || $reverbL30 > 0 || $isActiveReverbListing || $spricePushedRv;

            $breakdownData[] = [
                'marketplace' => 'Reverb',
                'sku' => $hasReverbData ? $fullSku : 'Not Listed',
                'price' => $rvPrice,
                'views' => $reverbProduct ? intval($reverbProduct->views ?? 0) : null,
                'l30' => $reverbL30,
                'gpft' => $rvGPFT,
                'ad' => $reverbAdsPct,
                'tacos_ch' => $reverbAdsPct,
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

            // Add Temu — exact sources/formulas as /temu-decrease
            // Price/views/L30: Temu API (temu_metrics + temu_orders); Ads%: ads sheet (temu_campaign_reports)
            $temuMargin = TemuShopifySalesService::temuMarginDecimal();
            $temu2Marketplace = MarketplacePercentage::where('marketplace', 'TemuTwo')->first();
            $temu2Margin = $temu2Marketplace ? ($temu2Marketplace->percentage / 100) : $temuMargin;
            $normalizedSku = self::normalizeTemuSkuForCvr($fullSku);
            $temuPriceMapOne = [];
            if (Schema::hasTable('temu_metrics')) {
                $temuMetricColsOne = ['sku', 'base_price', 'goods_id', 'quantity'];
                if (Schema::hasColumn('temu_metrics', 'product_clicks_l30')) {
                    $temuMetricColsOne[] = 'product_clicks_l30';
                }
                $temuPriceMapOne = $this->buildTemuPricingMapForProductSkus(
                    TemuMetric::query()->get($temuMetricColsOne),
                    [$fullSku]
                );
            }
            $temuPricing = $temuPriceMapOne[$fullSku] ?? null;
            if (!$temuPricing && Schema::hasTable('temu_metrics')) {
                $temuPricing = TemuMetric::where(function ($query) use ($fullSku, $normalizedSku) {
                    $query->where('sku', $fullSku)->orWhere('sku', $normalizedSku);
                })->first();
            }
            $temuPrice = 0;
            if ($temuPricing) {
                $basePrice = $temuPricing->base_price ?? 0;
                // FB Prc — same +$2.99 rule as /temu-decrease
                $temuPrice = $basePrice > 0
                    ? TemuShopifySalesService::computeFbPrice((float) $basePrice, 1)
                    : 0;
            }
            // Views: temu_metrics.product_clicks_l30 (API) — same as /temu-decrease Views
            $temuViews = $temuPricing
                ? $this->resolveTemuProductClicks($temuPricing->goods_id ?? null)
                : null;
            if (($temuViews === null || $temuViews === 0) && $temuPricing) {
                $fallbackClicks = (int) ($temuPricing->product_clicks_l30 ?? 0);
                if ($fallbackClicks > 0) {
                    $temuViews = $fallbackClicks;
                }
            }

            // L30 qty — temu_orders API window (same as /temu-decrease / temu-tabulator)
            $temuL30 = 0;
            try {
                [$apiStart, $apiEnd] = TemuShopifySalesService::channelMasterL30Window();
                $orderRows = TemuShopifySalesService::getOrdersTableRows($apiStart, $apiEnd);
                $normFull = $normalizedSku;
                $normNoSpace = str_replace(' ', '', $normFull);
                foreach ($orderRows as $row) {
                    $raw = trim((string) ($row['contribution_sku'] ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    $n = self::normalizeTemuSkuForCvr($raw);
                    if ($n === $normFull || str_replace(' ', '', $n) === $normNoSpace) {
                        $temuL30 += (int) ($row['quantity_purchased'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CVR breakdown Temu L30 (temu_orders / temu-tabulator): ' . $e->getMessage());
            }

            // GPFT% — ((FB × margin − LP − temu_ship) / FB) × 100
            $temuGPFT = $temuPrice > 0
                ? round((($temuPrice * $temuMargin - $lp - $temuShip) / $temuPrice) * 100, 2)
                : 0;

            // Ads%: aggregate badge Ads% on every row (~2.6%) — same as /temu-decrease
            // Exception: spend > 0 and L30 = 0 → 100%
            [$brSpendByGid, $brSpendBySku, $brSpendBySkuLoose] = $this->loadTemuCampaignSpendIndexes('L30');
            $temuAdSpendBr = $this->lookupTemuCampaignSpend(
                $temuPricing ? ($temuPricing->goods_id ?? null) : null,
                $fullSku,
                $brSpendByGid,
                $brSpendBySku,
                $brSpendBySkuLoose
            );
            $temuAggregateAdsBr = $this->resolveTemuAggregateAdsPercent('L30');
            if ($temuAdSpendBr > 0 && $temuL30 == 0) {
                $temuADS = 100.0;
            } else {
                $temuADS = round($temuAggregateAdsBr, 2);
            }
            // NPFT% = GPFT% − aggregate Ads% (do not subtract when Ads% = 100)
            $temuNPFT = ($temuADS == 100) ? $temuGPFT : round($temuGPFT - $temuADS, 2);

            // SPRICE: /temu-decrease stores lowercase sprice (+ sgprft_percent / sroi_percent)
            $temuDataView = TemuDataView::where(function ($query) use ($fullSku, $normalizedSku) {
                $query->where('sku', $fullSku)->orWhere('sku', $normalizedSku);
            })->first();
            $temuSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($temuDataView) {
                $val = is_array($temuDataView->value) ? $temuDataView->value : json_decode($temuDataView->value, true);
                if (is_array($val)) {
                    $temuSprice = floatval($val['sprice'] ?? $val['SPRICE'] ?? 0);
                    // Profit = (Sprice × 0.80) − temu_ship − LP
                    $temuProfit = $temuSprice * 0.80 - $lp - $temuShip;
                    $temuSgpft = $temuSprice > 0 ? round(($temuProfit / $temuSprice) * 100, 2) : 0;
                    $temuSroi = $lp > 0 ? round(($temuProfit / $lp) * 100, 2) : 0;
                    $temuSpft = ($temuADS == 100) ? $temuSgpft : round($temuSgpft - $temuADS, 2);
                    $temuSuggested = [
                        'sprice' => $temuSprice,
                        'sgpft' => $temuSgpft,
                        'sroi' => $temuSroi,
                        'spft' => $temuSpft,
                    ];
                }
            }

            $breakdownData[] = [
                'marketplace' => 'Temu',
                'sku' => $temuPricing ? $fullSku : 'Not Listed',
                'price' => $temuPrice,
                'views' => $temuViews,
                'l30' => $temuL30,
                'gpft' => $temuGPFT,
                'ad' => $temuADS,
                'tacos_ch' => $temuADS,
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

            // Temu2 — same sources as /temu2-decrease
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
                    $temu2AllPricing = Temu2Metric::query()->get(['sku', 'base_price', 'goods_id']);
                    $t2PriceMap = $this->buildTemu2PricingMapForProductSkus($temu2AllPricing, [$fullSku]);
                    $temu2PricingRow = $t2PriceMap[$fullSku] ?? null;
                    if ($temu2PricingRow) {
                        $temu2BaseBr = $temu2PricingRow->base_price ?? 0;
                        $temu2PriceBr = $temu2BaseBr > 0 ? ($temu2BaseBr <= 26.99 ? $temu2BaseBr + 2.99 : $temu2BaseBr) : 0;
                    }
                    $temu2GPFTBr = $temu2PriceBr > 0
                        ? round((($temu2PriceBr * $temu2Margin - $lp - $temuShip) / $temu2PriceBr) * 100, 2)
                        : 0;
                    // NPFT recalculated below after channel Ads% (AMM / temu2_campaign_reports)
                    $temu2NPFTBr = $temu2GPFTBr;
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
                            $suggSp = floatval($v2d['sprice'] ?? $v2d['SPRICE'] ?? 0);
                            // Profit = (Sprice × 0.80) − temu_ship − LP (Temu2 same as Temu)
                            $temu2Profit = $suggSp * 0.80 - $lp - $temuShip;
                            $temu2Sgpft = $suggSp > 0 ? round(($temu2Profit / $suggSp) * 100, 2) : 0;
                            $temu2Sroi = $lp > 0 ? round(($temu2Profit / $lp) * 100, 2) : 0;
                            $temu2Suggested = [
                                'sprice' => $suggSp,
                                'sgpft' => $temu2Sgpft,
                                'sroi' => $temu2Sroi,
                                'spft' => $temu2Sgpft, // Temu2: no ads
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
            // Temu 2: no Ads% / NPFT-from-ads (same as /temu2-decrease)
            $temu2NPFTBr = round($temu2GPFTBr, 2);
            $breakdownData[] = [
                'marketplace' => 'Temu2',
                'sku' => $temu2HasListSignal ? $fullSku : 'Not Listed',
                'price' => $temu2PriceBr,
                'views' => $temu2ViewsBr,
                'l30' => $temu2L30Br,
                'gpft' => $temu2GPFTBr,
                'ad' => 0,
                'tacos_ch' => 0,
                'npft' => $temu2NPFTBr,
                'is_listed' => $temu2HasListSignal,
                'sprice' => $temu2Suggested['sprice'],
                'sgpft' => $temu2Suggested['sgpft'],
                'sroi' => $temu2Suggested['sroi'],
                'spft' => $temu2Suggested['spft'],
                'lp' => $lp,
                'ship' => $temuShip,
                'margin' => $temu2Margin,
                'pushed_by' => null,
                'pushed_at' => null,
                'buyer_link' => $temu2Buyer,
                'seller_link' => $temu2Seller,
            ];

            // NOTE: Macy is added earlier as 'MACY' with enhanced suggested data (line ~1500)

            // BestBuy — same sources/formulas as /bestbuy-pricing:
            // BB Price = bestbuy_price_data (sheet, UPPER sku) if present, else bestbuy_usa_products.price
            $bbSkuUpper = strtoupper(trim((string) $fullSku));
            $bestbuyProduct = BestbuyUsaProduct::where('sku', $fullSku)->first()
                ?? BestbuyUsaProduct::whereRaw('UPPER(TRIM(sku)) = ?', [$bbSkuUpper])->first();
            $bestbuySheetRow = BestbuyPriceData::where('sku', $bbSkuUpper)->first()
                ?? BestbuyPriceData::whereRaw('UPPER(TRIM(sku)) = ?', [$bbSkuUpper])->first();

            $bestbuyMarketplace = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
            $bestbuyMargin = $bestbuyMarketplace ? ((float) $bestbuyMarketplace->percentage / 100) : 0.80;

            $bestbuyPrice = $bestbuySheetRow
                ? floatval($bestbuySheetRow->price ?? 0)
                : ($bestbuyProduct ? floatval($bestbuyProduct->price ?? 0) : 0);
            $bestbuyL30 = $bestbuyProduct ? intval($bestbuyProduct->m_l30 ?? 0) : 0;
            $bestbuyGPFT = $bestbuyPrice > 0
                ? round((($bestbuyPrice * $bestbuyMargin - $lp - $ship) / $bestbuyPrice) * 100, 2)
                : 0;
            $bestbuyNPFT = $bestbuyGPFT;

            $bestbuyDataView = BestbuyUSADataView::where('sku', $fullSku)->first()
                ?? BestbuyUSADataView::whereRaw('UPPER(TRIM(sku)) = ?', [$bbSkuUpper])->first();
            $bestbuySuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($bestbuyDataView) {
                $val = is_array($bestbuyDataView->value) ? $bestbuyDataView->value : json_decode($bestbuyDataView->value, true);
                if (is_array($val)) {
                    $bestbuySuggested = ['sprice' => floatval($val['SPRICE'] ?? 0), 'sgpft' => floatval($val['SGPFT'] ?? 0),
                                         'sroi' => floatval($val['SROI'] ?? 0), 'spft' => floatval($val['SPFT'] ?? 0)];
                }
            }

            $hasBestbuyData = ($bestbuyProduct || $bestbuySheetRow) && ($bestbuyL30 > 0 || $bestbuyPrice > 0);

            $breakdownData[] = [
                'marketplace' => 'BestBuy',
                'sku' => $hasBestbuyData ? $fullSku : 'Not Listed',
                'price' => $bestbuyPrice,
                'views' => null, // BestBuy has no views metric
                'l30' => $bestbuyL30,
                'gpft' => $bestbuyGPFT,
                'ad' => 0,
                'tacos_ch' => 0,
                'npft' => $bestbuyNPFT,
                'is_listed' => $hasBestbuyData,
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

            // Tiendamia excluded from this page (not shown in OV L30 breakdown / SW L30)

            // Shein — same sources/formulas as /shein-pricing (API)
            $sheinMarketplacePerc = MarketplacePercentage::where('marketplace', 'Shein')->first();
            $sheinMarginBd = $sheinMarketplacePerc ? ((float) $sheinMarketplacePerc->percentage / 100) : 1.00;
            $sheinNormBd = $this->normalizeSheinSkuForCvr((string) $fullSku);
            $sheinPricingRowBd = null;
            $sheinL30Bd = 0;
            try {
                if (Schema::hasTable('shein_pricing_prices') && $sheinNormBd !== '') {
                    $direct = \App\Models\SheinPricingPrice::where('sku', $fullSku)->first()
                        ?? \App\Models\SheinPricingPrice::whereRaw('UPPER(TRIM(sku)) = ?', [$sheinNormBd])->first();
                    if ($direct && $this->normalizeSheinSkuForCvr((string) $direct->sku) === $sheinNormBd) {
                        $sheinPricingRowBd = $direct;
                    } else {
                        foreach (\App\Models\SheinPricingPrice::query()->select(['sku', 'price', 'special_offer_price', 'shein_stock'])->cursor() as $row) {
                            if ($this->normalizeSheinSkuForCvr((string) ($row->sku ?? '')) === $sheinNormBd) {
                                $sheinPricingRowBd = $row;
                                break;
                            }
                        }
                    }
                }
                if (Schema::hasTable('shein_daily_data') && $sheinNormBd !== '') {
                    $sheinL30Bd = $this->fetchSheinL30QtyForSku($fullSku);
                }
            } catch (\Exception $e) {
                Log::warning('Shein breakdown data fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            // special_offer_price only for calc (same as /shein-pricing); ship = product_master ship
            $sheinPriceBd = $sheinPricingRowBd ? floatval($sheinPricingRowBd->special_offer_price ?? 0) : 0;
            $sheinGPFTBd = $sheinPriceBd > 0
                ? round((($sheinPriceBd * $sheinMarginBd - $lp - $ship) / $sheinPriceBd) * 100, 2)
                : 0;
            $sheinNPFTBd = $sheinGPFTBd; // Ads% = 0
            $sheinSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $sheinBuyerLink = null;
            $sheinSellerLink = null;
            try {
                $sheinDataViewBd = \App\Models\SheinDataView::where('sku', $fullSku)->first()
                    ?? \App\Models\SheinDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $fullSku))])->first();
                if ($sheinDataViewBd) {
                    $val = is_array($sheinDataViewBd->value) ? $sheinDataViewBd->value : json_decode($sheinDataViewBd->value, true);
                    if (is_array($val)) {
                        $sheinSprice = floatval($val['SPRICE'] ?? $val['sprice'] ?? 0);
                        $sheinSgpft = $sheinSprice > 0
                            ? round((($sheinSprice * $sheinMarginBd - $lp - $ship) / $sheinSprice) * 100, 2)
                            : 0;
                        $sheinSuggestedBd = [
                            'sprice' => $sheinSprice,
                            'sgpft' => $sheinSgpft,
                            'spft' => $sheinSgpft, // SPFT = SGPFT (no ads)
                            'sroi' => ($lp > 0 && $sheinSprice > 0)
                                ? round((($sheinSprice * $sheinMarginBd - $lp - $ship) / $lp) * 100, 2)
                                : 0,
                        ];
                    }
                }
                [$sheinBuyerLink, $sheinSellerLink] = $getListingLinks(\App\Models\SheinListingStatus::class, $fullSku);
            } catch (\Exception $e) {
                Log::warning('Shein DataView/ListingStatus fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            // Listed when special_offer > 0 (pricing page) or has L30 / SPRICE
            $hasSheinData = $sheinPriceBd > 0 || $sheinL30Bd > 0 || ($sheinSuggestedBd['sprice'] > 0);

            $breakdownData[] = [
                'marketplace' => 'Shein',
                'sku'         => $hasSheinData ? $fullSku : 'Not Listed',
                'price'       => round($sheinPriceBd, 2),
                'views'       => null, // /shein-pricing does not track views
                'l30'         => $sheinL30Bd,
                'gpft'        => $sheinGPFTBd,
                'ad'          => 0,
                'tacos_ch'    => 0,
                'npft'        => $sheinNPFTBd,
                'is_listed'   => $hasSheinData,
                'sprice'      => $sheinSuggestedBd['sprice'],
                'sgpft'       => $sheinSuggestedBd['sgpft'],
                'sroi'        => $sheinSuggestedBd['sroi'],
                'spft'        => $sheinSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => $ship, // product_master ship — same as /shein-pricing
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

            // Add Purchasing Power — exact same sources/formulas as /purchasing-power-pricing
            $ppMarketplacePercBd = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $ppMarginBd = $ppMarketplacePercBd ? ($ppMarketplacePercBd->percentage / 100) : 0.65;
            $ppProductBd = null;
            $ppL30Bd = 0;
            $ppPriceBd = 0.0;
            $ppBuyerLink = null;
            $ppSellerLink = null;
            try {
                $ppSkuUpper = strtoupper((string) $fullSku);
                $ppProductBd = \App\Models\PurchasingPowerProduct::whereRaw('UPPER(sku) = ?', [$ppSkuUpper])->first()
                    ?? \App\Models\PurchasingPowerProduct::where('sku', $fullSku)->first();
                $ppSalesSum = \App\Models\PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
                    ->whereRaw('UPPER(offer_sku) = ?', [$ppSkuUpper])
                    ->sum('quantity');
                $ppL30Bd = $ppSalesSum !== null && $ppSalesSum !== ''
                    ? (int) $ppSalesSum
                    : (int) ($ppProductBd->m_l30 ?? 0);

                // Price: MCM OF21 first, else macys_price_data fallback (same as PP pricing page)
                $mcmPriceBd = ($ppProductBd && $ppProductBd->price !== null && $ppProductBd->price !== '')
                    ? floatval($ppProductBd->price)
                    : 0.0;
                if ($mcmPriceBd > 0) {
                    $ppPriceBd = $mcmPriceBd;
                } else {
                    $ppOfferBd = MacysPriceData::query()
                        ->where(function ($q) use ($ppSkuUpper, $fullSku) {
                            $q->whereRaw('UPPER(sku) = ?', [$ppSkuUpper])
                                ->orWhereRaw('UPPER(offer_sku) = ?', [$ppSkuUpper])
                                ->orWhere('sku', $fullSku)
                                ->orWhere('offer_sku', $fullSku);
                        })
                        ->first();
                    if ($ppOfferBd && floatval($ppOfferBd->price ?? 0) > 0) {
                        $ppPriceBd = floatval($ppOfferBd->price);
                    } else {
                        $ppPriceBd = $ppProductBd ? floatval($ppProductBd->price ?? 0) : 0;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('PP breakdown data fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            // Ship intentionally excluded from all PP formulas (same as /purchasing-power-pricing)
            $ppGPFTBd = $ppPriceBd > 0 ? (($ppPriceBd * $ppMarginBd - $lp) / $ppPriceBd) * 100 : 0;
            $ppNPFTBd = round($ppGPFTBd, 2); // Ads% = 0
            $ppSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            try {
                $ppDataViewBd = \App\Models\PurchasingPowerDataView::where('sku', $fullSku)->first()
                    ?? \App\Models\PurchasingPowerDataView::whereRaw('UPPER(sku) = ?', [strtoupper((string) $fullSku)])->first();
                if ($ppDataViewBd) {
                    $val = is_array($ppDataViewBd->value) ? $ppDataViewBd->value : json_decode($ppDataViewBd->value, true);
                    if (is_array($val)) {
                        $ppSprice = floatval($val['SPRICE'] ?? 0);
                        // Recalc suggested metrics without ship (same as PP pricing page)
                        $ppSgpft = $ppSprice > 0 ? (($ppSprice * $ppMarginBd - $lp) / $ppSprice) * 100 : floatval($val['SGPFT'] ?? 0);
                        $ppSroi = $lp > 0 && $ppSprice > 0
                            ? (($ppSprice * $ppMarginBd - $lp) / $lp) * 100
                            : floatval($val['SROI'] ?? 0);
                        $ppSuggestedBd = [
                            'sprice' => $ppSprice,
                            'sgpft'  => round($ppSgpft, 2),
                            'sroi'   => round($ppSroi, 2),
                            'spft'   => round($ppSgpft, 2), // SPFT = SGPFT (no ads)
                        ];
                        $ppBuyerLink = $val['buyer_link'] ?? null;
                        $ppSellerLink = $val['seller_link'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('PP DataView fetch skipped for SKU ' . $fullSku . ': ' . $e->getMessage());
            }
            $hasPpData = ($ppPriceBd > 0 || $ppL30Bd > 0);

            $breakdownData[] = [
                'marketplace' => 'PPower',
                'sku'         => $hasPpData ? $fullSku : 'Not Listed',
                'price'       => round($ppPriceBd, 2),
                'views'       => null, // Purchasing Power has no views metric
                'l30'         => $ppL30Bd,
                'gpft'        => round($ppGPFTBd, 2),
                'ad'          => 0,
                'tacos_ch'    => 0,
                'npft'        => $ppNPFTBd,
                'is_listed'   => $hasPpData ? true : false,
                'sprice'      => $ppSuggestedBd['sprice'],
                'sgpft'       => $ppSuggestedBd['sgpft'],
                'sroi'        => $ppSuggestedBd['sroi'],
                'spft'        => $ppSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => 0, // not used in PP formulas
                'margin'      => $ppMarginBd,
                'pushed_by'   => null,
                'pushed_at'   => null,
                'buyer_link'  => $ppBuyerLink,
                'seller_link' => $ppSellerLink,
            ];

            // TopDawg — same sources/formulas as /topdawg-pricing (API products + order L30)
            $tdMarginBd = 0.0;
            $tdPctRow = MarketplacePercentage::where('marketplace', 'TopDawg')->value('percentage');
            if ($tdPctRow !== null) {
                $tdMarginBd = ((float) $tdPctRow) / 100;
            }
            $tdNormBd = ShopifySku::normalizeSkuForShopifyLookup((string) $fullSku);
            $tdLookupBd = Schema::hasTable('topdawg_products')
                ? TopDawgProduct::buildLookupByNormalizedSku([$fullSku, $sku])
                : [];
            $tdProductBd = ($tdNormBd !== '' && isset($tdLookupBd[$tdNormBd])) ? $tdLookupBd[$tdNormBd] : null;
            $tdPriceBd = $tdProductBd ? floatval($tdProductBd->price ?? 0) : 0;
            $tdViewsBd = $tdProductBd ? intval($tdProductBd->views ?? 0) : null;
            $tdL30Bd = 0;
            if (Schema::hasTable('topdawg_order_metrics') && $tdNormBd !== '') {
                $tdL30Bd = $this->fetchTopDawgL30QtyForSku($fullSku);
            } else {
                $tdL30Bd = $tdProductBd ? intval($tdProductBd->r_l30 ?? 0) : 0;
            }
            // No ship — same as /topdawg-pricing
            $tdGPFTBd = $tdPriceBd > 0
                ? round((($tdPriceBd * $tdMarginBd - $lp) / $tdPriceBd) * 100, 2)
                : 0;
            $tdNPFTBd = $tdGPFTBd; // Ads% = 0
            $tdSuggestedBd = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $tdBuyerLink = null;
            $tdSellerLink = null;
            if (Schema::hasTable('topdawg_data_views')) {
                $tdDataViewBd = TopDawgDataView::where('sku', $fullSku)->first()
                    ?? TopDawgDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $fullSku))])->first();
                if ($tdDataViewBd) {
                    $val = is_array($tdDataViewBd->value) ? $tdDataViewBd->value : json_decode($tdDataViewBd->value, true);
                    if (is_array($val)) {
                        $tdSprice = floatval($val['sprice'] ?? $val['SPRICE'] ?? 0);
                        $tdSgpft = $tdSprice > 0
                            ? round((($tdSprice * $tdMarginBd - $lp) / $tdSprice) * 100, 2)
                            : 0;
                        $tdSuggestedBd = [
                            'sprice' => $tdSprice,
                            'sgpft' => $tdSgpft,
                            'spft' => $tdSgpft, // SPFT = SGPFT (no ads)
                            'sroi' => ($lp > 0 && $tdSprice > 0)
                                ? round((($tdSprice * $tdMarginBd - $lp) / $lp) * 100, 2)
                                : 0,
                        ];
                        $tdBuyerLink = $val['buyer_link'] ?? null;
                        $tdSellerLink = $val['seller_link'] ?? null;
                    }
                }
            }
            $listingStateTd = strtolower(trim((string) ($tdProductBd->listing_state ?? '')));
            $isActiveTdListing = $tdProductBd && (
                ($listingStateTd === '' && !empty($tdProductBd->topdawg_listing_id ?? $tdProductBd->tdid))
                || in_array($listingStateTd, ['live', 'active'], true)
            );
            $hasTdData = $tdPriceBd > 0 || $tdL30Bd > 0 || $isActiveTdListing || ($tdSuggestedBd['sprice'] > 0);

            $breakdownData[] = [
                'marketplace' => 'TopDawg',
                'sku'         => $hasTdData ? $fullSku : 'Not Listed',
                'price'       => round($tdPriceBd, 2),
                'views'       => $tdViewsBd,
                'l30'         => $tdL30Bd,
                'gpft'        => $tdGPFTBd,
                'ad'          => 0,
                'tacos_ch'    => 0,
                'npft'        => $tdNPFTBd,
                'is_listed'   => $hasTdData,
                'sprice'      => $tdSuggestedBd['sprice'],
                'sgpft'       => $tdSuggestedBd['sgpft'],
                'sroi'        => $tdSuggestedBd['sroi'],
                'spft'        => $tdSuggestedBd['spft'],
                'lp'          => $lp,
                'ship'        => 0, // not used in TopDawg formulas
                'margin'      => $tdMarginBd,
                'pushed_by'   => null,
                'pushed_at'   => null,
                'buyer_link'  => $tdBuyerLink,
                'seller_link' => $tdSellerLink,
            ];

            // FBA row – same structure as other marketplaces (scoped to this SKU only)
            try {
                $baseSku = strtoupper(trim($fullSku));
                $fbaRow = FbaTable::query()
                    ->where(function ($q) use ($baseSku, $fullSku) {
                        $q->whereRaw('UPPER(TRIM(seller_sku)) = ?', [$baseSku])
                            ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [$baseSku . ' FBA'])
                            ->orWhereRaw('UPPER(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")) = ?', [$baseSku])
                            ->orWhere('seller_sku', 'LIKE', $fullSku . '%FBA%')
                            ->orWhere('seller_sku', 'LIKE', $fullSku . '%fba%');
                    })
                    ->where(function ($q) {
                        $q->where('seller_sku', 'LIKE', '%FBA%')->orWhere('seller_sku', 'LIKE', '%fba%');
                    })
                    ->first();
                if ($fbaRow) {
                    $fbaSellerSku = trim((string) ($fbaRow->seller_sku ?? ''));
                    $fbaSellerSkuUpper = strtoupper($fbaSellerSku);
                    $fbaPriceInfo = FbaPrice::whereRaw('UPPER(TRIM(seller_sku)) = ?', [$fbaSellerSkuUpper])->first()
                        ?? FbaPrice::whereRaw('UPPER(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")) = ?', [$baseSku])->first();
                    $fbaMonthly = FbaMonthlySale::whereRaw('UPPER(TRIM(seller_sku)) = ?', [$fbaSellerSkuUpper])->first()
                        ?? FbaMonthlySale::whereRaw('UPPER(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")) = ?', [$baseSku])->first();
                    $fbaReports = FbaReportsMaster::whereRaw('UPPER(TRIM(seller_sku)) = ?', [$fbaSellerSkuUpper])->first()
                        ?? FbaReportsMaster::whereRaw('UPPER(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")) = ?', [$baseSku])->first();
                    $fbaManual = FbaManualData::whereRaw('UPPER(TRIM(sku)) = ?', [$fbaSellerSkuUpper])->first()
                        ?? FbaManualData::whereRaw('UPPER(TRIM(sku)) = ?', [$baseSku])->first();

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
            $bestbuyLmpPrice = $resolveBestbuyLmpPrice($fullSku);
            if ($bestbuyLmpPrice === null) {
                foreach (BestbuySkuCompetitor::resolveLookupKeys($fullSku) as $bbKey) {
                    $bestbuyLmpPrice = $resolveBestbuyLmpPrice($bbKey);
                    if ($bestbuyLmpPrice !== null) {
                        break;
                    }
                }
            }
            $macyLmpPrice = $resolveMacyLmpPrice($fullSku);
            if ($macyLmpPrice === null) {
                foreach (MacySkuCompetitor::resolveLookupKeys($fullSku) as $macyKey) {
                    $macyLmpPrice = $resolveMacyLmpPrice($macyKey);
                    if ($macyLmpPrice !== null) {
                        break;
                    }
                }
            }
            $reverbLmpPrice = $resolveReverbLmpPrice($fullSku);
            if ($reverbLmpPrice === null) {
                foreach (ReverbSkuCompetitor::resolveLookupKeys($fullSku) as $reverbKey) {
                    $reverbLmpPrice = $resolveReverbLmpPrice($reverbKey);
                    if ($reverbLmpPrice !== null) {
                        break;
                    }
                }
            }
            $temuLmpPrice = $resolveTemuLmpPrice($fullSku);
            $tiktokLmpPrice = $resolveTiktokLmpPrice($fullSku);

            foreach ($breakdownData as &$row) {
                $mp = strtolower(trim((string) ($row['marketplace'] ?? '')));
                $gpftPct = (float) ($row['gpft'] ?? 0);

                // TikTok: SKU TACOS; Temu: aggregate Ads% (/temu-decrease);
                // Reverb: channel Ads% (Bump ÷ L30 Sales) — PFT = GPFT − Ads% (/reverb-pricing);
                // Temu2/Doba/PPower: Ads% = 0; else channel Ads% from AMM
                if ($mp === 'tiktok') {
                    $adsPct = (float) ($row['tacos_ch'] ?? $row['ad'] ?? 0);
                    $row['ad'] = $adsPct;
                    $row['tacos_ch'] = $adsPct;
                    $row['npft'] = round($gpftPct - $adsPct, 2);
                } elseif ($mp === 'temu') {
                    // Every row: aggregate Ads% (~2.6%) — same as /temu-decrease ADS% column (badgeAvgAds)
                    // Keep 100% when already set (spend > 0, L30 = 0)
                    $adsPct = (float) ($row['ad'] ?? $row['tacos_ch'] ?? 0);
                    if ($adsPct != 100) {
                        $adsPct = round($this->resolveTemuAggregateAdsPercent('L30'), 2);
                    }
                    $row['ad'] = $adsPct;
                    $row['tacos_ch'] = $adsPct;
                    $row['npft'] = ($adsPct == 100)
                        ? round($gpftPct, 2)
                        : round($gpftPct - $adsPct, 2);
                } elseif ($mp === 'reverb') {
                    $adsPct = (float) $getChannelAdsPercent('Reverb');
                    $row['ad'] = $adsPct;
                    $row['tacos_ch'] = $adsPct;
                    $row['npft'] = round($gpftPct - $adsPct, 2);
                    $sgpftVal = (float) ($row['sgpft'] ?? 0);
                    $spriceVal = (float) ($row['sprice'] ?? 0);
                    if ($spriceVal > 0 || $sgpftVal != 0) {
                        $row['spft'] = round($sgpftVal - $adsPct, 2);
                    }
                } elseif (in_array($mp, ['ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3'], true)) {
                    // Channel Ads% on every row — same as /ebay-tabulator-view (+ ebay2/ebay3)
                    $cmc = app(ChannelMasterController::class);
                    if (in_array($mp, ['ebaytwo', 'ebay2'], true)) {
                        $adsPct = (float) $cmc->getEbaytwoMasterAdsPercent();
                    } elseif (in_array($mp, ['ebaythree', 'ebay3'], true)) {
                        $adsPct = method_exists($cmc, 'getEbaythreeMasterAdsPercent')
                            ? (float) $cmc->getEbaythreeMasterAdsPercent()
                            : 0.0;
                    } else {
                        $adsPct = (float) $cmc->getEbayMasterAdsPercent();
                    }
                    $row['ad'] = $adsPct;
                    $row['tacos_ch'] = $adsPct;
                    $row['npft'] = round($gpftPct - $adsPct, 2);
                    $sgpftVal = (float) ($row['sgpft'] ?? 0);
                    $spriceVal = (float) ($row['sprice'] ?? 0);
                    if ($spriceVal > 0 || $sgpftVal != 0) {
                        $row['spft'] = round($sgpftVal - $adsPct, 2);
                    }
                } elseif (in_array($mp, ['temu2', 'doba', 'ppower', 'purchasingpower', 'purchase', 'topdawg', 'shein'], true)) {
                    // Temu2 / Doba / PPower / TopDawg / Shein: no ads (match channel pricing pages)
                    $row['ad'] = 0;
                    $row['tacos_ch'] = 0;
                    $row['npft'] = round($gpftPct, 2);
                    if (in_array($mp, ['ppower', 'purchasingpower', 'purchase', 'topdawg'], true)) {
                        $row['ship'] = 0; // PP / TopDawg formulas never use ship
                    }
                } else {
                    $adsPct = (float) $getChannelAdsPercent($row['marketplace'] ?? '');
                    $row['tacos_ch'] = $adsPct;
                    $row['npft'] = round($gpftPct - $adsPct, 2);
                }

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
                } elseif (in_array($mp, ['bestbuy', 'bestbuyusa'], true)) {
                    $lmpChannel = 'bestbuy';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveBestbuyLmpPrice($rowSku);
                    if ($lmpPrice === null) {
                        foreach (BestbuySkuCompetitor::resolveLookupKeys($rowSku, $fullSku) as $bbKey) {
                            $lmpPrice = $resolveBestbuyLmpPrice($bbKey);
                            if ($lmpPrice !== null) {
                                break;
                            }
                        }
                    }
                    if ($lmpPrice === null) {
                        $lmpPrice = $bestbuyLmpPrice;
                    }
                } elseif (in_array($mp, ['macy', 'macys'], true)) {
                    $lmpChannel = 'macy';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveMacyLmpPrice($rowSku);
                    if ($lmpPrice === null) {
                        foreach (MacySkuCompetitor::resolveLookupKeys($rowSku, $fullSku) as $macyKey) {
                            $lmpPrice = $resolveMacyLmpPrice($macyKey);
                            if ($lmpPrice !== null) {
                                break;
                            }
                        }
                    }
                    if ($lmpPrice === null) {
                        $lmpPrice = $macyLmpPrice;
                    }
                } elseif ($mp === 'reverb') {
                    $lmpChannel = 'reverb';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveReverbLmpPrice($rowSku);
                    if ($lmpPrice === null) {
                        foreach (ReverbSkuCompetitor::resolveLookupKeys($rowSku, $fullSku) as $reverbKey) {
                            $lmpPrice = $resolveReverbLmpPrice($reverbKey);
                            if ($lmpPrice !== null) {
                                break;
                            }
                        }
                    }
                    if ($lmpPrice === null) {
                        $lmpPrice = $reverbLmpPrice;
                    }
                } elseif (in_array($mp, ['temu', 'temu2'], true)) {
                    // Same temu_lmp source used by /temu-decrease and /temu2-decrease
                    $lmpChannel = 'temu';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveTemuLmpPrice($rowSku) ?? $temuLmpPrice;
                } elseif ($mp === 'tiktok') {
                    // Same tiktok_sku_competitors + Sku Link LMP groups as /tiktok-pricing
                    $lmpChannel = 'tiktok';
                    $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed') ? $row['sku'] : $fullSku;
                    $lmpPrice = $resolveTiktokLmpPrice($rowSku) ?? $tiktokLmpPrice;
                }

                $row['lmp_channel'] = $lmpChannel;
                $row['lmp_price'] = $lmpPrice;
            }
            unset($row);

            // Attach push history (date / price / user) per marketplace for OV L30 History column
            $this->enrichBreakdownPushHistory($breakdownData, $fullSku);

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
     * Build a continuous daily chart series for the selected window.
     * Missing snapshot days are forward-filled from the last known value (0 if none yet).
     *
     * @param  array<string, float>  $byDateKey  map of Y-m-d => value
     * @return array<int, array{date: string, value: float}>
     */
    /**
     * Latest audit history rows keyed by SKU — same source as /amz-cvr-issues.
     *
     * @param  list<string>  $skus
     * @return array<string, list<array<string, mixed>>>
     */
    private function amzCvrAuditHistoryBySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('amz_cvr_audit_histories')) {
            return [];
        }

        try {
            $grouped = [];
            AmzCvrAuditHistory::query()
                ->whereIn('sku', $skus)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->each(function (AmzCvrAuditHistory $row) use (&$grouped) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '') {
                        return;
                    }
                    if (! isset($grouped[$sku])) {
                        $grouped[$sku] = [];
                    }
                    if (count($grouped[$sku]) >= 10) {
                        return;
                    }
                    $dt = $row->created_at;
                    $grouped[$sku][] = [
                        'id' => (int) $row->id,
                        'sku' => (string) $row->sku,
                        'user' => (string) ($row->user_name ?: 'Unknown'),
                        'user_id' => $row->user_id ? (int) $row->user_id : null,
                        'task_count' => (int) $row->task_count,
                        'cvr_l30' => $row->cvr_l30 !== null ? round((float) $row->cvr_l30, 2) : null,
                        'date_key' => $dt ? $dt->format('Y-m-d') : '',
                        'date_label' => $dt ? strtoupper($dt->format('j M')) : '',
                        'created_at' => $dt ? $dt->toIso8601String() : null,
                        'sort_ts' => $dt ? $dt->getTimestamp() : 0,
                    ];
                });

            foreach ($grouped as $sku => $rows) {
                usort($grouped[$sku], static function (array $a, array $b): int {
                    return ((int) ($b['sort_ts'] ?? 0)) <=> ((int) ($a['sort_ts'] ?? 0))
                        ?: ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
                });
            }

            return $grouped;
        } catch (\Throwable $e) {
            Log::warning('Price Increase: failed loading amz CVR audit history', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function fillDailyChartSeries(array $byDateKey, int $days): array
    {
        $tz = 'America/Los_Angeles';
        $end = now($tz)->startOfDay();
        if ($days > 0) {
            $start = $end->copy()->subDays($days);
        } else {
            $keys = array_keys($byDateKey);
            sort($keys);
            $start = !empty($keys)
                ? Carbon::parse($keys[0], $tz)->startOfDay()
                : $end->copy();
        }

        $out = [];
        $last = 0.0;
        $hasAny = false;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            if (array_key_exists($key, $byDateKey)) {
                $last = (float) $byDateKey[$key];
                $hasAny = true;
            }
            $out[] = [
                'date' => $d->format('M j'),
                'value' => $hasAny || array_key_exists($key, $byDateKey) ? $last : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Get Master Analytics chart data (Rolling L30) for Inv, OV L30, Price, CVR graphs.
     * Data is read from pricing_master_daily_snapshots_sku (SKU-wise, saved on page load/refresh).
     * When "parent" is provided, aggregates data for all SKUs under that parent by snapshot_date.
     * Returns one point per calendar day (missing days forward-filled).
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
            $byDateKey = [];
            foreach ($rows as $row) {
                $key = Carbon::parse($row->snapshot_date)->toDateString();
                $byDateKey[$key] = match ($metric) {
                    'inv' => (float) ($row->total_inv ?? 0),
                    'ov_l30' => (float) ($row->total_ov_l30 ?? 0),
                    'price' => $row->avg_price !== null ? (float) $row->avg_price : 0,
                    'cvr' => $row->avg_cvr !== null ? (float) $row->avg_cvr : 0,
                    'dil' => ($row->total_inv ?? 0) > 0
                        ? round(((float) ($row->total_ov_l30 ?? 0) / (float) $row->total_inv) * 100, 2)
                        : 0,
                    default => (float) ($row->total_inv ?? 0),
                };
            }
            if (empty($byDateKey)) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $data = $this->fillDailyChartSeries($byDateKey, $days);
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

        $byDateKey = [];
        if ($isParent || $isAggregate) {
            // Aggregate by snapshot_date
            $byDate = $rows->groupBy(function ($row) {
                return Carbon::parse($row->snapshot_date)->format('Y-m-d');
            });
            foreach ($byDate as $dateStr => $dateRows) {
                $invSum = $dateRows->sum('inventory');
                $l30Sum = $dateRows->sum('overall_l30');
                $viewsSum = $dateRows->sum('total_views');
                $byDateKey[$dateStr] = match ($metric) {
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
            }
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
            foreach ($rows as $row) {
                $key = Carbon::parse($row->snapshot_date)->toDateString();
                $val = $row->{$column};
                $byDateKey[$key] = $val !== null ? (is_numeric($val) ? (float) $val : 0) : 0;
            }
        }

        if (empty($byDateKey)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $data = $this->fillDailyChartSeries($byDateKey, $days);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Save suggested pricing data (SPRICE, SGPFT, SPFT, SROI) to data_view tables
     */
    /**
     * Sibling SKUs = all ProductMaster children sharing the same parent
     * (excludes PARENT summary rows). Includes the seed SKU when found.
     */
    private function resolveSiblingSkus(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '' || strcasecmp($sku, 'Not Listed') === 0) {
            return [];
        }

        $pm = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        if (!$pm) {
            $pm = ProductMaster::where('sku', 'LIKE', $sku . '%')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();
        }
        if (!$pm) {
            return [$sku];
        }

        $parent = trim((string) ($pm->parent ?? ''));
        if ($parent === '') {
            return [trim((string) $pm->sku)];
        }

        // Case/whitespace-insensitive parent match (ProductMaster parent values vary)
        $skus = ProductMaster::whereRaw('UPPER(TRIM(parent)) = ?', [strtoupper($parent)])
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->pluck('sku')
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '')
            ->unique(fn ($s) => strtoupper($s))
            ->values()
            ->all();

        return !empty($skus) ? $skus : [trim((string) $pm->sku)];
    }

    /**
     * List sibling SKUs for the Details modal "Siblings Apply" checkbox.
     */
    public function getSiblingSkus(Request $request)
    {
        $sku = trim((string) $request->query('sku', $request->input('sku', '')));
        if ($sku === '') {
            return response()->json(['success' => false, 'error' => 'SKU required'], 400);
        }

        $all = $this->resolveSiblingSkus($sku);
        $others = array_values(array_filter(
            $all,
            fn ($s) => strtoupper(trim((string) $s)) !== strtoupper(trim($sku))
        ));

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'parent' => optional(
                ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            )->parent,
            'siblings' => $others,
            'all' => $all,
            'count' => count($others),
        ]);
    }

    /**
     * Aggregate SPRICE push history across all marketplaces for one SKU (outer table Hist column).
     */
    public function getSkuPushHistory(Request $request)
    {
        $sku = trim((string) $request->query('sku', $request->input('sku', '')));
        if ($sku === '') {
            return response()->json(['success' => false, 'error' => 'SKU required'], 400);
        }

        $productMaster = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: ProductMaster::where('sku', $sku)->first();
        $fullSku = $productMaster ? $productMaster->sku : $sku;

        $markets = [
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'ebay2' => 'eBay2',
            'ebay3' => 'eBay3',
            'doba' => 'Doba',
            // Walmart / Tiendamia excluded from this page
            'shopify' => 'Shopify',
            'sb2b' => 'SB2B',
            'tiktok' => 'TikTok',
            'temu' => 'Temu',
            'temu2' => 'Temu2',
            'bestbuy' => 'BestBuy',
            'macy' => "Macy's",
            'reverb' => 'Reverb',
            'topdawg' => 'TopDawg',
        ];

        $all = [];
        foreach ($markets as $mpKey => $mpLabel) {
            $val = $this->getMarketplaceDataViewValue($fullSku, $mpKey);
            if (empty($val) && strtoupper($fullSku) !== strtoupper($sku)) {
                $val = $this->getMarketplaceDataViewValue($sku, $mpKey);
            }
            $meta = $this->extractPushMeta($val);
            foreach ($meta['push_history'] as $h) {
                $h['marketplace'] = $h['marketplace'] ?: $mpLabel;
                $all[] = $h;
            }
        }

        usort($all, function ($a, $b) {
            $ta = strtotime((string) ($a['at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['at'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });

        return response()->json([
            'success' => true,
            'sku' => $fullSku,
            'history' => $all,
            'count' => count($all),
        ]);
    }

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
                // Same store as /tiktok-pricing (tiktok_shop_data_views)
                $existingTtView = TiktokShopDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($skuToUse))])->first();
                $dataView = $existingTtView ?: new TiktokShopDataView(['sku' => $skuToUse]);
            } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
                $dataView = BestbuyUSADataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
                $dataView = MacyDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'reverb') {
                $dataView = ReverbViewData::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'topdawg') {
                if (!Schema::hasTable('topdawg_data_views')) {
                    return response()->json(['error' => 'TopDawg is not set up. Run migrations (topdawg_data_views).'], 503);
                }
                $dataView = TopDawgDataView::firstOrNew(['sku' => $skuToUse]);
            } elseif ($marketplace === 'shein') {
                if (!Schema::hasTable('shein_data_views')) {
                    return response()->json(['error' => 'Shein is not set up. Run migrations (shein_data_views).'], 503);
                }
                $dataView = \App\Models\SheinDataView::firstOrNew(['sku' => $skuToUse]);
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
                $fbaSellerSku = $this->findFbaSellerSkuForBase($fullSku);
                if ($fbaSellerSku === null || $fbaSellerSku === '') {
                    return response()->json(['error' => 'No FBA listing found for this SKU'], 400);
                }
                $fbaSellerSku = strtoupper(trim($fbaSellerSku));
                $manual = FbaManualData::where('sku', $fbaSellerSku)->first();
                if (!$manual) {
                    $manual = new FbaManualData();
                    $manual->sku = $fbaSellerSku;
                    $manual->data = [];
                }
                $data = is_array($manual->data) ? $manual->data : [];
                // Never persist 0 as a suggested price (clear uses empty / omit)
                if ($sprice > 0) {
                    $data['s_price'] = $sprice;
                    $data['SPRICE_STATUS'] = 'applied'; // saved but not pushed
                } else {
                    unset($data['s_price']);
                    $data['SPRICE_STATUS'] = '';
                }
                $manual->data = $data;
                $manual->save();

                return $this->finishSuggestedSaveWithSiblings($request, $fullSku, $marketplace, [
                    'sprice' => $sprice,
                    'sgpft' => $sgpft,
                    'sroi' => $sroi,
                    'spft' => $spft,
                    'amazon_margin' => $amazonMargin,
                    'avg_pft' => $avgPft,
                ]);
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
            
            // Never write 0 as SPRICE for siblings-apply path; for primary clear, remove keys instead of storing 0
            if ($sprice > 0) {
                if ($marketplace === 'walmart' || $marketplace === 'temu' || $marketplace === 'temu2' || $marketplace === 'topdawg') {
                    $value['sprice'] = $sprice;
                } else {
                    $value['SPRICE'] = $sprice;
                }
                $value['SGPFT'] = $sgpft;
                $value['SROI'] = $sroi;
                $value['SPFT'] = $spft;

                // Temu / Temu2: keep lowercase keys used by /temu-decrease and /temu2-decrease
                // TopDawg: lowercase sprice only (same as /topdawg-pricing)
                if ($marketplace === 'walmart' || $marketplace === 'topdawg') {
                    unset($value['SPRICE']);
                } elseif ($marketplace === 'temu' || $marketplace === 'temu2') {
                    $value['sprice'] = $sprice;
                    $value['SPRICE'] = $sprice; // keep both so CVR + decrease stay in sync
                    $value['sgprft_percent'] = round($sgpft, 2);
                    $value['sroi_percent'] = round($sroi, 2);
                } else {
                    unset($value['sprice'], $value['sgpft'], $value['sroi'], $value['spft']);
                }
            } else {
                // Clear SPRICE on this SKU only — never leave literal 0
                unset(
                    $value['SPRICE'], $value['sprice'],
                    $value['SGPFT'], $value['sgpft'], $value['sgprft_percent'],
                    $value['SROI'], $value['sroi'], $value['sroi_percent'],
                    $value['SPFT'], $value['spft']
                );
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
                $fbaSellerSku = $this->findFbaSellerSkuForBase($fullSku);
                if ($fbaSellerSku) {
                    $fbaSellerSku = strtoupper(trim($fbaSellerSku));
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

            $payload = [
                'sprice' => $sprice,
                'sgpft' => $sgpft,
                'sroi' => $sroi,
                'spft' => $spft,
                'amazon_margin' => $amazonMargin,
                'avg_pft' => $avgPft,
            ];

            // Temu ↔ Temu2: any suggested price on one auto-applies to the other (same SKU)
            if ($marketplace === 'temu' || $marketplace === 'temu2') {
                $otherTemu = $marketplace === 'temu' ? 'temu2' : 'temu';
                try {
                    if ($sprice > 0) {
                        $this->applySuggestedSpriceToSku($skuToUse, $otherTemu, $payload);
                    } else {
                        $this->clearSuggestedSpriceOnSku($skuToUse, $otherTemu);
                    }
                } catch (\Throwable $e) {
                    Log::warning('CVR Temu↔Temu2 SPRICE cross-apply failed', [
                        'sku' => $skuToUse,
                        'from' => $marketplace,
                        'to' => $otherTemu,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->finishSuggestedSaveWithSiblings($request, $fullSku, $marketplace, $payload);
        } catch (\Exception $e) {
            Log::error('Error saving suggested data: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $message = config('app.debug') ? $e->getMessage() : 'Failed to save';
            return response()->json(['error' => $message], 500);
        }
    }

    /**
     * Resolve FBA seller SKU for a product base SKU (scoped query — never loads full fba_table).
     */
    private function findFbaSellerSkuForBase(string $sku): ?string
    {
        $baseSku = strtoupper(trim($sku));
        if ($baseSku === '') {
            return null;
        }
        $fbaRow = FbaTable::query()
            ->select(['seller_sku'])
            ->where(function ($q) use ($baseSku, $sku) {
                $q->whereRaw('UPPER(TRIM(seller_sku)) = ?', [$baseSku])
                    ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [$baseSku . ' FBA'])
                    ->orWhereRaw('UPPER(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")) = ?', [$baseSku])
                    ->orWhere('seller_sku', 'LIKE', $sku . '%FBA%')
                    ->orWhere('seller_sku', 'LIKE', $sku . '%fba%');
            })
            ->where(function ($q) {
                $q->where('seller_sku', 'LIKE', '%FBA%')->orWhere('seller_sku', 'LIKE', '%fba%');
            })
            ->first();

        $seller = $fbaRow ? trim((string) ($fbaRow->seller_sku ?? '')) : '';
        return $seller !== '' ? $seller : null;
    }

    /**
     * Lean SPRICE write for one SKU/channel. Never writes 0. Used by Siblings Apply.
     */
    private function applySuggestedSpriceToSku(string $sku, string $marketplace, array $payload): bool
    {
        $sprice = floatval($payload['sprice'] ?? 0);
        if (!($sprice > 0)) {
            return false; // Do NOT apply 0 anywhere
        }

        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        if ($sku === '' || $marketplace === '') {
            return false;
        }

        $productMaster = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: ProductMaster::where('sku', 'LIKE', $sku . '%')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();
        $fullSku = $productMaster ? trim((string) $productMaster->sku) : $sku;

        $sgpft = floatval($payload['sgpft'] ?? 0);
        $sroi = floatval($payload['sroi'] ?? 0);
        $spft = floatval($payload['spft'] ?? 0);

        if ($marketplace === 'fba') {
            $fbaSellerSku = $this->findFbaSellerSkuForBase($fullSku);
            if (!$fbaSellerSku) {
                return false;
            }
            $fbaSellerSku = strtoupper(trim($fbaSellerSku));
            $manual = FbaManualData::firstOrNew(['sku' => $fbaSellerSku]);
            $data = is_array($manual->data) ? $manual->data : [];
            $data['s_price'] = $sprice;
            $data['SPRICE_STATUS'] = 'applied';
            $manual->data = $data;
            $manual->save();
            return true;
        }

        $skuToUse = $fullSku;
        if ($marketplace === 'amazon') {
            $amazonData = AmazonDatasheet::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($fullSku)])->first()
                ?: AmazonDatasheet::where('sku', $fullSku)->first();
            $skuToUse = $amazonData ? $amazonData->sku : $fullSku;
        }

        $dataView = null;
        $usesValues = false;
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
                return false;
            }
            $dataView = Temu2DataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'doba') {
            $dataView = DobaDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'walmart') {
            $dataView = WalmartDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'tiktok') {
            $existingTtView = TiktokShopDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($skuToUse))])->first();
            $dataView = $existingTtView ?: new TiktokShopDataView(['sku' => $skuToUse]);
        } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
            $dataView = BestbuyUSADataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
            $dataView = MacyDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'reverb') {
            $dataView = ReverbViewData::firstOrNew(['sku' => $skuToUse]);
            $usesValues = true;
        } elseif ($marketplace === 'topdawg') {
            if (!Schema::hasTable('topdawg_data_views')) {
                return false;
            }
            $dataView = TopDawgDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'shein') {
            if (!Schema::hasTable('shein_data_views')) {
                return false;
            }
            $dataView = \App\Models\SheinDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'tiendamia') {
            $dataView = TiendamiaDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'shopifyb2c' || $marketplace === 'sb2c' || $marketplace === 'shopify') {
            $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $skuToUse]);
        } elseif ($marketplace === 'shopifyb2b' || $marketplace === 'sb2b') {
            $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $skuToUse]);
        } else {
            return false;
        }

        $value = $usesValues
            ? (is_array($dataView->values) ? $dataView->values : (is_string($dataView->values) ? json_decode($dataView->values, true) : []))
            : (is_array($dataView->value) ? $dataView->value : (is_string($dataView->value) ? json_decode($dataView->value, true) : []));
        if (!is_array($value)) {
            $value = [];
        }

        if ($marketplace === 'walmart' || $marketplace === 'temu' || $marketplace === 'temu2' || $marketplace === 'topdawg') {
            $value['sprice'] = $sprice;
        } else {
            $value['SPRICE'] = $sprice;
        }
        $value['SGPFT'] = $sgpft;
        $value['SROI'] = $sroi;
        $value['SPFT'] = $spft;

        if ($marketplace === 'walmart' || $marketplace === 'topdawg') {
            unset($value['SPRICE']);
        } elseif ($marketplace === 'temu' || $marketplace === 'temu2') {
            $value['sprice'] = $sprice;
            $value['SPRICE'] = $sprice;
            $value['sgprft_percent'] = round($sgpft, 2);
            $value['sroi_percent'] = round($sroi, 2);
        } else {
            unset($value['sprice'], $value['sgpft'], $value['sroi'], $value['spft']);
        }

        if ($usesValues) {
            $dataView->values = $value;
        } else {
            $dataView->value = $value;
        }
        $dataView->save();

        if ($marketplace === 'amazon') {
            $fbaSellerSku = $this->findFbaSellerSkuForBase($fullSku);
            if ($fbaSellerSku) {
                $fbaSellerSku = strtoupper(trim($fbaSellerSku));
                $fbaManual = FbaManualData::firstOrNew(['sku' => $fbaSellerSku]);
                $fbaData = is_array($fbaManual->data) ? $fbaManual->data : [];
                $fbaData['s_price'] = $sprice;
                if (!isset($fbaData['SPRICE_STATUS']) || $fbaData['SPRICE_STATUS'] === '') {
                    $fbaData['SPRICE_STATUS'] = 'applied';
                }
                $fbaManual->data = $fbaData;
                $fbaManual->save();
            }
        }

        return true;
    }

    /**
     * Clear SPRICE keys on one SKU/channel (used to keep Temu ↔ Temu2 in sync on clear).
     */
    private function clearSuggestedSpriceOnSku(string $sku, string $marketplace): bool
    {
        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        if ($sku === '' || $marketplace === '') {
            return false;
        }

        $productMaster = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: ProductMaster::where('sku', 'LIKE', $sku . '%')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();
        $fullSku = $productMaster ? trim((string) $productMaster->sku) : $sku;

        if ($marketplace === 'temu') {
            $dataView = TemuDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($fullSku)])->first()
                ?: TemuDataView::where('sku', $fullSku)->first();
        } elseif ($marketplace === 'temu2') {
            if (!Schema::hasTable('temu2_data_view')) {
                return false;
            }
            $dataView = Temu2DataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($fullSku)])->first()
                ?: Temu2DataView::where('sku', $fullSku)->first();
        } else {
            return false;
        }

        if (!$dataView) {
            return true;
        }

        $value = is_array($dataView->value)
            ? $dataView->value
            : (is_string($dataView->value) ? json_decode($dataView->value, true) : []);
        if (!is_array($value)) {
            $value = [];
        }

        unset(
            $value['SPRICE'], $value['sprice'],
            $value['SGPFT'], $value['sgpft'], $value['sgprft_percent'],
            $value['SROI'], $value['sroi'], $value['sroi_percent'],
            $value['SPFT'], $value['spft']
        );
        $dataView->value = $value;
        $dataView->save();

        return true;
    }

    /**
     * After a successful SPRICE save, optionally copy the same SPRICE to sibling child SKUs (same parent).
     * Never applies 0 to siblings.
     * Temu / Temu2: siblings get both channels (same as primary Temu↔Temu2 cross-apply).
     */
    private function finishSuggestedSaveWithSiblings(Request $request, string $fullSku, string $marketplace, array $payload)
    {
        $applySiblings = filter_var($request->input('apply_siblings'), FILTER_VALIDATE_BOOLEAN);
        $siblingSaved = [];
        $sprice = floatval($payload['sprice'] ?? 0);
        $marketplace = strtolower(trim($marketplace));

        $channels = [$marketplace];
        if ($marketplace === 'temu' || $marketplace === 'temu2') {
            $channels[] = $marketplace === 'temu' ? 'temu2' : 'temu';
        }

        // Siblings Apply only when there is a real (non-zero) suggested price
        if ($applySiblings && $sprice > 0) {
            foreach ($this->resolveSiblingSkus($fullSku) as $sibSku) {
                if (strtoupper(trim((string) $sibSku)) === strtoupper(trim($fullSku))) {
                    continue;
                }
                $anyOk = false;
                foreach ($channels as $ch) {
                    try {
                        if ($this->applySuggestedSpriceToSku((string) $sibSku, $ch, $payload)) {
                            $anyOk = true;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('CVR siblings SPRICE save failed', [
                            'sku' => $sibSku,
                            'marketplace' => $ch,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                if ($anyOk) {
                    $siblingSaved[] = $sibSku;
                }
            }
        }

        return response()->json([
            'success' => true,
            'siblings_applied' => $siblingSaved,
            'siblings_count' => count($siblingSaved),
            'temu_cross_applied' => in_array($marketplace, ['temu', 'temu2'], true),
            'message' => count($siblingSaved)
                ? ('Saved (+' . count($siblingSaved) . ' siblings)')
                : 'Saved',
        ]);
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
                'apply_siblings' => $request->input('apply_siblings'),
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
            $rawPrice = $request->input('price');
            // Normalize "Ebay 3" / "eBay3" / "ebaythree" → compact lowercase keys
            $marketplace = preg_replace('/\s+/', '', strtolower(trim((string) $request->input('marketplace'))));

            // Never push when price is null / empty / zero
            if ($rawPrice === null || $rawPrice === '' || !is_numeric($rawPrice)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price is required and must be a number greater than 0.'
                ], 400);
            }

            $price = round(floatval($rawPrice), 2);
            if ($price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot push — price is 0 or empty.'
                ], 400);
            }

            $selfPickPrice = $request->has('self_pick_price')
                ? round(floatval($request->input('self_pick_price')), 2)
                : null;

            // Handle different marketplaces (API push where available)
            if ($marketplace === 'amazon') {
                $response = $this->pushToAmazon($sku, $price);
            } elseif ($marketplace === 'doba') {
                $response = $this->pushToDoba($sku, $price, $selfPickPrice);
            } elseif ($marketplace === 'walmart') {
                $response = $this->pushToWalmart($sku, $price);
            } elseif ($marketplace === 'sb2c' || $marketplace === 'shopifyb2c' || $marketplace === 'shopify') {
                $response = $this->pushToShopifyB2C($sku, $price);
            } elseif ($marketplace === 'sb2b' || $marketplace === 'shopifyb2b') {
                $response = $this->pushToShopifyB2B($sku, $price);
            } elseif ($marketplace === 'pls' || $marketplace === 'prolightsounds') {
                $response = $this->pushToPls($sku, $price);
            } elseif ($marketplace === 'reverb') {
                $response = $this->pushToReverb($sku, $price);
            } elseif ($marketplace === 'fba') {
                $response = $this->pushToFba($sku, $price);
            } elseif ($marketplace === 'ebay' || $marketplace === 'ebay1') {
                $response = $this->pushToEbay($sku, $price);
            } elseif ($marketplace === 'ebay2' || $marketplace === 'ebaytwo') {
                $response = $this->pushToEbay2($sku, $price);
            } elseif ($marketplace === 'ebay3' || $marketplace === 'ebaythree') {
                $response = $this->pushToEbay3($sku, $price);
            } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
                $response = $this->pushToBestBuy($sku, $price);
            } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
                $response = $this->pushToMacy($sku, $price);
            } elseif ($marketplace === 'topdawg') {
                $response = $this->pushToTopDawg($sku, $price);
            } elseif ($marketplace === 'temu') {
                $response = $this->pushToTemu(
                    $sku,
                    $price,
                    $request->input('goods_id'),
                    $request->input('sku_id')
                );
            } elseif ($marketplace === 'temu2') {
                $response = $this->pushToTemu2(
                    $sku,
                    $price,
                    $request->input('goods_id'),
                    $request->input('sku_id')
                );
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Price push is not available for this channel ($marketplace). Supported: Amazon, eBay1/2/3, Doba, Walmart, Shopify, SB2B, BestBuy, Macy, Reverb, TopDawg, Temu, Temu2, FBA."
                ], 400);
            }

            $applySiblings = filter_var($request->input('apply_siblings'), FILTER_VALIDATE_BOOLEAN);
            // Never push 0 / empty to siblings
            if (!$applySiblings || !($price > 0)) {
                return $response;
            }

            $primaryData = method_exists($response, 'getData') ? $response->getData(true) : [];
            if (empty($primaryData['success'])) {
                return $response;
            }

            $siblingResults = [];
            foreach ($this->resolveSiblingSkus($sku) as $sibSku) {
                if (strtoupper(trim((string) $sibSku)) === strtoupper(trim($sku))) {
                    continue;
                }
                $sibPayload = [
                    'sku' => $sibSku,
                    'price' => $price,
                    'marketplace' => $marketplace,
                    'apply_siblings' => 0,
                ];
                if ($selfPickPrice !== null && $selfPickPrice > 0) {
                    $sibPayload['self_pick_price'] = $selfPickPrice;
                }
                $subReq = Request::create('/cvr-master-push-price', 'POST', $sibPayload);
                $subReq->headers->set('Accept', 'application/json');
                try {
                    $sibResp = $this->pushPriceToAmazon($subReq);
                    $sibData = method_exists($sibResp, 'getData') ? $sibResp->getData(true) : [];
                    $siblingResults[] = [
                        'sku' => $sibSku,
                        'success' => !empty($sibData['success']),
                        'message' => $sibData['message'] ?? ($sibData['error'] ?? ''),
                    ];
                } catch (\Throwable $e) {
                    $siblingResults[] = [
                        'sku' => $sibSku,
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $okSiblings = count(array_filter($siblingResults, fn ($r) => !empty($r['success'])));
            $baseMsg = $primaryData['message'] ?? 'Price pushed';
            if (count($siblingResults) > 0) {
                $baseMsg .= ' (+' . $okSiblings . '/' . count($siblingResults) . ' siblings)';
            }

            return response()->json([
                'success' => true,
                'message' => $baseMsg,
                'siblings' => $siblingResults,
                'siblings_count' => $okSiblings,
            ]);

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
     * Matches DobaController::pushPriceToDoba + /doba-tabulator (self pick = SPRICE − Ship)
     */
    private function pushToDoba($sku, $price, $selfPickPrice = null)
    {
        try {
            // Case-insensitive SKU lookup (DB may store "CAPO BLUE 1Pc")
            $dobaMetric = DobaMetric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $sku))])->first();
            if (!$dobaMetric) {
                $dobaMetric = DobaMetric::where('sku', $sku)->first();
            }
            
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
            $metricSku = trim((string) $dobaMetric->sku);

            // Same as /doba-tabulator: Self Pick = SPRICE − Ship (ProductMaster ship)
            if ($selfPickPrice === null || !is_numeric($selfPickPrice) || floatval($selfPickPrice) < 0) {
                $ship = 0.0;
                $pm = ProductMaster::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($metricSku)])->first();
                if ($pm) {
                    // ProductMaster stores ship inside Values JSON
                    $vals = is_array($pm->Values) ? $pm->Values
                        : (json_decode($pm->Values ?? '{}', true) ?: []);
                    $ship = floatval($vals['ship'] ?? 0);
                }
                if ($ship <= 0 && is_numeric($dobaMetric->self_pick_price ?? null)
                    && is_numeric($dobaMetric->anticipated_income ?? null)
                    && floatval($dobaMetric->anticipated_income) > 0) {
                    // Infer ship from last known anticipated − self_pick
                    $ship = max(0, floatval($dobaMetric->anticipated_income) - floatval($dobaMetric->self_pick_price));
                }
                $selfPickPrice = round(max(0, floatval($price) - $ship), 2);
            } else {
                $selfPickPrice = round(floatval($selfPickPrice), 2);
            }
            
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
                $this->savePricePushStatus($metricSku, 'doba', 'error', $price);
                
                Log::warning('CVR Master - Doba price push failed', [
                    'sku' => $metricSku,
                    'item_id' => $itemId,
                    'price' => $price,
                    'self_pick_price' => $selfPickPrice,
                    'error' => $errorMessage,
                    'debug' => $priceResult['debug'] ?? null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Price update failed: ' . $errorMessage,
                    'errors' => [['message' => 'Price update: ' . $errorMessage]]
                ], 400);
            }

            // Keep local metrics in sync (same as DobaController::pushPriceToDoba)
            $dobaMetric->anticipated_income = $price;
            $dobaMetric->self_pick_price = $selfPickPrice;
            $dobaMetric->save();

            $this->savePricePushStatus($metricSku, 'doba', 'pushed', $price);
            
            Log::info('CVR Master - Doba price push successful', [
                'sku' => $metricSku,
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Price $" . number_format($price, 2) . " pushed to Doba successfully for SKU: $metricSku",
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
            $skuNorm = strtoupper(trim((string) $sku));
            $ebayMetric = Ebay3Metric::where('sku', $sku)->first();
            if (!$ebayMetric) {
                $ebayMetric = Ebay3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])->first();
            }
            if (!$ebayMetric && $skuNorm !== '') {
                // Same fallback as eBay2 — some rows store OPEN BOX / USED prefixes.
                $ebayMetric = Ebay3Metric::where('sku', 'LIKE', '%' . $sku . '%')->first();
            }

            if (!$ebayMetric || !$ebayMetric->item_id) {
                $this->savePricePushStatus($sku, 'ebay3', 'error', $price);
                return response()->json([
                    'success' => false,
                    'message' => "eBay3 listing not found for SKU: $sku",
                ], 404);
            }

            // Prefer the metric's exact SKU casing so variation listings match on eBay.
            $apiSku = trim((string) ($ebayMetric->sku ?: $sku));

            $result = (new EbayThreeApiService())->reviseFixedPriceItem(
                $ebayMetric->item_id,
                $price,
                null,
                $apiSku
            );

            if (!empty($result['success'])) {
                // Keep /pricing-master-cvr modal in sync after reload (same as eBay2).
                $ebayMetric->ebay_price = $price;
                $ebayMetric->save();
                $this->savePricePushStatus($apiSku, 'ebay3', 'pushed', $price);
                return response()->json([
                    'success' => true,
                    'message' => "Price $" . number_format($price, 2) . " pushed to eBay3 for SKU: $apiSku",
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($apiSku, 'ebay3', !empty($result['accountRestricted']) ? 'account_restricted' : 'error', $price);
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
     * Push price to Temu via TemuApiService::updateSkuBasePrice (same as /temu-decrease → /temu/push-price).
     */
    private function pushToTemu($sku, $price, $goodsId = null, $skuId = null)
    {
        try {
            if (!($price > 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Temu price (must be > 0)',
                ], 400);
            }

            $result = app(TemuApiService::class)->updateSkuBasePrice(
                (string) $sku,
                (float) $price,
                $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null,
                $skuId !== null && $skuId !== '' ? (string) $skuId : null
            );

            if (!empty($result['success'])) {
                $this->savePricePushStatus($sku, 'temu', 'pushed', $price);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? ("Price $" . number_format($price, 2) . " pushed to Temu for SKU: $sku"),
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($sku, 'temu', 'error', $price);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to push price to Temu',
                'errors' => [['message' => $result['message'] ?? 'Failed to push price to Temu']],
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'temu', 'error', $price);
            Log::error('CVR Master - Temu push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Temu API error: ' . $e->getMessage(),
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Push price to Temu2 via Temu2ApiService::updateSkuBasePrice (same SPRICE→base rule as Temu on Price Increase).
     */
    private function pushToTemu2($sku, $price, $goodsId = null, $skuId = null)
    {
        try {
            if (!($price > 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Temu2 price (must be > 0)',
                ], 400);
            }

            $result = app(Temu2ApiService::class)->updateSkuBasePrice(
                (string) $sku,
                (float) $price,
                $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null,
                $skuId !== null && $skuId !== '' ? (string) $skuId : null
            );

            if (!empty($result['success'])) {
                $this->savePricePushStatus($sku, 'temu2', 'pushed', $price);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? ("Price $" . number_format($price, 2) . " pushed to Temu2 for SKU: $sku"),
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($sku, 'temu2', 'error', $price);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to push price to Temu2',
                'errors' => [['message' => $result['message'] ?? 'Failed to push price to Temu2']],
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'temu2', 'error', $price);
            Log::error('CVR Master - Temu2 push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Temu2 API error: ' . $e->getMessage(),
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Push price to TopDawg via TopDawgApiService::pushPrice (same as /topdawg-pricing).
     */
    private function pushToTopDawg($sku, $price)
    {
        try {
            if (!($price > 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid TopDawg price (must be > 0)',
                ], 400);
            }
            if (!Schema::hasTable('topdawg_products') || !Schema::hasTable('topdawg_data_views')) {
                return response()->json([
                    'success' => false,
                    'message' => 'TopDawg is not set up. Run migrations (topdawg_products / topdawg_data_views).',
                ], 503);
            }

            $result = app(TopDawgApiService::class)->pushPrice((string) $sku, (float) $price);
            if (!empty($result['ok'])) {
                $this->savePricePushStatus($sku, 'topdawg', 'pushed', $price);
                $msg = is_array($result['response'] ?? null)
                    ? ($result['response']['message'] ?? ($result['response']['error'] ?? null))
                    : (is_string($result['response'] ?? null) ? $result['response'] : null);

                return response()->json([
                    'success' => true,
                    'message' => $msg ?: ("Price $" . number_format($price, 2) . " pushed to TopDawg for SKU: $sku"),
                    'result' => $result,
                ]);
            }

            $this->savePricePushStatus($sku, 'topdawg', 'error', $price);
            $errMsg = is_array($result['response'] ?? null)
                ? ($result['response']['message'] ?? ($result['response']['error'] ?? json_encode($result['response'])))
                : (string) ($result['response'] ?? 'Failed to push price to TopDawg');

            return response()->json([
                'success' => false,
                'message' => $errMsg,
                'status' => $result['status'] ?? 0,
            ], 400);
        } catch (\Exception $e) {
            $this->savePricePushStatus($sku, 'topdawg', 'error', $price);
            Log::error('CVR Master - TopDawg push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'TopDawg API error: ' . $e->getMessage(),
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
            if (!($price > 0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot push — price is 0 or empty.'
                ], 400);
            }

            $fbaSellerSku = $this->findFbaSellerSkuForBase($sku);
            if ($fbaSellerSku === null || $fbaSellerSku === '') {
                return response()->json([
                    'success' => false,
                    'message' => "No FBA listing found for SKU: $sku"
                ], 400);
            }

            $fbaSellerSku = trim((string) $fbaSellerSku);
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
     * Read marketplace data_view JSON value for a SKU (for push history enrichment).
     */
    private function getMarketplaceDataViewValue(string $sku, string $marketplace): array
    {
        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        if ($sku === '' || $marketplace === '') {
            return [];
        }

        $dataView = null;
        $usesValues = false;
        try {
            if ($marketplace === 'amazon') {
                $dataView = AmazonDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: AmazonDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'doba') {
                $dataView = DobaDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: DobaDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'walmart') {
                $dataView = WalmartDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: WalmartDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'ebay' || $marketplace === 'ebay1') {
                $dataView = EbayDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: EbayDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'ebay2' || $marketplace === 'ebaytwo') {
                $dataView = EbayTwoDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: EbayTwoDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'ebay3' || $marketplace === 'ebaythree') {
                $dataView = EbayThreeDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: EbayThreeDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'bestbuy' || $marketplace === 'bestbuyusa') {
                $dataView = BestbuyUSADataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: BestbuyUSADataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'macy' || $marketplace === 'macys') {
                $dataView = MacyDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: MacyDataView::where('sku', $sku)->first();
            } elseif (in_array($marketplace, ['shopifyb2c', 'sb2c', 'shopify'], true)) {
                $dataView = Shopifyb2cDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: Shopifyb2cDataView::where('sku', $sku)->first();
            } elseif (in_array($marketplace, ['shopifyb2b', 'sb2b'], true)) {
                $dataView = ShopifyB2BDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: ShopifyB2BDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'reverb') {
                $dataView = ReverbViewData::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: ReverbViewData::where('sku', $sku)->first();
                $usesValues = true;
            } elseif ($marketplace === 'topdawg' && Schema::hasTable('topdawg_data_views')) {
                $dataView = TopDawgDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: TopDawgDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'temu') {
                $dataView = TemuDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: TemuDataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'temu2' && Schema::hasTable('temu2_data_view')) {
                $dataView = Temu2DataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: Temu2DataView::where('sku', $sku)->first();
            } elseif ($marketplace === 'tiktok') {
                $dataView = TiktokShopDataView::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                    ?: TiktokShopDataView::where('sku', $sku)->first();
            }
        } catch (\Throwable $e) {
            return [];
        }

        if (!$dataView) {
            return [];
        }

        $raw = $usesValues ? ($dataView->values ?? null) : ($dataView->value ?? null);
        $val = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        return is_array($val) ? $val : [];
    }

    /**
     * Normalize push meta + history from a data_view value array.
     *
     * @return array{pushed_by:?string,pushed_at:?string,pushed_price:?float,push_history:array}
     */
    private function extractPushMeta(array $val): array
    {
        $history = [];
        if (isset($val['SPRICE_PUSH_HISTORY']) && is_array($val['SPRICE_PUSH_HISTORY'])) {
            foreach ($val['SPRICE_PUSH_HISTORY'] as $h) {
                if (!is_array($h)) {
                    continue;
                }
                $history[] = $h;
            }
        }

        // Backfill a single entry from last-push fields when history is empty
        if (empty($history) && !empty($val['SPRICE_PUSHED_AT'])) {
            $history[] = [
                'price' => isset($val['SPRICE_PUSHED_VALUE']) ? floatval($val['SPRICE_PUSHED_VALUE']) : null,
                'by' => $val['SPRICE_PUSHED_BY'] ?? null,
                'at' => $val['SPRICE_PUSHED_AT'],
                'marketplace' => $val['SPRICE_PUSHED_MARKETPLACE'] ?? null,
            ];
        }

        $formatted = [];
        foreach (array_slice($history, 0, 30) as $h) {
            $atRaw = $h['at'] ?? null;
            $atFmt = null;
            if ($atRaw) {
                try {
                    $atFmt = Carbon::parse($atRaw)->format('Y-m-d H:i');
                } catch (\Throwable $e) {
                    $atFmt = is_string($atRaw) ? $atRaw : null;
                }
            }
            $price = isset($h['price']) && is_numeric($h['price']) ? round(floatval($h['price']), 2) : null;
            if ($price === null && $atFmt === null && empty($h['by'])) {
                continue;
            }
            $formatted[] = [
                'price' => $price,
                'by' => $h['by'] ?? null,
                'at' => $atFmt,
                'marketplace' => $h['marketplace'] ?? null,
            ];
        }

        $pushedAtShort = null;
        if (!empty($val['SPRICE_PUSHED_AT'])) {
            try {
                $pushedAtShort = Carbon::parse($val['SPRICE_PUSHED_AT'])->format('jM');
            } catch (\Throwable $e) {
                $pushedAtShort = null;
            }
        }

        return [
            'pushed_by' => $val['SPRICE_PUSHED_BY'] ?? null,
            'pushed_at' => $pushedAtShort,
            'pushed_price' => isset($val['SPRICE_PUSHED_VALUE']) && is_numeric($val['SPRICE_PUSHED_VALUE'])
                ? round(floatval($val['SPRICE_PUSHED_VALUE']), 2)
                : null,
            'push_history' => $formatted,
        ];
    }

    /**
     * Attach push_history / pushed_price onto each OV L30 breakdown row.
     */
    private function enrichBreakdownPushHistory(array &$breakdownData, string $fullSku): void
    {
        foreach ($breakdownData as &$row) {
            $mp = strtolower(trim((string) ($row['marketplace'] ?? '')));
            $rowSku = ($row['sku'] ?? null) && ($row['sku'] !== 'Not Listed')
                ? (string) $row['sku']
                : $fullSku;
            $val = $this->getMarketplaceDataViewValue($rowSku, $mp);
            if (empty($val) && strtoupper($rowSku) !== strtoupper($fullSku)) {
                $val = $this->getMarketplaceDataViewValue($fullSku, $mp);
            }
            $meta = $this->extractPushMeta($val);
            if (empty($row['pushed_by']) && !empty($meta['pushed_by'])) {
                $row['pushed_by'] = $meta['pushed_by'];
                $row['pushed_at'] = $meta['pushed_at'];
            }
            $row['pushed_price'] = $meta['pushed_price'];
            // Ensure marketplace label is on each history row for display
            $hist = [];
            foreach ($meta['push_history'] as $h) {
                $h['marketplace'] = $h['marketplace'] ?: ($row['marketplace'] ?? $mp);
                $hist[] = $h;
            }
            $row['push_history'] = $hist;
        }
        unset($row);
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
            } elseif ($marketplace === 'topdawg') {
                if (Schema::hasTable('topdawg_data_views')) {
                    $dataView = TopDawgDataView::firstOrNew(['sku' => $sku]);
                }
            } elseif ($marketplace === 'temu') {
                $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'temu2' && Schema::hasTable('temu2_data_view')) {
                $dataView = Temu2DataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'tiktok') {
                $dataView = TiktokShopDataView::firstOrNew(['sku' => $sku]);
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
                $pushedBy = null;
                if (auth()->check()) {
                    $pushedBy = auth()->user()->name ?? auth()->user()->email;
                    $existing['SPRICE_PUSHED_BY'] = $pushedBy;
                    $existing['SPRICE_PUSHED_BY_ID'] = auth()->id();
                }
                $pushedAt = now()->toDateTimeString();
                $existing['SPRICE_PUSHED_AT'] = $pushedAt;
                
                // Save the pushed price
                if ($pushedPrice !== null) {
                    $existing['SPRICE_PUSHED_VALUE'] = $pushedPrice;
                }

                // Append to rolling push history (newest first, keep 30)
                if ($status === 'pushed' && $pushedPrice !== null && floatval($pushedPrice) > 0) {
                    $hist = isset($existing['SPRICE_PUSH_HISTORY']) && is_array($existing['SPRICE_PUSH_HISTORY'])
                        ? $existing['SPRICE_PUSH_HISTORY']
                        : [];
                    array_unshift($hist, [
                        'price' => round(floatval($pushedPrice), 2),
                        'by' => $pushedBy ?: 'Unknown',
                        'at' => $pushedAt,
                        'marketplace' => $marketplace,
                    ]);
                    $existing['SPRICE_PUSH_HISTORY'] = array_slice($hist, 0, 30);
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
     * - Doba: 25% discount (price * 0.75)
     * - Shopify B2B: always SPRICE/price = (base × 0.75) − Ship
     * - Others (Amazon, Walmart, Shopify B2C): Full price
     */
    public function bulkChangePrice(Request $request)
    {
        // Prefer per-SKU items (Decrease / Increase / Same Price modes).
        // Legacy: single `price` + `skus[]` still supported.
        $rawItems = $request->input('items');
        $skuPrices = [];

        if (is_array($rawItems) && count($rawItems) > 0) {
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
                $price = isset($item['price']) ? round(floatval($item['price']), 2) : 0;
                if ($sku !== '' && $price >= 0.01) {
                    $skuPrices[$sku] = $price;
                }
            }
        } else {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'price' => 'required|numeric|min:0.01|max:999999.99',
                'skus' => 'required|array',
                'skus.*' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                ], 400);
            }

            $basePrice = round(floatval($request->input('price')), 2);
            $skus = array_map(fn ($s) => strtoupper(trim($s)), $request->input('skus', []));
            $skus = array_values(array_filter(array_unique($skus)));
            foreach ($skus as $sku) {
                $skuPrices[$sku] = $basePrice;
            }
        }

        if ($skuPrices === []) {
            return response()->json(['success' => false, 'message' => 'No valid SKUs / prices provided'], 400);
        }

        $mode = strtolower(trim((string) $request->input('mode', 'same')));
        $pushableMarketplaces = ['amazon', 'doba', 'walmart', 'sb2c', 'shopify', 'sb2b', 'reverb'];
        $updated = 0;
        $errors = [];

        foreach ($skuPrices as $sku => $basePrice) {
            $dobaPrice = round($basePrice * 0.75, 2);
            // SB2B: always (Price × 0.75) − Ship
            $sb2bShip = 0.0;
            $pmForShip = ProductMaster::where('sku', $sku)->first();
            if ($pmForShip) {
                $pmVals = is_array($pmForShip->Values)
                    ? $pmForShip->Values
                    : (is_string($pmForShip->Values) ? json_decode($pmForShip->Values, true) : []);
                if (is_array($pmVals)) {
                    foreach ($pmVals as $k => $v) {
                        if (strtolower((string) $k) === 'ship') {
                            $sb2bShip = floatval($v);
                            break;
                        }
                    }
                }
                if ($sb2bShip <= 0 && isset($pmForShip->ship)) {
                    $sb2bShip = floatval($pmForShip->ship);
                }
            }
            $sb2bPrice = max(0.01, round(($basePrice * 0.75) - $sb2bShip, 2));

            foreach ($pushableMarketplaces as $mp) {
                $price = match ($mp) {
                    'doba' => $dobaPrice,
                    'sb2b' => $sb2bPrice,
                    default => $basePrice
                };

                try {
                    // Keep suggested SPRICE in sync with bulk pricing rules,
                    // so Doba/Shopify wholesale shows 25% reduced price in UI.
                    if (in_array($mp, ['doba', 'sb2b'], true)) {
                        $this->saveSpriceToView($sku, $mp, $price);
                    }

                    $req = Request::create('/cvr-master-push-price', 'POST', [
                        'sku' => $sku,
                        'price' => $price,
                        'marketplace' => $mp,
                        '_token' => $request->input('_token'),
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

        $skuCount = count($skuPrices);
        $modeLabel = match ($mode) {
            'increase' => 'Increase',
            'decrease' => 'Decrease',
            default => 'Same Price',
        };

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'errors' => array_slice($errors, 0, 10),
            'message' => "{$modeLabel} applied to {$skuCount} SKU(s) across marketplaces.",
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
     * Loose SKU key (alphanumeric only) — same fallback as /temu-decrease campaign join.
     */
    private static function normalizeTemuSkuLooseForCvr(string $sku): string
    {
        $s = strtoupper(trim((string) $sku));
        if ($s === '') {
            return '';
        }
        return preg_replace('/[^A-Z0-9]/', '', $s) ?? '';
    }

    /**
     * Aggregate Temu Ads% — same formula as /temu-decrease badgeAvgAds:
     * (SUM temu_campaign_reports.spend for range) ÷ marketplace_daily_metrics Temu total_sales × 100.
     */
    private function resolveTemuAggregateAdsPercent(string $reportRange = 'L30'): float
    {
        static $cache = [];
        $key = strtoupper($reportRange);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $aggregate = 0.0;
        try {
            $totalSales = 0.0;
            if (Schema::hasTable('marketplace_daily_metrics')) {
                $metrics = MarketplaceDailyMetric::where('channel', 'Temu')->latest('date')->first();
                $totalSales = (float) ($metrics->total_sales ?? 0);
            }
            $totalAdSpend = 0.0;
            if (Schema::hasTable('temu_campaign_reports')) {
                $totalAdSpend = (float) (TemuCampaignReport::where('report_range', $key)
                    ->selectRaw('SUM(spend) as total_spend')
                    ->value('total_spend') ?? 0);
            }
            $aggregate = $totalSales > 0 ? ($totalAdSpend / $totalSales) * 100 : 0.0;
        } catch (\Throwable $e) {
            Log::warning('CVR Master Temu aggregate Ads% failed: ' . $e->getMessage());
        }

        $cache[$key] = round($aggregate, 2);
        return $cache[$key];
    }

    /**
     * Load Temu ads-sheet spend indexes from temu_campaign_reports (same as /temu-decrease).
     *
     * @return array{0: array<string, float>, 1: array<string, float>, 2: array<string, float>}
     */
    private function loadTemuCampaignSpendIndexes(string $reportRange = 'L30'): array
    {
        $byGoods = [];
        $bySku = [];
        $bySkuLoose = [];
        if (!Schema::hasTable('temu_campaign_reports')) {
            return [$byGoods, $bySku, $bySkuLoose];
        }
        try {
            $rows = TemuCampaignReport::where('report_range', $reportRange)
                ->selectRaw('goods_id, sku, SUM(spend) as spend_l30')
                ->groupBy('goods_id', 'sku')
                ->get();
            foreach ($rows as $r) {
                $spend = round((float) ($r->spend_l30 ?? 0), 2);
                $gidKey = TemuGoodsIdHelper::normalizeKey($r->goods_id);
                if ($gidKey) {
                    $byGoods[$gidKey] = ($byGoods[$gidKey] ?? 0) + $spend;
                }
                $skuKey = self::normalizeTemuSkuForCvr((string) ($r->sku ?? ''));
                if ($skuKey !== '') {
                    $bySku[$skuKey] = ($bySku[$skuKey] ?? 0) + $spend;
                }
                $loose = self::normalizeTemuSkuLooseForCvr((string) ($r->sku ?? ''));
                if ($loose !== '') {
                    $bySkuLoose[$loose] = ($bySkuLoose[$loose] ?? 0) + $spend;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CVR Master Temu campaign spend load skipped: ' . $e->getMessage());
        }
        return [$byGoods, $bySku, $bySkuLoose];
    }

    /**
     * Resolve Temu ad spend — goods_id → strict SKU → loose SKU (same chain as /temu-decrease).
     *
     * @param  array<string, float>  $byGoods
     * @param  array<string, float>  $bySku
     * @param  array<string, float>  $bySkuLoose
     */
    private function lookupTemuCampaignSpend(
        $goodsId,
        string $sku,
        array $byGoods,
        array $bySku,
        array $bySkuLoose
    ): float {
        $gidKey = TemuGoodsIdHelper::normalizeKey($goodsId);
        if ($gidKey && isset($byGoods[$gidKey])) {
            return (float) $byGoods[$gidKey];
        }
        $skuKey = self::normalizeTemuSkuForCvr($sku);
        if ($skuKey !== '' && isset($bySku[$skuKey])) {
            return (float) $bySku[$skuKey];
        }
        $loose = self::normalizeTemuSkuLooseForCvr($sku);
        if ($loose !== '' && isset($bySkuLoose[$loose])) {
            return (float) $bySkuLoose[$loose];
        }
        return 0.0;
    }

    /**
     * Effective Temu LMP = Price + Delivery.
     * Default Delivery = $2.99 when Price is below $27 (manual delivery overrides).
     *
     * @param  array{price?: mixed, delivery?: mixed}|null  $entry
     */
    private function temuLmpEntryEffectivePriceForCvr(?array $entry): ?float
    {
        if (! is_array($entry)) {
            return null;
        }
        $price = $entry['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0) && $p !== 0.0) {
            return null;
        }
        $delivery = $entry['delivery'] ?? 0;
        $d = (is_numeric($delivery) && (float) $delivery > 0) ? (float) $delivery : 0.0;
        if ($d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    /**
     * Temu LMP entries from a temu_lmp row — same logic as TemuController::extractTemuLmpEntries.
     *
     * @param  TemuLmp|object|null  $temuLmpRow
     * @return list<array{price: mixed, link: mixed}>
     */
    private function extractTemuLmpEntriesFromRow($temuLmpRow): array
    {
        if (!$temuLmpRow) {
            return [];
        }

        $entries = $temuLmpRow->lmp_entries ?? null;
        if (is_string($entries)) {
            $entries = json_decode($entries, true);
        }
        if (is_array($entries) && count($entries) > 0) {
            return $entries;
        }

        $lmpEntries = [];
        if (($temuLmpRow->lmp ?? null) !== null || !empty($temuLmpRow->lmp_link)) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp, 'link' => $temuLmpRow->lmp_link ?? null];
        }
        if (($temuLmpRow->lmp_2 ?? null) !== null || !empty($temuLmpRow->lmp_link_2)) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp_2, 'link' => $temuLmpRow->lmp_link_2 ?? null];
        }

        return $lmpEntries;
    }

    /**
     * Load temu_lmp rows for a SKU (and its LMP Sku Link group). Never TemuLmp::all().
     *
     * @param  list<string>  $seedSkus
     * @return array{0: array<string, TemuLmp>, 1: LmpSkuGroupService|null}
     */
    private function loadTemuLmpMapForSkus(array $seedSkus): array
    {
        $map = [];
        $groupService = null;
        $seedSkus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $seedSkus
        ))));
        if ($seedSkus === [] || !Schema::hasTable('temu_lmp')) {
            return [$map, $groupService];
        }

        try {
            $groupService = app(LmpSkuGroupService::class);
            $groupService->prepareForSkus($seedSkus);
        } catch (\Throwable $e) {
            $groupService = null;
        }

        $memberSkus = $seedSkus;
        if ($groupService) {
            foreach ($seedSkus as $s) {
                try {
                    $group = $groupService->groupContaining($s);
                    if (!empty($group)) {
                        $memberSkus = array_merge($memberSkus, $group);
                    }
                } catch (\Throwable $e) {
                    // keep seed SKUs
                }
            }
        }
        $memberSkus = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $memberSkus
        ))));

        foreach (array_chunk($memberSkus, 400) as $chunk) {
            foreach (
                TemuLmp::query()
                    ->select(['id', 'sku', 'lmp', 'lmp_link', 'lmp_2', 'lmp_link_2', 'lmp_entries'])
                    ->whereIn('sku', $chunk)
                    ->get() as $temuLmpRow
            ) {
                $map[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
            }
        }

        // Normalized fallback only for missing members (bounded OR, not full table)
        $missingNorms = [];
        foreach ($memberSkus as $memberSku) {
            $n = self::normalizeTemuSkuForCvr($memberSku);
            if ($n !== '' && !isset($map[$n])) {
                $missingNorms[$n] = true;
            }
        }
        if ($missingNorms !== []) {
            $q = TemuLmp::query()->select(['id', 'sku', 'lmp', 'lmp_link', 'lmp_2', 'lmp_link_2', 'lmp_entries']);
            $q->where(function ($qq) use ($missingNorms) {
                foreach (array_keys($missingNorms) as $n) {
                    $qq->orWhereRaw(
                        'UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(sku), CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?',
                        [$n]
                    );
                }
            });
            foreach ($q->limit(50)->get() as $temuLmpRow) {
                $map[self::normalizeTemuSkuForCvr((string) ($temuLmpRow->sku ?? ''))] = $temuLmpRow;
            }
        }

        return [$map, $groupService];
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
     * @param  array<string, TemuLmp|object>  $temuLmpByNormalizedSku
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
            if (! $row) {
                continue;
            }
            foreach ($this->extractTemuLmpEntriesFromRow($row) as $e) {
                if (! is_array($e)) {
                    continue;
                }
                // Preserve which temu_lmp row owns this entry (edit/delete write back here)
                $e['source_sku'] = (string) ($row->sku ?? $linkedSku);
                $lmpEntries[] = $e;
            }
        }
        $lmpEntries = $this->dedupeTemuLmpEntriesList($lmpEntries);

        // L1 = lowest non-ignored entry (Price + Delivery; same as /temu-decrease)
        $prices = [];
        $l1Link = null;
        foreach ($lmpEntries as $entry) {
            if (! empty($entry['ignored'])) {
                continue;
            }
            $eff = $this->temuLmpEntryEffectivePriceForCvr($entry);
            if ($eff !== null && $eff > 0) {
                $prices[] = $eff;
            }
        }
        if (count($prices) > 0) {
            $minPrice = min($prices);
            foreach ($lmpEntries as $entry) {
                if (! empty($entry['ignored'])) {
                    continue;
                }
                $eff = $this->temuLmpEntryEffectivePriceForCvr($entry);
                if ($eff !== null && abs($eff - (float) $minPrice) < 0.00001) {
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

            [$temuLmpByNormalizedSku, $groupService] = $this->loadTemuLmpMapForSkus([$sku]);

            $resolved = $this->resolveTemuLmpForSku($sku, $temuLmpByNormalizedSku, $groupService);
            $competitors = [];
            foreach ($resolved['entries'] as $idx => $entry) {
                $price = isset($entry['price']) && $entry['price'] !== '' ? floatval($entry['price']) : 0;
                $deliveryRaw = $entry['delivery'] ?? 0;
                $delivery = (is_numeric($deliveryRaw) && (float) $deliveryRaw > 0) ? (float) $deliveryRaw : 0.0;
                // Default Temu Del $2.99 when Price < $27
                if ($delivery <= 0 && $price > 0 && $price < 27) {
                    $delivery = 2.99;
                }
                $effective = round($price + $delivery, 2);
                if ($price <= 0) {
                    continue;
                }
                $competitors[] = [
                    'id' => 'temu-' . ($idx + 1),
                    // Keep item Price as base; Del/P+S use delivery separately in the LMP drawer
                    'price' => round($price, 2),
                    'base_price' => round($price, 2),
                    'delivery' => $delivery,
                    'shipping_cost' => $delivery,
                    'total_price' => $effective,
                    'ignored' => ! empty($entry['ignored']),
                    'product_link' => $entry['link'] ?? null,
                    'link' => $entry['link'] ?? null,
                    'source_sku' => $entry['source_sku'] ?? $sku,
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
     * Toggle LMP competitor ignored flag (same behavior as /temu-decrease ignore for L1).
     * Amazon / eBay / Google: column on *_sku_competitors. Temu: lmp_entries[].ignored.
     */
    public function toggleLmpIgnored(Request $request)
    {
        $marketplace = strtolower(trim((string) $request->input('marketplace', '')));
        $ignored = filter_var($request->input('ignored'), FILTER_VALIDATE_BOOLEAN);
        $id = $request->input('id');
        $sku = trim((string) $request->input('sku', ''));

        if (!in_array($marketplace, ['amazon', 'ebay', 'google', 'bestbuy', 'macy', 'reverb', 'temu'], true)) {
            return response()->json(['success' => false, 'error' => 'Invalid marketplace'], 400);
        }

        try {
            if ($marketplace === 'temu') {
                if ($sku === '') {
                    return response()->json(['success' => false, 'error' => 'SKU required'], 400);
                }
                $idx = preg_match('/^temu-(\d+)$/i', (string) $id, $mm) ? ((int) $mm[1] - 1) : -1;
                if ($idx < 0) {
                    return response()->json(['success' => false, 'error' => 'Invalid Temu LMP id'], 400);
                }

                [$temuLmpByNormalizedSku, $groupService] = $this->loadTemuLmpMapForSkus([$sku]);
                $resolved = $this->resolveTemuLmpForSku($sku, $temuLmpByNormalizedSku, $groupService);
                $merged = $resolved['entries'];
                if ($idx >= count($merged)) {
                    return response()->json(['success' => false, 'error' => 'Temu LMP entry not found'], 404);
                }

                $targetEntry = $merged[$idx];
                $sourceSku = trim((string) ($targetEntry['source_sku'] ?? $sku));
                if ($sourceSku === '') {
                    $sourceSku = $sku;
                }
                $sourceKey = self::normalizeTemuSkuForCvr($sourceSku);
                $row = $temuLmpByNormalizedSku[$sourceKey] ?? null;

                // Mutate the owning temu_lmp row only (not the full merged list)
                $ownEntries = $row ? $this->extractTemuLmpEntriesFromRow($row) : [];
                $matched = false;
                $op = isset($targetEntry['price']) && is_numeric($targetEntry['price']) ? (float) $targetEntry['price'] : null;
                $ol = strtoupper(trim((string) ($targetEntry['link'] ?? '')));
                foreach ($ownEntries as $i => $own) {
                    $ep = isset($own['price']) && is_numeric($own['price']) ? (float) $own['price'] : null;
                    $el = strtoupper(trim((string) ($own['link'] ?? '')));
                    if ($op !== null && $ep !== null && abs($ep - $op) < 0.001 && $el === $ol) {
                        $ownEntries[$i]['ignored'] = $ignored;
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    // Fallback: update by merged index when this SKU owns the whole list
                    if ($sourceKey === self::normalizeTemuSkuForCvr($sku) && $idx < count($ownEntries)) {
                        $ownEntries[$idx]['ignored'] = $ignored;
                        $matched = true;
                    }
                }
                if (! $matched) {
                    return response()->json(['success' => false, 'error' => 'Temu LMP entry not found on source SKU'], 404);
                }

                if (! $row) {
                    if (! Schema::hasTable('temu_lmp')) {
                        return response()->json(['success' => false, 'error' => 'Temu LMP table not available'], 404);
                    }
                    $row = TemuLmp::create([
                        'sku' => $sourceSku,
                        'lmp_entries' => $ownEntries,
                    ]);
                }

                $active = array_values(array_filter($ownEntries, fn ($e) => empty($e['ignored'])));
                $effectivePrices = [];
                foreach ($active as $e) {
                    $eff = $this->temuLmpEntryEffectivePriceForCvr($e);
                    if ($eff !== null) {
                        $effectivePrices[] = $eff;
                    }
                }
                $firstPrice = count($effectivePrices) > 0 ? min($effectivePrices) : null;
                $firstLink = null;
                if ($firstPrice !== null) {
                    foreach ($active as $e) {
                        $eff = $this->temuLmpEntryEffectivePriceForCvr($e);
                        if ($eff !== null && abs($eff - (float) $firstPrice) < 0.00001) {
                            $firstLink = $e['link'] ?? null;
                            break;
                        }
                    }
                }

                $row->update([
                    'lmp' => $firstPrice,
                    'lmp_link' => $firstLink,
                    'lmp_entries' => $ownEntries,
                    'lmp_2' => null,
                    'lmp_link_2' => null,
                ]);

                return response()->json(['success' => true, 'ignored' => $ignored, 'message' => $ignored ? 'Ignored for L1' : 'Included in L1']);
            }

            $model = match ($marketplace) {
                'amazon' => AmazonSkuCompetitor::class,
                'ebay' => EbaySkuCompetitor::class,
                'google' => GoogleSkuCompetitor::class,
                'bestbuy' => BestbuySkuCompetitor::class,
                'macy' => MacySkuCompetitor::class,
                'reverb' => ReverbSkuCompetitor::class,
            };
            $table = (new $model)->getTable();
            if (!Schema::hasColumn($table, 'ignored')) {
                return response()->json(['success' => false, 'error' => 'Ignore column missing — run migrations'], 500);
            }
            if (!$id || !is_numeric($id)) {
                return response()->json(['success' => false, 'error' => 'Valid ID is required'], 400);
            }
            $comp = $model::find((int) $id);
            if (!$comp) {
                return response()->json(['success' => false, 'error' => 'LMP entry not found'], 404);
            }
            $comp->ignored = $ignored;
            $comp->save();

            return response()->json(['success' => true, 'ignored' => $ignored, 'message' => $ignored ? 'Ignored for L1' : 'Included in L1']);
        } catch (\Throwable $e) {
            Log::error('toggleLmpIgnored failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed to update ignore: ' . $e->getMessage()], 500);
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
     * Product clicks for a goods_id — same metric as /temu-decrease Views.
     * Temu 1: SUM(product_clicks) from temu_view_data sheet; fallback Ads API product_clicks_l30.
     * Temu 2: SUM(product_clicks) from temu2_view_data sheet.
     * Returns null when goods_id is missing (N/A), otherwise the click total (may be 0).
     */
    private function resolveTemuProductClicks($goodsId, bool $isTemu2 = false): ?int
    {
        if ($goodsId === null || $goodsId === '') {
            return null;
        }
        if (!$isTemu2) {
            $nk = TemuGoodsIdHelper::normalizeKey($goodsId);
            if (Schema::hasTable('temu_view_data')) {
                $sum = (int) TemuViewData::where('goods_id', $goodsId)->sum('product_clicks');
                if ($sum === 0 && $nk && (string) $goodsId !== $nk) {
                    $sum = (int) TemuViewData::where('goods_id', $nk)->sum('product_clicks');
                }
                // If sheet has any rows for this goods_id, use sheet total (even 0)
                $hasSheet = TemuViewData::where('goods_id', $goodsId)->exists()
                    || ($nk && TemuViewData::where('goods_id', $nk)->exists());
                if ($hasSheet) {
                    return $sum;
                }
            }
            if (!Schema::hasTable('temu_metrics') || !Schema::hasColumn('temu_metrics', 'product_clicks_l30')) {
                return null;
            }
            $max = (int) TemuMetric::where('goods_id', $goodsId)->max('product_clicks_l30');
            if ($max === 0 && $nk && (string) $goodsId !== $nk) {
                $max = (int) TemuMetric::where('goods_id', $nk)->max('product_clicks_l30');
            }
            return $max;
        }
        if (!Schema::hasTable('temu2_view_data')) {
            return null;
        }
        $nk = TemuGoodsIdHelper::normalizeKey($goodsId);
        $sum = (int) Temu2ViewData::where('goods_id', $goodsId)->sum('product_clicks');
        if ($sum === 0 && $nk && (string) $goodsId !== $nk) {
            $sum = (int) Temu2ViewData::where('goods_id', $nk)->sum('product_clicks');
        }
        return $sum;
    }

    /**
     * Normalize Shein SKU the same way as /shein-pricing (SheinController::normalizeSheinSkuExact).
     */
    private function normalizeSheinSkuForCvr(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku) ?? '');
    }

    /**
     * Shein AL30 qty per normalized seller_sku from shein_daily_data.
     * Same status exclusions as /shein-pricing.
     *
     * @return array<string, int>
     */
    private function fetchSheinL30ByNormalizedSku(): array
    {
        if (!Schema::hasTable('shein_daily_data')) {
            return [];
        }

        try {
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $out = [];
            \App\Models\SheinDailyData::query()
                ->whereNotNull('seller_sku')->where('seller_sku', '!=', '')
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->get(['seller_sku', 'quantity'])
                ->each(function ($row) use (&$out) {
                    $key = $this->normalizeSheinSkuForCvr((string) ($row->seller_sku ?? ''));
                    if ($key === '') {
                        return;
                    }
                    $qty = max(1, (int) ($row->quantity ?? 0));
                    $out[$key] = ($out[$key] ?? 0) + $qty;
                });

            return $out;
        } catch (\Throwable $e) {
            Log::warning('CVR Master fetchSheinL30ByNormalizedSku: ' . $e->getMessage());

            return [];
        }
    }

    /** Shein AL30 for one SKU (normalized), same source as /shein-pricing. */
    private function fetchSheinL30QtyForSku(string $sku): int
    {
        $norm = $this->normalizeSheinSkuForCvr($sku);
        if ($norm === '') {
            return 0;
        }

        return (int) (($this->fetchSheinL30ByNormalizedSku())[$norm] ?? 0);
    }

    /**
     * L30 qty + sales $ per normalized SKU from topdawg_order_metrics.
     * Same window / status exclusions as /topdawg-pricing (America/Los_Angeles, last 30 days).
     *
     * @return array<string, array{qty: int, sales: float}>
     */
    private function fetchTopDawgL30OrderAggregatesBySku(): array
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return [];
        }

        try {
            $today = Carbon::now('America/Los_Angeles')->startOfDay();
            $l30Start = $today->copy()->subDays(30)->toDateString();
            $l30End = $today->toDateString();
            $excluded = ['returned', 'refunded', 'cancelled', 'declined', 'failed'];

            $rows = TopDawgOrderMetric::query()
                ->whereBetween('order_date', [$l30Start, $l30End])
                ->whereNotIn('status', $excluded)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'quantity', 'amount']);

            $out = [];
            foreach ($rows as $row) {
                $key = ShopifySku::normalizeSkuForShopifyLookup((string) ($row->sku ?? ''));
                if ($key === '') {
                    continue;
                }
                $qty = (int) ($row->quantity ?? 1);
                $qty = $qty >= 1 ? $qty : 1;
                $amount = (float) ($row->amount ?? 0);

                if (!isset($out[$key])) {
                    $out[$key] = ['qty' => 0, 'sales' => 0.0];
                }
                $out[$key]['qty'] += $qty;
                $out[$key]['sales'] += $amount;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('CVR Master fetchTopDawgL30OrderAggregatesBySku: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * TopDawg L30 qty for one SKU (normalized), same source as /topdawg-pricing.
     */
    private function fetchTopDawgL30QtyForSku(string $sku): int
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm === '') {
            return 0;
        }
        $agg = $this->fetchTopDawgL30OrderAggregatesBySku();

        return (int) ($agg[$norm]['qty'] ?? 0);
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
