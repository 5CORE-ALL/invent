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

        $listingStatus = EbayListingStatus::select("sku", "value")->get()->keyBy("sku");

        return view("market-places.ebay_pricing_increase_decrease", [
            "mode" => $mode,
            "demo" => $demo,
            "ebayPercentage" => $percentage,
            "listingStatus" => $listingStatus,
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

        // 2. SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nonParentSkus = $productMasters->pluck("sku")
            ->filter()
            ->filter(function ($sku) {
                return stripos($sku, 'PARENT') === false;
            })
            ->unique()
            ->values()
            ->all();

        // 3. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        $ebayMetrics = DB::connection('apicentral')->table('ebay_one_metrics')->whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn("sku", $skus)->pluck("value", "sku");

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
        $nrl = $request->input("nrl");
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
        if (!$sku || ($nr === null && $nrl === null && $hide === null)) {
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

        if ($nrl !== null) {
            $value["NRL"] = $nrl;
        }

        $ebayDataView->value = $value;
        $ebayDataView->save();

        return response()->json(["success" => true, "data" => $ebayDataView]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        // LOG::info('Saving Shopify pricing data', $request->all());
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent']);

        if (!$sku || !$spriceData['sprice']) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }


        $ebayDataView = EbayDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($ebayDataView->value)
            ? $ebayDataView->value
            : (json_decode($ebayDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
        ]);

        $ebayDataView->value = $merged;
        $ebayDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
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
}
