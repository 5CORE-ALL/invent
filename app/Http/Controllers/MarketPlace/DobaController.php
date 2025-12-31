<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Jobs\UpdateDobaSPriceJob;
use App\Models\ChannelMaster;
use App\Models\DobaDailyData;
use App\Models\DobaDataView;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Models\ProductMaster; // Add this at the top with other use statements
use App\Services\DobaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; // Ensure you import Log for logging
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class DobaController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function updatePrice(Request $request)
    {
        $sku = $request["sku"];
        $price = $request["price"];

        $result = UpdateDobaSPriceJob::dispatch($sku, $price)->delay(now()->addMinutes(3));

        return response()->json(['status' => 200]);
    }

    public function dobaView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from cache or database
        // $percentage = Cache::remember(
        //     "doba_marketplace_percentage",
        //     now()->addDays(30),
        //     function () {
        //         $marketplaceData = MarketplacePercentage::where(
        //             "marketplace",
        //             "Doba"
        //         )->first();
        //         return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        //     }
        // );

        $marketplaceData = ChannelMaster::where('channel', 'Doba')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view("market-places.doba-analytics", [
            "mode" => $mode,
            "demo" => $demo,
            "dobaPercentage" => $percentage,
        ]);
    }



    public function dobaPricingCVR(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage directly from database (no cache)
        $marketplaceData = MarketplacePercentage::where("marketplace", "Doba")->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view("market-places.doba_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "dobaPercentage" => $percentage,
        ]);
    }

    public function getViewdobaData(Request $request)
    {
        // 1. Get Doba-listed SKUs from DobaMetric table (populated from API)
        $dobaListedSkus = DobaMetric::pluck('sku')->filter()->unique()->values()->all();
        
        // 2. Base ProductMaster fetch - only for Doba-listed SKUs
        $productMasters = ProductMaster::whereIn('sku', $dobaListedSkus)
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        // 3. SKU list
        $skus = $productMasters
            ->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 4. Related Models
        $shopifyData = ShopifySku::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");
        $dobaMetrics = dobaMetric::whereIn("sku", $skus)
            ->get()
            ->keyBy("sku");
        $nrValues = DobaDataView::whereIn("sku", $skus)->pluck("value", "sku");

        // 5. Aggregate S L30 from doba_daily_data - sum quantity by SKU for L30 period, excluding cancelled orders
        $dobaDailyL30 = DB::table('doba_daily_data')
            ->select(
                'sku',
                DB::raw('SUM(quantity) as s_l30_count')
            )
            ->whereIn('sku', $skus)
            ->whereRaw("LOWER(period) = 'l30'")
            ->where(function($query) {
                $query->whereNotIn('order_status', ['Canceled', 'Cancelled', 'CANCELED', 'CANCELLED', 'canceled', 'cancelled'])
                      ->orWhereNull('order_status');
            })
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');

        // 6. Get marketplace percentage (no cache)
        $percentage = (MarketplacePercentage::where("marketplace", "Doba")->value("percentage") ?? 100) / 100;

        // 7. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;
            $dobaMetric = $dobaMetrics[$pm->sku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;

            // Shopify
            $row["INV"] = $shopify->inv ?? 0;
            $row["L30"] = $shopify->quantity ?? 0;

            //Doba Metrics - using quantity instead of order count for SOLD column
            $row["doba L30"] = $dobaMetric->quantity_l30 ?? 0;
            $row["doba L60"] = $dobaMetric->quantity_l60 ?? 0;
            $row["doba Price"] = $dobaMetric->anticipated_income ?? 0;
            $row['doba_item_id'] = $dobaMetric->item_id ?? null;
            $row['self_pick_price'] = $dobaMetric->self_pick_price ?? 0;
            $row['msrp'] = $dobaMetric->msrp ?? 0;
            $row['map'] = $dobaMetric->map ?? 0;

            // S L30 from doba_daily_data (excluding cancelled orders)
            $sL30Data = $dobaDailyL30[$pm->sku] ?? null;
            $row["s_l30"] = $sL30Data ? (int) $sL30Data->s_l30_count : 0;

            // Values: LP & Ship
            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values)
                    ? json_decode($pm->Values, true)
                    : []);
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
            $ship = isset($values["ship"])
                ? floatval($values["ship"])
                : (isset($pm->ship)
                    ? floatval($pm->ship)
                    : 0);

            // Price and units for calculations
            $price = floatval($row["doba Price"] ?? 0);
            $units_ordered_l30 = floatval($row["doba L30"] ?? 0);

            $row["Total_pft"] = round(
                ($price * $percentage - $lp - $ship) * $units_ordered_l30,
                2
            );
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT_percentage"] = round(
                $price > 0
                    ? (($price * $percentage - $lp - $ship) / $price) * 100
                    : 0,
                2
            );
            $row["ROI_percentage"] = round(
                $lp > 0
                    ? (($price * $percentage - $lp - $ship) / $lp) * 100
                    : 0,
                2
            );
            $row["T_COGS"] = round($lp * $units_ordered_l30, 2);

            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & Hide

            $row['NR'] = null;
            $row['SPRICE'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['S_SELF_PICK'] = null;
            $row['PUSH_STATUS'] = null;
            $row['PUSH_STATUS_UPDATED_AT'] = null;
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
                    $row['S_SELF_PICK'] = $raw['S_SELF_PICK'] ?? null;
                    $row['PUSH_STATUS'] = $raw['PUSH_STATUS'] ?? null;
                    $row['PUSH_STATUS_UPDATED_AT'] = $raw['PUSH_STATUS_UPDATED_AT'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // Image
            $row["image_path"] =
                $shopify->image_src ??
                ($values["image_path"] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "doba Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    public function updateAlldobaSkus(Request $request)
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
                ["marketplace" => "Doba"],
                ["percentage" => $percent]
            );

            return response()->json([
                "status" => 200,
                "message" => "Percentage updated successfully",
                "data" => [
                    "marketplace" => "Doba",
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

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nrInput = $request->input('nr'); // This could be string or JSON string

        if (!$sku || !$nrInput) {
            return response()->json(['error' => 'SKU and NR are required.'], 400);
        }

        // Normalize NR Input
        $nrValue = null;

        // If NR is a JSON string, decode it
        if (is_string($nrInput)) {
            $decoded = json_decode($nrInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['NR'])) {
                $nrValue = $decoded['NR'];
            } else {
                $nrValue = $nrInput;
            }
        } elseif (is_array($nrInput) && isset($nrInput['NR'])) {
            $nrValue = $nrInput['NR'];
        }

        // Fetch or create the record
        $dobaDataView = DobaDataView::firstOrNew(['sku' => $sku]);

        // Decode existing JSON value
        $existing = is_array($dobaDataView->value)
            ? $dobaDataView->value
            : (json_decode($dobaDataView->value, true) ?: []);

        // Update NR in existing data
        $existing['NR'] = $nrValue;

        // Save merged data
        $dobaDataView->value = $existing;
        $dobaDataView->save();

        return response()->json(['success' => true, 'data' => $dobaDataView]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent', 's_self_pick', 'push_status']);

        if (!$sku || !isset($spriceData['sprice'])) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }


        $dobaDataView = DobaDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($dobaDataView->value)
            ? $dobaDataView->value
            : (json_decode($dobaDataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
            'S_SELF_PICK' => $spriceData['s_self_pick'] ?? null,
            'PUSH_STATUS' => $spriceData['push_status'] ?? null,
            'PUSH_STATUS_UPDATED_AT' => isset($spriceData['push_status']) ? now()->format('Y-m-d H:i:s') : ($existing['PUSH_STATUS_UPDATED_AT'] ?? null),
        ]);

        $dobaDataView->value = $merged;
        $dobaDataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
    }

    /**
     * Push price to Doba API
     */
    public function pushPriceToDoba(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request->input('price');
        $selfPickPrice = $request->input('self_pick_price'); // Optional

        if (!$sku || !$price) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'SKU and price are required.']]
            ], 400);
        }

        // Get the item_id from DobaMetric table
        $dobaMetric = DobaMetric::where('sku', $sku)->first();

        if (!$dobaMetric || !$dobaMetric->item_id) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Item ID not found for this SKU. Please run Doba metrics fetch first.']]
            ], 404);
        }

        $itemId = $dobaMetric->item_id;

        try {
            $dobaApiService = new DobaApiService();
            
            // Only call Price API (Sale API disabled - requires special permission)
            $priceResult = $dobaApiService->updateItemPrice($itemId, $price, $selfPickPrice);

            // Check for errors
            if (isset($priceResult['errors'])) {
                Log::warning('Doba price push failed', [
                    'sku' => $sku,
                    'item_id' => $itemId,
                    'price' => $price,
                    'self_pick_price' => $selfPickPrice,
                    'error' => $priceResult['errors']
                ]);

                return response()->json([
                    'success' => false,
                    'errors' => [['message' => 'Price update: ' . $priceResult['errors']]]
                ], 400);
            }

            // Success - update the anticipated_income in DobaMetric as well
            $dobaMetric->anticipated_income = $price;
            if ($selfPickPrice !== null) {
                $dobaMetric->self_pick_price = $selfPickPrice;
            }
            $dobaMetric->save();

            Log::info('Doba price push successful', [
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price pushed to Doba successfully',
                'data' => [
                    'price_update' => $priceResult
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Doba price push exception', [
                'sku' => $sku,
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'errors' => [['message' => 'API Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Doba')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = DobaDataView::firstOrCreate(
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

    public function importDobaAnalytics(Request $request)
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
                DobaDataView::updateOrCreate(
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

    public function exportDobaAnalytics()
    {
        $dobaData = DobaDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($dobaData as $data) {
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
        $fileName = 'Doba_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Doba_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function dobaTabulatorView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database (no cache)
        $marketplaceData = MarketplacePercentage::where("marketplace", "Doba")->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view('market-places.doba_tabulator_view', [
            'mode' => $mode,
            'demo' => $demo,
            'dobaPercentage' => $percentage,
        ]);
    }
}
