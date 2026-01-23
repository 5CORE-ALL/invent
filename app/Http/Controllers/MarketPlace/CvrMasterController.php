<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
use App\Models\TemuAdData;
use App\Models\MarketplacePercentage;
use App\Models\AmazonSpCampaignReport;
use Carbon\Carbon;

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
            $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

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
            $temuPricings = TemuPricing::whereIn('sku', $skus)->get()->keyBy('sku');
            $temuDailySales = TemuDailyData::whereIn('contribution_sku', $skus)
                ->selectRaw('contribution_sku as sku, SUM(quantity_purchased) as temu_l30')
                ->groupBy('contribution_sku')
                ->get()
                ->keyBy('sku');
            
            // Get Temu percentage
            $temuMarketplace = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $temuPercentage = $temuMarketplace ? ($temuMarketplace->percentage / 100) : 0.87;
            
            Log::info('CVR Master - Temu Data fetched', [
                'temu_pricings' => $temuPricings->count(),
                'temu_percentage' => $temuPercentage * 100 . '%'
            ]);

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

                // Get LP and Ship from ProductMaster Values
                $lp = 0;
                $ship = 0;
                if ($values) {
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") $lp = floatval($v);
                        if (strtolower($k) === "ship") $ship = floatval($v);
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
                
                // Amazon GPFT% = (price × 0.80 - ship - lp) / price × 100 (hardcoded 80%)
                $amazonGPFT = $amazonPrice > 0 ? ((($amazonPrice * 0.80 - $ship - $lp) / $amazonPrice) * 100) : 0;
                
                // Get Amazon ad spend
                $amazonCampaign = $amazonSpCampaigns->get($sku);
                $amazonAdSpend = $amazonCampaign ? floatval($amazonCampaign->spend ?? 0) : 0;
                $amazonRevenue = $amazonPrice * $amazonL30;
                $amazonAD = $amazonRevenue > 0 ? ($amazonAdSpend / $amazonRevenue) * 100 : 0;
                
                // Amazon PFT% = GPFT% - AD%
                $amazonPFT = $amazonGPFT - $amazonAD;
                
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
                
                // Calculate aggregated metrics across all marketplaces
                
                // Get views from all marketplaces
                $amazonViews = $amazonSheet ? intval($amazonSheet->sessions_l30 ?? 0) : 0;
                $ebay1Views = $ebay1Metric ? intval($ebay1Metric->views ?? 0) : 0;
                $ebay2Views = $ebay2Metric ? intval($ebay2Metric->views ?? 0) : 0;
                $ebay3Views = $ebay3Metric ? intval($ebay3Metric->views ?? 0) : 0;
                $temuViews = 0;
                if ($temuPricing) {
                    $goodsId = $temuPricing->goods_id ?? null;
                    if ($goodsId) {
                        $temuViewData = TemuViewData::where('goods_id', $goodsId)
                            ->selectRaw('SUM(product_clicks) as product_clicks')
                            ->first();
                        $temuViews = $temuViewData ? intval($temuViewData->product_clicks ?? 0) : 0;
                    }
                }
                $tiktokViews = $tiktokProduct ? intval($tiktokProduct->views ?? 0) : 0;
                $bbViews = 0; // BestBuy doesn't track views
                $sb2cViews = 0; // Shopify B2C doesn't track views separately
                $macyViews = $macyProduct ? intval($macyProduct->views ?? 0) : 0;
                $reverbViews = $reverbProduct ? intval($reverbProduct->views ?? 0) : 0;
                $dobaViews = $dobaMetric ? intval($dobaMetric->impressions ?? 0) : 0;
                
                // Total Views (sum of all marketplace views)
                $totalViews = $amazonViews + $ebay1Views + $ebay2Views + $ebay3Views + $temuViews + 
                              $walmartViews + $tiktokViews + $bbViews + $sb2cViews + 
                              $macyViews + $reverbViews + $dobaViews;
                
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
                $totalL30 = $amazonL30 + $ebay1L30 + $ebay2L30 + $ebay3L30 + $temuL30 + 
                           $walmartL30 + $tiktokL30 + $bbL30 + $sb2cL30 + 
                           $macyL30 + $reverbL30 + $dobaL30;
                
                // Calculate Avg CVR using CVR formula: (Total L30 / Total Views) × 100
                $avgCVR = $totalViews > 0 ? round(($totalL30 / $totalViews) * 100, 2) : 0;
                
                // Collect all prices (non-zero)
                $prices = [];
                if ($amazonPrice > 0) $prices[] = $amazonPrice;
                if ($ebay1Price > 0) $prices[] = $ebay1Price;
                if ($ebay2Price > 0) $prices[] = $ebay2Price;
                if ($ebay3Price > 0) $prices[] = $ebay3Price;
                if ($temuPrice > 0) $prices[] = $temuPrice;
                if ($walmartPrice > 0) $prices[] = $walmartPrice;
                if ($tiktokPrice > 0) $prices[] = $tiktokPrice;
                if ($bbPrice > 0) $prices[] = $bbPrice;
                if ($sb2cPrice > 0) $prices[] = $sb2cPrice;
                if ($macyPrice > 0) $prices[] = $macyPrice;
                if ($reverbPrice > 0) $prices[] = $reverbPrice;
                if ($dobaPrice > 0) $prices[] = $dobaPrice;
                
                // Collect all GPFT values (non-zero or negative)
                $gpftValues = [];
                if ($amazonPrice > 0) $gpftValues[] = $amazonGPFT;
                if ($ebay1Price > 0) $gpftValues[] = $ebay1GPFT;
                if ($ebay2Price > 0) $gpftValues[] = $ebay2GPFT;
                if ($ebay3Price > 0) $gpftValues[] = $ebay3GPFT;
                if ($temuPrice > 0) $gpftValues[] = $temuGPFT;
                if ($walmartPrice > 0) $gpftValues[] = $walmartGPFT;
                if ($tiktokPrice > 0) $gpftValues[] = $tiktokGPFT;
                if ($bbPrice > 0) $gpftValues[] = $bbGPFT;
                if ($sb2cPrice > 0) $gpftValues[] = $sb2cGPFT;
                if ($macyPrice > 0) $gpftValues[] = $macyGPFT;
                if ($reverbPrice > 0) $gpftValues[] = $reverbGPFT;
                if ($dobaPrice > 0) $gpftValues[] = $dobaGPFT;
                
                // Collect all AD values (marketplaces with ads: Amazon, Temu, Walmart)
                $adValues = [];
                if ($amazonPrice > 0) $adValues[] = $amazonAD;
                if ($temuPrice > 0) $adValues[] = $temuAD;
                if ($walmartPrice > 0) $adValues[] = $walmartAD;
                
                // Collect all PFT values
                $pftValues = [];
                if ($amazonPrice > 0) $pftValues[] = $amazonPFT;
                if ($ebay1Price > 0) $pftValues[] = $ebay1PFT;
                if ($ebay2Price > 0) $pftValues[] = $ebay2PFT;
                if ($ebay3Price > 0) $pftValues[] = $ebay3PFT;
                if ($temuPrice > 0) $pftValues[] = $temuPFT;
                if ($walmartPrice > 0) $pftValues[] = $walmartPFT;
                if ($tiktokPrice > 0) $pftValues[] = $tiktokPFT;
                if ($bbPrice > 0) $pftValues[] = $bbPFT;
                if ($sb2cPrice > 0) $pftValues[] = $sb2cPFT;
                if ($macyPrice > 0) $pftValues[] = $macyPFT;
                if ($reverbPrice > 0) $pftValues[] = $reverbPFT;
                if ($dobaPrice > 0) $pftValues[] = $dobaPFT;
                
                // Calculate averages
                $avgPrice = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
                $avgGPFT = count($gpftValues) > 0 ? round(array_sum($gpftValues) / count($gpftValues), 2) : 0;
                $avgAD = count($adValues) > 0 ? round(array_sum($adValues) / count($adValues), 2) : 0;
                $avgPFT = count($pftValues) > 0 ? round(array_sum($pftValues) / count($pftValues), 2) : 0;

                $result[] = (object) [
                    "sku" => $sku,
                    "parent" => $parent,
                    "image_path" => $imagePath,
                    "inventory" => $inventory,
                    "overall_l30" => $overallL30,
                    "m_l30" => $totalL30,
                    "dil_percent" => $dilPercent,
                    "total_views" => $totalViews,
                    "avg_cvr" => $avgCVR,
                    "avg_price" => $avgPrice,
                    "avg_gpft" => $avgGPFT,
                    "avg_ad" => $avgAD,
                    "avg_pft" => $avgPFT,
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
                $parentRow = [
                    'SL No.' => $slNo++,
                    'sku' => 'PARENT ' . $parent,
                    'parent' => $parent,
                    'image_path' => null,
                    'inventory' => $rows->sum('inventory'),
                    'overall_l30' => $rows->sum('overall_l30'),
                    'm_l30' => $rows->sum('m_l30'),
                    'dil_percent' => 0, // Calculate after
                    'total_views' => $rows->sum('total_views'),
                    'avg_cvr' => $rows->count() > 0 ? round($rows->avg('avg_cvr'), 2) : 0,
                    'avg_price' => $rows->count() > 0 ? round($rows->avg('avg_price'), 2) : 0,
                    'avg_gpft' => $rows->count() > 0 ? round($rows->avg('avg_gpft'), 2) : 0,
                    'avg_ad' => $rows->count() > 0 ? round($rows->avg('avg_ad'), 2) : 0,
                    'avg_pft' => $rows->count() > 0 ? round($rows->avg('avg_pft'), 2) : 0,
                    'is_parent_summary' => true,
                ];

                // Calculate parent DIL%
                $parentInv = $parentRow['inventory'];
                $parentL30 = $parentRow['overall_l30'];
                $parentRow['dil_percent'] = $parentInv > 0 ? round(($parentL30 / $parentInv) * 100, 2) : 0;

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

            return response()->json($finalResult);
            
        } catch (\Exception $e) {
            Log::error('Error fetching CVR data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch CVR data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save column visibility preferences
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility');
            
            // Store in session
            session(['cvr_master_column_visibility' => $visibility]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save column visibility'], 500);
        }
    }

    /**
     * Get column visibility preferences
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getColumnVisibility(Request $request)
    {
        try {
            $visibility = session('cvr_master_column_visibility', []);
            
            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get column visibility'], 500);
        }
    }

    /**
     * Get marketplace breakdown data for specific SKU
     * Used for the OV L30 modal breakdown
     * Shows Amazon, eBay, and eBay 2 data
     *
     * @param string $sku
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBreakdownData($sku)
    {
        try {
            $breakdownData = [];

            Log::info('Fetching breakdown data for SKU: ' . $sku);

            // First, get the full SKU from ProductMaster (in case shortened SKU is passed)
            $productMaster = ProductMaster::where('sku', $sku)
                ->orWhere('sku', 'LIKE', $sku . '%')
                ->first();
            
            // Use the full SKU from ProductMaster if found
            $fullSku = $productMaster ? $productMaster->sku : $sku;
            
            if ($fullSku !== $sku) {
                Log::info('Found full SKU in ProductMaster: ' . $fullSku . ' (from: ' . $sku . ')');
            }

            // Get LP and Ship from ProductMaster for profit calculations
            $values = $productMaster ? ($productMaster->Values ?: []) : [];
            $lp = 0;
            $ship = 0;
            $temuShip = 0;
            
            if ($values) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") $lp = floatval($v);
                    if (strtolower($k) === "ship") $ship = floatval($v);
                    if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                }
            }

            // Fetch views data from views_pull_data for marketplaces that use it
            $viewsPullData = ViewsPullData::where('sku', $fullSku)->first();

            // Fetch Amazon data (using full SKU)
            $amazonData = AmazonDatasheet::where('sku', $fullSku)->first();
            
            // Amazon uses HARDCODED 80% (line 1888), not from database
            $amazonPercentage = 0.80;
            
            // Calculate Amazon GPFT% (line 1887-1890: (price × 0.80 - ship - lp) / price × 100)
            $amazonPrice = $amazonData->price ?? 0;
            $amazonL30 = $amazonData->units_ordered_l30 ?? 0; // CORRECT field name!
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
            
            // Amazon NPFT% (line 1892-1893: GPFT% - AD%)
            $amazonNPFT = $amazonGPFT - $amazonAD;
            
            Log::info('Amazon Profits - GPFT%: ' . $amazonGPFT . ', AD%: ' . $amazonAD . ', NPFT%: ' . $amazonNPFT);
            
            $breakdownData[] = [
                'marketplace' => 'AMZ',
                'sku' => $amazonData ? $fullSku : 'Not Listed',
                'price' => $amazonPrice,
                'views' => $amazonData->sessions_l30 ?? 0,
                'l30' => $amazonL30, // Using correct field
                'gpft' => $amazonGPFT,
                'ad' => $amazonAD,
                'npft' => $amazonNPFT,
                'is_listed' => $amazonData ? true : false,
            ];

            // Fetch eBay 1 data from EbayMetric model (using full SKU)
            $ebayData = EbayMetric::where('sku', $fullSku)->first();
            $breakdownData[] = [
                'marketplace' => 'E 1',
                'sku' => $ebayData ? $fullSku : 'Not Listed',
                'price' => $ebayData->ebay_price ?? 0,
                'views' => $ebayData->views ?? 0,
                'l30' => $ebayData->ebay_l30 ?? 0,
                'gpft' => 0,
                'ad' => 0,
                'npft' => 0,
                'is_listed' => $ebayData ? true : false,
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
            
            $breakdownData[] = [
                'marketplace' => 'E 2',
                'sku' => $ebay2Data ? $ebay2Data->sku : 'Not Listed',
                'price' => $ebay2Data->ebay_price ?? 0,
                'views' => $ebay2Data->views ?? 0,
                'l30' => $ebay2Data->ebay_l30 ?? 0,
                'gpft' => 0,
                'ad' => 0,
                'npft' => 0,
                'is_listed' => $ebay2Data ? true : false,
            ];

            // Fetch eBay 3 data from Ebay3Metric model (using full SKU)
            $ebay3Data = Ebay3Metric::where('sku', $fullSku)->first();
            $breakdownData[] = [
                'marketplace' => 'E 3',
                'sku' => $ebay3Data ? $fullSku : 'Not Listed',
                'price' => $ebay3Data->ebay_price ?? 0,
                'views' => $ebay3Data->views ?? 0,
                'l30' => $ebay3Data->ebay_l30 ?? 0,
                'gpft' => 0,
                'ad' => 0,
                'npft' => 0,
                'is_listed' => $ebay3Data ? true : false,
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
            $temuPercentage = $temuMarketplaceData && $temuMarketplaceData->percentage ? ($temuMarketplaceData->percentage / 100) : 0.87;
            
            Log::info('Temu Marketplace % - Found: ' . ($temuMarketplaceData ? 'Yes' : 'No') . ', percentage: ' . ($temuMarketplaceData->percentage ?? 'NULL') . ', Final: ' . $temuPercentage);
            
            // Get view data by goods_id (matching line 1600-1604)
            $productClicks = 0;
            if ($goodsId) {
                $temuViewData = TemuViewData::where('goods_id', $goodsId)
                    ->selectRaw('SUM(product_clicks) as product_clicks')
                    ->first();
                $productClicks = $temuViewData ? ($temuViewData->product_clicks ?? 0) : 0;
            }
            
            // Get Temu views (ViewsPullData first, then product_clicks)
            $temuViews = 0;
            if ($viewsPullData && $viewsPullData->temu) {
                $temuViews = $viewsPullData->temu;
            } else {
                $temuViews = $productClicks;
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
            
            $breakdownData[] = [
                'marketplace' => 'TEMU',
                'sku' => $hasTemuData ? $fullSku : 'Not Listed',
                'price' => $temuPrice,
                'views' => $temuViews,
                'l30' => $temuL30Value,
                'gpft' => $temuGPFT,
                'ad' => $temuADS,
                'npft' => $temuNPFT,
                'is_listed' => $hasTemuData,
            ];

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
            
            $breakdownData[] = [
                'marketplace' => 'DOBA',
                'sku' => $hasDobaData ? $fullSku : 'Not Listed',
                'price' => $dobaPrice,
                'views' => $dobaMetric->impressions ?? 0,
                'l30' => $dobaMetric->quantity_l30 ?? 0,
                'gpft' => $dobaGPFT,
                'ad' => 0, // Doba doesn't have ads
                'npft' => $dobaNPFT,
                'is_listed' => $hasDobaData,
            ];

            // Fetch Walmart data (matching WalmartSheetUploadController)
            $walmartPriceItem = WalmartPriceData::where('sku', $fullSku)->first();
            $walmartViewsItem = WalmartListingViewsData::where('sku', $fullSku)->first();
            $walmartDataViewItem = WalmartDataView::where('sku', $fullSku)->first();
            
            // Get SPRICE (priority: WalmartDataView > WalmartPriceData)
            $wPrice = 0;
            if ($walmartDataViewItem && isset($walmartDataViewItem->value['sprice']) && $walmartDataViewItem->value['sprice'] > 0) {
                $wPrice = floatval($walmartDataViewItem->value['sprice']);
            } else {
                $wPrice = $walmartPriceItem ? floatval($walmartPriceItem->price ?? $walmartPriceItem->comparison_price ?? 0) : 0;
            }
            
            // Orders from walmart_order_data (L30 only)
            $walmartOrders = WalmartOrderData::where('sku', $fullSku)
                ->where('status', '!=', 'Canceled')
                ->selectRaw('SUM(qty) as total_qty, SUM(item_cost) as total_revenue')
                ->first();
            
            $wViews = $walmartViewsItem ? ($walmartViewsItem->page_views ?? 0) : 0;
            $wQty = $walmartOrders ? intval($walmartOrders->total_qty ?? 0) : 0;
            $wRevenue = $walmartOrders ? floatval($walmartOrders->total_revenue ?? 0) : 0;
            
            // Get Walmart campaign ad spend
            $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));
            $normalizedFullSku = $normalizeSku($fullSku);
            $walmartCampaign = WalmartCampaignReport::where('report_range', 'L30')
                ->where('campaignName', $normalizedFullSku)
                ->first();
            $wAdSpend = $walmartCampaign ? floatval($walmartCampaign->spend ?? 0) : 0;
            
            // Calculate W L30 Sales Amount
            $wL30Sales = $wPrice * $wQty;
            
            // Calculate Walmart GPFT% = ((price × 0.80 - ship - lp) / price) × 100
            $wGPFT = $wPrice > 0 ? ((($wPrice * 0.80 - $ship - $lp) / $wPrice) * 100) : 0;
            
            // Calculate Walmart AD%
            $wAD = 0;
            if ($wL30Sales > 0) {
                $wAD = ($wAdSpend / $wL30Sales) * 100;
            } elseif ($wAdSpend > 0) {
                $wAD = 100;
            }
            
            // Calculate Walmart NPFT% = GPFT% - AD%
            $wNPFT = $wGPFT - $wAD;
            
            $hasWalmartData = $walmartPriceItem || $walmartDataViewItem || ($walmartOrders && $wQty > 0) || $walmartViewsItem;
            
            $breakdownData[] = [
                'marketplace' => 'WM',
                'sku' => $hasWalmartData ? $fullSku : 'Not Listed',
                'price' => $wPrice,
                'views' => $wViews,
                'l30' => $wQty,
                'gpft' => $wGPFT,
                'ad' => $wAD,
                'npft' => $wNPFT,
                'is_listed' => $hasWalmartData,
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
            $ttNPFT = $ttGPFT;
            
            $hasTikTokData = $tiktokData && ($ttPrice > 0 || $tiktokL30 > 0);
            
            $breakdownData[] = [
                'marketplace' => 'TT',
                'sku' => $hasTikTokData ? $fullSku : 'Not Listed',
                'price' => $ttPrice,
                'views' => $tiktokData->views ?? 0,
                'l30' => $tiktokL30 ?? 0,
                'gpft' => $ttGPFT,
                'ad' => 0, // TikTok doesn't have ads
                'npft' => $ttNPFT,
                'is_listed' => $hasTikTokData,
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
            
            $hasBestBuyData = $bestbuyProduct && ($bestbuyProduct->m_l30 > 0 || $bbPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'BB',
                'sku' => $hasBestBuyData ? $fullSku : 'Not Listed',
                'price' => $bbPrice,
                'views' => 0,
                'l30' => $bestbuyProduct->m_l30 ?? 0,
                'gpft' => $bbGPFT,
                'ad' => 0, // BestBuy doesn't have ads
                'npft' => $bbNPFT,
                'is_listed' => $hasBestBuyData,
            ];

            // Fetch Shopify B2C data (matching Shopifyb2cController)
            // L30 from shopify_b2c_daily_data (period = 'l30')
            $shopifyB2COrders = ShopifyB2CDailyData::where('sku', $fullSku)
                ->where('period', 'l30')
                ->where('financial_status', '!=', 'refunded')
                ->selectRaw('SUM(quantity) as total_quantity, AVG(price) as avg_price')
                ->first();
            
            // Get Shopify B2C percentage
            $sb2cMarketplace = MarketplacePercentage::where('marketplace', 'ShopifyB2C')->first();
            $sb2cPercentage = $sb2cMarketplace ? ($sb2cMarketplace->percentage / 100) : 1.00;
            
            $sb2cPrice = $shopifyB2COrders ? floatval($shopifyB2COrders->avg_price ?? 0) : 0;
            
            // Calculate Shopify B2C GPFT% = ((price × percentage - ship - lp) / price) × 100
            $sb2cGPFT = $sb2cPrice > 0 ? ((($sb2cPrice * $sb2cPercentage - $lp - $ship) / $sb2cPrice) * 100) : 0;
            
            // Shopify B2C doesn't have ads, so NPFT = GPFT
            $sb2cNPFT = $sb2cGPFT;
            
            $hasShopifyB2CData = $shopifyB2COrders && $shopifyB2COrders->total_quantity > 0;
            
            $breakdownData[] = [
                'marketplace' => 'SB2C',
                'sku' => $hasShopifyB2CData ? $fullSku : 'Not Listed',
                'price' => $sb2cPrice,
                'views' => 0,
                'l30' => $shopifyB2COrders->total_quantity ?? 0,
                'gpft' => $sb2cGPFT,
                'ad' => 0, // Shopify B2C doesn't have ads
                'npft' => $sb2cNPFT,
                'is_listed' => $hasShopifyB2CData,
            ];

            // Fetch Macy data from macy_products table (using full SKU)
            $macyProduct = MacyProduct::where('sku', $fullSku)->first();
            
            // Get Macy's percentage
            $macyMarketplace = MarketplacePercentage::where('marketplace', 'Macys')->first();
            $macyPercentage = $macyMarketplace ? ($macyMarketplace->percentage / 100) : 0.80;
            
            $macyPrice = $macyProduct ? floatval($macyProduct->price ?? 0) : 0;
            
            // Calculate Macy's GPFT% = ((price × percentage - ship - lp) / price) × 100
            $macyGPFT = $macyPrice > 0 ? ((($macyPrice * $macyPercentage - $lp - $ship) / $macyPrice) * 100) : 0;
            
            // Macy's doesn't have ads, so NPFT = GPFT
            $macyNPFT = $macyGPFT;
            
            $hasMacyData = $macyProduct && ($macyProduct->m_l30 > 0 || $macyPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'MACY',
                'sku' => $hasMacyData ? $fullSku : 'Not Listed',
                'price' => $macyPrice,
                'views' => $macyProduct->views ?? 0,
                'l30' => $macyProduct->m_l30 ?? 0,
                'gpft' => $macyGPFT,
                'ad' => 0, // Macy's doesn't have ads
                'npft' => $macyNPFT,
                'is_listed' => $hasMacyData,
            ];

            // Fetch Reverb data from reverb_products table (using full SKU)
            $reverbProduct = ReverbProduct::where('sku', $fullSku)->first();
            
            // Get Reverb percentage
            $reverbMarketplace = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            $reverbPercentage = $reverbMarketplace ? ($reverbMarketplace->percentage / 100) : 0.85;
            
            $rvPrice = $reverbProduct ? floatval($reverbProduct->price ?? 0) : 0;
            
            // Calculate Reverb GPFT% = ((price × percentage - ship - lp) / price) × 100
            $rvGPFT = $rvPrice > 0 ? ((($rvPrice * $reverbPercentage - $lp - $ship) / $rvPrice) * 100) : 0;
            
            // Reverb doesn't have ads, so NPFT = GPFT
            $rvNPFT = $rvGPFT;
            
            $hasReverbData = $reverbProduct && ($reverbProduct->r_l30 > 0 || $rvPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'RV',
                'sku' => $hasReverbData ? $fullSku : 'Not Listed',
                'price' => $rvPrice,
                'views' => $reverbProduct->views ?? 0,
                'l30' => $reverbProduct->r_l30 ?? 0,
                'gpft' => $rvGPFT,
                'ad' => 0, // Reverb doesn't have ads
                'npft' => $rvNPFT,
                'is_listed' => $hasReverbData,
            ];

            Log::info('Total marketplaces: ' . count($breakdownData));

            return response()->json($breakdownData);
            
        } catch (\Exception $e) {
            Log::error('Error fetching breakdown data for SKU ' . $sku . ': ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch breakdown data'], 500);
        }
    }
}
