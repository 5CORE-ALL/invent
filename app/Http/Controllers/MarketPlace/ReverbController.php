<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\ReverbViewData;
use App\Models\ReverbListingStatus;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonChannelSummary;

class ReverbController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function reverbView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 85;
            }
        );

        return view("market-places.reverb", [
            "mode" => $mode,
            "demo" => $demo,
            "percentage" => $percentage,
        ]);
    }

    public function reverbPricingCvr(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.reverb_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "percentage" => $percentage,
        ]);
    }

    public function reverbPricingIncreaseCvr(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.reverb_pricing_increase_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "percentage" => $percentage,
        ]);
    }


    public function reverbPricingdecreaseCvr(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );

        return view("market-places.reverb_pricing_decrease_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "percentage" => $percentage,
        ]);
    }


    public function getViewReverbData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 100;
            }
        );
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch reverb data for these SKUs
        $reverbData = ReverbProduct::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch all required data from ReverbViewData in a single query
        $reverbViewData = ReverbViewData::whereIn("sku", $skus)->get()->keyBy("sku");

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, "PARENT") !== false;

            // Initialize the data structure
            $processedItem = [
                "SL No." => $slNo++,
                "Parent" => $productMaster->parent ?? null,
                "Sku" => $sku,
                "R&A" => false,
                "is_parent" => $isParent,
                "raw_data" => [
                    "parent" => $productMaster->parent,
                    "sku" => $sku,
                    "Values" => $productMaster->Values,
                ],
                "Listed" => false,
                "Live" => false,
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem["LP"] = $values["lp"] ?? 0;
            $processedItem["Ship"] = $values["ship"] ?? 0;
            $processedItem["COGS"] = $values["cogs"] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
            }

            // Add data from reverb_products if available
            if (isset($reverbData[$sku])) {
                $reverbItem = $reverbData[$sku];
                $reverbPrice = $reverbItem->price ?? 0;
                $ship = $values["ship"] ?? 0;

                $processedItem["price"] = $reverbPrice > 0 ? $reverbPrice + $ship : 0;
                $processedItem["price_wo_ship"] = $reverbPrice;
                $processedItem["views"] = $reverbItem->views ?? 0;
                $processedItem["r_l30"] = $reverbItem->r_l30 ?? 0;
                $processedItem["r_l60"] = $reverbItem->r_l60 ?? 0;
            } else {
                $processedItem["price"] = 0;
                $processedItem["price_wo_ship"] = 0;
                $processedItem["views"] = 0;
                $processedItem["r_l30"] = 0;
                $processedItem["r_l60"] = 0;
            }

            // Add all data from reverb_view_data if available
            if (isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];

                // Process values column (cast to array)
                $valuesArr = $viewData->values ?: [];
                $processedItem["Bump"] = $valuesArr["bump"] ?? null;
                $processedItem["s bump"] = $valuesArr["s_bump"] ?? null;
                $processedItem["sprice"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : null;
                $processedItem["spft_percent"] = isset($valuesArr["SPFT"]) ? floatval(str_replace("%", "", $valuesArr["SPFT"])) : null;
                $processedItem["sroi_percent"] = isset($valuesArr["SROI"]) ? floatval(str_replace("%", "", $valuesArr["SROI"])) : null;
                $processedItem["R&A"] = $valuesArr["R&A"] ?? false;
                $processedItem["NR"] = $valuesArr["NR"] ?? '';

                // Check if Listed and Live are in the values column
                if (isset($valuesArr['Listed'])) {
                    $processedItem["Listed"] = filter_var($valuesArr['Listed'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($valuesArr['Live'])) {
                    $processedItem["Live"] = filter_var($valuesArr['Live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Process value column (for Listed and Live, if not in values)
                $value = $viewData->value;
                if ($value !== null) {
                    if (is_array($value)) {
                        $processedItem["Listed"] = isset($value['Listed']) ? filter_var($value['Listed'], FILTER_VALIDATE_BOOLEAN) : $processedItem["Listed"];
                        $processedItem["Live"] = isset($value['Live']) ? filter_var($value['Live'], FILTER_VALIDATE_BOOLEAN) : $processedItem["Live"];
                    } else {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $processedItem["Listed"] = isset($decoded['Listed']) ? filter_var($decoded['Listed'], FILTER_VALIDATE_BOOLEAN) : $processedItem["Listed"];
                            $processedItem["Live"] = isset($decoded['Live']) ? filter_var($decoded['Live'], FILTER_VALIDATE_BOOLEAN) : $processedItem["Live"];
                        } else {
                            Log::error("JSON decode error for SKU {$sku}: " . json_last_error_msg());
                        }
                    }
                }
            }

            // Default values for other fields
            $processedItem["A L30"] = 0;
            $processedItem["Sess30"] = 0;
            $processedItem["TOTAL PFT"] = 0;
            $processedItem["T Sales L30"] = 0;
            $processedItem["percentage"] = $percentageValue;

            $price = floatval($processedItem["price"]);
            $percentage = floatval($processedItem["percentage"]);
            $lp = floatval($processedItem["LP"]);
            $ship = floatval($processedItem["Ship"]);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage - $lp - $ship) / $price) * 100;
                $processedItem["PFT_percentage"] = round($pft_percentage, 2);
            } else {
                $processedItem["PFT_percentage"] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage - $lp - $ship) / $lp) * 100;
                $processedItem["ROI_percentage"] = round($roi_percentage, 2);
            } else {
                $processedItem["ROI_percentage"] = 0;
            }

            $processedData[] = $processedItem;
        }

        return response()->json([
            "message" => "Data fetched successfully",
            "data" => $processedData,
            "status" => 200,
        ]);
    }

    public function updateAllReverbSkus(Request $request)
    {
        try {
            $percent = $request->input("percent");

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json(
                    [
                        "status" => 400,
                        "message" =>
                        "Invalid percentage value. Must be between 0 and 100.",
                    ],
                    400
                );
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ["marketplace" => "Reverb"],
                ["percentage" => $percent]
            );

            // Store in cache
            Cache::put(
                "reverb_marketplace_percentage",
                $percent,
                now()->addDays(30)
            );

            return response()->json([
                "status" => 200,
                "message" => "Percentage updated successfully",
                "data" => [
                    "marketplace" => "Reverb",
                    "percentage" => $percent,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "status" => 500,
                    "message" => "Error updating percentage",
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }

    // Add this to your ReverbController.php
    public function updateReverbColumn(Request $request)
    {
        $validated = $request->validate([
            "slNo" => "required|integer",
            "sku" => "required|string",
            "parent" => "required|string",
            "updates" => "required|array",
        ]);

        try {
            $sku = $validated["sku"];
            $updates = $validated["updates"];

            // Find or create the record
            $reverbData = ReverbViewData::firstOrNew(["sku" => $sku]);

            // Set parent if it's a new record
            if (!$reverbData->exists) {
                $reverbData->parent = $validated["parent"];
            }

            // Get current values or initialize empty array
            $currentValues = $reverbData->values ?: [];

            // Process updates with consistent field names
            foreach ($updates as $key => $value) {
                // Normalize field names to lowercase with underscores
                $field = strtolower(str_replace(" ", "_", $key));

                // Special handling for specific fields to ensure consistency
                if (
                    $field === "bump" ||
                    $field === "s_bump" ||
                    $field === "s_price" ||
                    $field === "r&a"
                ) {
                    // Keep these field names exactly as they are in the database
                    $field = $key; // Use the original key to match database
                }

                $currentValues[$field] = $value;
            }

            // Update the values
            $reverbData->values = $currentValues;
            $reverbData->save();

            return response()->json([
                "status" => 200,
                "message" => "Update successful",
                "data" => $reverbData,
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "status" => 500,
                    "message" => "Failed to update",
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input("sku");
        if (!$sku) {
            return response()->json(["error" => "SKU is required."], 400);
        }

        $reverbDataView = ReverbViewData::firstOrNew(["sku" => $sku]);
        $values = is_array($reverbDataView->values)
            ? $reverbDataView->values
            : (json_decode($reverbDataView->values, true) ?:
                []);

        // Update values safely
        if ($request->has("nr")) {
            $values["NR"] = $request->input("nr");
        }
        if ($request->filled("sprice")) {
            $values["SPRICE"] = $request->input("sprice");
        }
        if ($request->filled("sprofit_percent")) {
            $values["SPFT"] = $request->input("sprofit_percent");
        }
        if ($request->filled("sroi_percent")) {
            $values["SROI"] = $request->input("sroi_percent");
        }

        $reverbDataView->values = $values;
        $reverbDataView->save();

        return response()->json(["success" => true, "data" => $reverbDataView]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = ReverbViewData::firstOrCreate(
            ['sku' => $request->sku],
            ['values' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->values)
            ? $product->values
            : (json_decode($product->values, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->values = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importReverbAnalytics(Request $request)
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
                ReverbViewData::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['values' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportReverbAnalytics()
    {
        $reverbData = ReverbViewData::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($reverbData as $data) {
            $values = is_array($data->values)
                ? $data->values
                : (json_decode($data->values, true) ?? []);

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
        $fileName = 'Reverb_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Reverb_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // Reverb Tabulator View and Methods
    public function reverbTabulatorView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 85;
            }
        );

        return view("market-places.reverb_tabulator_view", [
            "mode" => $mode,
            "demo" => $demo,
            "reverbPercentage" => $percentage,
        ]);
    }

    public function reverbDataJson(Request $request)
    {
        try {
            $response = $this->getViewReverbTabularData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Reverb data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewReverbTabularData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember(
            "reverb_marketplace_percentage",
            now()->addDays(30),
            function () {
                $marketplaceData = MarketplacePercentage::where(
                    "marketplace",
                    "Reverb"
                )->first();
                return $marketplaceData ? $marketplaceData->percentage : 85;
            }
        );
        $percentageValue = $percentage / 100;

        // Fetch all product master records (excluding parent rows)
        $productMasterRows = ProductMaster::all()
            ->filter(function ($item) {
                return stripos($item->sku, 'PARENT') === false;
            })
            ->keyBy("sku");

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck("sku")->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch reverb data for these SKUs
        $reverbData = ReverbProduct::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch reverb view data for SPRICE
        $reverbViewData = ReverbViewData::whereIn("sku", $skus)->get()->keyBy("sku");

        // Fetch reverb listing status for NR/REQ (same table as listing page)
        $reverbListingStatus = ReverbListingStatus::whereIn("sku", $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy("sku");

        // Fetch Amazon data for price comparison
        $amazonData = \App\Models\AmazonDatasheet::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");

        // Process data
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, "PARENT") !== false;

            // Initialize the data structure
            $processedItem = [
                "SL No." => $slNo++,
                "Parent" => $productMaster->parent ?? null,
                "(Child) sku" => $sku,
                "is_parent" => $isParent,
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem["LP_productmaster"] = $values["lp"] ?? 0;
            $processedItem["Ship_productmaster"] = $values["ship"] ?? 0;
            $processedItem["COGS"] = $values["cogs"] ?? 0;
            
            // Image path - check shopify first, then product master Values, then product master direct field
            $processedItem["image_path"] = null;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem["INV"] = $shopifyItem->inv ?? 0;
                $processedItem["L30"] = $shopifyItem->quantity ?? 0;
                // Get image from shopify if available
                $processedItem["image_path"] = $shopifyItem->image_src ?? ($values["image_path"] ?? ($productMaster->image_path ?? null));
            } else {
                $processedItem["INV"] = 0;
                $processedItem["L30"] = 0;
                // Fallback to product master for image
                $processedItem["image_path"] = $values["image_path"] ?? ($productMaster->image_path ?? null);
            }

            // Add data from reverb_products if available
            if (isset($reverbData[$sku])) {
                $reverbItem = $reverbData[$sku];
                $reverbPrice = $reverbItem->price ?? 0;

                $processedItem["RV Price"] = $reverbPrice;
                $processedItem["Views"] = $reverbItem->views ?? 0;
                $processedItem["RV L30"] = $reverbItem->r_l30 ?? 0;
                $processedItem["RV L60"] = $reverbItem->r_l60 ?? 0;
                $processedItem["R Stock"] = $reverbItem->remaining_inventory ?? 0;
                $processedItem["Missing"] = ''; // SKU exists in Reverb
            } else {
                $processedItem["RV Price"] = 0;
                $processedItem["Views"] = 0;
                $processedItem["RV L30"] = 0;
                $processedItem["RV L60"] = 0;
                $processedItem["R Stock"] = 0;
                $processedItem["Missing"] = ''; // Will be set later based on INV and nr_req
            }

            // Store temp values for MAP calculation after nr_req is set
            $tempMissingCheck = !isset($reverbData[$sku]);

            // Calculate CVR percentage (L30 / Views * 100)
            $views = $processedItem["Views"];
            $rvL30 = $processedItem["RV L30"];
            $processedItem["CVR"] = $views > 0 ? round(($rvL30 / $views) * 100, 2) : 0;

            // Amazon Price
            if (isset($amazonData[$sku])) {
                $processedItem["A Price"] = $amazonData[$sku]->price ?? 0;
            } else {
                $processedItem["A Price"] = 0;
            }

            // Get NR/REQ from reverb_listing_statuses (same table as listing page)
            $processedItem["nr_req"] = 'REQ'; // Default value
            
            if (isset($reverbListingStatus[$sku])) {
                $listingStatus = $reverbListingStatus[$sku];
                $statusValue = is_array($listingStatus->value) 
                    ? $listingStatus->value 
                    : (json_decode($listingStatus->value, true) ?? []);
                
                // Get rl_nrl from listing status and convert to REQ/NR for tabulator
                $rlNrl = $statusValue['rl_nrl'] ?? null;
                
                // If old nr_req field exists and no rl_nrl, convert it
                if (!$rlNrl && isset($statusValue['nr_req'])) {
                    $rlNrl = ($statusValue['nr_req'] === 'REQ') ? 'RL' : (($statusValue['nr_req'] === 'NR') ? 'NRL' : 'RL');
                }
                
                // Convert RL/NRL to REQ/NR for display
                if ($rlNrl === 'RL') {
                    $processedItem["nr_req"] = 'REQ';
                } else if ($rlNrl === 'NRL') {
                    $processedItem["nr_req"] = 'NR';
                } else {
                    $processedItem["nr_req"] = 'REQ'; // Default
                }
            }

            // Now calculate MAP and Missing based on nr_req and INV
            $inv = $processedItem["INV"];
            $rStock = $processedItem["R Stock"];
            $nrReq = $processedItem["nr_req"];
            
            // Missing: Only for REQ items with INV > 0
            $isMissing = false;
            if ($nrReq === 'REQ' && $inv > 0 && $tempMissingCheck) {
                $processedItem["Missing"] = 'M';
                $isMissing = true;
            } else {
                $processedItem["Missing"] = '';
            }
            
            // MAP: Only for REQ items with INV > 0 and NOT Missing
            if ($nrReq === 'REQ' && $inv > 0 && !$isMissing) {
                if ($inv == $rStock) {
                    $processedItem["MAP"] = 'Map';
                } else {
                    // Stocks don't match - show N Map with qty difference
                    $diff = abs($inv - $rStock);
                    $processedItem["MAP"] = "N Map|$diff";
                }
            } else {
                // Don't show MAP for NR items, INV = 0, or Missing items
                $processedItem["MAP"] = '';
            }

            // Get SPRICE from reverb_view_data
            $processedItem["SPRICE"] = 0;
            $processedItem["SGPFT"] = 0;
            $processedItem["SPFT"] = 0;
            $processedItem["SROI"] = 0;

            if (isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];
                $valuesArr = $viewData->values ?: [];
                
                $processedItem["SPRICE"] = isset($valuesArr["SPRICE"]) ? floatval($valuesArr["SPRICE"]) : 0;
                $processedItem["SGPFT"] = isset($valuesArr["SGPFT"]) ? floatval($valuesArr["SGPFT"]) : 0;
                $processedItem["SPFT"] = isset($valuesArr["SPFT"]) ? floatval(str_replace("%", "", $valuesArr["SPFT"])) : 0;
                $processedItem["SROI"] = isset($valuesArr["SROI"]) ? floatval(str_replace("%", "", $valuesArr["SROI"])) : 0;
            }

            // Calculate profit metrics
            $processedItem["percentage"] = $percentageValue;

            $price = floatval($processedItem["RV Price"]);
            $lp = floatval($processedItem["LP_productmaster"]);
            $ship = floatval($processedItem["Ship_productmaster"]);

            // GPFT%
            if ($price > 0) {
                $gpft_percentage = (($price * $percentageValue - $lp - $ship) / $price) * 100;
                $processedItem["GPFT%"] = round($gpft_percentage, 2);
            } else {
                $processedItem["GPFT%"] = 0;
            }

            // PFT%
            $processedItem["PFT %"] = $processedItem["GPFT%"];

            // ROI%
            if ($lp > 0) {
                $roi_percentage = (($price * $percentageValue - $lp - $ship) / $lp) * 100;
                $processedItem["ROI%"] = round($roi_percentage, 2);
            } else {
                $processedItem["ROI%"] = 0;
            }

            // Profit
            $processedItem["Profit"] = ($price * $percentageValue) - $lp - $ship;

            // Sales L30
            $processedItem["Sales L30"] = $price * $processedItem["RV L30"];

            // Dil%
            $inv = $processedItem["INV"];
            $l30 = $processedItem["L30"];
            $processedItem["RV Dil%"] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0;

            $processedData[] = $processedItem;
        }

        return response()->json([
            "message" => "Data fetched successfully",
            "data" => $processedData,
            "status" => 200,
        ]);
    }

    public function saveSpriceUpdates(Request $request)
    {
        try {
            // Handle both single SKU and batch updates
            $updates = [];
            
            if ($request->has('updates')) {
                // Batch update format
                $updates = $request->input('updates', []);
            } elseif ($request->has('sku') && $request->has('sprice')) {
                // Single SKU format (from cellEdited)
                $updates = [[
                    'sku' => $request->input('sku'),
                    'sprice' => $request->input('sprice')
                ]];
            }

            $updatedCount = 0;
            $errors = [];

            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                $sprice = $update['sprice'] ?? null;

                if (!$sku || $sprice === null) {
                    $errors[] = "Invalid update data for SKU: " . ($sku ?? 'unknown');
                    continue;
                }

                // Find or create reverb view data
                $reverbViewData = ReverbViewData::firstOrNew(['sku' => $sku]);
                
                // Get existing values
                $values = is_array($reverbViewData->values) 
                    ? $reverbViewData->values 
                    : (json_decode($reverbViewData->values, true) ?: []);

                // Update SPRICE
                $values['SPRICE'] = floatval($sprice);

                // Get product master data for calculations
                $productMaster = ProductMaster::where('sku', $sku)->first();
                if ($productMaster) {
                    $pmValues = $productMaster->Values ?: [];
                    $lp = $pmValues['lp'] ?? 0;
                    $ship = $pmValues['ship'] ?? 0;
                    $percentage = 0.85; // 85% margin for Reverb

                    // Calculate SGPFT
                    if ($sprice > 0) {
                        $sgpft = (($sprice * $percentage - $lp - $ship) / $sprice) * 100;
                        $values['SGPFT'] = round($sgpft, 2);
                    } else {
                        $values['SGPFT'] = 0;
                    }

                    // Calculate SPFT (same as SGPFT for now)
                    $values['SPFT'] = $values['SGPFT'] . '%';

                    // Calculate SROI
                    if ($lp > 0) {
                        $sroi = (($sprice * $percentage - $lp - $ship) / $lp) * 100;
                        $values['SROI'] = round($sroi, 2) . '%';
                    } else {
                        $values['SROI'] = '0%';
                    }
                }

                // Save values
                $reverbViewData->values = $values;
                $reverbViewData->save();

                $updatedCount++;
            }

            // Return response based on request format
            if ($request->has('sku') && !$request->has('updates')) {
                // Single SKU response (for cellEdited)
                if ($updatedCount > 0 && count($updates) > 0) {
                    $update = $updates[0];
                    $sku = $update['sku'];
                    
                    // Get calculated values
                    $reverbViewData = ReverbViewData::where('sku', $sku)->first();
                    $values = $reverbViewData ? ($reverbViewData->values ?: []) : [];
                    
                    return response()->json([
                        'success' => true,
                        'sgpft_percent' => $values['SGPFT'] ?? 0,
                        'spft_percent' => floatval(str_replace('%', '', $values['SPFT'] ?? '0')),
                        'sroi_percent' => floatval(str_replace('%', '', $values['SROI'] ?? '0'))
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to save SPRICE'
                    ], 400);
                }
            } else {
                // Batch update response
                return response()->json([
                    'success' => true,
                    'updated' => $updatedCount,
                    'errors' => $errors
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error saving Reverb SPRICE updates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateReverbListedLive(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'required|in:REQ,NR'
        ]);

        try {
            $sku = trim($request->sku);
            $nrReq = $request->nr_req;

            // Convert REQ/NR to RL/NRL for storage in reverb_listing_statuses
            $rlNrl = ($nrReq === 'REQ') ? 'RL' : 'NRL';

            // Get the most recent record or create new
            $listingStatus = ReverbListingStatus::where('sku', $sku)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($listingStatus) {
                $existing = is_array($listingStatus->value) 
                    ? $listingStatus->value 
                    : (json_decode($listingStatus->value, true) ?? []);
                
                if (empty($existing)) {
                    $existing = [];
                }
            } else {
                $existing = [];
            }

            // Update rl_nrl field
            $existing['rl_nrl'] = $rlNrl;

            // Clean up duplicates before creating/updating
            ReverbListingStatus::where('sku', $sku)->delete();

            // Create a single clean record
            ReverbListingStatus::create([
                'sku' => $sku,
                'value' => $existing
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error updating Reverb NR/REQ: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "reverb_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    public function setColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "reverb_tabulator_column_visibility_{$userId}";
        
        $visibility = $request->input('visibility', []);
        
        Cache::put($key, $visibility, now()->addDays(365));
        
        return response()->json(['success' => true]);
    }

    /**
     * Auto-save daily Reverb summary snapshot (channel-wise)
     * Matches JavaScript updateSummary() logic exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            
            // Filter: INV > 0 && nr_req === 'REQ' && not parent (EXACT JavaScript logic)
            $filteredData = collect($products)->filter(function($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                $notParent = !(isset($p['Parent']) && str_starts_with($p['Parent'], 'PARENT'));
                
                return $invCheck && $reqCheck && $notParent;
            });
            
            if ($filteredData->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = $filteredData->count();
            $totalPft = 0;
            $totalSales = 0;
            $totalGpft = 0;
            $totalPrice = 0;
            $priceCount = 0;
            $totalInv = 0;
            $totalL30 = 0;
            $zeroSoldCount = 0;
            $moreSoldCount = 0;
            $totalDil = 0;
            $dilCount = 0;
            $totalCogs = 0;
            $totalRoi = 0;
            $roiCount = 0;
            $lessAmzCount = 0;
            $moreAmzCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $invRStockCount = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($filteredData as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['Sales L30'] ?? 0);
                $totalGpft += floatval($row['GPFT%'] ?? 0);
                
                $price = floatval($row['RV Price'] ?? 0);
                if ($price > 0) {
                    $totalPrice += $price;
                    $priceCount++;
                }
                
                $totalInv += floatval($row['INV'] ?? 0);
                $totalL30 += floatval($row['RV L30'] ?? 0);
                
                $l30 = floatval($row['RV L30'] ?? 0);
                if ($l30 == 0) {
                    $zeroSoldCount++;
                } else {
                    $moreSoldCount++;
                }
                
                $dil = floatval($row['RV Dil%'] ?? 0);
                if ($dil > 0) {
                    $totalDil += $dil;
                    $dilCount++;
                }
                
                // COGS = LP × RV L30
                $lp = floatval($row['LP_productmaster'] ?? 0);
                $totalCogs += $lp * $l30;
                
                $roi = floatval($row['ROI%'] ?? 0);
                if ($roi != 0) {
                    $totalRoi += $roi;
                    $roiCount++;
                }
                
                // Compare RV Price with Amazon Price
                $rvPrice = floatval($row['RV Price'] ?? 0);
                $amzPrice = floatval($row['A Price'] ?? 0);
                
                if ($amzPrice > 0 && $rvPrice > 0) {
                    if ($rvPrice < $amzPrice) {
                        $lessAmzCount++;
                    } elseif ($rvPrice > $amzPrice) {
                        $moreAmzCount++;
                    }
                }
                
                // Get variables for filtering (EXACT JavaScript updated logic)
                $inv = floatval($row['INV'] ?? 0);
                $nrReq = $row['nr_req'] ?? 'REQ';
                $isMissing = ($row['Missing'] ?? '') === 'M';
                
                // Count Missing (only REQ items with INV > 0)
                if ($isMissing && $nrReq === 'REQ' && $inv > 0) {
                    $missingCount++;
                }
                
                // Count Map (only REQ items with INV > 0 and NOT Missing)
                $mapValue = $row['MAP'] ?? '';
                if ($mapValue === 'Map' && $nrReq === 'REQ' && $inv > 0 && !$isMissing) {
                    $mapCount++;
                }
                
                // Count N Map (only REQ items with INV > 0 and NOT Missing)
                if ($mapValue && str_contains($mapValue, 'N Map|') && $nrReq === 'REQ' && $inv > 0 && !$isMissing) {
                    $invRStockCount++;
                }
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgGpft = $totalSkuCount > 0 ? $totalGpft / $totalSkuCount : 0;
            $avgPrice = $priceCount > 0 ? $totalPrice / $priceCount : 0;
            $avgDil = $dilCount > 0 ? $totalDil / $dilCount : 0;
            $avgRoi = $roiCount > 0 ? $totalRoi / $roiCount : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'inv_r_stock_count' => $invRStockCount,
                'less_amz_count' => $lessAmzCount,
                'more_amz_count' => $moreAmzCount,
                
                // Financial Totals
                'total_pft' => round($totalPft, 2),
                'total_sales' => round($totalSales, 2),
                'total_cogs' => round($totalCogs, 2),
                
                // Inventory
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                
                // Calculated Percentages & Averages
                'avg_gpft' => round($avgGpft, 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round($avgRoi, 2),
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
                    'channel' => 'reverb',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, REQ only)',
                ]
            );
            
            Log::info("Daily Reverb summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $moreSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily Reverb summary: ' . $e->getMessage());
        }
    }
}
