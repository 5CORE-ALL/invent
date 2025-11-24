<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\EbayMetric;
use App\Models\ShopifySku;
use App\Models\EbayDataView;
use Illuminate\Http\Request;
use App\Services\EbayApiService;
use App\Models\EbayGeneralReport;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiController;
use App\Jobs\UpdateEbaySPriceJob;
use App\Models\ChannelMaster;
use App\Models\ADVMastersData;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster; // Add this at the top with other use statements
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\EbayGeneralReports;
use App\Models\EbayListingStatus;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class EbayController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebayView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;


        return view("market-places.ebay", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }

    public function ebayTabulatorView(Request $request)
    {
        return view("market-places.ebay_tabulator_view");
    }

    public function ebayDataJson(Request $request)
    {
        try {
            $response = $this->getViewEbayData($request);
            $data = json_decode($response->getContent(), true);
            
            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getAdvEbayTotalSaveData(Request $request)
    {
        return ADVMastersData::getAdvEbayTotalSaveDataProceed($request);
    }

    public function ebayPricingCVR(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
            }
        );

        return view("market-places.ebay_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }


    public function ebayPricingIncreaseDecrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
            }
        );

        $marketplaceData = MarketplacePercentage::where("marketplace", "Ebay")->first();
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        $listingStatus = EbayListingStatus::select("sku", "value")->get()->keyBy("sku");

        return view("market-places.ebay_pricing_increase_decrease", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "listingStatus" => $listingStatus,
            "ebayAdUpdates" => $adUpdates,
        ]);
    }
    
    public function ebayPricingIncrease(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "ebay_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Ebay"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.ebay_pricing_increase", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
        ]);
    }
    public function updateFbaStatusEbay(Request $request)
    {
        $sku = $request->input('shopify_id');
        $fbaStatus = $request->input('fba');

        if (!$sku || !is_numeric($fbaStatus)) {
            return response()->json(['error' => 'SKU and FBA status are required.'], 400);
        }
        $amazonData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        if (!$amazonData) {
            return response()->json(['error' => 'SKU not found.'], 404);
        }
        DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->update(['fba' => $fbaStatus]);
        $updatedData = DB::table('ebay_data_view')
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'FBA status updated successfully.',
            'data' => $updatedData
        ]);
    }

    public function getViewEbayData(Request $request)
    {
        // 1. Base ProductMaster fetch
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

            $productMasters = $productMasters->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })->values();


        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

            $nonParentSkus = $skus;

        // $nonParentSkus = $productMasters->pluck("sku")
        //     ->filter()
        //     ->filter(function ($sku) {
        //         return stripos($sku, 'PARENT') === false;
        //     })
        //     ->unique()
        //     ->values()
        //     ->all();

        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        $ebayMetrics = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->select(
                'sku',
                'ebay_l30',
                'ebay_l60',
                'ebay_price',
                'views',
                'item_id'
            )
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        $campaignListings = DB::connection('apicentral')
            ->table('ebay_campaign_ads_listings')
            ->select('listing_id', 'bid_percentage', 'suggested_bid')
            ->get()
            ->keyBy('listing_id')
            ->toArray();

        $nrValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $ebayMetrics->pluck('item_id')->toArray())
            ->get();

        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            $lmpRecords = DB::connection('repricer')
                ->table('lmp_data')
                ->select('sku', 'price', 'link')
                ->where('price', '>', 0)
                ->whereIn('sku', $nonParentSkus)
                ->orderBy('sku')
                ->orderBy('price')
                ->get()
                ->groupBy('sku');

            $lmpDetailsLookup = $lmpRecords;
            $lmpLowestLookup = $lmpRecords->map(function ($items) {
                return $items->first();
            });
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

            // Get bid percentage from campaign listings
            if ($ebayMetric && isset($campaignListings[$ebayMetric->item_id])) {
                $row['bid_percentage'] = $campaignListings[$ebayMetric->item_id]->bid_percentage ?? null;
                $row['suggested_bid'] = $campaignListings[$ebayMetric->item_id]->suggested_bid ?? null;
            } else {
                $row['bid_percentage'] = null;
                $row['suggested_bid'] = null;
            }

            // LMP data - lowest entry plus top entries
            $lmpEntries = $lmpDetailsLookup->get($pm->sku);
            if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                $lmpEntries = collect();
            }

            $lowestLmp = $lmpLowestLookup->get($pm->sku);
            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->price))
                ? round((float) $lowestLmp->price, 2)
                : null;
            $row['lmp_link'] = $lowestLmp->link ?? null;
            $row['lmp_entries'] = $lmpEntries
                ->take(5)
                ->map(function ($entry) {
                    $entryArray = (array) $entry;

                    return [
                        'price' => isset($entryArray['price']) ? (float) $entryArray['price'] : null,
                        'link' => $entryArray['link'] ?? null,
                    ];
                })
                ->values()
                ->toArray();
            $row['lmp_entries_total'] = $lmpEntries->count();

            $row["E Dil%"] = ($row["eBay L30"] && $row["INV"] > 0)
                ? round(($row["eBay L30"] / $row["INV"]), 2)
                : 0;

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });

            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebayMetric) {
                if (!$ebayMetric || empty($ebayMetric->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebayMetric->item_id);
            });

            // Keyword campaign
            $kw_spend_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $kw_sales_l30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $kw_sold_l30  = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);

            // General ads
            $pmt_spend_l30 = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
            $pmt_sales_l30 = (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
            $pmt_sold_l30  = (int) ($matchedGeneralL30->sales ?? 0);

            // Final AD totals
            $AD_Spend_L30 = $kw_spend_l30 + $pmt_spend_l30;
            $AD_Sales_L30 = $kw_sales_l30 + $pmt_sales_l30;
            $AD_Units_L30 = $kw_sold_l30 + $pmt_sold_l30;

            $row["AD_Spend_L30"] = round($AD_Spend_L30, 2);
            $row["AD_Sales_L30"] = round($AD_Sales_L30, 2);
            $row["AD_Units_L30"] = $AD_Units_L30;

            // AD% Formula = (pt_spend + kw_spend) / (l30_ads_sales_kw + l30_ads_sales_pt)
            $adDenominator = $kw_sales_l30 + $pmt_sales_l30;

            $row["AD%"] = $adDenominator > 0
                ? round((($kw_spend_l30 + $pmt_spend_l30) / $adDenominator) * 100, 4)
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
            $row["Profit"] = $row["Total_pft"]; // Add this for frontend compatibility
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["Sales L30"] = $row["T_Sale_l30"]; // Add this for frontend compatibility
            
            // Calculate GPFT%
            $gpft = $price > 0 ? (($price * 0.86 - $ship - $lp) / $price) * 100 : 0;
            
            // PFT% = GPFT% - AD%
            $row["PFT %"] = round($gpft - $row["AD%"], 2);
            $totalPercentage = $percentage + $adUpdates; 

            $row["ROI%"] = round(
                $lp > 0 ? (($price * 0.86 - $ship - $lp) / $lp) * 100 : 0,
                2
            );


            $row["GPFT%"] = round(
                $price  > 0 ? (($price * 0.86 - $ship - $lp) / $price) * 100 : 0,
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
            $row['SGPFT'] = null;
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
                    $row['NRL'] = $raw['NRL'] ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['SPFT'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['SGPFT'] = $raw['SGPFT'] ?? null;
                    $row['spend_l30'] = $raw['Spend_L30'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // If SPRICE is null or empty, use eBay Price as default and calculate SPFT/SROI/SGPFT
            if (empty($row['SPRICE']) && $price > 0) {
                $row['SPRICE'] = $price;
                $row['has_custom_sprice'] = false; // Flag to indicate using default price
                
                // Calculate SGPFT based on default price
                $sgpft = round(
                    $price > 0 ? (($price * 0.86 - $ship - $lp) / $price) * 100 : 0,
                    2
                );
                $row['SGPFT'] = $sgpft;
                
                // Calculate SPFT = SGPFT - AD%
                $row['SPFT'] = round($sgpft - $row["AD%"], 2);
                
                // Calculate SROI using ROI formula with SPRICE: ((SPRICE * 0.86 - ship - lp) / lp) * 100
                $row['SROI'] = round(
                    $lp > 0 ? (($price * 0.86 - $ship - $lp) / $lp) * 100 : 0,
                    2
                );
            } else {
                $row['has_custom_sprice'] = true; // Flag to indicate custom SPRICE
                
                // Calculate SGPFT using custom SPRICE if not already set
                if (empty($row['SGPFT'])) {
                    $sprice = floatval($row['SPRICE']);
                    $sgpft = round(
                        $sprice > 0 ? (($sprice * 0.86 - $ship - $lp) / $sprice) * 100 : 0,
                        2
                    );
                    $row['SGPFT'] = $sgpft;
                }
                
                // Recalculate SPFT if already set, to ensure it uses SGPFT - AD%
                if (!empty($row['SGPFT'])) {
                    $row['SPFT'] = round($row['SGPFT'] - $row["AD%"], 2);
                }
                
                // Recalculate SROI using ROI formula with custom SPRICE
                if (!empty($row['SPRICE'])) {
                    $sprice = floatval($row['SPRICE']);
                    $row['SROI'] = round(
                        $lp > 0 ? (($sprice * 0.86 - $ship - $lp) / $lp) * 100 : 0,
                        2
                    );
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


    // Helper function
    private function extractNumber($value)
    {
        if (empty($value)) return 0;
        return (float) preg_replace('/[^\d.]/', '', $value);
    }


    public function updateAllEbaySkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Ebay')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            // Handle percentage update
            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

            // Save both fields
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Ebay'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]
            );

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully!',
                'data' => [
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Ebay marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $skus = $request->input("skus");
        $hideValues = $request->input("hideValues"); // <-- add this
        $sku = $request->input("sku");
        $nr = $request->input("nr");
        $hide = $request->input("hide");

        // Decode hideValues if it's a JSON string
        if (is_string($hideValues)) {
            $hideValues = json_decode($hideValues, true);
        }

        // Bulk update with individual hide values
        if (is_array($skus) && is_array($hideValues)) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
                // Use the value from hideValues for each SKU
                $value["Hide"] = filter_var(
                    $hideValues[$skuItem] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Bulk update if 'skus' is present and 'hide' is a single value (legacy)
        if (is_array($skus) && $hide !== null) {
            foreach ($skus as $skuItem) {
                $ebayDataView = EbayDataView::firstOrNew(["sku" => $skuItem]);
                $value = is_array($ebayDataView->value)
                    ? $ebayDataView->value
                    : (json_decode($ebayDataView->value, true) ?:
                        []);
                $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
                $ebayDataView->value = $value;
                $ebayDataView->save();
            }
            return response()->json([
                "success" => true,
                "updated" => count($skus),
            ]);
        }

        // Single update (existing logic)
        if (!$sku || ($nr === null && $hide === null)) {
            return response()->json(
                ["error" => "SKU and at least one of NR or Hide is required."],
                400
            );
        }

        $ebayDataView = EbayDataView::firstOrNew(["sku" => $sku]);
        $value = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?:
                []);

        if ($nr !== null) {
            $value["NR"] = $nr;
        }

        if ($hide !== null) {
            $value["Hide"] = filter_var($hide, FILTER_VALIDATE_BOOLEAN);
        }

        $ebayDataView->value = $value;
        $ebayDataView->save();

        // Create a user-friendly message based on what was updated
        $message = "Data updated successfully";
        if ($nr !== null) {
            $message = $nr === 'NRL' ? "NRL updated" : ($nr === 'REQ' ? "REQ updated" : "NR updated to {$nr}");
        } elseif ($hide !== null) {
            $message = "Hide status updated";
        }

        return response()->json(["success" => true, "data" => $ebayDataView, "message" => $message]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving eBay pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            Log::error('SKU or sprice missing', ['sku' => $sku, 'sprice' => $sprice]);
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            Log::error('SKU not found in ProductMaster', ['sku' => $sku]);
            return response()->json(['error' => 'SKU not found in ProductMaster.'], 404);
        }

        // Extract lp and ship
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
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        // Calculate profit
        $spriceFloat = floatval($sprice);
        $profit = ($spriceFloat * $percentage - $lp - $ship);

        // Calculate SGPFT first
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * 0.86 - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // Get AD% from the product
        $adPercent = 0;
        $ebayMetric = DB::connection('apicentral')
            ->table('ebay_one_metrics')
            ->where('sku', $sku)
            ->first();
        
        if ($ebayMetric) {
            $campaignListings = DB::connection('apicentral')
                ->table('ebay_campaign_ads_listings')
                ->where('listing_id', $ebayMetric->item_id)
                ->first();
            
            $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
                ->where('campaign_name', 'LIKE', '%' . $sku . '%')
                ->first();
            
            $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
                ->where('listing_id', $ebayMetric->item_id)
                ->first();
            
            $kw_spend_l30 = (float) str_replace('USD ', '', $ebayCampaignReportsL30->cpc_ad_fees_payout_currency ?? 0);
            $kw_sales_l30 = (float) str_replace('USD ', '', $ebayCampaignReportsL30->cpc_sale_amount_payout_currency ?? 0);
            $pmt_spend_l30 = (float) str_replace('USD ', '', $ebayGeneralReportsL30->ad_fees ?? 0);
            $pmt_sales_l30 = (float) str_replace('USD ', '', $ebayGeneralReportsL30->sale_amount ?? 0);
            
            $adDenominator = $kw_sales_l30 + $pmt_sales_l30;
            $adPercent = $adDenominator > 0 ? (($kw_spend_l30 + $pmt_spend_l30) / $adDenominator) * 100 : 0;
        }
        
        // SPFT = SGPFT - AD%
        $spft = round($sgpft - $adPercent, 2);
        // SROI = same formula as ROI% but with SPRICE: ((SPRICE * 0.86 - ship - lp) / lp) * 100
        $sroi = $lp > 0 ? round((($spriceFloat * 0.86 - $ship - $lp) / $lp) * 100, 2) : 0;
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'ad_percent' => $adPercent, 'spft' => $spft, 'sroi' => $sroi]);

        $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);

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
        Log::info('Data saved successfully', ['sku' => $sku]);

        return response()->json([
            'message' => 'Data saved successfully.',
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft
        ]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = EbayDataView::firstOrCreate(
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

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'eBay')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function importEbayAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
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
                EbayDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportEbayAnalytics()
    {
        $ebayData = EbayDataView::all();

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
        $fileName = 'Ebay_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Ebay_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function getEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setEbayColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "ebay_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    public function exportEbayPricingData(Request $request)
    {
        try {
            $response = $this->getViewEbayData($request);
            $data = json_decode($response->getContent(), true);
            $ebayData = $data['data'] ?? [];

            // Set headers for CSV download
            $fileName = 'eBay_Pricing_Data_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            // Open output stream
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row
            $headers = [
                'Parent', 'SKU', 'INV', 'L30', 'Dil%', 'eBay L30', 'eBay L60', 
                'eBay Price', 'LMP', 'AD Spend L30', 'AD Sales L30', 'AD Units L30',
                'AD%', 'TACOS L30', 'Total Sales L30', 'Total Profit', 'PFT %', 
                'ROI%', 'GPFT%', 'Views', 'NR', 'SPRICE', 'SPFT', 'SROI', 
                'Listed', 'Live'
            ];
            fputcsv($output, $headers);

            // Data Rows
            foreach ($ebayData as $item) {
                $item = (array) $item;
                
                // Calculate Dil%
                $dil = ($item['INV'] > 0) ? round(($item['L30'] / $item['INV']) * 100, 2) : 0;

                fputcsv($output, [
                    $item['Parent'] ?? '',
                    $item['(Child) sku'] ?? '',
                    $item['INV'] ?? 0,
                    $item['L30'] ?? 0,
                    $dil,
                    $item['eBay L30'] ?? 0,
                    $item['eBay L60'] ?? 0,
                    number_format($item['eBay Price'] ?? 0, 2),
                    $item['lmp_price'] ? number_format($item['lmp_price'], 2) : '',
                    number_format($item['AD_Spend_L30'] ?? 0, 2),
                    number_format($item['AD_Sales_L30'] ?? 0, 2),
                    $item['AD_Units_L30'] ?? 0,
                    number_format(($item['AD%'] ?? 0) * 100, 2),
                    number_format(($item['TacosL30'] ?? 0) * 100, 2),
                    number_format($item['T_Sale_l30'] ?? 0, 2),
                    number_format($item['Total_pft'] ?? 0, 2),
                    number_format($item['PFT %'] ?? 0, 0),
                    number_format($item['ROI%'] ?? 0, 0),
                    number_format($item['GPFT%'] ?? 0, 0),
                    $item['views'] ?? 0,
                    $item['NR'] ?? '',
                    $item['SPRICE'] ? number_format($item['SPRICE'], 2) : '',
                    $item['SPFT'] ? number_format($item['SPFT'], 0) : '',
                    $item['SROI'] ? number_format($item['SROI'], 0) : '',
                    ($item['Listed'] ?? false) ? 'TRUE' : 'FALSE',
                    ($item['Live'] ?? false) ? 'TRUE' : 'FALSE',
                ]);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            Log::error('Error exporting eBay pricing data: ' . $e->getMessage());
            return back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }
}
