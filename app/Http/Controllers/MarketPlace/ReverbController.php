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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        // $percentage = Cache::remember(
        //     "reverb_marketplace_percentage",
        //     now()->addDays(30),
        //     function () {
        //         $marketplaceData = MarketplacePercentage::where(
        //             "marketplace",
        //             "Reverb"
        //         )->first();
        //         return $marketplaceData ? $marketplaceData->percentage : 100;
        //     }
        // );

        $marketplaceData = ChannelMaster::where('channel', 'Reverb')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

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
}
