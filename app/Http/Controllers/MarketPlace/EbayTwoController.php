<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\EbayTwoDataView;
use App\Services\Ebay2ApiService;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\Ebay2GeneralReport;
use App\Models\ADVMastersData;
use App\Models\Ebay2Metric;
use App\Models\Ebay2PriorityReport;
use App\Models\EbayTwoListingStatus;
use App\Models\Ebay2Order;
use App\Models\Ebay2OrderItem;
use App\Models\AmazonDatasheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use App\Models\AmazonChannelSummary;

class EbayTwoController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function updateEbayPricing(Request $request)
    {

        $service = new Ebay2ApiService();

        $itemID = $request["sku"];
        $newPrice = $request["price"];
        $response = $service->reviseFixedPriceItem(
            itemId: $itemID,
            price: $newPrice,
        );

        return response()->json(['status' => 200, 'data' => $response]);
    }

    public function overallEbay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view('market-places.ebayTwoAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function ebay2TabulatorView(Request $request)
    {
        return view("market-places.ebay2_tabulator_view");
    }

    public function getEbay2TotsalSaleDataSave(Request $request)
    {
        return ADVMastersData::getEbay2TotsalSaleDataSaveProceed($request);
    }

    public function EbayTwoPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        return view('market-places.EbayTwoPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'ebayTwoPercentage' => $percentage
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get()
            ->keyBy("sku");

        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();
        
        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        // Fetch ALL ebay2_metrics (including Open Box items not in product_masters)
        $ebayMetrics = Ebay2Metric::select('sku', 'ebay_price', 'ebay_l30', 'ebay_l60', 'views', 'item_id', 'ebay_stock')
            ->get()
            ->keyBy("sku");
        
        // Fetch Amazon prices for comparison
        $amazonPrices = AmazonDatasheet::whereIn('sku', $skus)->pluck('price', 'sku');
        
        // Add OPEN BOX and USED items from ebay2_metrics to processing list
        foreach ($ebayMetrics as $metric) {
            $sku = $metric->sku;
            
            // Skip if already in product masters
            if (isset($productMasters[$sku])) {
                continue;
            }
            
            // Check if this is OPEN BOX or USED item
            $isOpenBox = stripos($sku, 'OPEN BOX') !== false;
            $isUsed = stripos($sku, 'USED') !== false;
            
            if ($isOpenBox || $isUsed) {
                // Extract base SKU
                $baseSku = $sku;
                if ($isOpenBox) {
                    $baseSku = trim(str_ireplace('OPEN BOX', '', $baseSku));
                } elseif ($isUsed) {
                    $baseSku = trim(str_ireplace('USED', '', $baseSku));
                }
                
                // Check if base SKU exists in product masters
                if (isset($productMasters[$baseSku])) {
                    // Create a pseudo product master entry for this OPEN BOX/USED item
                    $baseProduct = $productMasters[$baseSku];
                    $pseudoProduct = clone $baseProduct;
                    $pseudoProduct->sku = $sku;
                    $productMasters[$sku] = $pseudoProduct;
                }
            }
        }

        $nrValues = EbayTwoDataView::whereIn("sku", $skus)->pluck("value", "sku");
        
        // Fetch listing status data for nr_req field
        // Key listing status by lowercase SKU for case-insensitive lookup (UI sends upper/lower mixed)
        $listingStatusData = EbayTwoListingStatus::whereIn("sku", $skus)
            ->get()
            ->mapWithKeys(function ($item) {
                return [strtolower($item->sku) => $item];
            });

        // Mapping: item_id → sku
        $itemIdToSku = $ebayMetrics->pluck('sku', 'item_id')->toArray();

        // ✅ Fetch L30 Clicks directly from ebay2_general_reports
        $extraClicksData = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->pluck('clicks', 'listing_id')
            ->toArray();

        // 4. Fetch General Reports (listing_id → sku)
        $generalReports = Ebay2GeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->whereIn('report_range', ['L60', 'L30', 'L7'])
            ->get();

        $adMetricsBySku = [];

        // General Reports
        foreach ($generalReports as $report) {
            $sku = $itemIdToSku[$report->listing_id] ?? null;
            if (!$sku) continue;

            $range = strtoupper($report->report_range);

            $adMetricsBySku[$sku][$range]['GENERAL_SPENT'] =
                ($adMetricsBySku[$sku][$range]['GENERAL_SPENT'] ?? 0) + $this->extractNumber($report->ad_fees);

            $adMetricsBySku[$sku][$range]['Imp'] =
                ($adMetricsBySku[$sku][$range]['Imp'] ?? 0) + (int) $report->impressions;

            $adMetricsBySku[$sku][$range]['Clk'] =
                ($adMetricsBySku[$sku][$range]['Clk'] ?? 0) + (int) $report->clicks;

            $adMetricsBySku[$sku][$range]['Ctr'] =
                ($adMetricsBySku[$sku][$range]['Ctr'] ?? 0) + (float) $report->ctr;

            $adMetricsBySku[$sku][$range]['Sls'] =
                ($adMetricsBySku[$sku][$range]['Sls'] ?? 0) + (int) $report->sales;
        }

        // 5. Use fixed percentage of 0.85 (85%) for eBay2
        $percentage = 0.85;
        $pmtAds = 0; // No PMT ads updates tracking for eBay2

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;
            // Try both lowercase and original case for listing status lookup
            $listingStatus = $listingStatusData[strtolower($pm->sku)] ?? $listingStatusData[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;
            
            // NR/REQ status from listing status
            if ($listingStatus) {
                $statusValue = is_array($listingStatus->value) ? $listingStatus->value : json_decode($listingStatus->value, true);
                $row['nr_req'] = $statusValue['nr_req'] ?? 'REQ';
                $row['B Link'] = $statusValue['buyer_link'] ?? '';
                $row['S Link'] = $statusValue['seller_link'] ?? '';
            } else {
                $row['nr_req'] = 'REQ';
                $row['B Link'] = '';
                $row['S Link'] = '';
            }

            // eBay2 Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['views'] = $ebayMetric->views ?? 0;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
            $row['E Stock'] = $ebayMetric->ebay_stock ?? 0;
            
            // Amazon Price for comparison
            $row['A Price'] = isset($amazonPrices[$pm->sku]) ? floatval($amazonPrices[$pm->sku]) : 0;

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            // Ad Metrics (only GENERAL from ebay2_general_reports)
            $pmtData = $adMetricsBySku[$sku] ?? [];
            foreach (['L60', 'L30', 'L7'] as $range) {
                $metrics = $pmtData[$range] ?? [];
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = $metrics[$suffix] ?? 0;
                }
            }

            // ✅ Merge Extra Clicks (L30 only)
            if ($ebayMetric && isset($extraClicksData[$ebayMetric->item_id])) {
                $row["PmtClkL30"] += (int) $extraClicksData[$ebayMetric->item_id];
            }

            // Calculate AD_Spend_L30 from GENERAL_SPENT (L30)
            $pmt_spend_l30 = $adMetricsBySku[$sku]['L30']['GENERAL_SPENT'] ?? 0;
            $row["AD_Spend_L30"] = round($pmt_spend_l30, 2);
            $row["spend_l30"] = round($pmt_spend_l30, 2); // Add for frontend compatibility
            $row["pmt_spend_L30"] = round($pmt_spend_l30, 2);
            $row["kw_spend_L30"] = 0; // No keyword campaigns for ebay2
            $row["AD_Sales_L30"] = 0; // Can be calculated if needed
            $row["AD_Units_L30"] = 0; // Can be calculated if needed

            // Values: LP & Ship
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }

            $ship = isset($values["ebay2_ship"]) ? floatval($values["ebay2_ship"]) : (isset($pm->ebay2_ship) ? floatval($pm->ebay2_ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);
            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
            $row["PmtClkL30"] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            
            // Calculate AD% = (AD Spend L30 / (Price * eBay L30)) * 100
            $totalRevenue = $price * $units_ordered_l30;
            $row["AD%"] = $totalRevenue > 0 ? round(($pmt_spend_l30 / $totalRevenue) * 100, 4) : 0;
            
            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["Profit"] = $row["Total_pft"]; // Add for frontend compatibility
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"]; // Add for frontend compatibility
            
            // Calculate TacosL30 = AD Spend L30 / Total Sales L30
            $row["TacosL30"] = $row["T_Sale_l30"] > 0 ? round($pmt_spend_l30 / $row["T_Sale_l30"], 4) : 0;
            
            // Calculate GPFT% = ((Price * $percentage - Ship - LP) / Price) * 100
            $gpft = $price > 0 ? (($price * $percentage - $ship - $lp) / $price) * 100 : 0;
            $row["GPFT%"] = round($gpft, 2);
            
            // PFT% = GPFT% - AD%
            $row["PFT %"] = round($gpft - $row["AD%"], 2);
            
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0,
                2
            );
            
            // Calculate SCVR = (eBay L30 / views) * 100
            $views = floatval($row['views'] ?? 0);
            $ebayL30 = floatval($row["eBay L30"] ?? 0);
            $row['SCVR'] = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : 0;
            
            $row["percentage"] = $percentage;
            $row["pmt_ads"] = $pmtAds;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;
            $row["ebay2_ship"] = $ship;

            // NR & Hide
            $row['NR'] = "";
            $row['SPRICE'] = null;
            $row['SGPFT'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['SGPFT'] = $raw['SGPFT'] ?? null;
                    $row['SPFT'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        // Add Open Box and other items from ebay2_metrics that don't exist in product_masters
        $processedSkus = collect($result)->pluck('(Child) sku')->toArray();
        foreach ($ebayMetrics as $metricSku => $metric) {
            if (!in_array($metricSku, $processedSkus)) {
                // This SKU exists in ebay2_metrics but not in product_masters (e.g., Open Box items)
                $row = [];
                $row["Parent"] = "";
                $row["(Child) sku"] = $metricSku;
                $row['fba'] = "";
                $row["INV"] = 0;
                $row["L30"] = 0;
                $row['nr_req'] = 'REQ';
                $row['B Link'] = '';
                $row['S Link'] = '';
                
                // eBay2 Metrics from ebay_2_metrics
                $row["eBay L30"] = $metric->ebay_l30 ?? 0;
                $row["eBay L60"] = $metric->ebay_l60 ?? 0;
                $row["eBay Price"] = $metric->ebay_price ?? 0;
                $row['views'] = $metric->views ?? 0;
                $row['eBay_item_id'] = $metric->item_id ?? null;
                $row["E Dil%"] = 0;
                
                // Initialize ad metrics
                foreach (['L60', 'L30', 'L7'] as $range) {
                    foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT'] as $suffix) {
                        $key = "Pmt{$suffix}{$range}";
                        $row[$key] = 0;
                    }
                }
                
                $row["AD_Spend_L30"] = 0;
                $row["spend_l30"] = 0;
                $row["pmt_spend_L30"] = 0;
                $row["kw_spend_L30"] = 0;
                $row["AD_Sales_L30"] = 0;
                $row["AD_Units_L30"] = 0;
                
                $price = floatval($row["eBay Price"] ?? 0);
                $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
                $row["AD%"] = 0;
                $row["Total_pft"] = 0;
                $row["Profit"] = 0;
                $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
                $row["Sales L30"] = $row["T_Sale_l30"];
                $row["TacosL30"] = 0;
                $row["GPFT%"] = 0;
                $row["PFT %"] = 0;
                $row["ROI%"] = 0;
                $row['SCVR'] = 0;
                $row["percentage"] = 0.85;
                $row["pmt_ads"] = 0;
                $row["LP_productmaster"] = 0;
                $row["Ship_productmaster"] = 0;
                $row["ebay2_ship"] = 0;
                $row['NR'] = "";
                $row['SPRICE'] = null;
                $row['SGPFT'] = null;
                $row['SPFT'] = null;
                $row['SROI'] = null;
                $row['Listed'] = null;
                $row['Live'] = null;
                $row['APlus'] = null;
                $row["image_path"] = null;
                
                $result[] = (object) $row;
            }
        }

        // Auto-save daily summary in background (non-blocking)
        $this->saveDailySummaryIfNeeded($result);

        return response()->json([
            "message" => "eBay2 Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }




    public function updateAllEbay2Skus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'EbayTwo'],
                ['percentage' => $percent]
            );

            // No caching needed for instant results
            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'EbayTwo',   // ✅ Fix here
                    'percentage' => $percent
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = $nr;
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }


    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayTwoDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }
    function extractNumber($value)
    {
        if (is_null($value)) {
            return 0;
        }

        // Handle string values like "USD 10.50" or "10.50"
        if (is_string($value)) {
            // Remove currency symbols and text, keep numbers and decimal point
            $value = str_replace('USD ', '', $value);
            $value = preg_replace('/[^0-9.]/', '', $value);
        }

        return floatval($value) ?? 0;
    }


    public function saveSpriceToDatabase(Request $request)
    {
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Use fixed 85% for EbayTwo
        $percentage = 0.85;

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in ProductMaster.'], 404);
        }

        // Extract LP and Ship
        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === "lp") {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($pm->lp)) {
            $lp = floatval($pm->lp);
        }

        $ship = isset($values["ebay2_ship"]) ? floatval($values["ebay2_ship"]) : (isset($pm->ebay2_ship) ? floatval($pm->ebay2_ship) : 0);

        // Calculate SGPFT
        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * $percentage - $ship - $lp) / $spriceFloat) * 100, 2) : 0;

        // Get AD% the same way as regular pricing - using CURRENT eBay price, not SPRICE
        $adPercent = 0;
        $ebay2Metric = Ebay2Metric::where('sku', $sku)->first();
        
        Log::info('SPRICE Debug - SKU: ' . $sku, [
            'ebay2_metric_found' => $ebay2Metric ? 'YES' : 'NO',
            'item_id' => $ebay2Metric->item_id ?? 'NULL',
            'ebay_price' => $ebay2Metric->ebay_price ?? 'NULL',
            'ebay_l30' => $ebay2Metric->ebay_l30 ?? 'NULL'
        ]);
        
        if ($ebay2Metric && $ebay2Metric->item_id) {
            // Fetch from ebay2_general_reports (same as getViewEbayData)
            $generalReport = Ebay2GeneralReport::where('listing_id', $ebay2Metric->item_id)
                ->where('report_range', 'L30')
                ->first();
            
            Log::info('SPRICE Debug - General Report', [
                'general_report_found' => $generalReport ? 'YES' : 'NO',
                'ad_fees' => $generalReport->ad_fees ?? 'NULL'
            ]);
            
            if ($generalReport) {
                $pmt_spend_l30 = $this->extractNumber($generalReport->ad_fees);
                $currentPrice = floatval($ebay2Metric->ebay_price ?? 0); // Use current eBay price
                $units_ordered_l30 = floatval($ebay2Metric->ebay_l30 ?? 0);
                $totalRevenue = $currentPrice * $units_ordered_l30; // Revenue based on current price
                $adPercent = $totalRevenue > 0 ? ($pmt_spend_l30 / $totalRevenue) * 100 : 0;
                
                Log::info('SPRICE Debug - AD% Calculation', [
                    'pmt_spend_l30' => $pmt_spend_l30,
                    'current_price' => $currentPrice,
                    'units_l30' => $units_ordered_l30,
                    'total_revenue' => $totalRevenue,
                    'ad_percent' => $adPercent
                ]);
            }
        }

        // SPFT = SGPFT - AD%
        $spft = round($sgpft - $adPercent, 2);
        
        Log::info('SPRICE Debug - Final Calculations', [
            'sgpft' => $sgpft,
            'ad_percent' => $adPercent,
            'spft' => $spft,
            'lp' => $lp,
            'ship' => $ship
        ]);

        // SROI = ((SPRICE * 0.85 - lp - ship) / lp) * 100 (same as regular ROI formula)
        $sroi = round(
            $lp > 0 ? (($spriceFloat * $percentage - $lp - $ship) / $lp) * 100 : 0,
            2
        );

        $ebayDataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();

        return response()->json([
            'message' => 'Data saved successfully.',
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft,
        ]);
    }

    public function importEbayTwoAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['xlsx', 'xls', 'csv'];
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('The excel file must be a file of type: xlsx, xls, csv.');
                    }
                }
            ]
        ]);

        try {
            $file = $request->file('excel_file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Handle CSV files differently
            if ($extension === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                $spreadsheet = $reader->load($file->getPathName());
            } else {
            $spreadsheet = IOFactory::load($file->getPathName());
            }
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                EbayTwoDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported $importCount records!",
                    'count' => $importCount
                ]);
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error importing file: ' . $e->getMessage()
                ], 400);
            }
            
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportEbayTwoAnalytics()
    {
        $ebayData = EbayTwoDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($ebayData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Ebay_Two_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Ebay_Two_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function getEbay2ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbay2ColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay2_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function exportEbay2PricingData(Request $request)
    {
        try {
            $response = $this->getViewEbayData($request);
            $data = json_decode($response->getContent(), true);
            $ebayData = $data['data'] ?? [];

            // Get selected columns from request
            $selectedColumns = [];
            if ($request->has('columns')) {
                $columnsJson = $request->input('columns');
                $selectedColumns = json_decode($columnsJson, true) ?: [];
            }

            // Column mapping: field => [header_name, data_extractor]
            $columnMap = [
                'Parent' => ['Parent', function($item) { return $item['Parent'] ?? ''; }],
                '(Child) sku' => ['SKU', function($item) { return $item['(Child) sku'] ?? ''; }],
                'INV' => ['INV', function($item) { return $item['INV'] ?? 0; }],
                'L30' => ['L30', function($item) { return $item['L30'] ?? 0; }],
                'E Dil%' => ['Dil%', function($item) { 
                    return ($item['INV'] > 0) ? round(($item['L30'] / $item['INV']) * 100, 2) : 0; 
                }],
                'eBay L30' => ['eBay L30', function($item) { return $item['eBay L30'] ?? 0; }],
                'eBay L60' => ['eBay L60', function($item) { return $item['eBay L60'] ?? 0; }],
                'eBay Price' => ['eBay Price', function($item) { return number_format($item['eBay Price'] ?? 0, 2); }],
                'AD_Spend_L30' => ['AD Spend L30', function($item) { return number_format($item['AD_Spend_L30'] ?? 0, 2); }],
                'AD_Sales_L30' => ['AD Sales L30', function($item) { return number_format($item['AD_Sales_L30'] ?? 0, 2); }],
                'AD_Units_L30' => ['AD Units L30', function($item) { return $item['AD_Units_L30'] ?? 0; }],
                'AD%' => ['AD%', function($item) { return number_format(($item['AD%'] ?? 0) * 100, 2); }],
                'TacosL30' => ['TACOS L30', function($item) { return number_format(($item['TacosL30'] ?? 0) * 100, 2); }],
                'T_Sale_l30' => ['Total Sales L30', function($item) { return number_format($item['T_Sale_l30'] ?? 0, 2); }],
                'Total_pft' => ['Total Profit', function($item) { return number_format($item['Total_pft'] ?? 0, 2); }],
                'PFT %' => ['PFT %', function($item) { return number_format($item['PFT %'] ?? 0, 0); }],
                'ROI%' => ['ROI%', function($item) { return number_format($item['ROI%'] ?? 0, 0); }],
                'GPFT%' => ['GPFT%', function($item) { return number_format($item['GPFT%'] ?? 0, 0); }],
                'views' => ['Views', function($item) { return $item['views'] ?? 0; }],
                'nr_req' => ['NR/REQ', function($item) { return $item['nr_req'] ?? ''; }],
                'SPRICE' => ['SPRICE', function($item) { return $item['SPRICE'] ? number_format($item['SPRICE'], 2) : ''; }],
                'SPFT' => ['SPFT', function($item) { return $item['SPFT'] ? number_format($item['SPFT'], 0) : ''; }],
                'SROI' => ['SROI', function($item) { return $item['SROI'] ? number_format($item['SROI'], 0) : ''; }],
                'SCVR' => ['SCVR', function($item) { return number_format($item['SCVR'] ?? 0, 1); }],
                'pmt_spend_L30' => ['PMT Spend L30', function($item) { return number_format($item['pmt_spend_L30'] ?? 0, 2); }],
            ];

            // If no columns selected, export all
            if (empty($selectedColumns)) {
                $selectedColumns = array_keys($columnMap);
            }

            // Filter column map to only selected columns
            $selectedColumnMap = array_intersect_key($columnMap, array_flip($selectedColumns));

            // Set headers for CSV download
            $fileName = 'eBay2_Pricing_Data_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row (only selected columns)
            $headers = array_column($selectedColumnMap, 0);
            fputcsv($output, $headers);

            // Data Rows
            foreach ($ebayData as $item) {
                $item = (array) $item;
                $row = [];
                
                foreach ($selectedColumnMap as $extractor) {
                    $row[] = $extractor[1]($item);
                }
                
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    public function getMetricsHistory(Request $request)
    {
        $days = $request->input('days', 30);
        $sku = $request->input('sku');
        
        $minDays = 7;
        if ($days < $minDays) {
            $days = $minDays;
        }
        
        $californiaToday = \Carbon\Carbon::now('America/Los_Angeles')->startOfDay();
        $endDate = $californiaToday;
        $startDate = $endDate->copy()->subDays($days - 1);
        
        $chartData = [];
        $dataByDate = [];
        
        try {
            // Get data from ebay2_metrics table
            if ($sku) {
                $metric = Ebay2Metric::where('sku', $sku)->first();
                
                if ($metric) {
                    // Create data points for the date range using the current metric data
                    $currentDate = \Carbon\Carbon::parse($startDate);
                    while ($currentDate->lte($endDate)) {
                        $dateStr = $currentDate->format('Y-m-d');
                        
                        // Distribute the L30 and L60 data across the date range
                        $dataByDate[$dateStr] = [
                            'date' => $dateStr,
                            'units' => 0,
                            'revenue' => 0,
                            'views' => (int)($metric->views ?? 0) / $days
                        ];
                        
                        $currentDate->addDay();
                    }
                    
                    // Add recent data points
                    $today = $californiaToday->format('Y-m-d');
                    if (isset($dataByDate[$today])) {
                        $dataByDate[$today]['units'] = (int)($metric->ebay_l30 ?? 0);
                        $dataByDate[$today]['revenue'] = (float)(($metric->ebay_price ?? 0) * ($metric->ebay_l30 ?? 0));
                        $dataByDate[$today]['views'] = (int)($metric->views ?? 0);
                    }
                }
            } else {
                // If no specific SKU, aggregate from all metrics
                $metrics = Ebay2Metric::all();
                
                $totalUnits = $metrics->sum('ebay_l30');
                $totalViews = $metrics->sum('views');
                $totalRevenue = $metrics->sum(function($m) {
                    return ($m->ebay_price ?? 0) * ($m->ebay_l30 ?? 0);
                });
                
                $currentDate = \Carbon\Carbon::parse($startDate);
                while ($currentDate->lte($endDate)) {
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    $dataByDate[$dateStr] = [
                        'date' => $dateStr,
                        'units' => 0,
                        'revenue' => 0,
                        'views' => (int)($totalViews / $days)
                    ];
                    
                    $currentDate->addDay();
                }
                
                // Add recent data to today
                $today = $californiaToday->format('Y-m-d');
                if (isset($dataByDate[$today])) {
                    $dataByDate[$today]['units'] = (int)$totalUnits;
                    $dataByDate[$today]['revenue'] = (float)$totalRevenue;
                    $dataByDate[$today]['views'] = (int)$totalViews;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching eBay2 metrics history: ' . $e->getMessage());
        }

        // Fill in missing dates with zero values
        $currentDate = \Carbon\Carbon::parse($startDate);
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            if (!isset($dataByDate[$dateStr])) {
                $dataByDate[$dateStr] = [
                    'date' => $dateStr,
                    'units' => 0,
                    'revenue' => 0,
                    'views' => 0
                ];
            }
            
            $currentDate->addDay();
        }
        
        ksort($dataByDate);
        $chartData = array_values($dataByDate);

        return response()->json($chartData);
    }

    public function pushEbay2Price(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $price = $request->input('price');

        if (empty($sku)) {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required'
            ], 400);
        }

        $priceFloat = floatval($price);
        if (!is_numeric($price) || $priceFloat <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid price value'
            ], 400);
        }

        try {
            $ebayMetric = Ebay2Metric::where('sku', $sku)->first();
            
            if (!$ebayMetric || !$ebayMetric->item_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item ID not found for SKU: ' . $sku
                ], 404);
            }

            $service = new Ebay2ApiService();
            $response = $service->reviseFixedPriceItem(
                itemId: $ebayMetric->item_id,
                price: $priceFloat
            );

            if (isset($response['Ack']) && ($response['Ack'] === 'Success' || $response['Ack'] === 'Warning')) {
                $ebayMetric->ebay_price = $priceFloat;
                $ebayMetric->save();

                $this->saveSpriceStatus($sku, 'success');

                return response()->json([
                    'success' => true,
                    'message' => 'Price updated successfully on eBay2',
                    'new_price' => $priceFloat
                ]);
            } else {
                $errorMessage = $response['Errors'][0]['LongMessage'] ?? 'Unknown error from eBay2 API';
                
                $this->saveSpriceStatus($sku, 'failed');

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            }
        } catch (\Exception $e) {
            $this->saveSpriceStatus($sku, 'failed');

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveSpriceStatus($sku, $status)
    {
        try {
            $ebayDataView = EbayTwoDataView::firstOrNew(['sku' => $sku]);
            
            $value = is_array($ebayDataView->value)
                ? $ebayDataView->value
                : (is_string($ebayDataView->value) ? json_decode($ebayDataView->value, true) : []);
            
            $value['sprice_push_status'] = $status;
            $value['sprice_push_time'] = now()->toDateTimeString();
            
            $ebayDataView->value = $value;
            $ebayDataView->save();
        } catch (\Exception $e) {
            \Log::error('Error saving eBay2 sprice status: ' . $e->getMessage());
        }
    }

    public function updateEbay2SpriceStatus(Request $request)
    {
        $sku = $request->input('sku');
        $status = $request->input('status');

        $this->saveSpriceStatus($sku, $status);

        return response()->json(['success' => true]);
    }

    public function getEbay2AdsSpend()
    {
        try {
            // Get ad spend from ebay2_general_reports for L30 (last 30 days only)
            $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
            $generalReports = Ebay2GeneralReport::where('report_range', 'L30')
                ->whereDate('updated_at', '>=', $thirtyDaysAgo)
                ->get();
            
            $adsSpend = 0;
            foreach ($generalReports as $report) {
                $adsSpend += $this->extractNumber($report->ad_fees);
            }

            return response()->json([
                'success' => true,
                'ads_spend' => round($adsSpend, 2)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-save daily eBay 2 summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: INV > 0 && nr_req === 'REQ' (EXACT JavaScript logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p->INV ?? 0) > 0;
                $reqCheck = ($p->nr_req ?? '') === 'REQ';
                
                return $invCheck && $reqCheck;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalPmtSpendL30 = 0;
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalFbaInv = 0;
            $totalFbaL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $notMapCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $totalWeightedPrice = 0;
            $totalL30 = 0;
            $totalViews = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $inv = floatval($row->INV ?? 0);
                $ebayL30 = floatval($row->{'eBay L30'} ?? 0);
                
                $totalPftAmt += floatval($row->Total_pft ?? 0);
                $totalSalesAmt += floatval($row->T_Sale_l30 ?? 0);
                $totalLpAmt += floatval($row->LP_productmaster ?? 0) * $ebayL30;
                $totalFbaInv += $inv;
                $totalFbaL30 += $ebayL30;
                $totalPmtSpendL30 += floatval($row->pmt_spend_L30 ?? 0);
                
                // Count sold and 0-sold
                if ($ebayL30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                // Count Missing (exclude NR items)
                $ebayPrice = floatval($row->{'eBay Price'} ?? 0);
                $itemId = $row->eBay_item_id ?? '';
                $nrReq = $row->nr_req ?? '';
                if ($ebayPrice == 0 && (!$itemId || $itemId === null || $itemId === '') && $nrReq !== 'NR' && $nrReq !== 'NRL') {
                    $missingCount++;
                }
                
                // Count Map and N MP
                if ($itemId && $itemId !== null && $itemId !== '') {
                    $ebayStock = floatval($row->{'E Stock'} ?? 0);
                    if ($inv > 0 && $ebayStock > 0 && $inv == $ebayStock) {
                        $mapCount++;
                    } elseif ($inv > 0 && ($ebayStock == 0 || ($ebayStock > 0 && $inv != $ebayStock))) {
                        $notMapCount++;
                    }
                }
                
                // Count < Amz and > Amz
                $amazonPrice = floatval($row->{'A Price'} ?? 0);
                if ($amazonPrice > 0 && $ebayPrice > 0) {
                    if ($ebayPrice < $amazonPrice) {
                        $lessAmzCount++;
                    } elseif ($ebayPrice > $amazonPrice) {
                        $moreAmzCount++;
                    }
                }
                
                // Weighted price
                $totalWeightedPrice += $ebayPrice * $ebayL30;
                $totalL30 += $ebayL30;
                
                // Views
                $totalViews += floatval($row->views ?? 0);
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalL30 > 0 ? $totalWeightedPrice / $totalL30 : 0;
            $avgCVR = $totalViews > 0 ? ($totalL30 / $totalViews * 100) : 0;
            $tacosPercent = $totalSalesAmt > 0 ? (($totalPmtSpendL30 / $totalSalesAmt) * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            $avgGpft = $totalSalesAmt > 0 ? (($totalPftAmt / $totalSalesAmt) * 100) : 0; // GPFT = (PFT/Sales)*100
            $npftPercent = $avgGpft - $tacosPercent;
            $nroiPercent = $groiPercent - $tacosPercent;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $notMapCount,  // Renamed from not_map_count for consistency
                'not_map_count' => $notMapCount,  // Keep for backward compatibility
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                
                // Financial Totals
                'total_pmt_spend_l30' => round($totalPmtSpendL30, 2),
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_sales_amt' => round($totalSalesAmt, 2),
                'total_lp_amt' => round($totalLpAmt, 2),
                
                // Inventory
                'total_fba_inv' => round($totalFbaInv, 2),
                'total_ebay_l30' => round($totalFbaL30, 2),
                'total_views' => $totalViews,
                
                // Calculated Percentages
                'tcos_percent' => round($tacosPercent, 2),
                'groi_percent' => round($groiPercent, 2),
                'nroi_percent' => round($nroiPercent, 2),
                'cvr_percent' => round($avgCVR, 2),
                'gpft_percent' => round($avgGpft, 2),
                'npft_percent' => round($npftPercent, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'REQ',        // REQ only
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'ebay2',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily eBay2 summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily eBay2 summary: ' . $e->getMessage());
        }
    }

    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        $cleanSku = strtoupper(trim((string) $sku));

        $ebay2Metric = Ebay2Metric::where('sku', $sku)->first();
        if (!$ebay2Metric) {
            $ebay2Metric = Ebay2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $itemId = $ebay2Metric && !empty($ebay2Metric->item_id) ? trim((string) $ebay2Metric->item_id) : null;

        $shopify = ShopifySku::where('sku', $sku)->first();
        if (!$shopify) {
            $shopify = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$cleanSku])->first();
        }
        $inv = $shopify ? (float) ($shopify->inv ?? 0) : 0.0;

        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $lastSbidMap = [];
        $lastSbidReports = Ebay2PriorityReport::where('report_range', $dayBeforeYesterday)
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();
        // Build lastSbidMap (matching utilized page logic exactly)
        // Utilized page: if (!empty($report->campaign_id) && !empty($report->last_sbid)) { $lastSbidMap[$report->campaign_id] = $report->last_sbid; }
        foreach ($lastSbidReports as $report) {
            if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                $lastSbidMap[(string) $report->campaign_id] = $report->last_sbid;
            }
        }

        $kwCampaigns = [];
        $kwL30 = Ebay2PriorityReport::where('report_range', 'L30')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get();
        $kwL7 = Ebay2PriorityReport::where('report_range', 'L7')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $kwL1 = Ebay2PriorityReport::where('report_range', 'L1')
            ->whereIn('campaignStatus', ['RUNNING', 'PAUSED'])
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$cleanSku])
            ->get()
            ->keyBy('campaign_id');
        $price = $ebay2Metric ? (float) ($ebay2Metric->ebay_price ?? 0) : 0;

        foreach ($kwL30 as $r) {
            $campaignId = $r->campaign_id ?? null;
            $cid = $campaignId !== null ? (string) $campaignId : null;
            
            // Skip if no valid campaign_id (not a real campaign)
            if (empty($cid) || $cid === '' || $cid === '0') {
                continue;
            }
            
            $rL7 = $cid ? $kwL7->get($cid) : null;
            $rL1 = $cid ? $kwL1->get($cid) : null;
            if (!$rL7 && $cid) {
                $rL7 = $kwL7->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }
            if (!$rL1 && $cid) {
                $rL1 = $kwL1->first(fn ($x) => (string) ($x->campaign_id ?? '') === $cid);
            }

            $spend = (float) str_replace(['USD ', 'USD'], '', $r->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', 'USD'], '', $r->cpc_sale_amount_payout_currency ?? '0');
            $clicks = (int) ($r->cpc_clicks ?? 0);
            $sold = (int) ($r->cpc_attributed_sales ?? 0);
            $acos = ($sales > 0) ? (($spend / $sales) * 100) : (($spend > 0) ? 100 : 0);
            $adCvr = $clicks > 0 ? (($sold / $clicks) * 100) : 0;
            $bgt = (float) ($r->campaignBudgetAmount ?? 0);

            $l7Spend = $rL7 ? (float) str_replace(['USD ', 'USD'], '', $rL7->cpc_ad_fees_payout_currency ?? '0') : 0;
            $l1Spend = $rL1 ? (float) str_replace(['USD ', 'USD'], '', $rL1->cpc_ad_fees_payout_currency ?? '0') : 0;
            $l7Cpc = $rL7 ? (float) str_replace(['USD ', 'USD'], '', $rL7->cost_per_click ?? '0') : null;
            $l1Cpc = $rL1 ? (float) str_replace(['USD ', 'USD'], '', $rL1->cost_per_click ?? '0') : null;

            $ub7 = ($bgt > 0) ? (($l7Spend / ($bgt * 7)) * 100) : 0;
            $ub1 = ($bgt > 0) ? (($l1Spend / $bgt) * 100) : 0;

            // SBGT: ebay/utilized rule – ACOS-based only
            $acosForSbgt = $acos;
            if ($acosForSbgt === 0 && $spend > 0) {
                $acosForSbgt = 100;
            }
            if ($acosForSbgt < 4) {
                $sbgt = 9;
            } elseif ($acosForSbgt >= 4 && $acosForSbgt < 8) {
                $sbgt = 6;
            } else {
                $sbgt = 3;
            }

            // Get last_sbid from day-before-yesterday map only (matching utilized page logic)
            // Utilized page: if (isset($lastSbidMap[$campaignId])) { $campaignMap[$key]['last_sbid'] = $lastSbidMap[$campaignId]; } else { $campaignMap[$key]['last_sbid'] = ''; }
            // Do NOT use $r->last_sbid or $r->apprSbid as fallback - only use day-before-yesterday map
            $lastSbid = null;
            if ($cid && isset($lastSbidMap[$cid]) && !empty($lastSbidMap[$cid])) {
                $lastSbidRaw = $lastSbidMap[$cid];
                if ($lastSbidRaw !== null && $lastSbidRaw !== '' && $lastSbidRaw !== '0') {
                    $f = is_numeric($lastSbidRaw) ? (float) $lastSbidRaw : null;
                    if ($f !== null && $f > 0) {
                        $lastSbid = $f;
                    }
                }
            }
            $l1CpcVal = $l1Cpc !== null ? (float) $l1Cpc : 0;
            $l7CpcVal = $l7Cpc !== null ? (float) $l7Cpc : 0;

            $sbid = $this->calculateSbidUtilized($ub7, $ub1, $inv, $bgt, $l1CpcVal, $l7CpcVal, $lastSbid, $price);

            // Only include campaigns that have activity (spend, clicks, sales, or budget)
            if ($spend > 0 || $clicks > 0 || $sales > 0 || $bgt > 0) {
                $kwCampaigns[] = [
                    'campaign_name' => $r->campaign_name ?? 'N/A',
                    'bgt' => $bgt,
                    'sbgt' => $sbgt,
                    'acos' => $acos,
                    'clicks' => $clicks,
                    'ad_spend' => $spend,
                    'ad_sales' => $sales,
                    'ad_sold' => $sold,
                    'ad_cvr' => $adCvr,
                    '7ub' => $ub7,
                    '1ub' => $ub1,
                    'l7cpc' => $l7Cpc,
                    'l1cpc' => $l1Cpc,
                    'l_bid' => $lastSbid,
                    'sbid' => $sbid,
                ];
            }
        }

        $ptCampaigns = [];
        if ($itemId) {
            // Match ebay/pmp/ads: prefer COST_PER_SALE row per listing (EbayPMPAdsController)
            $campaignListing = null;
            try {
                $campaignListing = DB::connection('apicentral')
                    ->table('ebay2_campaign_ads_listings')
                    ->where('listing_id', $itemId)
                    ->select('listing_id', 'bid_percentage', 'suggested_bid')
                    ->orderByRaw('CASE WHEN funding_strategy = "COST_PER_SALE" THEN 0 ELSE 1 END')
                    ->orderByDesc('id')
                    ->first();
            } catch (\Exception $e) {
                // apicentral may be unavailable
            }
            $cbid = $campaignListing ? (($campaignListing->bid_percentage !== null && $campaignListing->bid_percentage !== '') ? (float) $campaignListing->bid_percentage : null) : null;
            $esBid = $campaignListing ? (($campaignListing->suggested_bid !== null && $campaignListing->suggested_bid !== '') ? (float) $campaignListing->suggested_bid : null) : null;
            $views = $ebay2Metric ? (float) ($ebay2Metric->views ?? 0) : 0;
            $l7Views = $ebay2Metric ? (float) ($ebay2Metric->l7_views ?? 0) : 0;
            $ebayL30 = $ebay2Metric ? (float) ($ebay2Metric->ebay_l30 ?? 0) : 0;
            $scvr = $views > 0 ? round(($ebayL30 / $views) * 100, 2) : null;
            
            // Calculate SBID for PMT based on L7_VIEWS (same logic as ebay/pmp/ads page)
            $sBid = null;
            if ($l7Views >= 0 && $l7Views < 50) {
                // 0-50: use ESBID
                $sBid = $esBid !== null ? (float) $esBid : null;
            } elseif ($l7Views >= 50 && $l7Views < 100) {
                $sBid = 9.0;
            } elseif ($l7Views >= 100 && $l7Views < 150) {
                $sBid = 8.0;
            } elseif ($l7Views >= 150 && $l7Views < 200) {
                $sBid = 7.0;
            } elseif ($l7Views >= 200 && $l7Views < 250) {
                $sBid = 6.0;
            } elseif ($l7Views >= 250 && $l7Views < 300) {
                $sBid = 5.0;
            } elseif ($l7Views >= 300 && $l7Views < 350) {
                $sBid = 4.0;
            } elseif ($l7Views >= 350 && $l7Views < 400) {
                $sBid = 3.0;
            } elseif ($l7Views >= 400) {
                $sBid = 2.0;
            } else {
                // Fallback: use ESBID
                $sBid = $esBid !== null ? (float) $esBid : null;
            }
            // Cap sbidValue to maximum of 15
            if ($sBid !== null && $sBid > 15) {
                $sBid = 15.0;
            }

            $ptReports = Ebay2GeneralReport::where('report_range', 'L30')
                ->where('listing_id', $itemId)
                ->get();
            
            // Only include PMT campaigns that have views or valid bids
            if ($views > 0 || ($cbid !== null && $cbid > 0) || ($esBid !== null && $esBid > 0)) {
                if ($ptReports->isEmpty()) {
                    // If no reports but have views/bids, still show PMT data
                    $ptCampaigns[] = [
                        'campaign_name' => 'PMT - ' . ($itemId ?? 'N/A'),
                        'cbid' => $cbid,
                        'es_bid' => $esBid,
                        's_bid' => $sBid,
                        't_views' => $views > 0 ? $views : null,
                        'l7_views' => $l7Views,
                        'scvr' => $scvr,
                    ];
                } else {
                    foreach ($ptReports as $r) {
                        $ptCampaigns[] = [
                            'campaign_name' => 'PMT - ' . ($r->listing_id ?? 'N/A'),
                            'cbid' => $cbid,
                            'es_bid' => $esBid,
                            's_bid' => $sBid,
                            't_views' => $views > 0 ? $views : null,
                            'l7_views' => $l7Views,
                            'scvr' => $scvr,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'kw_campaigns' => $kwCampaigns,
            'pt_campaigns' => $ptCampaigns,
        ]);
    }

    /**
     * SBID calculation – same logic as ebay/utilized (all mode, per-campaign).
     * No SBID when UB7/UB1 colors don't match (utilized formatter).
     * Under-utilized: !over && budget>0 && ub7<66 && ub1<66 && inv>0 (match EbayOverUtilizedBgtController).
     */
    private function calculateSbidUtilized(
        float $ub7,
        float $ub1,
        float $inv,
        float $bgt,
        float $l1Cpc,
        float $l7Cpc,
        ?float $lastSbid,
        float $price
    ): ?float {
        $getUbColor = function (float $ub): string {
            if ($ub >= 66 && $ub <= 99) {
                return 'green';
            }
            if ($ub > 99) {
                return 'pink';
            }
            return 'red';
        };

        if ($getUbColor($ub7) !== $getUbColor($ub1)) {
            return null;
        }

        $sbid = 0.0;
        $over = $ub7 > 99 && $ub1 > 99;
        $under = !$over && $bgt > 0 && $ub7 < 66 && $ub1 < 66 && $inv > 0;

        if ($over) {
            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90 (matching utilized page logic)
            // Note: Always use 0.90 multiplier, not 0.80 even if L1_CPC > 1.25
            if ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
            // Price cap: If price < $20, cap SBID at 0.20 (matching ebay2-utilized.blade.php)
            if ($price < 20 && $sbid > 0.20) {
                $sbid = 0.20;
            }
        } elseif ($under) {
            // New UB1-based bid increase rules (matching utilized page logic exactly)
            // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
            $baseBid = 0;
            
            // Parse last_sbid, treat empty/0/null as 0 (matching utilized page logic exactly)
            // Utilized page: if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0)
            if ($lastSbid === null || $lastSbid === '' || $lastSbid === '0' || $lastSbid === 0 || $lastSbid <= 0) {
                $baseBid = 0;
            } else {
                $baseBid = (float) $lastSbid;
                if (is_nan($baseBid) || $baseBid <= 0) {
                    $baseBid = 0;
                }
            }
            
            // If last_sbid is 0, use L1_CPC or L7_CPC as fallback (matching utilized page logic)
            // Utilized page: if (baseBid === 0) { baseBid = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0); }
            if ($baseBid === 0 || $baseBid <= 0) {
                $baseBid = ($l1Cpc > 0) ? $l1Cpc : (($l7Cpc > 0) ? $l7Cpc : 0);
            }
            
            if ($baseBid > 0) {
                // If UB1 < 33%: increase bid by 0.10
                if ($ub1 < 33) {
                    $sbid = floor(($baseBid + 0.10) * 100) / 100;
                }
                // If UB1 is 33% to 66%: increase bid by 10%
                elseif ($ub1 >= 33 && $ub1 < 66) {
                    $sbid = floor($baseBid * 1.10 * 100) / 100;
                } else {
                    // For UB1 >= 66%, use base bid (no increase)
                    $sbid = floor($baseBid * 100) / 100;
                }
            } else {
                $sbid = 0.0;
            }
        } else {
            if ($l1Cpc > 0) {
                $sbid = floor($l1Cpc * 0.90 * 100) / 100;
            } elseif ($l7Cpc > 0) {
                $sbid = floor($l7Cpc * 0.90 * 100) / 100;
            } else {
                $sbid = 0.0;
            }
        }

        return $sbid > 0 ? $sbid : null;
    }
}
