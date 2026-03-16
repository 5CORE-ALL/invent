<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\WalmartDataView;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\SheinDataView;
use App\Models\SheinDailyData;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class SheinController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function overallShein(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Shein')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.sheinAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function sheinPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.walmartPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewSheinData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Shein', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Shein')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from walmartDataView
        $walmartDataViews = SheinDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];

        foreach ($walmartDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? false;
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
            $processedItem['NR'] = $nrValues[$sku] ?? false;
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

    public function updateAllSheinSkus(Request $request)
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
                ['marketplace' => 'Shein'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('Shein', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Wayfair',
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

        // Flatten properly
        $nrValue = is_array($nr) && isset($nr['NR']) ? $nr['NR'] : $nr;

        $dataView = SheinDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        // Save correctly
        $value['NR'] = $nrValue;

        $dataView->value = $value;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => $dataView
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
        $product = SheinDataView::firstOrCreate(
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

    public function importSheinAnalytics(Request $request)
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
                SheinDataView::updateOrCreate(
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

    public function exportSheinAnalytics()
    {
        $sheinData = SheinDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($sheinData as $data) {
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
        $fileName = 'Shein_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Shein_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload Shein daily data file in chunks
     */
    public function uploadDailyDataChunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv,txt',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);

            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('shein_upload_'));

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
                
                // Truncate the table on first chunk
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                SheinDailyData::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                
                Log::info('Shein daily data table truncated before import');
            }

            // Load and process the spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Skip first two rows (headers)
            unset($rows[0]); // First header row
            unset($rows[1]); // Second header row with actual column names

            $totalRows = count($rows);
            $chunkSize = ceil($totalRows / $totalChunks);
            $startRow = $chunk * $chunkSize;
            $endRow = min(($chunk + 1) * $chunkSize, $totalRows);

            // Process only this chunk's rows
            $chunkRows = array_slice($rows, $startRow, $endRow - $startRow, true);
            
            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($chunkRows as $index => $row) {
                    if (empty($row[1])) { // Skip if order_number is empty
                        $skipped++;
                        continue;
                    }

                    // Map row data to database columns
                    $insertData = [
                        'order_type' => isset($row[0]) && $row[0] !== '' ? trim($row[0]) : null,
                        'order_number' => isset($row[1]) && $row[1] !== '' ? trim($row[1]) : null,
                        'exchange_order' => isset($row[2]) && $row[2] !== '' ? trim($row[2]) : null,
                        'order_status' => isset($row[3]) && $row[3] !== '' ? trim($row[3]) : null,
                        'shipment_mode' => isset($row[4]) && $row[4] !== '' ? trim($row[4]) : null,
                        'urged_or_not' => isset($row[5]) && $row[5] !== '' ? trim($row[5]) : null,
                        'is_it_lost' => isset($row[6]) && $row[6] !== '' ? trim($row[6]) : null,
                        'whether_to_stay' => isset($row[7]) && $row[7] !== '' ? trim($row[7]) : null,
                        'order_issue' => isset($row[8]) && $row[8] !== '' ? trim($row[8]) : null,
                        'product_name' => isset($row[9]) && $row[9] !== '' ? trim($row[9]) : null,
                        'product_description' => isset($row[10]) && $row[10] !== '' ? trim($row[10]) : null,
                        'specification' => isset($row[11]) && $row[11] !== '' ? trim($row[11]) : null,
                        'seller_sku' => isset($row[12]) && $row[12] !== '' ? trim($row[12]) : null,
                        'shein_sku' => isset($row[13]) && $row[13] !== '' ? trim($row[13]) : null,
                        'skc' => isset($row[14]) && $row[14] !== '' ? trim($row[14]) : null,
                        'item_id' => isset($row[15]) && $row[15] !== '' ? trim($row[15]) : null,
                        'product_status' => isset($row[16]) && $row[16] !== '' ? trim($row[16]) : null,
                        'inventory_id' => isset($row[17]) && $row[17] !== '' ? trim($row[17]) : null,
                        'exchange_id' => isset($row[18]) && $row[18] !== '' ? trim($row[18]) : null,
                        'reason_for_replacement' => isset($row[19]) && $row[19] !== '' ? trim($row[19]) : null,
                        'product_id_to_be_exchanged' => isset($row[20]) && $row[20] !== '' ? trim($row[20]) : null,
                        'locked_or_not' => isset($row[21]) && $row[21] !== '' ? trim($row[21]) : null,
                        'order_processed_on' => isset($row[22]) ? $this->parseDate($row[22]) : null,
                        'collection_deadline' => isset($row[23]) ? $this->parseDate($row[23]) : null,
                        'delivery_deadline' => isset($row[24]) ? $this->parseDate($row[24]) : null,
                        'delivery_time' => isset($row[25]) ? $this->parseDate($row[25]) : null,
                        'tracking_number' => isset($row[26]) && $row[26] !== '' ? trim($row[26]) : null,
                        'sellers_package' => isset($row[27]) && $row[27] !== '' ? trim($row[27]) : null,
                        'seller_currency' => isset($row[28]) && $row[28] !== '' ? trim($row[28]) : null,
                        'product_price' => isset($row[29]) ? $this->sanitizePrice($row[29]) : null,
                        'coupon_discount' => isset($row[30]) ? $this->sanitizePrice($row[30]) : null,
                        'store_campaign_discount' => isset($row[31]) ? $this->sanitizePrice($row[31]) : null,
                        'commission' => isset($row[32]) ? $this->sanitizePrice($row[32]) : null,
                        'estimated_merchandise_revenue' => isset($row[33]) ? $this->sanitizePrice($row[33]) : null,
                        'consumption_tax' => isset($row[34]) ? $this->sanitizePrice($row[34]) : null,
                        'province' => isset($row[35]) && $row[35] !== '' ? trim($row[35]) : null,
                        'city' => isset($row[36]) && $row[36] !== '' ? trim($row[36]) : null,
                        'quantity' => 1, // Default quantity is 1
                    ];

                    SheinDailyData::create($insertData);
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
            Log::error('Error uploading Shein daily data chunk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sanitize price values
     */
    private function sanitizePrice($value)
    {
        if (empty($value) || $value === '?') {
            return null;
        }

        // Remove currency symbols, commas, and whitespace
        $cleaned = preg_replace('/[USD$,\s]/', '', $value);
        
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }

    /**
     * Parse date string to Carbon instance
     */
    private function parseDate($dateString)
    {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }

        try {
            // Handle Excel numeric dates
            if (is_numeric($dateString)) {
                $baseDate = Carbon::create(1899, 12, 30);
                return $baseDate->addDays((int)$dateString);
            }

            // Try common date formats
            $formats = [
                'Y-F-d H:i',       // 2025-December-10 07:31
                'Y-M-d H:i',       // 2025-Dec-10 07:31
                'm/d/Y H:i',
                'd/m/Y H:i',
                'Y-m-d H:i:s',
                'Y-m-d',
                'm/d/Y',
                'd/m/Y',
            ];

            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, trim($dateString));
                    if ($parsed) {
                        return $parsed;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Try general parsing as last resort
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateString}");
            return null;
        }
    }

    /**
     * Get daily data for Shein tabulator view
     */
    public function getDailyData(Request $request)
    {
        try {
            // Get all Shein daily data
            $data = SheinDailyData::orderBy('order_processed_on', 'desc')->get();
            
            // Get unique SKUs
            $skus = $data->pluck('seller_sku')->unique()->filter()->values()->toArray();
            
            // Fetch ProductMaster data for all SKUs
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');
            
            // Enhance data with LP and Ship from ProductMaster
            $data = $data->map(function($item) use ($productMasters) {
                $sku = $item->seller_sku;
                
                // Fetch from ProductMaster
                if ($sku && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) 
                        ? $pm->Values 
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
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
                    $item->lp = $lp;
                    
                    // Get Ship
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($pm->ship) ? floatval($pm->ship) : 0);
                    $item->ship = $ship;
                } else {
                    $item->lp = 0;
                    $item->ship = 0;
                }
                
                // Commission from CSV is already stored in item->commission (for display only)
                // PFT calculation uses 0.89 multiplier in frontend
                
                return $item;
            });
            
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error fetching Shein daily data: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show Shein tabulator view
     */
    public function sheinTabulatorView()
    {
        return view('market-places.shein_tabulator_view');
    }

    /**
     * Save column visibility preferences
     */
    public function saveSheinColumnVisibility(Request $request)
    {
        try {
            $visibility = $request->input('visibility', []);
            $userId = auth()->id() ?? 'guest';
            
            cache()->put("shein_column_visibility_{$userId}", $visibility, now()->addYear());
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get column visibility preferences
     */
    public function getSheinColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = cache()->get("shein_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }
}
