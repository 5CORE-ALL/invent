<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\AmazonDataView;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;
use App\Services\AmazonSpApiService;    
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\JungleScoutProductData;
use App\Http\Controllers\ApiController;
use App\Models\AmazonDatasheet;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2Metric;
use App\Models\EbayDataView;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\WalmartDataView;
use App\Models\WalmartMetrics;
use App\Models\WalmartProductSheet;
use Exception;
use Illuminate\Support\Facades\DB;

class OrganicViewsController extends Controller
{

    public function amazonOrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.amazon-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getAmazonOrganicViewsData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();

        $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(trim($item->parent));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : [];
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->pluck('data.price');

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : [];
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku', 'fba');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1; 
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0;   

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            if (str_starts_with($sku, 'PARENT ')) {
                continue;
            }

            $parent = $pm->parent;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            $row['Parent'] = $parent;
            $row['(Child) sku'] = $pm->sku;

            if ($amazonSheet) {
                $row['A_L30'] = $amazonSheet->units_ordered_l30;
                $row['Sess30'] = $amazonSheet->sessions_l30;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60;
                $row['organic_views'] = $amazonSheet->organic_views;
                $row['sold'] = $amazonSheet->sold;
            }

            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['fba'] = $pm->fba;


            // LP & ship cost
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // if (!isset($values['moq']) || $values['moq'] === null) {
            //     $values['moq'] = 0; // or set dynamically if you prefer
            //     $pm->Values = json_encode($values, JSON_UNESCAPED_UNICODE);
            //     $pm->save();
            // }

            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;

            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);
            $row['ad_updates'] = $adUpdates;

            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                $row['scout_data'] = $jungleScoutData[$parentKey];
            }

            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;

            $row['MOQ'] = isset($values['moq']) ? intval($values['moq']) : null;

            // Default values
            $row['NRL'] = '';
            $row['NRA'] = '';
            $row['FBA'] = null;
            $row['SPRICE'] = null;
            $row['Spft'] = null;
            $row['SROI'] = null;
            $row['ad_spend'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['js_comp_manual_api_link'] = null;
            $row['js_comp_manual_link'] = null;

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }

                if (is_array($raw)) {
                    $row['NRL'] = $raw['NRL'] ?? null;
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['shopify_id'] = $shopify->id ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['Spft%'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['ad_spend'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['js_comp_manual_api_link'] = $raw['js_comp_manual_api_link'] ?? '';
                    $row['js_comp_manual_link'] = $raw['js_comp_manual_link'] ?? '';
                }
            }

            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            $result[] = (object) $row;
        }

        // Parent-wise grouping
        $groupedByParent = collect($result)->groupBy('Parent');
        $finalResult = [];

        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalResult[] = $row;
            }

            if (empty($parent)) {
                continue;
            }

            $sumRow = [
                '(Child) sku' => 'PARENT ' . $parent,
                'Parent' => $parent,
                'INV' => $rows->sum('INV'),
                'OV_L30' => $rows->sum('OV_L30'),
                'AVG_Price' => null,
                'MSRP' => null,
                'MAP' => null,
                'is_parent_summary' => true,
                'ad_updates' => $adUpdates
            ];

            $finalResult[] = (object) $sumRow;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $finalResult,
            'status' => 200,
        ]);
    }


    public function ebayOrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.ebay-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getEbayOrganicViewsData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

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

        $ebayMetrics = DB::connection('apicentral')->table('ebay_one_metrics')->whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");

        $lmpLookup = collect();
        try {
            $lmpLookup = DB::connection('repricer')
                ->table('lmp_data')
                ->select('sku', DB::raw('MIN(price) as lowest_price'))
                ->where('price', '>', 0)
                ->whereIn('sku', $skus)
                ->groupBy('sku')
                ->get()
                ->keyBy('sku');
        } catch (Exception $e) {
            Log::warning('Could not fetch LMP data from repricer database: ' . $e->getMessage());
        }

        // 5. Marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1; 
        $adUpdates  = $marketplaceData ? $marketplaceData->ad_updates : 0;   

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            // eBay Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['price_lmpa'] = $ebayMetric->price_lmpa ?? null;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;
            $row['views'] = $ebayMetric->views ?? 0;

            // LMP data from api_central with link
            $lmpData = $lmpLookup[$pm->sku] ?? null;
            $row['lmp_price'] = $lmpData ? $lmpData->lowest_price : null;
            $row['lmp_link'] = $lmpData ? "https://example.com/lmp/" . $pm->sku : null;

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            // Initialize ad metrics with zero values since we're using EbayMetric data
            foreach (['L60', 'L30', 'L7'] as $range) {
                foreach (['Imp', 'Clk', 'Ctr', 'Sls', 'GENERAL_SPENT', 'PRIORITY_SPENT'] as $suffix) {
                    $key = "Pmt{$suffix}{$range}";
                    $row[$key] = 0;
                }
            }

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

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);

            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);

            // Simplified Tacos Formula (no ad spend since using EbayMetric)
            $row["TacosL30"] = 0;

            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT %"] = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) : 0,
                2
            );
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) : 0,
                2
            );
            $row["percentage"] = $percentage;
            $row['ad_updates'] = $adUpdates;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & Hide
            $row['NR'] = "";
            $row['SPRICE'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['spend_l30'] = null;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['SPFT'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['spend_l30'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // Image
            $row["image_path"] = $shopify->image_src ?? ($values["image_path"] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "eBay Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }


    public function ebay2OrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.ebay2-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getEbay2OrganicViewsData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

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

        $ebayMetrics = Ebay2Metric::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        $nrValues = EbayTwoDataView::whereIn("sku", $skus)->pluck("value", "sku");

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

        // 5. Marketplace percentage (EbayTwo)
        $percentage = (MarketplacePercentage::where("marketplace", "EbayTwo")->value("percentage") ?? 100) / 100;

        // 6. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;
            $ebayMetric = $ebayMetrics[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;
            $row['fba'] = $pm->fba;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            // eBay2 Metrics
            $row["eBay L30"] = $ebayMetric->ebay_l30 ?? 0;
            $row["eBay L60"] = $ebayMetric->ebay_l60 ?? 0;
            $row["eBay Price"] = $ebayMetric->ebay_price ?? 0;
            $row['eBay_item_id'] = $ebayMetric->item_id ?? null;

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

            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            // Price and units for calculations
            $price = floatval($row["eBay Price"] ?? 0);
            $units_ordered_l30 = floatval($row["eBay L30"] ?? 0);
            $row["PmtClkL30"] = $adMetricsBySku[$sku]['L30']['Clk'] ?? 0;
            // Profit/Sales
            $row["Total_pft"] = round(($price * $percentage - $lp - $ship) * $units_ordered_l30, 2);
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT %"] = round(
                $price > 0 ? (($price * $percentage - $lp - $ship) / $price) : 0,
                2
            );
            $row["ROI%"] = round(
                $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) : 0,
                2
            );
            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & Hide
            $row['NR'] = "";
            $row['SPRICE'] = null;
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

        return response()->json([
            "message" => "eBay2 Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }


    public function ebay3OrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.ebay3-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getEbay3OrganicViewsData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Ebay3', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay3')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from EbayDataView
        $ebayDataViews = EbayThreeDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];

        foreach ($ebayDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? null;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
        }

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Fetch NR value if available
            $processedItem['NR'] = $nrValues[$sku] ?? null;
            $processedItem['Listed'] = $listedValues[$sku] ?? false;
            $processedItem['Live'] = $liveValues[$sku] ?? false;


            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['price'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['PFT %'] = 0;
            $processedItem['Roi'] = 0;
            $processedItem['percentage'] = $percentageValue;

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }


    public function temuOrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.temu-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getTemuOrganicViewsData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Temu data for these SKUs
        $temuMetrics = TemuMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values from temu_data_view
        $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // NEW: Fetch Temu product sheet data
        $temuProductSheets = TemuProductSheet::whereIn('sku', $skus)->get()->keyBy('sku');

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Add data from temu_metrics if available
            if (isset($temuMetrics[$sku])) {
                $temuMetric = $temuMetrics[$sku];
                $temuPrice = $temuMetric->base_price ?? 0;
                $ship = $values['ship'] ?? 0;

                $processedItem['price'] = $temuPrice > 0 ? $temuPrice + $ship : 0;
                $processedItem['price_wo_ship'] = $temuPrice;
                $processedItem['views_l30'] = $temuMetric->product_impressions_l30 ?? 0;
                $processedItem['views_l60'] = $temuMetric->product_impressions_l60 ?? 0;
                $processedItem['clicks_l30'] = $temuMetric->product_clicks_l30 ?? 0;
                $processedItem['clicks_l60'] = $temuMetric->product_clicks_l60 ?? 0;
                $processedItem['sales_l30'] = $temuMetric->quantity_purchased_l30 ?? 0;
                $processedItem['sales_l60'] = $temuMetric->quantity_purchased_l60 ?? 0;
                $processedItem['org_views'] = $temuMetric->organic_views ?? 0;
                $processedItem['ad_sold'] = $temuMetric->ad_sold ?? 0;
            } else {
                $processedItem['price'] = 0;
                $processedItem['price_wo_ship'] = 0;
                $processedItem['views_l30'] = 0;
                $processedItem['views_l60'] = 0;
                $processedItem['clicks_l30'] = 0;
                $processedItem['clicks_l60'] = 0;
                $processedItem['sales_l30'] = 0;
                $processedItem['sales_l60'] = 0;
            }

            // NEW: Add data from temu_product_sheets if available
            if (isset($temuProductSheets[$sku])) {
                $temuSheet = $temuProductSheets[$sku];
                $processedItem['sheet_price'] = $temuSheet->price ?? 0;
                $processedItem['sheet_pft'] = $temuSheet->pft ?? 0;
                $processedItem['sheet_roi'] = $temuSheet->roi ?? 0;
                $processedItem['sheet_l30'] = $temuSheet->l30 ?? 0;
                $processedItem['sheet_dil'] = $temuSheet->dil ?? 0;
                $processedItem['buy_link'] = $temuSheet->buy_link ?? '';

                // Calculate T Sales and T DIL
                $inv = $processedItem['INV'] ?? 0;
                $sales_l30 = $processedItem['sales_l30'] ?? 0;
                $sheet_dil = $temuSheet->dil ?? 0;

                $processedItem['T_Sales'] = $temuSheet->dil ?? 0; // T Sales from sheet
                $processedItem['T_DIL'] = $inv > 0 ? ($sheet_dil - ($sales_l30 / $inv)) * 100 : 0; // T DIL formula
            } else {
                $processedItem['sheet_price'] = 0;
                $processedItem['sheet_pft'] = 0;
                $processedItem['sheet_roi'] = 0;
                $processedItem['sheet_l30'] = 0;
                $processedItem['sheet_dil'] = 0;
                $processedItem['buy_link'] = '';
                $processedItem['T_Sales'] = 0;
                $processedItem['T_DIL'] = 0;
            }

            // Calculate CVR
            $clicks_l30 = $processedItem['clicks_l30'] ?? 0;
            $sales_l30 = $processedItem['sales_l30'] ?? 0; // Using sales from Temu API
            $processedItem['CVR'] = ($clicks_l30 > 0) ? ($sales_l30 / $clicks_l30) : 0;
            $processedItem['SOLD'] = $sales_l30; // Add SOLD field for tooltip

            // Add NR, Listed and Live values from temu_data_view if available
            if (isset($temuDataViews[$sku])) {
                $viewData = $temuDataViews[$sku];
                $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value, true) ?: []);
                $processedItem['NR'] = $valuesArr['NR'] ?? 'REQ';
                $processedItem['Listed'] = isset($valuesArr['Listed']) ? (bool)$valuesArr['Listed'] : false;
                $processedItem['Live'] = isset($valuesArr['Live']) ? (bool)$valuesArr['Live'] : false;
                $processedItem['SPRICE'] = isset($valuesArr['SPRICE']) ? (float)$valuesArr['SPRICE'] : 0;
                $processedItem['SPFT']   = isset($valuesArr['SPFT']) ? (float)$valuesArr['SPFT'] : 0;
                $processedItem['SROI']   = isset($valuesArr['SROI']) ? (float)$valuesArr['SROI'] : 0;
                $processedItem['SHIP']   = isset($valuesArr['SHIP']) ? (float)$valuesArr['SHIP'] : 0;
            } else {
                $processedItem['NR']     = 'REQ';
                $processedItem['Listed'] = false;
                $processedItem['Live']   = false;
                $processedItem['SPRICE'] = 0;
                $processedItem['SPFT']   = 0;
                $processedItem['SROI']   = 0;
                $processedItem['SHIP']   = 0;
            }

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['percentage'] = $percentageValue;

            // Calculate profit and ROI percentages
            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage - $lp - $ship) / $price) * 100;
                $processedItem['PFT_percentage'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT_percentage'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage - $lp - $ship) / $lp) * 100;
                $processedItem['ROI_percentage'] = round($roi_percentage, 2);
            } else {
                $processedItem['ROI_percentage'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }


    public function walmartOrganicViews(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('marketing-masters.walmart-organic-views', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    public function getWalmartOrganicViewsData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('walmart_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Temu data for these SKUs
        $temuMetrics = WalmartMetrics::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values from walmart_data_view
        $temuDataViews = WalmartDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // NEW: Fetch Walmart product sheet data
        $temuProductSheets = WalmartProductSheet::whereIn('sku', $skus)->get()->keyBy('sku');

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Add data from temu_metrics if available
            if (isset($temuMetrics[$sku])) {
                $temuMetric = $temuMetrics[$sku];
                $temuPrice = $temuMetric->base_price ?? 0;
                $ship = $values['ship'] ?? 0;

                $processedItem['price'] = $temuPrice > 0 ? $temuPrice + $ship : 0;
                $processedItem['price_wo_ship'] = $temuPrice;
                $processedItem['views_l30'] = $temuMetric->product_impressions_l30 ?? 0;
                $processedItem['views_l60'] = $temuMetric->product_impressions_l60 ?? 0;
                $processedItem['clicks_l30'] = $temuMetric->product_clicks_l30 ?? 0;
                $processedItem['clicks_l60'] = $temuMetric->product_clicks_l60 ?? 0;
                $processedItem['sales_l30'] = $temuMetric->quantity_purchased_l30 ?? 0;
                $processedItem['sales_l60'] = $temuMetric->quantity_purchased_l60 ?? 0;
            } else {
                $processedItem['price'] = 0;
                $processedItem['price_wo_ship'] = 0;
                $processedItem['views_l30'] = 0;
                $processedItem['views_l60'] = 0;
                $processedItem['clicks_l30'] = 0;
                $processedItem['clicks_l60'] = 0;
                $processedItem['sales_l30'] = 0;
                $processedItem['sales_l60'] = 0;
            }

            // NEW: Add data from temu_product_sheets if available
            if (isset($temuProductSheets[$sku])) {
                $temuSheet = $temuProductSheets[$sku];
                $processedItem['sheet_price'] = $temuSheet->price ?? 0;
                $processedItem['sheet_pft'] = $temuSheet->pft ?? 0;
                $processedItem['sheet_roi'] = $temuSheet->roi ?? 0;
                $processedItem['sheet_l30'] = $temuSheet->l30 ?? 0;
                $processedItem['sheet_dil'] = $temuSheet->dil ?? 0;
                $processedItem['buy_link'] = $temuSheet->buy_link ?? '';

                // Calculate T Sales and T DIL
                $inv = $processedItem['INV'] ?? 0;
                $sales_l30 = $processedItem['sales_l30'] ?? 0;
                $sheet_dil = $temuSheet->dil ?? 0;

                $processedItem['T_Sales'] = $temuSheet->dil ?? 0; // T Sales from sheet
                $processedItem['T_DIL'] = $inv > 0 ? ($sheet_dil - ($sales_l30 / $inv)) * 100 : 0; // T DIL formula
            } else {
                $processedItem['sheet_price'] = 0;
                $processedItem['sheet_pft'] = 0;
                $processedItem['sheet_roi'] = 0;
                $processedItem['sheet_l30'] = 0;
                $processedItem['sheet_dil'] = 0;
                $processedItem['buy_link'] = '';
                $processedItem['T_Sales'] = 0;
                $processedItem['T_DIL'] = 0;
            }

            // Calculate CVR
            $clicks_l30 = $processedItem['clicks_l30'] ?? 0;
            $sales_l30 = $processedItem['sales_l30'] ?? 0; // Using sales from Temu API
            $processedItem['CVR'] = ($clicks_l30 > 0) ? ($sales_l30 / $clicks_l30) : 0;
            $processedItem['SOLD'] = $sales_l30; // Add SOLD field for tooltip

            // Add NR, Listed and Live values from temu_data_view if available
            if (isset($temuDataViews[$sku])) {
                $viewData = $temuDataViews[$sku];
                $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value, true) ?: []);
                $processedItem['NR'] = $valuesArr['NR'] ?? 'REQ';
                $processedItem['Listed'] = isset($valuesArr['Listed']) ? (bool)$valuesArr['Listed'] : false;
                $processedItem['Live'] = isset($valuesArr['Live']) ? (bool)$valuesArr['Live'] : false;
                $processedItem['SPRICE'] = isset($valuesArr['SPRICE']) ? (float)$valuesArr['SPRICE'] : 0;
                $processedItem['SPFT']   = isset($valuesArr['SPFT']) ? (float)$valuesArr['SPFT'] : 0;
                $processedItem['SROI']   = isset($valuesArr['SROI']) ? (float)$valuesArr['SROI'] : 0;
                $processedItem['SHIP']   = isset($valuesArr['SHIP']) ? (float)$valuesArr['SHIP'] : 0;
            } else {
                $processedItem['NR']     = 'REQ';
                $processedItem['Listed'] = false;
                $processedItem['Live']   = false;
                $processedItem['SPRICE'] = 0;
                $processedItem['SPFT']   = 0;
                $processedItem['SROI']   = 0;
                $processedItem['SHIP']   = 0;
            }

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['percentage'] = $percentageValue;

            // Calculate profit and ROI percentages
            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage - $lp - $ship) / $price) * 100;
                $processedItem['PFT_percentage'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT_percentage'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage - $lp - $ship) / $lp) * 100;
                $processedItem['ROI_percentage'] = round($roi_percentage, 2);
            } else {
                $processedItem['ROI_percentage'] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }



}
