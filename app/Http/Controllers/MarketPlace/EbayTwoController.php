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
}
