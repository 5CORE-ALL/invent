<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuDataView;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\TemuDailyData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class TemuController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function temuView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100;
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.temu', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function temuPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

        return view('market-places.temu-cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewTemuData(Request $request)
    {
        try {
            $days = $request->input('days', 30);
            $dateFrom = Carbon::now()->subDays($days)->startOfDay();
            
            // Get daily data grouped by SKU
            $dailyData = TemuDailyData::where('purchase_date', '>=', $dateFrom)
                ->select(
                    'contribution_sku',
                    DB::raw('COUNT(DISTINCT order_id) as total_orders'),
                    DB::raw('SUM(quantity_purchased) as total_quantity_purchased'),
                    DB::raw('SUM(quantity_shipped) as total_quantity_shipped'),
                    DB::raw('SUM(quantity_to_ship) as total_quantity_to_ship'),
                    DB::raw('SUM(base_price_total) as total_revenue'),
                    DB::raw('AVG(base_price_total) as avg_price'),
                    DB::raw('MAX(purchase_date) as last_order_date'),
                    DB::raw('MIN(purchase_date) as first_order_date')
                )
                ->groupBy('contribution_sku')
                ->get()
                ->keyBy('contribution_sku');

            // Fetch all product master records
            $productMasterRows = ProductMaster::all()->keyBy('sku');

            // Get all unique SKUs from product master
            $skus = $productMasterRows->pluck('sku')->toArray();

            // Fetch shopify data for these SKUs
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            // Fetch NR values from temu_data_view
            $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()->keyBy('sku');

            // Get marketplace percentage
            $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
            $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
            $percentageValue = $percentage / 100;

            // Process data
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
                    'R&A' => false,
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

                // Add data from daily data if available
                if (isset($dailyData[$sku])) {
                    $daily = $dailyData[$sku];
                    $processedItem['total_orders'] = $daily->total_orders ?? 0;
                    $processedItem['sales_l30'] = $daily->total_quantity_purchased ?? 0;
                    $processedItem['quantity_shipped'] = $daily->total_quantity_shipped ?? 0;
                    $processedItem['quantity_to_ship'] = $daily->total_quantity_to_ship ?? 0;
                    $processedItem['total_revenue'] = $daily->total_revenue ?? 0;
                    $processedItem['price'] = $daily->avg_price ?? 0;
                    $processedItem['last_order_date'] = $daily->last_order_date;
                    
                    // Calculate views and clicks (you may want to adjust these)
                    $processedItem['views_l30'] = 0;
                    $processedItem['clicks_l30'] = 0;
                    
                    // Calculate CVR if you have clicks data
                    $clicks = $processedItem['clicks_l30'];
                    $sales = $processedItem['sales_l30'];
                    $processedItem['CVR'] = ($clicks > 0) ? ($sales / $clicks) : 0;
                } else {
                    $processedItem['total_orders'] = 0;
                    $processedItem['sales_l30'] = 0;
                    $processedItem['quantity_shipped'] = 0;
                    $processedItem['quantity_to_ship'] = 0;
                    $processedItem['total_revenue'] = 0;
                    $processedItem['price'] = 0;
                    $processedItem['last_order_date'] = null;
                    $processedItem['views_l30'] = 0;
                    $processedItem['clicks_l30'] = 0;
                    $processedItem['CVR'] = 0;
                }

                $processedItem['SOLD'] = $processedItem['sales_l30'];

                // Add NR, Listed and Live values from temu_data_view if available
                if (isset($temuDataViews[$sku])) {
                    $viewData = $temuDataViews[$sku];
                    $valuesArr = is_array($viewData->value) ? $viewData->value : (json_decode($viewData->value, true) ?: []);
                    $processedItem['NR'] = $valuesArr['NR'] ?? 'REQ';
                    $processedItem['Listed'] = isset($valuesArr['Listed']) ? (bool)$valuesArr['Listed'] : false;
                    $processedItem['Live'] = isset($valuesArr['Live']) ? (bool)$valuesArr['Live'] : false;
                    $processedItem['SPRICE'] = isset($valuesArr['SPRICE']) ? (float)$valuesArr['SPRICE'] : 0;
                    $processedItem['SPFT'] = isset($valuesArr['SPFT']) ? (float)$valuesArr['SPFT'] : 0;
                    $processedItem['SROI'] = isset($valuesArr['SROI']) ? (float)$valuesArr['SROI'] : 0;
                    $processedItem['SHIP'] = isset($valuesArr['SHIP']) ? (float)$valuesArr['SHIP'] : 0;
                } else {
                    $processedItem['NR'] = 'REQ';
                    $processedItem['Listed'] = false;
                    $processedItem['Live'] = false;
                    $processedItem['SPRICE'] = 0;
                    $processedItem['SPFT'] = 0;
                    $processedItem['SROI'] = 0;
                    $processedItem['SHIP'] = 0;
                }

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
        } catch (\Exception $e) {
            Log::error('Error fetching Temu data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function updateAllTemuSkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');
            
            // Support legacy 'percent' parameter
            if (!$type && $request->has('percent')) {
                $type = 'percentage';
                $value = $request->input('percent');
            }

            $channelData = ChannelMaster::where('channel', 'Temu')->first();
            $percent = $channelData ? $channelData->channel_percentage : 100;
            $adUpdates = $channelData ? $channelData->ad_updates : 100;

            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid percentage value. Must be between 0 and 100.'
                    ], 400);
                }
                $percent = $value;
            }

            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid ad_updates value.'
                    ], 400);
                }
                $adUpdates = $value;
            }

            // Update database
            $channel = ChannelMaster::updateOrCreate(
                ['channel' => 'Temu'],
                [
                    'channel_percentage' => $percent,
                    'ad_updates' => $adUpdates
                ]
            );

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully',
                'data' => [
                    'channel' => 'Temu',
                    'percentage' => $channel->channel_percentage,
                    'ad_updates' => $channel->ad_updates
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

        $dataView = TemuDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        if ($nr !== null) {
            $value["NR"] = $nr;
        }
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
        $product = TemuDataView::firstOrCreate(
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


    public function saveSpriceToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent', 'ship']);

        if (!$sku || !$spriceData['sprice'] || !isset($spriceData['ship'])) {
            return response()->json(['error' => 'SKU, sprice, and ship are required.'], 400);
        }

        try {
            $temuDataView = TemuDataView::firstOrNew(['sku' => $sku]);

            // Decode existing JSON safely
            $existing = is_array($temuDataView->value)
                ? $temuDataView->value
                : (json_decode($temuDataView->value, true) ?: []);

            // Merge with new values
            $merged = array_merge($existing, [
                'SPRICE' => (float) $spriceData['sprice'],
                'SPFT'   => (float) $spriceData['spft_percent'],
                'SROI'   => (float) $spriceData['sroi_percent'],
                'SHIP'   => (float) $spriceData['ship'],
                'Live'   => true,   // proper boolean
                'Listed' => true    // proper boolean
            ]);

            // Encode JSON with booleans preserved
            $temuDataView->value = $merged;
            $temuDataView->save();

            return response()->json([
                'success' => true,
                'message' => 'Data saved successfully.',
                'data'    => $merged
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving SPRICE for SKU {$sku}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred while saving.'], 500);
        }
    }


    public function temuPricingCVRinc(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

        return view('market-places.temu_pricing_inc', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function temuPricingCVRdsc(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from ChannelMaster
        $marketplaceData = ChannelMaster::where('channel', 'Temu')->first();
        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;

        return view('market-places.temu_pricing_dsc', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function importTemuAnalytics(Request $request)
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
                TemuDataView::updateOrCreate(
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

    public function exportTemuAnalytics()
    {
        $temuData = TemuDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($temuData as $data) {
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
        $fileName = 'Temu_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Temu_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function uploadDailyDataChunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);

            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');

            // Create a unique identifier for this upload session
            $uploadId = $request->input('uploadId', uniqid('temu_upload_'));

            // Store the file temporarily
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $fileName = $uploadId . '_' . $file->getClientOriginalName();
            $filePath = $tempPath . '/' . $fileName;

            // Move uploaded file on first chunk
            if ($chunk == 0) {
                $file->move($tempPath, $fileName);
                
                // Truncate the table on first chunk to remove all existing data
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                TemuDailyData::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                
                Log::info('Temu daily data table truncated before import');
            }

            // Load and process the spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Get headers from first row
            $rawHeaders = $rows[0];
            $headers = [];
            $headerMapping = [];
            
            foreach ($rawHeaders as $index => $header) {
                $normalized = $this->normalizeHeader($header);
                $headers[] = $normalized;
                $headerMapping[$index] = [
                    'raw' => $header,
                    'normalized' => $normalized
                ];
            }

            // Log headers for debugging on first chunk
            if ($chunk == 0) {
                Log::info('Temu Upload - Header Mapping:', $headerMapping);
            }

            unset($rows[0]); // Remove header row

            $totalRows = count($rows);
            $chunkSize = ceil($totalRows / $totalChunks);
            $startRow = $chunk * $chunkSize;
            $endRow = min(($chunk + 1) * $chunkSize, $totalRows);

            // Process only this chunk's rows
            $chunkRows = array_slice($rows, $startRow, $endRow - $startRow, true);
            
            $imported = 0;
            $skipped = 0;
            $errors = [];

            DB::beginTransaction();
            try {
                foreach ($chunkRows as $index => $row) {
                    if (empty($row[0])) { // Skip if order_id is empty
                        $skipped++;
                        continue;
                    }

                    // Ensure row has same number of elements as headers
                    $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                    $data = array_combine($headers, $rowData);

                    // Log first row of first chunk for debugging
                    if ($chunk == 0 && $imported == 0) {
                        Log::info('Temu Upload - First Row Complete Data:', $data);
                        Log::info('Temu Upload - Sample Fields:', [
                            'order_id' => $data['order_id'] ?? 'MISSING',
                            'purchase_date_raw' => $data['purchase_date'] ?? 'MISSING',
                            'contribution_sku' => $data['contribution_sku'] ?? 'MISSING',
                            'recipient_name' => $data['recipient_name'] ?? 'MISSING',
                            'ship_address_1' => $data['ship_address_1'] ?? 'MISSING',
                            'ship_city' => $data['ship_city'] ?? 'MISSING',
                            'tracking_number' => $data['tracking_number'] ?? 'MISSING',
                        ]);
                    }

                    // Clean and prepare data - use isset and check for actual content
                    $insertData = [
                        'order_id' => isset($data['order_id']) && $data['order_id'] !== '' ? trim($data['order_id']) : null,
                        'order_status' => isset($data['order_status']) && $data['order_status'] !== '' ? trim($data['order_status']) : null,
                        'fulfillment_mode' => isset($data['fulfillment_mode']) && $data['fulfillment_mode'] !== '' ? trim($data['fulfillment_mode']) : null,
                        'logistics_service_suggestion' => isset($data['logistics_service_suggestion']) && $data['logistics_service_suggestion'] !== '' ? trim($data['logistics_service_suggestion']) : null,
                        'order_item_id' => isset($data['order_item_id']) && $data['order_item_id'] !== '' ? trim($data['order_item_id']) : null,
                        'order_item_status' => isset($data['order_item_status']) && $data['order_item_status'] !== '' ? trim($data['order_item_status']) : null,
                        'product_name_by_customer_order' => isset($data['product_name_by_customer_order']) && $data['product_name_by_customer_order'] !== '' ? trim($data['product_name_by_customer_order']) : null,
                        'product_name' => isset($data['product_name']) && $data['product_name'] !== '' ? trim($data['product_name']) : null,
                        'variation' => isset($data['variation']) && $data['variation'] !== '' ? trim($data['variation']) : null,
                        'contribution_sku' => isset($data['contribution_sku']) && $data['contribution_sku'] !== '' ? trim($data['contribution_sku']) : null,
                        'sku_id' => isset($data['sku_id']) && $data['sku_id'] !== '' ? trim($data['sku_id']) : null,
                        'quantity_purchased' => isset($data['quantity_purchased']) && $data['quantity_purchased'] !== '' ? (int)$data['quantity_purchased'] : null,
                        'quantity_shipped' => isset($data['quantity_shipped']) && $data['quantity_shipped'] !== '' ? (int)$data['quantity_shipped'] : null,
                        'quantity_to_ship' => isset($data['quantity_to_ship']) && $data['quantity_to_ship'] !== '' ? (int)$data['quantity_to_ship'] : null,
                        'recipient_name' => isset($data['recipient_name']) && $data['recipient_name'] !== '' ? trim($data['recipient_name']) : null,
                        'recipient_first_name' => isset($data['recipient_first_name']) && $data['recipient_first_name'] !== '' ? trim($data['recipient_first_name']) : null,
                        'recipient_last_name' => isset($data['recipient_last_name']) && $data['recipient_last_name'] !== '' ? trim($data['recipient_last_name']) : null,
                        'recipient_phone_number' => isset($data['recipient_phone_number']) && $data['recipient_phone_number'] !== '' ? trim($data['recipient_phone_number']) : null,
                        'ship_address_1' => isset($data['ship_address_1']) && $data['ship_address_1'] !== '' ? trim($data['ship_address_1']) : null,
                        'ship_address_2' => isset($data['ship_address_2']) && $data['ship_address_2'] !== '' ? trim($data['ship_address_2']) : null,
                        'ship_address_3' => isset($data['ship_address_3']) && $data['ship_address_3'] !== '' ? trim($data['ship_address_3']) : null,
                        'district' => isset($data['district']) && $data['district'] !== '' ? trim($data['district']) : null,
                        'ship_city' => isset($data['ship_city']) && $data['ship_city'] !== '' ? trim($data['ship_city']) : null,
                        'ship_state' => isset($data['ship_state']) && $data['ship_state'] !== '' ? trim($data['ship_state']) : null,
                        'ship_postal_code' => isset($data['ship_postal_code']) && $data['ship_postal_code'] !== '' ? trim($data['ship_postal_code']) : null,
                        'ship_country' => isset($data['ship_country']) && $data['ship_country'] !== '' ? trim($data['ship_country']) : null,
                        'purchase_date' => isset($data['purchase_date']) ? $this->parseDate($data['purchase_date']) : null,
                        'latest_shipping_time' => isset($data['latest_shipping_time']) ? $this->parseDate($data['latest_shipping_time']) : null,
                        'latest_delivery_time' => isset($data['latest_delivery_time']) ? $this->parseDate($data['latest_delivery_time']) : null,
                        'iphone_serial_number' => isset($data['iphone_serial_number']) && $data['iphone_serial_number'] !== '' ? trim($data['iphone_serial_number']) : null,
                        'virtual_email' => isset($data['virtual_email']) && $data['virtual_email'] !== '' ? trim($data['virtual_email']) : null,
                        'activity_goods_base_price' => isset($data['activity_goods_base_price']) ? $this->sanitizePrice($data['activity_goods_base_price']) : null,
                        'base_price_total' => isset($data['base_price_total']) ? $this->sanitizePrice($data['base_price_total']) : null,
                        'tracking_number' => isset($data['tracking_number']) && $data['tracking_number'] !== '' ? trim($data['tracking_number']) : null,
                        'carrier' => isset($data['carrier']) && $data['carrier'] !== '' ? trim($data['carrier']) : null,
                        'order_settlement_status' => isset($data['order_settlement_status']) && $data['order_settlement_status'] !== '' ? trim($data['order_settlement_status']) : null,
                        'keep_proof_of_shipment_before_delivery' => isset($data['keep_proof_of_shipment_before_delivery']) && $data['keep_proof_of_shipment_before_delivery'] !== '' ? trim($data['keep_proof_of_shipment_before_delivery']) : null,
                    ];

                    // Log parsed date for first row
                    if ($chunk == 0 && $imported == 0) {
                        Log::info('Temu Upload - About to insert data:', [
                            'order_id' => $insertData['order_id'],
                            'purchase_date' => $insertData['purchase_date'],
                            'recipient_name' => $insertData['recipient_name'],
                            'ship_address_1' => $insertData['ship_address_1'],
                            'ship_city' => $insertData['ship_city'],
                            'tracking_number' => $insertData['tracking_number'],
                        ]);
                    }

                    TemuDailyData::create($insertData);
                    $imported++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            // Clean up temp file on last chunk
            if ($chunk == $totalChunks - 1) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2)
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Temu daily data chunk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function normalizeHeader($header)
    {
        // Store original for logging
        $original = $header;
        
        // Convert header to snake_case
        $headerLower = strtolower(trim($header));
        
        // Remove special characters and convert to underscores
        $headerNormalized = preg_replace('/[^a-z0-9_]/', '_', $headerLower);
        $headerNormalized = preg_replace('/_+/', '_', $headerNormalized);
        $headerNormalized = trim($headerNormalized, '_');
        
        // Map specific headers (including common variations)
        $mapping = [
            // Order fields
            'order_id' => 'order_id',
            'order_status' => 'order_status',
            'fulfillment_mode' => 'fulfillment_mode',
            'logistics_service_suggestion' => 'logistics_service_suggestion',
            
            // Order item fields
            'order_item_id' => 'order_item_id',
            'order_item_status' => 'order_item_status',
            
            // Product fields
            'product_name_by_customer_order' => 'product_name_by_customer_order',
            'product_name' => 'product_name',
            'variation' => 'variation',
            
            // SKU fields
            'contribution_sku' => 'contribution_sku',
            'sku_id' => 'sku_id',
            
            // Quantity fields
            'quantity_purchased' => 'quantity_purchased',
            'quantity_shipped' => 'quantity_shipped',
            'quantity_to_ship' => 'quantity_to_ship',
            
            // Recipient fields
            'recipient_name' => 'recipient_name',
            'recipient_first_name' => 'recipient_first_name',
            'recipient_last_name' => 'recipient_last_name',
            'recipient_phone_number' => 'recipient_phone_number',
            
            // Address fields
            'ship_address_1' => 'ship_address_1',
            'ship_address_2' => 'ship_address_2',
            'ship_address_3' => 'ship_address_3',
            'district' => 'district',
            'ship_city' => 'ship_city',
            'ship_state' => 'ship_state',
            'ship_postal_code' => 'ship_postal_code',
            'ship_postal_code_must_be_shipped_to_the_following_zip_code_' => 'ship_postal_code',
            'ship_country' => 'ship_country',
            
            // Date fields (including UTC variations)
            'purchase_date' => 'purchase_date',
            'purchase_date_utc_0_' => 'purchase_date',
            'purchase_date_utc_8_' => 'purchase_date',
            'latest_shipping_time' => 'latest_shipping_time',
            'latest_shipping_time_utc_0_' => 'latest_shipping_time',
            'latest_shipping_time_utc_8_' => 'latest_shipping_time',
            'latest_delivery_time' => 'latest_delivery_time',
            'latest_delivery_time_utc_0_' => 'latest_delivery_time',
            'latest_delivery_time_utc_8_' => 'latest_delivery_time',
            
            // Other fields
            'iphone_serial_number' => 'iphone_serial_number',
            'virtual_email' => 'virtual_email',
            'activity_goods_base_price' => 'activity_goods_base_price',
            'base_price_total' => 'base_price_total',
            'tracking_number' => 'tracking_number',
            'carrier' => 'carrier',
            'order_settlement_status' => 'order_settlement_status',
            'keep_proof_of_shipment_before_delivery' => 'keep_proof_of_shipment_before_delivery',
        ];

        $result = $mapping[$headerNormalized] ?? $headerNormalized;
        
        // Log if header is not in mapping (for debugging new CSV formats)
        if (!isset($mapping[$headerNormalized])) {
            Log::info("Temu Upload - Header normalized: '$original' -> '$headerNormalized' -> '$result'");
        }
        
        return $result;
    }

    /**
     * Sanitize price values by removing currency symbols and converting to decimal
     */
    private function sanitizePrice($value)
    {
        if (empty($value) || $value === '?') {
            return null;
        }

        // Remove currency symbols, commas, and whitespace
        $cleaned = preg_replace('/[$,\s]/', '', $value);
        
        // Return as float or null if not numeric
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }

    private function parseDate($dateString)
    {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }

        try {
            // Clean up the date string - remove timezone info like IST(UTC+5)
            $dateString = trim($dateString);
            $dateString = preg_replace('/\s+IST\(UTC[+-]\d+\)\s*$/', '', $dateString);
            $dateString = preg_replace('/\s+UTC[+-]\d+\s*$/', '', $dateString);
            $dateString = trim($dateString);
            
            // Check if it's an Excel numeric date (like 45321.5)
            if (is_numeric($dateString)) {
                // Excel dates are days since 1900-01-01
                $excelEpoch = Carbon::create(1900, 1, 1)->subDays(2); // Excel has a bug, needs -2 adjustment
                return $excelEpoch->copy()->addDays($dateString);
            }

            // Try parsing various date formats (including Temu format: Dec 9, 2025, 4:20 am)
            $formats = [
                'M j, Y, g:i a',      // Dec 9, 2025, 4:20 am (Temu format)
                'M d, Y, g:i a',      // Dec 09, 2025, 4:20 am
                'M j, Y g:i a',       // Dec 9, 2025 4:20 am
                'Y-m-d H:i:s',
                'Y-m-d',
                'm/d/Y H:i:s',
                'm/d/Y',
                'd/m/Y H:i:s',
                'd/m/Y',
                'M d, Y H:i:s',
                'M d, Y',
                'Y/m/d H:i:s',
                'Y/m/d',
                'd-m-Y H:i:s',
                'd-m-Y',
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, trim($dateString));
                    if ($date !== false && !$date->hasErrors()) {
                        return $date;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // If all else fails, try Carbon's parse method
            $parsed = Carbon::parse($dateString);
            return $parsed;
        } catch (\Exception $e) {
            Log::warning("Could not parse date: '$dateString' - Error: " . $e->getMessage());
            return null;
        }
    }

    public function downloadDailyDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row - All columns from migration
        $headers = [
            'Order ID', 'Order Status', 'Fulfillment Mode', 'Logistics Service Suggestion',
            'Order Item ID', 'Order Item Status', 'Product Name by Customer Order', 'Product Name',
            'Variation', 'Contribution SKU', 'SKU ID', 'Quantity Purchased', 'Quantity Shipped',
            'Quantity to Ship', 'Recipient Name', 'Recipient First Name', 'Recipient Last Name',
            'Recipient Phone Number', 'Ship Address 1', 'Ship Address 2', 'Ship Address 3',
            'District', 'Ship City', 'Ship State', 'Ship Postal Code', 'Ship Country',
            'Purchase Date', 'Latest Shipping Time', 'Latest Delivery Time', 'iPhone Serial Number',
            'Virtual Email', 'Activity Goods Base Price', 'Base Price Total', 'Tracking Number',
            'Carrier', 'Order Settlement Status', 'Keep Proof of Shipment Before Delivery'
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data (3 rows)
        $sampleData = [
            [
                'ORD001', 'Shipped', 'Standard', 'USPS Priority',
                'ITEM001', 'Delivered', 'Blue Widget', 'Widget Pro',
                'Color: Blue', 'SKU-WIDGET-001', 'WID001', 2, 2,
                0, 'John Doe', 'John', 'Doe',
                '+1234567890', '123 Main St', 'Apt 4B', '',
                'Downtown', 'New York', 'NY', '10001', 'USA',
                '2025-01-15 10:30:00', '2025-01-16 17:00:00', '2025-01-20 17:00:00', '',
                'john@example.com', '19.99', '39.98', 'TRACK123456',
                'USPS', 'Paid', 'Signature Required'
            ],
            [
                'ORD002', 'Processing', 'Express', 'FedEx Overnight',
                'ITEM002', 'Processing', 'Red Gadget', 'Gadget Max',
                'Color: Red', 'SKU-GADGET-002', 'GAD002', 1, 0,
                1, 'Jane Smith', 'Jane', 'Smith',
                '+0987654321', '456 Oak Ave', '', '',
                'Westside', 'Los Angeles', 'CA', '90001', 'USA',
                '2025-01-15 14:20:00', '2025-01-15 23:59:00', '2025-01-17 17:00:00', '',
                'jane@example.com', '49.99', '49.99', '',
                'FedEx', 'Pending', 'No Signature Required'
            ],
            [
                'ORD003', 'Cancelled', 'Standard', '',
                'ITEM003', 'Cancelled', 'Green Tool', 'Tool Plus',
                'Size: Large', 'SKU-TOOL-003', 'TOL003', 3, 0,
                0, 'Bob Johnson', 'Bob', 'Johnson',
                '+1122334455', '789 Elm St', 'Suite 200', 'Floor 2',
                'Midtown', 'Chicago', 'IL', '60601', 'USA',
                '2025-01-14 09:15:00', '2025-01-15 17:00:00', '2025-01-18 17:00:00', '',
                'bob@example.com', '29.99', '89.97', '',
                '', 'Refunded', ''
            ]
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths for better readability
        foreach (range('A', 'AK') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:AK1')->applyFromArray($headerStyle);

        // Output Download
        $fileName = 'Temu_Daily_Data_Sample_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Get daily data - show only SKUs that exist in temu_daily_data
     * Fetch LP and temu_ship from ProductMaster for those SKUs only
     */
    public function getDailyData(Request $request)
    {
        try {
            // 1. Fetch ALL TemuDailyData first - show ALL records (no date filter)
            $allTemuData = TemuDailyData::orderBy('purchase_date', 'desc')
                ->orderBy('order_id', 'desc')
                ->get();
            
            Log::info('Temu data fetched', [
                'total_records' => $allTemuData->count(),
                'unique_skus_in_temu' => $allTemuData->pluck('contribution_sku')->unique()->count()
            ]);
            
            // 2. Get unique SKUs from TemuDailyData only
            $temuSkus = $allTemuData->pluck('contribution_sku')
                ->filter()
                ->unique()
                ->values()
                ->all();
            
            // 3. Fetch ProductMaster data ONLY for SKUs that exist in TemuDailyData
            $productMasters = ProductMaster::whereIn('sku', $temuSkus)
                ->get()
                ->keyBy('sku');
            
            // 4. Build result array - show only Temu data (exactly 2562 records, no parent rows)
            $result = [];

            // Process ALL Temu data directly (no parent grouping to avoid extra rows)
            foreach ($allTemuData as $item) {
                $sku = $item->contribution_sku;
                $pm = $productMasters[$sku] ?? null;
                
                // Get parent from ProductMaster if available
                $parent = $pm ? $pm->parent : '';
                
                // Extract LP and Temu Ship from ProductMaster Values (only if PM exists)
                $lp = 0;
                $temuShip = 0;
                
                if ($pm) {
                    $values = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }

                    // Get Temu Ship
                    $temuShip = isset($values["temu_ship"]) 
                        ? floatval($values["temu_ship"]) 
                        : (isset($pm->temu_ship) ? floatval($pm->temu_ship) : 0);
                }
                
                // Get base price and quantity from Temu data
                $basePrice = $item->base_price_total !== null ? (float)$item->base_price_total : 0;
                $quantity = $item->quantity_purchased !== null ? (int)$item->quantity_purchased : 0;
                $total = $basePrice * $quantity;
                
                // Calculate FB Price (if total < 27, add 2.99)
                $fbPrice = $total < 27 ? ($basePrice + 2.99) : $basePrice;
                
                // Calculate PFT = (FB Prc * 0.87 - LP - Temu Ship) * Quantity
                $pft = ($fbPrice * 0.87 - $lp - $temuShip) * $quantity;

                $row = [
                    'Parent' => $parent,
                    'contribution_sku' => $item->contribution_sku ?? '',
                    'order_id' => $item->order_id ?? '',
                    'product_name_by_customer_order' => $item->product_name_by_customer_order ?? '',
                    'variation' => $item->variation ?? '',
                    'quantity_purchased' => $quantity,
                    'quantity_shipped' => (int)($item->quantity_shipped ?? 0),
                    'quantity_to_ship' => (int)($item->quantity_to_ship ?? 0),
                    'base_price_total' => $basePrice,
                    'fb_price' => round($fbPrice, 2),
                    'lp' => $lp,
                    'temu_ship' => $temuShip,
                    'pft' => round($pft, 2),
                    'order_status' => $item->order_status ?? '',
                    'fulfillment_mode' => $item->fulfillment_mode ?? '',
                    'tracking_number' => $item->tracking_number ?? '',
                    'carrier' => $item->carrier ?? '',
                    'created_at' => $item->purchase_date ? $item->purchase_date->format('Y-m-d H:i:s') : null,
                ];

                $result[] = $row;
            }

            Log::info('Temu daily data fetched (exact count match)', [
                'result_count' => count($result),
                'temu_data_count' => $allTemuData->count(),
                'unique_temu_skus' => count($temuSkus),
                'product_master_matches' => $productMasters->count(),
                'match_check' => count($result) === $allTemuData->count() ? 'MATCH' : 'MISMATCH'
            ]);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching Temu daily data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show Temu Tabulator View
     */
    public function temuTabulatorView()
    {
        return view('market-places.temu_tabulator_view');
    }

    /**
     * Save Temu column visibility preferences
     */
    public function saveTemuColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_tabulator_column_visibility_{$userId}";
            
            $visibility = $request->input('visibility', []);
            
            // Store in cache (matching eBay pattern)
            Cache::put($key, $visibility, now()->addDays(365));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    /**
     * Get Temu column visibility preferences
     */
    public function getTemuColumnVisibility()
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $key = "temu_tabulator_column_visibility_{$userId}";
            
            $visibility = Cache::get($key, []);
            return response()->json($visibility);
        } catch (\Exception $e) {
            Log::error('Error getting Temu column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }
}
