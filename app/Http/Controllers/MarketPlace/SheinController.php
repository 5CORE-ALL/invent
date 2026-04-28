<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\WalmartDataView;
use App\Models\ProductMaster;
use App\Models\SheinDataView;
use App\Models\SheinDailyData;
use App\Models\ShopifySku;
use App\Models\AmazonChannelSummary;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\Carbon;
class SheinController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Shein margin (0–100) from marketplace_percentages; default 100 when missing.
     */
    private function sheinMarketplaceMarginPercent(): float
    {
        $row = MarketplacePercentage::query()
            ->where('marketplace', 'Shein')
            ->first();

        if (! $row || $row->percentage === null || $row->percentage === '') {
            return 100.0;
        }

        return (float) $row->percentage;
    }

    /**
     * LP and ship from product_master.Values (keys lp, ship); optional model attributes as fallback.
     *
     * @return array{lp: float, ship: float}
     */
    private function lpAndShipFromProductMaster(?ProductMaster $pm): array
    {
        if (! $pm) {
            return ['lp' => 0.0, 'ship' => 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        if (isset($values['lp'])) {
            $lp = (float) $values['lp'];
        } else {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = 0.0;
        if (isset($values['ship'])) {
            $ship = (float) $values['ship'];
        } else {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'ship') {
                    $ship = (float) $v;
                    break;
                }
            }
        }
        if ($ship === 0.0 && isset($pm->ship)) {
            $ship = (float) $pm->ship;
        }

        return ['lp' => $lp, 'ship' => $ship];
    }

    /**
     * Key product_master rows by normalized SKU using a base Collection (not Eloquent\Collection) for safe key lookups.
     */
    private function productMasterByNormalizedSku(): SupportCollection
    {
        $pm = new ProductMaster;
        if (! Schema::hasTable($pm->getTable())) {
            return new SupportCollection;
        }

        return SupportCollection::make(
            ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->all()
        )->keyBy(static fn(ProductMaster $r) => strtoupper(trim((string) $r->sku)));
    }

    public function overallShein(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = $this->sheinMarketplaceMarginPercent();

        return view('market-places.sheinAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function getViewSheinData(Request $request)
    {
        $percentage = $this->sheinMarketplaceMarginPercent();
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

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

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Shein',
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

                    // Map columns to current Shein order export (0-based, row 2 = headers after group row).
                    // Template adds "Requested Shipping Time" before delivery dates; Referral Fees→commission; Sales Tax→consumption_tax.
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
                        'requested_shipping_time' => isset($row[24]) ? $this->parseDate($row[24]) : null,
                        'delivery_deadline' => isset($row[25]) ? $this->parseDate($row[25]) : null,
                        'delivery_time' => isset($row[26]) ? $this->parseDate($row[26]) : null,
                        'tracking_number' => isset($row[27]) && $row[27] !== '' ? trim($row[27]) : null,
                        'sellers_package' => isset($row[28]) && $row[28] !== '' ? trim($row[28]) : null,
                        'seller_currency' => isset($row[29]) && $row[29] !== '' ? trim($row[29]) : null,
                        'product_price' => isset($row[30]) ? $this->sanitizePrice($row[30]) : null,
                        'coupon_discount' => isset($row[31]) ? $this->sanitizePrice($row[31]) : null,
                        'store_campaign_discount' => isset($row[32]) ? $this->sanitizePrice($row[32]) : null,
                        'commission' => isset($row[33]) ? $this->sanitizePrice($row[33]) : null,
                        'estimated_merchandise_revenue' => isset($row[34]) ? $this->sanitizePrice($row[34]) : null,
                        'fulfillment_service_fee' => isset($row[35]) ? $this->sanitizePrice($row[35]) : null,
                        'storage_fee' => isset($row[36]) ? $this->sanitizePrice($row[36]) : null,
                        'consumption_tax' => isset($row[37]) ? $this->sanitizePrice($row[37]) : null,
                        'province' => isset($row[38]) && $row[38] !== '' ? trim($row[38]) : null,
                        'city' => isset($row[39]) && $row[39] !== '' ? trim($row[39]) : null,
                        'quantity' => 1,
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
            $marketplaceMarginDecimal = $this->sheinMarketplaceMarginPercent() / 100;

            // Get all Shein daily data
            $data = SheinDailyData::orderBy('order_processed_on', 'desc')->get();

            $normalizeSku = static fn($v) => strtoupper(trim((string) $v));
            $productMasters = $this->productMasterByNormalizedSku();

            $data = $data->map(function ($item) use ($productMasters, $normalizeSku) {
                $key = $item->seller_sku ? $normalizeSku($item->seller_sku) : '';
                $pm = $key !== '' ? $productMasters->get($key) : null;
                if (! $pm instanceof ProductMaster) {
                    $pm = null;
                }
                $resolved = $this->lpAndShipFromProductMaster($pm);
                $item->lp = $resolved['lp'];
                $item->ship = $resolved['ship'];

                return $item;
            });

            return response()->json([
                'data' => $data->values()->all(),
                'marketplace_margin_decimal' => $marketplaceMarginDecimal,
            ]);
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

    // =========================================================================
    // SHEIN PRICING PAGE  (mirrors AliExpress pricing page exactly)
    // =========================================================================

    public function sheinBadgeChartData(\Illuminate\Http\Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'avg_gpft');
            $days = max(0, (int) $request->input('days', 30));

            $validMetrics = [
                'total_pft', 'total_sales', 'avg_gpft', 'avg_roi',
                'total_al30', 'avg_dil', 'total_cogs', 'missing_count', 'map_count', 'nmap_count',
                'total_sku', 'zero_sold', 'more_sold',
            ];
            if (!in_array($metric, $validMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $query = AmazonChannelSummary::where('channel', 'shein')
                ->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }
            $rows = $query->get(['snapshot_date', 'summary_data']);

            $data = [];
            foreach ($rows as $row) {
                $sd    = is_array($row->summary_data)
                       ? $row->summary_data
                       : (json_decode($row->summary_data ?? '{}', true) ?: []);
                $value = (float) ($sd[$metric] ?? 0);
                $data[] = [
                    'date'  => optional($row->snapshot_date)->format('M d'),
                    'value' => $value,
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Shein badge chart data error: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    public function sheinPricingView()
    {
        return view('market-places.shein_pricing_view');
    }

    public function downloadSheinPricingSample()
    {
        $fileName = 'shein_pricing_sample.csv';
        $rows = [
            ['sku', 'price', 'stock'],
            ['SKU-001', '19.99', '10'],
            ['SKU-002', '24.50', '25'],
            ['SKU-003', '13.25', '0'],
        ];

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $handle = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        exit;
    }

    public function uploadSheinPriceSheet(Request $request)
    {
        $request->validate(['price_file' => 'required|file']);

        try {
            $file = $request->file('price_file');
            $path = $file->getPathName();

            $rows = [];

            // ── Detect file type ─────────────────────────────────────────
            if ($this->sheinIsExcelFile($path)) {
                // Excel (xlsx / xls)
                $spreadsheet = IOFactory::load($path);
                $raw         = $spreadsheet->getActiveSheet()->toArray();
                $headerRow   = array_shift($raw);
                $rows        = $this->parseSheinRows($headerRow, $raw, false);
            } else {
                // TSV / CSV – handle BOM, auto-detect delimiter
                $handle = fopen($path, 'r');
                $bom    = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);
                $firstLine = fgets($handle);
                rewind($handle);
                if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

                $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ",")) ? "\t" : ",";
                $headerRow = fgetcsv($handle, 0, $delimiter);
                if (!$headerRow) {
                    fclose($handle);
                    return response()->json(['success' => false, 'message' => 'Empty file.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $rawData = [];
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if ($row && count(array_filter($row, fn($v) => $v !== '' && $v !== null)) > 0) {
                        $rawData[] = $row;
                    }
                }
                fclose($handle);
                $rows = $this->parseSheinRows($headerRow, $rawData, true);
            }

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'No data rows found.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $updated = 0;
            foreach ($rows as $row) {
                \App\Models\SheinPricingPrice::updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'price'               => max(0, $row['price']),
                        'original_price'      => max(0, $row['original_price'] ?? 0),
                        'special_offer_price' => max(0, $row['special_offer_price']),
                        'shein_stock'         => max(0, $row['stock']),
                    ]
                );
                $updated++;
            }

            return response()->json(
                ['success' => true, 'message' => "{$updated} SKU(s) updated.", 'updated' => $updated],
                200,
                [],
                JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (\Throwable $e) {
            Log::error('Shein pricing upload failed: ' . $e->getMessage());
            $msg = $this->sanitizeUtf8String('Upload failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $msg], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Parse header + data rows from Shein native sheet OR simple sku/price/stock sheet.
     *
     * Shein native sheet columns (normalised lowercase, non-alnum stripped):
     *   sellersku                        → sku
     *   current inventory                → stock
     *   original price shein us usd      → price
     *   special offer price shein us usd → special_offer_price
     *
     * Simple sheet columns: sku, price, stock (+ optional special_offer_price)
     */
    private function parseSheinRows(array $headerRow, array $dataRows, bool $isCsv): array
    {
        // Normalise headers – keep letters/numbers/spaces only, then trim
        $headers = array_map(
            fn($h) => strtolower(trim(preg_replace('/[^a-z0-9 ]/i', ' ', (string) $h))),
            $headerRow
        );

        // ── Detect Shein native format (has "sellersku" column) ──────────
        $isNativeShein = in_array('sellersku', $headers, true)
                      || in_array('seller sku', $headers, true);

        if ($isNativeShein) {
            // CSV columns (Shein native export):
            //   sellerSKU                         → sku
            //   price                             → price        (the plain "price" column)
            //   Original Price(shein-us_USD)      → original_price
            //   Special Offer Price(shein-us_USD) → special_offer_price
            //   Current inventory                 → shein_stock
            $skuIdx          = null;
            $priceIdx        = null;   // exact "price" column
            $origPriceIdx    = null;   // Original Price(shein-us_USD)
            $spOfferIdx      = null;   // Special Offer Price(shein-us_USD)
            $stockIdx        = null;

            foreach ($headers as $i => $h) {
                if ($skuIdx       === null && (str_contains($h, 'sellersku') || $h === 'seller sku'))  $skuIdx       = $i;
                if ($stockIdx     === null && str_contains($h, 'current inventory'))                    $stockIdx     = $i;
                if ($spOfferIdx   === null && str_contains($h, 'special offer price'))                  $spOfferIdx   = $i;
                if ($origPriceIdx === null && str_contains($h, 'original price'))                       $origPriceIdx = $i;
                // Match plain "price" exactly — must come after the above so it doesn't grab "original price"
                if ($priceIdx     === null && trim($h) === 'price')                                     $priceIdx     = $i;
            }

            if ($skuIdx === null) {
                throw new \RuntimeException('sellerSKU column not found in Shein sheet.');
            }
        } else {
            // Generic sheet: supports standard (sku/price/stock) and marketplace exports (Offer SKU/Price/Quantity)
            $skuIdx          = null;
            $priceIdx        = null;
            $origPriceIdx    = null;
            $spOfferIdx      = null;
            $stockIdx        = null;

            foreach ($headers as $i => $h) {
                $h = trim($h);
                if ($skuIdx       === null && in_array($h, ['sku', 'offer sku', 'offer_sku', 'offersku'], true)) $skuIdx       = $i;
                if ($priceIdx     === null && $h === 'price')                                                     $priceIdx     = $i;
                if ($stockIdx     === null && in_array($h, ['stock', 'quantity'], true))                          $stockIdx     = $i;
                if ($origPriceIdx === null && in_array($h, ['original price', 'original_price'], true))           $origPriceIdx = $i;
                if ($spOfferIdx   === null && in_array($h, ['special offer price', 'special_offer_price', 'discount price'], true)) $spOfferIdx = $i;
            }

            if ($skuIdx === null || $priceIdx === null) {
                throw new \RuntimeException(
                    'Columns not found. Detected: [' . implode(', ', array_slice($headers, 0, 12)) . ']. ' .
                    'Expected "sku" and "price" columns.'
                );
            }
        }

        $rows = [];
        foreach ($dataRows as $row) {
            $sku = trim((string) ($row[$skuIdx] ?? ''));
            // Skip blank rows and repeated header rows
            if ($sku === '' || in_array(strtolower($sku), ['sellersku', 'seller sku', 'offer sku', 'sku'], true)) continue;

            $price      = $priceIdx     !== null ? (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$priceIdx]     ?? ''))) : 0;
            $origPrice  = $origPriceIdx !== null ? (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$origPriceIdx] ?? ''))) : 0;
            $spOffer    = $spOfferIdx   !== null ? (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$spOfferIdx]   ?? ''))) : 0;
            $stock      = $stockIdx     !== null ? (int) trim((string) ($row[$stockIdx] ?? '0')) : 0;

            // Simple sku/price/stock sheets have no "special offer" column — grid + margin math use special_offer_price.
            if ($spOffer <= 0 && $price > 0) {
                $spOffer = $price;
            }

            $rows[] = [
                'sku'                 => $this->sanitizeUtf8String($sku),
                'price'               => $price,
                'original_price'      => $origPrice,
                'special_offer_price' => $spOffer,
                'stock'               => $stock,
            ];
        }

        return $rows;
    }

    private function sheinIsExcelFile(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if (!$handle) return false;
        $magic  = fread($handle, 4);
        fclose($handle);
        return str_starts_with($magic, "\x50\x4B\x03\x04") || str_starts_with($magic, "\xD0\xCF\x11\xE0");
    }

    /**
     * Strip invalid UTF-8 from a string (legacy DB / CSV bytes mis-labeled as UTF-8).
     */
    private function sanitizeUtf8String(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

        return $clean !== false ? $clean : '';
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    private function sanitizeUtf8Recursive($data)
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $key = is_string($k) ? $this->sanitizeUtf8String($k) : $k;
                $out[$key] = $this->sanitizeUtf8Recursive($v);
            }

            return $out;
        }
        if (is_string($data)) {
            return $this->sanitizeUtf8String($data);
        }

        return $data;
    }

    public function getSheinPricingData(Request $request)
    {
        try {
            $normalizeSku = static fn($v) => strtoupper(trim((string) $v));

            // ── 1. All uploaded prices (base SKU list)
            $pricingRows  = \App\Models\SheinPricingPrice::all();
            $pricingBySku = $pricingRows->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 2. Product master → LP / Ship (Support Collection keyed by normalized SKU)
            $pmTable = (new ProductMaster)->getTable();
            $productMasterBySku = new SupportCollection();
            if (Schema::hasTable($pmTable)) {
                $productMasterBySku = SupportCollection::make(
                    ProductMaster::query()
                        ->whereNotNull('sku')->where('sku', '!=', '')
                        ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                        ->get()
                        ->all()
                )->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            // ── 3. Shein sales → al30 / sales  (from shein_daily_data.seller_sku)
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $salesAgg = SheinDailyData::query()
                ->selectRaw('seller_sku, SUM(COALESCE(quantity, 0)) AS al30')
                ->whereNotNull('seller_sku')->where('seller_sku', '!=', '')
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->groupBy('seller_sku')
                ->get()
                ->keyBy(fn($r) => $normalizeSku($r->seller_sku));

            // ── 4. Shopify → INV / OV L30
            $allNormalizedSkus = collect(array_merge(
                $pricingBySku->keys()->all(),
                $productMasterBySku->keys()->all()
            ))->unique()->values();

            $shopifyBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $shopifyBySku = ShopifySku::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            // ── 5. SPRICE from shein_data_views
            $viewMetaBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $viewMetaBySku = SheinDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            // ── 6. Margin from marketplace_percentages
            $percentage = $this->sheinMarketplaceMarginPercent();
            $margin = $percentage / 100;

            // ── 7. Build rows
            $rows = [];
            foreach ($allNormalizedSkus as $normalizedSku) {
                $priceRow   = $pricingBySku->get($normalizedSku);
                $price      = $priceRow ? (float) $priceRow->price              : 0;
                $origPrice  = $priceRow ? (float) ($priceRow->original_price      ?? 0) : 0;
                $spOffer    = $priceRow ? (float) ($priceRow->special_offer_price  ?? 0) : 0;
                $sheinStock = $priceRow ? (int)   ($priceRow->shein_stock          ?? 0) : 0;

                $productMaster = $productMasterBySku->get($normalizedSku);
                if (! $productMaster instanceof ProductMaster) {
                    $productMaster = null;
                }
                $resolved = $this->lpAndShipFromProductMaster($productMaster);
                $lp   = $resolved['lp'];
                $ship = $resolved['ship'];

                $sale  = $salesAgg->get($normalizedSku);
                $al30  = $sale ? (float) $sale->al30 : 0;
                // Use theoretical sales (al30 × special_offer_price) — consistent with profit calc
                // Same pattern as TikTok: TT L30 × TT Price
                $sales = $al30 * $spOffer;

                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv      ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src      ?? null) : null;

                $metaRecord = $viewMetaBySku->get($normalizedSku);
                $meta       = $metaRecord ? ($metaRecord->value ?? []) : [];
                $sprice     = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;

                // Use special_offer_price only for all calculations
                $calcPrice  = $spOffer;
                $profit = ($calcPrice * $margin) - $lp - $ship;
                $gpft   = $calcPrice > 0 ? ($profit / $calcPrice) * 100 : 0;
                $groi   = $lp        > 0 ? ($profit / $lp)         * 100 : 0;
                $sgpft  = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi   = ($sprice > 0 && $lp > 0) ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;

                $displaySku = $productMaster?->sku ?? ($priceRow->sku ?? $normalizedSku);
                // Missing only when special_offer_price is 0 or no row exists
                $isMissing  = !$priceRow || $spOffer <= 0;

                // MAP: |INV − Shein stock| ≤ 3 → Map (same tolerance as other pricing pages)
                if ($isMissing) {
                    $mapValue = '';
                } else {
                    $adiff = abs($inv - $sheinStock);
                    if ($adiff <= 3) {
                        $mapValue = 'Map';
                    } else {
                        $mapValue = 'N Map|' . $adiff;
                    }
                }

                $rows[] = [
                    'sku'          => trim((string) $displaySku),
                    'parent'       => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent'    => false,
                    'image'        => $imageSrc,
                    'missing'      => $isMissing ? 'M' : '',
                    'map'          => $mapValue,
                    'gpft'         => round($gpft,  2),
                    'groi'         => round($groi,  2),
                    'profit'       => round($profit, 2),
                    'sales'        => round($sales,  2),
                    'al30'         => (int) round($al30),
                    'lp'           => round($lp,   2),
                    'ship'         => round($ship,  2),
                    'sprice'       => round($sprice, 2),
                    'sgpft'        => round($sgpft, 2),
                    'sroi'         => round($sroi,  2),
                    '_margin'      => round($margin, 4),
                    'inv'          => $inv,
                    'shein_stock'      => $sheinStock,
                    'original_price'   => round($origPrice, 2),
                    'special_offer'    => round($spOffer,   2),
                    'calc_price'       => round($calcPrice, 2),
                    'ov_l30'       => $ovL30,
                    'dil_percent'  => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                ];
            }

            // Sort by parent groups then by SKU
            usort($rows, static function ($a, $b) {
                $pa = (string) ($a['parent'] ?? '');
                $pb = (string) ($b['parent'] ?? '');
                if ($pa === '' && $pb === '') return strnatcasecmp($a['sku'], $b['sku']);
                if ($pa === '') return 1;
                if ($pb === '') return -1;
                $cmp = strnatcasecmp($pa, $pb);
                return $cmp !== 0 ? $cmp : strnatcasecmp($a['sku'], $b['sku']);
            });

            $rows = $this->insertSheinParentRows($rows);
            $rows = $this->sanitizeUtf8Recursive($rows);

            $this->saveSheinPricingSnapshot($rows);

            $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE;
            if (defined('JSON_UNESCAPED_UNICODE')) {
                $jsonFlags |= JSON_UNESCAPED_UNICODE;
            }

            return response()->json($rows, 200, [], $jsonFlags);
        } catch (\Exception $e) {
            Log::error('Shein pricing data error: ' . $e->getMessage());
            $msg = $this->sanitizeUtf8String($e->getMessage());

            return response()->json(['error' => $msg], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    private function insertSheinParentRows(array $rows): array
    {
        $result = []; $group = []; $currentParent = null;
        foreach ($rows as $row) {
            $p = $row['parent'] ?? null;
            $p = ($p !== null && $p !== '') ? (string) $p : null;
            if ($p === null) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildSheinParentRow($currentParent, $group);
                    $group = []; $currentParent = null;
                }
                $result[] = $row;
                continue;
            }
            if ($p !== $currentParent) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildSheinParentRow($currentParent, $group);
                    $group = [];
                }
                $currentParent = $p;
            }
            $group[] = $row;
        }
        if (!empty($group)) {
            foreach ($group as $r) $result[] = $r;
            $result[] = $this->buildSheinParentRow($currentParent, $group);
        }
        return $result;
    }

    /**
     * N Map badge logic — same as shein_pricing_view.js aeSheinStrictNMapFromMap.
     */
    private function sheinStrictNMapFromMapValue(string $mapVal): bool
    {
        $mapVal = trim($mapVal);
        if ($mapVal === '' || ! str_starts_with($mapVal, 'N Map|')) {
            return false;
        }
        $rest = trim(substr($mapVal, strlen('N Map|')));
        if ($rest === '' || ! is_numeric($rest)) {
            return false;
        }

        return abs((float) $rest) > 3;
    }

    /**
     * Persist daily summary for badge charts (matches pricing page updateSummary on child SKUs).
     */
    private function saveSheinPricingSnapshot(array $rows): void
    {
        try {
            $today = now()->toDateString();
            $children = collect($rows)->filter(fn ($r) => empty($r['is_parent']));
            if ($children->isEmpty()) {
                return;
            }

            $totalSales = 0.0;
            $totalPft = 0.0;
            $totalCogs = 0.0;
            $gpftSum = 0.0;
            $gpftCount = 0;
            $roiSum = 0.0;
            $roiCount = 0;
            $totalAl30 = 0.0;
            $zeroSold = 0;
            $moreSold = 0;
            $dilSum = 0.0;
            $dilCount = 0;
            $missingCount = 0;
            $mapCount = 0;
            $nmapCount = 0;

            foreach ($children as $row) {
                $isMissing = strtoupper(trim((string) ($row['missing'] ?? ''))) === 'M';
                $al30 = (float) ($row['al30'] ?? 0);
                $inv = (float) ($row['inv'] ?? 0);
                $ovL30 = (float) ($row['ov_l30'] ?? 0);
                $profit = (float) ($row['profit'] ?? 0);
                $lp = (float) ($row['lp'] ?? 0);

                $totalCogs += $lp * $al30;
                $totalAl30 += $al30;
                if ($al30 === 0.0) {
                    $zeroSold++;
                } else {
                    $moreSold++;
                }
                if ($inv > 0.0) {
                    $dilSum += ($ovL30 / $inv) * 100;
                    $dilCount++;
                }
                if ($isMissing) {
                    $missingCount++;
                }

                $mapVal = trim((string) ($row['map'] ?? ''));
                if (! $isMissing && $mapVal === 'Map') {
                    $mapCount++;
                } elseif ($this->sheinStrictNMapFromMapValue($mapVal)) {
                    $nmapCount++;
                }

                if (! $isMissing) {
                    $totalSales += (float) ($row['sales'] ?? 0);
                    $totalPft += $al30 * $profit;

                    $gpft = isset($row['gpft']) ? (float) $row['gpft'] : null;
                    if ($gpft !== null && is_finite($gpft)) {
                        $gpftSum += $gpft;
                        $gpftCount++;
                    }
                    $groi = isset($row['groi']) ? (float) $row['groi'] : null;
                    if ($groi !== null && is_finite($groi)) {
                        $roiSum += $groi;
                        $roiCount++;
                    }
                }
            }

            $totalSku = $children->count();
            $avgGpft = $gpftCount > 0 ? $gpftSum / $gpftCount : 0.0;
            $avgDil = $dilCount > 0 ? $dilSum / $dilCount : 0.0;
            $avgRoi = $roiCount > 0 ? $roiSum / $roiCount : 0.0;

            $summaryData = [
                'total_sku' => $totalSku,
                'total_sales' => round($totalSales, 2),
                'total_pft' => round($totalPft, 2),
                'total_cogs' => round($totalCogs, 2),
                'total_al30' => round($totalAl30, 2),
                'avg_gpft' => round($avgGpft, 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round($avgRoi, 2),
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $nmapCount,
                'zero_sold' => $zeroSold,
                'more_sold' => $moreSold,
                'calculated_at' => now()->toDateTimeString(),
            ];

            AmazonChannelSummary::updateOrCreate(
                ['channel' => 'shein', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved Shein pricing snapshot']
            );
        } catch (\Exception $e) {
            Log::error('Shein daily snapshot save failed: '.$e->getMessage());
        }
    }

    private function buildSheinParentRow(string $parentName, array $childRows): array
    {
        $sumInv = $sumOvL30 = $sumSheinStock = $sumAl30 = $sumSales = $sumProfit = 0;
        foreach ($childRows as $r) {
            $sumInv        += (float) ($r['inv']         ?? 0);
            $sumOvL30      += (float) ($r['ov_l30']       ?? 0);
            $sumSheinStock += (float) ($r['shein_stock']  ?? 0);
            $sumAl30       += (float) ($r['al30']         ?? 0);
            $sumSales      += (float) ($r['sales']        ?? 0);
            $sumProfit     += (float) ($r['al30'] ?? 0) * (float) ($r['profit'] ?? 0);
        }
        $key = 'PARENT ' . $parentName;
        return [
            'sku'         => $key,  'parent' => $key,  'is_parent' => true,
            'image'       => null,  'price'  => '-',   'missing'   => '-',
            'map'         => '-',   'gpft'   => $sumSales > 0 ? round(($sumProfit / $sumSales) * 100, 2) : 0,
            'groi'        => '-',   'profit' => round($sumProfit, 2),
            'sales'       => round($sumSales, 2),       'al30'      => (int) round($sumAl30),
            'lp'          => '-',   'ship'   => '-',   'sprice'    => '-',
            'sgpft'       => '-',   'sroi'   => '-',   '_margin'   => '-',
            'inv'         => (int) $sumInv,  'shein_stock' => (int) $sumSheinStock,
            'ov_l30'      => (int) $sumOvL30,
            'dil_percent' => $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0,
        ];
    }

    public function saveSheinSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }
            $margin = $this->sheinMarketplaceMarginPercent() / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku    = $update['sku']    ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) continue;
                $sprice = (float) $sprice;

                $n  = strtoupper(trim((string) $sku));
                $pm = Schema::hasTable((new ProductMaster)->getTable())
                    ? ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$n])->first()
                    : null;
                $resolved = $this->lpAndShipFromProductMaster($pm instanceof ProductMaster ? $pm : null);
                $lp   = $resolved['lp'];
                $ship = $resolved['ship'];

                $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

                $view   = SheinDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);
                $stored['SPRICE'] = $sprice;
                $stored['SGPFT']  = $sgpft;
                $stored['SROI']   = $sroi;
                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }
            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Exception $e) {
            Log::error('Shein SPRICE save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
