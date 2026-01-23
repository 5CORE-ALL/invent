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
use App\Models\TikTokProduct;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyPriceData;
use App\Models\ShopifyB2CDailyData;
use App\Models\ViewsPullData;
use App\Models\TemuViewData;
use App\Models\TemuAdData;
use App\Models\ChannelMaster;
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
            // Fetch all product master records (excluding parent rows)
            $productMasterRows = ProductMaster::all()
                ->filter(function ($item) {
                    return stripos($item->sku, 'PARENT') === false;
                })
                ->keyBy("sku");

            // Get all unique SKUs from product master
            $skus = $productMasterRows->pluck("sku")->toArray();

            // Fetch shopify data for these SKUs (for inventory and overall L30)
            $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

            // Process data
            $processedData = [];
            $slNo = 1;

            foreach ($productMasterRows as $productMaster) {
                $sku = $productMaster->sku;

                // Initialize the data structure
                $processedItem = [
                    "SL No." => $slNo++,
                    "sku" => $sku,
                ];

                // Add values from product_master
                $values = $productMaster->Values ?: [];
                
                // Image path - check shopify first, then product master Values, then product master direct field
                $processedItem["image_path"] = null;

                // Add data from shopify_skus if available
                if (isset($shopifyData[$sku])) {
                    $shopifyItem = $shopifyData[$sku];
                    $processedItem["inventory"] = $shopifyItem->inv ?? 0;
                    $processedItem["overall_l30"] = $shopifyItem->quantity ?? 0;
                    // Get image from shopify if available
                    $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
                } else {
                    $processedItem["inventory"] = 0;
                    $processedItem["overall_l30"] = 0;
                    // Fallback to product master for image
                    $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
                }

                // Calculate DIL% (Overall L30 / INV * 100)
                $inv = $processedItem["inventory"];
                $overallL30 = $processedItem["overall_l30"];
                $processedItem["dil_percent"] = $inv > 0 ? round(($overallL30 / $inv) * 100, 2) : 0;

                $processedData[] = $processedItem;
            }

            return response()->json($processedData);
            
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
            
            // Get Amazon percentage
            $amazonMarketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            $amazonPercentage = $amazonMarketplace && $amazonMarketplace->percentage ? ($amazonMarketplace->percentage / 100) : 0.80;
            
            // Calculate Amazon GPFT% (line 1887-1890: (price × percentage - ship - lp) / price × 100)
            $amazonPrice = $amazonData->price ?? 0;
            $amazonL30 = $amazonData->l30 ?? 0;
            $amazonGPFT = $amazonPrice > 0 ? (($amazonPrice * $amazonPercentage - $ship - $lp) / $amazonPrice) * 100 : 0;
            
            // Get Amazon ad spend from AmazonSpCampaignReport (L30)
            $amazonAdSpend = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L30')
                ->where('campaignName', 'LIKE', '%' . $fullSku . '%')
                ->sum('cost');
            
            // Calculate Amazon AD% (line 1881-1885: AD_Spend / Revenue * 100)
            $amazonRevenue = $amazonPrice * $amazonL30;
            $amazonAD = $amazonRevenue > 0 ? ($amazonAdSpend / $amazonRevenue) * 100 : 0;
            
            // Amazon NPFT% (line 1892-1893: GPFT% - AD%)
            $amazonNPFT = $amazonGPFT - $amazonAD;
            
            $breakdownData[] = [
                'marketplace' => 'AMZ',
                'sku' => $amazonData ? $fullSku : 'Not Listed',
                'price' => $amazonPrice,
                'views' => $amazonData->sessions_l30 ?? 0,
                'l30' => $amazonData->l30 ?? 0,
                'gpft' => $amazonGPFT,
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
                'npft' => $temuNPFT,
                'is_listed' => $hasTemuData,
            ];

            // Fetch Doba data from doba_metrics table (using full SKU)
            $dobaMetric = DobaMetric::where('sku', $fullSku)->first();
            
            $hasDobaData = $dobaMetric && ($dobaMetric->quantity_l30 > 0 || $dobaMetric->anticipated_income > 0);
            
            $breakdownData[] = [
                'marketplace' => 'DOBA',
                'sku' => $hasDobaData ? $fullSku : 'Not Listed',
                'price' => $dobaMetric->anticipated_income ?? 0,
                'views' => $dobaMetric->impressions ?? 0,
                'l30' => $dobaMetric->quantity_l30 ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasDobaData,
            ];

            // Fetch Walmart data (matching WalmartSheetUploadController)
            // Price from walmart_price_data (line 478: price or comparison_price)
            $walmartPrice = WalmartPriceData::where('sku', $fullSku)->first();
            
            // Orders from walmart_order_data (L30 only)
            $walmartOrders = WalmartOrderData::where('sku', $fullSku)
                ->where('status', '!=', 'Canceled')
                ->selectRaw('SUM(qty) as total_qty')
                ->first();
            
            $wPrice = $walmartPrice ? ($walmartPrice->price ?? $walmartPrice->comparison_price ?? 0) : 0;
            $hasWalmartData = $walmartPrice || ($walmartOrders && $walmartOrders->total_qty > 0);
            
            $breakdownData[] = [
                'marketplace' => 'WM',
                'sku' => $hasWalmartData ? $fullSku : 'Not Listed',
                'price' => $wPrice,
                'views' => $viewsPullData ? ($viewsPullData->walmart ?? 0) : 0,
                'l30' => $walmartOrders->total_qty ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasWalmartData,
            ];

            // Fetch TikTok data (matching TikTokPricingController)
            $tiktokData = TikTokProduct::where('sku', strtoupper($fullSku))->first();
            
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
            
            $hasTikTokData = $tiktokData && ($tiktokData->price > 0 || $tiktokL30 > 0);
            
            $breakdownData[] = [
                'marketplace' => 'TT',
                'sku' => $hasTikTokData ? $fullSku : 'Not Listed',
                'price' => $tiktokData->price ?? 0,
                'views' => $tiktokData->views ?? 0,
                'l30' => $tiktokL30 ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasTikTokData,
            ];

            // Fetch BestBuy data (matching BestBuyPricingController)
            $bestbuyProduct = BestbuyUsaProduct::where('sku', $fullSku)->first();
            $bestbuyPrice = BestbuyPriceData::where('sku', $fullSku)->first();
            
            // Price: BestbuyPriceData takes priority, fallback to BestbuyUsaProduct
            $bbPrice = $bestbuyPrice ? ($bestbuyPrice->price ?? 0) : ($bestbuyProduct->price ?? 0);
            $hasBestBuyData = $bestbuyProduct && ($bestbuyProduct->m_l30 > 0 || $bbPrice > 0);
            
            $breakdownData[] = [
                'marketplace' => 'BB',
                'sku' => $hasBestBuyData ? $fullSku : 'Not Listed',
                'price' => $bbPrice,
                'views' => 0,
                'l30' => $bestbuyProduct->m_l30 ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasBestBuyData,
            ];

            // Fetch Shopify B2C data (matching Shopifyb2cController)
            // L30 from shopify_b2c_daily_data (period = 'l30')
            $shopifyB2COrders = ShopifyB2CDailyData::where('sku', $fullSku)
                ->where('period', 'l30')
                ->where('financial_status', '!=', 'refunded')
                ->selectRaw('SUM(quantity) as total_quantity, AVG(price) as avg_price')
                ->first();
            
            $hasShopifyB2CData = $shopifyB2COrders && $shopifyB2COrders->total_quantity > 0;
            
            $breakdownData[] = [
                'marketplace' => 'SB2C',
                'sku' => $hasShopifyB2CData ? $fullSku : 'Not Listed',
                'price' => $shopifyB2COrders->avg_price ?? 0,
                'views' => 0,
                'l30' => $shopifyB2COrders->total_quantity ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasShopifyB2CData,
            ];

            // Fetch Macy data from macy_products table (using full SKU)
            $macyProduct = MacyProduct::where('sku', $fullSku)->first();
            
            $hasMacyData = $macyProduct && ($macyProduct->m_l30 > 0 || $macyProduct->price > 0);
            
            $breakdownData[] = [
                'marketplace' => 'MACY',
                'sku' => $hasMacyData ? $fullSku : 'Not Listed',
                'price' => $macyProduct->price ?? 0,
                'views' => $macyProduct->views ?? 0,
                'l30' => $macyProduct->m_l30 ?? 0,
                'gpft' => 0,
                'npft' => 0,
                'is_listed' => $hasMacyData,
            ];

            // Fetch Reverb data from reverb_products table (using full SKU)
            $reverbProduct = ReverbProduct::where('sku', $fullSku)->first();
            
            $hasReverbData = $reverbProduct && ($reverbProduct->r_l30 > 0 || $reverbProduct->price > 0);
            
            $breakdownData[] = [
                'marketplace' => 'RV',
                'sku' => $hasReverbData ? $fullSku : 'Not Listed',
                'price' => $reverbProduct->price ?? 0,
                'views' => $reverbProduct->views ?? 0,
                'l30' => $reverbProduct->r_l30 ?? 0,
                'gpft' => 0,
                'npft' => 0,
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
