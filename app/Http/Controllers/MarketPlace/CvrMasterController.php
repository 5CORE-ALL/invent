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
use App\Models\EbayDataView;
use App\Models\EbayTwoDataView;
use App\Models\EbayThreeDataView;
use App\Models\EbayPriorityReport;
use App\Models\Ebay3PriorityReport;
use App\Models\Ebay3GeneralReport;
use App\Models\AmazonDataView;
use App\Models\TemuDataView;
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
use App\Models\TemuAdData;
use App\Models\MarketplacePercentage;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Models\CvrRemark;
use Carbon\Carbon;
use App\Services\AmazonSpApiService;
use App\Services\DobaApiService;
use App\Services\WalmartService;

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
     * Display Pricing Master CVR view (uses same data as CVR Master)
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

            // Fetch Amazon LMP data from amazon_sku_competitors
            $amazonLmpLookup = collect();
            $amazonLmpCountLookup = collect();
            try {
                $amazonLmpRecords = AmazonSkuCompetitor::where('marketplace', 'amazon')
                    ->where('price', '>', 0)
                    ->orderBy('price', 'asc')
                    ->get()
                    ->groupBy(function ($item) {
                        return strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
                    });
                $amazonLmpLookup = $amazonLmpRecords->map(fn ($items) => $items->first());
                $amazonLmpCountLookup = $amazonLmpRecords->map(fn ($items) => $items->count());
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

                // Get latest remark for this SKU
                $latestRemark = $latestRemarks->get($sku);
                $remarkText = $latestRemark ? $latestRemark->remark : null;
                $remarkSolved = $latestRemark ? $latestRemark->is_solved : false;

                // Amazon LMP and eBay LMP
                $skuLookupKey = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
                $amazonLmp = $amazonLmpLookup->get($skuLookupKey);
                $ebayLmp = $ebayLmpLookup->get($skuLookupKey);
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
                    "amazon_lmp_price" => $amazonLmpPrice,
                    "ebay_lmp_price" => $ebayLmpPrice,
                    "amazon_lmp_link" => $amazonLmpLink,
                    "ebay_lmp_link" => $ebayLmpLink,
                    "amazon_lmp_count" => $amazonLmpCount,
                    "ebay_lmp_count" => $ebayLmpCount,
                    "latest_remark" => $remarkText,
                    "remark_solved" => $remarkSolved,
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
                    'amazon_lmp_price' => $amazonLmpVals->isNotEmpty() ? round($amazonLmpVals->avg(), 2) : null,
                    'ebay_lmp_price' => $ebayLmpVals->isNotEmpty() ? round($ebayLmpVals->avg(), 2) : null,
                    'amazon_lmp_link' => null,
                    'ebay_lmp_link' => null,
                    'amazon_lmp_count' => $rows->sum('amazon_lmp_count'),
                    'ebay_lmp_count' => $rows->sum('ebay_lmp_count'),
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
            $ebay2Ship = 0;
            
            if ($values) {
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") $lp = floatval($v);
                    if (strtolower($k) === "ship") $ship = floatval($v);
                    if (strtolower($k) === "temu_ship") $temuShip = floatval($v);
                    if (strtolower($k) === "ebay2_ship") $ebay2Ship = floatval($v);
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
                            $amazonPushedAt = Carbon::parse($amazonPushedAt)->format('M d, Y h:i A');
                        } catch (\Exception $e) {
                            $amazonPushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Amazon',
                'sku' => $amazonData ? $fullSku : 'Not Listed',
                'price' => $amazonPrice,
                'views' => $amazonData->sessions_l30 ?? 0,
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
            $ebay1Marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();
            $ebay1Margin = $ebay1Marketplace ? ($ebay1Marketplace->percentage / 100) : 0.85;
            $ebay1Price = $ebayData->ebay_price ?? 0;
            $ebay1L30 = $ebayData->ebay_l30 ?? 0;
            $ebay1GPFT = $ebay1Price > 0 ? (($ebay1Price * $ebay1Margin - $ship - $lp) / $ebay1Price) * 100 : 0;
            $ebay1AD = 0;
            $ebay1NPFT = $ebay1L30 == 0 ? $ebay1GPFT : ($ebay1GPFT - $ebay1AD);
            
            $ebayDataView = EbayDataView::where('sku', $fullSku)->first();
            $ebay1Suggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            if ($ebayDataView) {
                $val = is_array($ebayDataView->value) ? $ebayDataView->value : json_decode($ebayDataView->value, true);
                if (is_array($val)) {
                    $ebay1Suggested = ['sprice' => $val['SPRICE'] ?? 0, 'sgpft' => $val['SGPFT'] ?? 0,
                                       'sroi' => $val['SROI'] ?? 0, 'spft' => $val['SPFT'] ?? 0];
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'Ebay',
                'sku' => $ebayData ? $fullSku : 'Not Listed',
                'price' => $ebay1Price,
                'views' => $ebayData->views ?? 0,
                'l30' => $ebay1L30,
                'gpft' => $ebay1GPFT,
                'ad' => $ebay1AD,
                'npft' => $ebay1NPFT,
                'is_listed' => $ebayData ? true : false,
                'sprice' => $ebay1Suggested['sprice'],
                'sgpft' => $ebay1Suggested['sgpft'],
                'sroi' => $ebay1Suggested['sroi'],
                'spft' => $ebay1Suggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $ebay1Margin,
                'pushed_by' => null,
                'pushed_at' => null,
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
            $ebay2Marketplace = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
            $ebay2Margin = $ebay2Marketplace ? ($ebay2Marketplace->percentage / 100) : 0.85;
            $ebay2Price = $ebay2Data->ebay_price ?? 0;
            $ebay2L30 = $ebay2Data->ebay_l30 ?? 0;
            $ebay2GPFT = $ebay2Price > 0 ? (($ebay2Price * $ebay2Margin - $lp - $ebay2Ship) / $ebay2Price) * 100 : 0;
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
                'marketplace' => 'EbayTwo',
                'sku' => $ebay2Data ? $ebay2Data->sku : 'Not Listed',
                'price' => $ebay2Price,
                'views' => $ebay2Data->views ?? 0,
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
                'ship' => $ebay2Ship,
                'margin' => $ebay2Margin,
                'pushed_by' => null,
                'pushed_at' => null,
            ];

            // eBay 3
            $ebay3Data = Ebay3Metric::where('sku', $fullSku)->first();
            $ebay3Marketplace = MarketplacePercentage::where('marketplace', 'EbayThree')->first();
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
                'marketplace' => 'EbayThree',
                'sku' => $ebay3Data ? $fullSku : 'Not Listed',
                'price' => $ebay3Price,
                'views' => $ebay3Data->views ?? 0,
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
                            $dobaPushedAt = Carbon::parse($dobaPushedAt)->format('M d, Y h:i A');
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
                'views' => $dobaMetric->impressions ?? 0,
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
            
            // Get Walmart margin from marketplace_percentages
            $walmartMarketplace = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            $walmartMargin = $walmartMarketplace ? ($walmartMarketplace->percentage / 100) : 0.80;
            
            // Calculate Walmart GPFT% = ((price × margin - ship - lp) / price) × 100
            $wGPFT = $wPrice > 0 ? ((($wPrice * $walmartMargin - $ship - $lp) / $wPrice) * 100) : 0;
            
            // Calculate Walmart AD%
            $wAD = 0;
            if ($wL30Sales > 0) {
                $wAD = ($wAdSpend / $wL30Sales) * 100;
            } elseif ($wAdSpend > 0) {
                $wAD = 100;
            }
            
            // Calculate Walmart NPFT% = GPFT% - AD%
            $wNPFT = $wQty == 0 ? $wGPFT : ($wGPFT - $wAD);
            
            // Get Walmart suggested data from walmart_data_view (uses lowercase 'sprice')
            $walmartSuggested = ['sprice' => 0, 'sgpft' => 0, 'sroi' => 0, 'spft' => 0];
            $walmartPushedBy = null;
            $walmartPushedAt = null;
            if ($walmartDataViewItem) {
                $val = is_array($walmartDataViewItem->value) ? $walmartDataViewItem->value : 
                       json_decode($walmartDataViewItem->value, true);
                if (is_array($val)) {
                    // Note: Walmart uses lowercase 'sprice', not 'SPRICE'
                    $walmartSuggested = [
                        'sprice' => floatval($val['sprice'] ?? $val['SPRICE'] ?? 0),
                        'sgpft' => floatval($val['SGPFT'] ?? 0),
                        'sroi' => floatval($val['SROI'] ?? 0),
                        'spft' => floatval($val['SPFT'] ?? 0)
                    ];
                    // Get pushed by information
                    $walmartPushedBy = $val['SPRICE_PUSHED_BY'] ?? null;
                    $walmartPushedAt = $val['SPRICE_PUSHED_AT'] ?? null;
                    // Format pushed at timestamp
                    if ($walmartPushedAt) {
                        try {
                            $walmartPushedAt = Carbon::parse($walmartPushedAt)->format('M d, Y h:i A');
                        } catch (\Exception $e) {
                            $walmartPushedAt = null;
                        }
                    }
                }
            }
            
            $hasWalmartData = $walmartPriceItem || $walmartDataViewItem || ($walmartOrders && $wQty > 0) || $walmartViewsItem;
            
            $breakdownData[] = [
                'marketplace' => 'Walmart',
                'sku' => $hasWalmartData ? $fullSku : 'Not Listed',
                'price' => $wPrice,
                'views' => $wViews,
                'l30' => $wQty,
                'gpft' => $wGPFT,
                'ad' => $wAD,
                'npft' => $wNPFT,
                'is_listed' => $hasWalmartData,
                'sprice' => $walmartSuggested['sprice'],
                'sgpft' => $walmartSuggested['sgpft'],
                'sroi' => $walmartSuggested['sroi'],
                'spft' => $walmartSuggested['spft'],
                'lp' => $lp,
                'ship' => $ship,
                'margin' => $walmartMargin,
                'pushed_by' => $walmartPushedBy,
                'pushed_at' => $walmartPushedAt,
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
                'views' => $tiktokData->views ?? 0,
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
            // Price from shopify_skus, L30 from shopify_b2c_daily_data (count rows)
            $shopifySku = ShopifySku::where('sku', $fullSku)->first();
            $sb2cPrice = $shopifySku ? floatval($shopifySku->price ?? 0) : 0;
            
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
                            $sb2cPushedAt = Carbon::parse($sb2cPushedAt)->format('M d, Y h:i A');
                        } catch (\Exception $e) {
                            $sb2cPushedAt = null;
                        }
                    }
                }
            }
            
            $breakdownData[] = [
                'marketplace' => 'SB2C',
                'sku' => $fullSku, // Always show SKU (from ProductMaster)
                'price' => $sb2cPrice,
                'views' => 0,
                'l30' => $sb2cL30,
                'gpft' => $sb2cGPFT,
                'ad' => 0,
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
                            $sb2bPushedAt = Carbon::parse($sb2bPushedAt)->format('M d, Y h:i A');
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
                'views' => 0,
                'l30' => $sb2bL30,
                'gpft' => $sb2bGPFT,
                'ad' => 0,
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
                'views' => $macyProduct->views ?? 0,
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
                'views' => $reverbProduct->views ?? 0,
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
            ];

            // Add Temu
            $temuMarketplace = MarketplacePercentage::where('marketplace', 'Temu')->first();
            $temuMargin = $temuMarketplace ? ($temuMarketplace->percentage / 100) : 0.95;
            $temuPricing = TemuPricing::where('sku', $fullSku)->first();
            $temuPrice = 0;
            if ($temuPricing) {
                $basePrice = $temuPricing->base_price ?? 0;
                $temuPrice = $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice;
            }
            $temuL30 = TemuDailyData::where('contribution_sku', $fullSku)
                ->selectRaw('SUM(quantity_purchased) as l30')->value('l30') ?? 0;
            $temuGPFT = $temuPrice > 0 ? (($temuPrice * $temuMargin - $lp - $temuShip) / $temuPrice) * 100 : 0;
            $temuNPFT = $temuL30 == 0 ? $temuGPFT : $temuGPFT;
            
            $temuDataView = TemuDataView::where('sku', $fullSku)->first();
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
                'views' => 0,
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
                'views' => 0,
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
                'views' => 0,
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
            ];

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
     * Save suggested pricing data (SPRICE, SGPFT, SPFT, SROI) to data_view tables
     */
    public function saveSuggestedData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $marketplace = strtolower($request->input('marketplace'));
            $sprice = floatval($request->input('sprice', 0));
            $sgpft = floatval($request->input('sgpft', 0));
            $sroi = floatval($request->input('sroi', 0));
            $spft = floatval($request->input('spft', 0));

            // Determine which data_view table to use
            if ($marketplace === 'amazon') {
                $dataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebay') {
                $dataView = EbayDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebaytwo') {
                $dataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'ebaythree') {
                $dataView = EbayThreeDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'temu') {
                $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'doba') {
                $dataView = DobaDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'walmart') {
                $dataView = WalmartDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'tiktok') {
                $dataView = TikTokDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'bestbuy') {
                $dataView = BestbuyUSADataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'macy') {
                $dataView = MacyDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'reverb') {
                $dataView = ReverbViewData::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'tiendamia') {
                $dataView = TiendamiaDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'shopifyb2c' || $marketplace === 'sb2c') {
                $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'shopifyb2b' || $marketplace === 'sb2b') {
                $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
            } else {
                return response()->json(['error' => 'Marketplace not supported'], 400);
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
            
            // Remove lowercase duplicates (but not for Walmart which uses lowercase 'sprice')
            if ($marketplace !== 'walmart') {
                unset($value['sprice'], $value['sgpft'], $value['sroi'], $value['spft']);
            } else {
                // For Walmart, remove uppercase duplicates instead
                unset($value['SPRICE']);
            }
            
            // Save to correct field (Reverb uses 'values', others use 'value')
            if ($marketplace === 'reverb') {
                $dataView->values = $value;
            } else {
                $dataView->value = $value;
            }
            $dataView->save();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving suggested data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save'], 500);
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

            // Handle different marketplaces
            if ($marketplace === 'amazon') {
                return $this->pushToAmazon($sku, $price);
            } elseif ($marketplace === 'doba') {
                return $this->pushToDoba($sku, $price);
            } elseif ($marketplace === 'walmart') {
                return $this->pushToWalmart($sku, $price);
            } elseif ($marketplace === 'sb2c' || $marketplace === 'shopifyb2c') {
                return $this->pushToShopifyB2C($sku, $price);
            } elseif ($marketplace === 'sb2b' || $marketplace === 'shopifyb2b') {
                return $this->pushToShopifyB2B($sku, $price);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Price push is only supported for Amazon, Doba, Walmart, Shopify B2C, and Shopify B2B. Received: $marketplace"
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
            // Push to Amazon using SP API
            $service = new AmazonSpApiService();
            $result = $service->updateAmazonPriceUS($sku, $price);

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

            // Check if the response indicates errors
            if (isset($priceResult['errors'])) {
                // Save error status to doba_data_view
                $this->savePricePushStatus($sku, 'doba', 'error', $price);
                
                Log::warning('CVR Master - Doba price push failed', [
                    'sku' => $sku,
                    'item_id' => $itemId,
                    'price' => $price,
                    'self_pick_price' => $selfPickPrice,
                    'error' => $priceResult['errors']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Price update failed: ' . $priceResult['errors'],
                    'errors' => [['message' => 'Price update: ' . $priceResult['errors']]]
                ], 400);
            }

            // Success - update the anticipated_income in DobaMetric as well
            $dobaMetric->anticipated_income = $price;
            if ($selfPickPrice !== null) {
                $dobaMetric->self_pick_price = $selfPickPrice;
            }
            $dobaMetric->save();

            // Save success status
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
            // Get variant_id from shopify_skus table
            $shopifyRecord = ShopifySku::where('sku', $sku)->first();
            
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
                // Update local database after successful API push
                try {
                    DB::beginTransaction();
                    
                    $verifiedPrice = $result['verified_price'] ?? $price;
                    $shopifyRecord->price = $verifiedPrice;
                    $shopifyRecord->price_updated_manually_at = now();
                    $shopifyRecord->save();
                    
                    DB::commit();
                    
                    // Save success status
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
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('CVR Master - Shopify B2C local DB update failed', [
                        'sku' => $sku,
                        'error' => $e->getMessage()
                    ]);
                    // Still return success since API worked
                    $this->savePricePushStatus($sku, 'shopifyb2c', 'pushed', $price);
                    
                    return response()->json([
                        'success' => true,
                        'message' => "Price pushed to Shopify but local update failed",
                        'data' => $result
                    ]);
                }
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
            } elseif ($marketplace === 'shopifyb2c' || $marketplace === 'sb2c') {
                $dataView = Shopifyb2cDataView::firstOrNew(['sku' => $sku]);
            } elseif ($marketplace === 'shopifyb2b' || $marketplace === 'sb2b') {
                $dataView = ShopifyB2BDataView::firstOrNew(['sku' => $sku]);
            }
            
            if ($dataView) {
                // Decode value column safely
                $existing = is_array($dataView->value)
                    ? $dataView->value
                    : (json_decode($dataView->value ?? '{}', true) ?? []);
                
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
                
                $dataView->value = $existing;
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

        $pushableMarketplaces = ['amazon', 'doba', 'walmart', 'sb2c', 'sb2b'];
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
        } elseif ($marketplace === 'sb2c' || $marketplace === 'shopifyb2c') {
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
}
