<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\AliexpressDataView;
use App\Models\AliexpressDailyData;
use App\Models\AliexpressPricingPrice;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class AliexpressController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function overallAliexpress(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Aliexpress')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.aliexpress_analytics', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }


    public function getViewAliexpressData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Aliexpress', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Aliexpress')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch NR values for these SKUs from AliexpressDataView
        $aliexpressDataViews = AliexpressDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];

        foreach ($aliexpressDataViews as $sku => $dataView) {
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

        $dataView = AliexpressDataView::firstOrNew(['sku' => $sku]);
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
        $product = AliexpressDataView::firstOrCreate(
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

    public function importAliexpressAnalytics(Request $request)
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
                AliexpressDataView::updateOrCreate(
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

    public function exportAliexpressAnalytics()
    {
        $aliexpressData = AliexpressDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($aliexpressData as $data) {
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
        $fileName = 'Aliexpress_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Aliexpress_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload Aliexpress daily data file in chunks
     */
    public function uploadDailyDataChunk(Request $request)
    {
        try {
            $file = $request->file('file');
            $chunk = $request->input('chunk', 0);
            $totalChunks = $request->input('totalChunks', 1);
            
            if (!$file) {
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
            }

            // Load the spreadsheet
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'File is empty'], 400);
            }

            // Get headers (first row)
            $headers = array_shift($rows);
            
            // Truncate table on first chunk
            if ($chunk == 0) {
                AliexpressDailyData::truncate();
                Log::info('Aliexpress daily data table truncated');
            }

            $imported = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    // Combine headers with row data
                    $rowData = array_combine($headers, $row);
                    
                    // Extract SKU and Quantity from "SKU Code" column
                    // Format: "WF 8"-890 1PC * 1" -> SKU: "WF 8"-890 1PC", Quantity: 1
                    $skuCodeRaw = $rowData['SKU Code'] ?? '';
                    $sku = '';
                    $quantity = 1;
                    
                    if (!empty($skuCodeRaw) && strpos($skuCodeRaw, '*') !== false) {
                        $parts = explode('*', $skuCodeRaw);
                        $sku = trim($parts[0]);
                        $quantity = isset($parts[1]) ? (int)trim($parts[1]) : 1;
                    } else {
                        $sku = trim($skuCodeRaw);
                    }

                    // Prepare data for insertion
                    $data = [
                        'order_id' => $rowData['Order ID'] ?? null,
                        'order_status' => $rowData['Order Status'] ?? null,
                        'owner' => $rowData['Owner'] ?? null,
                        'buyer_name' => $rowData['Buyer Name'] ?? null,
                        'order_date' => $this->parseDate($rowData['Order Date'] ?? null),
                        'payment_time' => $this->parseDate($rowData['Payment time'] ?? null),
                        'payment_method' => $rowData['Payment method'] ?? null,
                        'supply_price' => $this->sanitizePrice($rowData['Supply Price'] ?? null),
                        'product_total' => $this->sanitizePrice($rowData['Product Total'] ?? null),
                        'shipping_cost' => $this->sanitizePrice($rowData['Shipping Cost'] ?? null),
                        'estimated_vat' => $this->sanitizePrice($rowData['Estimated VAT'] ?? null),
                        'platform_collects' => $rowData['Whether the platform collects and pays for itself'] ?? null,
                        'order_amount' => $this->sanitizePrice($rowData['Order amount'] ?? null),
                        'ddp_tariff' => $this->sanitizePrice($rowData['DDP tariff'] ?? null),
                        'store_promotion' => $this->sanitizePrice($rowData['Store Promotion'] ?? null),
                        'store_direct_discount' => $this->sanitizePrice($rowData['Store Direct Discount'] ?? null),
                        'platform_coupon' => $this->sanitizePrice($rowData['Platform Coupon'] ?? null),
                        'item_id' => $rowData['Item ID'] ?? null,
                        'product_information' => $rowData['Product Information'] ?? null,
                        'ean_code' => $rowData['EANcode'] ?? null,
                        'sku_code' => $sku,
                        'quantity' => $quantity,
                        'order_note' => $rowData['Order Note'] ?? null,
                        'complete_shipping_address' => $rowData['Complete shipping address'] ?? null,
                        'receiver_name' => $rowData['Receiver Name'] ?? null,
                        'buyer_country' => $rowData['Buyer Country'] ?? null,
                        'state_province' => $rowData['State/Province'] ?? null,
                        'city' => $rowData['City'] ?? null,
                        'detailed_address' => $rowData['Detailed address'] ?? null,
                        'zip_code' => $rowData['Zip Code'] ?? null,
                        'national_address' => $rowData['National address (used only in SA)'] ?? null,
                        'email' => $rowData['Email'] ?? null,
                        'phone' => $rowData['Phone '] ?? null, // Note the space in original
                        'mobile' => $rowData['Mobile'] ?? null,
                        'tax_number' => $rowData['Tax number'] ?? null,
                        'shipping_method' => $rowData['Shipping Method'] ?? null,
                        'shipping_deadline' => $this->parseDate($rowData['Shipping Deadline'] ?? null),
                        'tracking_number' => $rowData['Tracking number'] ?? null,
                        'shipping_time' => $this->parseDate($rowData['Shipping Time'] ?? null),
                        'buyer_confirmation_time' => $this->parseDate($rowData['Buyer Confirmation Time'] ?? null),
                        'order_type' => $rowData['Order type'] ?? null,
                    ];

                    AliexpressDailyData::create($data);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    Log::error("Error importing Aliexpress row " . ($index + 2) . ": " . $e->getMessage());
                }
            }

            $isLastChunk = ($chunk + 1) >= $totalChunks;
            
            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk uploaded. Imported: $imported records" . ($errors ? ", Errors: " . count($errors) : ""),
                'imported' => $imported,
                'errors' => $errors,
                'isLastChunk' => $isLastChunk
            ]);

        } catch (\Exception $e) {
            Log::error('Aliexpress upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
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

        // Remove currency symbols (US $), commas, and whitespace
        $cleaned = preg_replace('/US\s*\$|[$,\s]/', '', $value);
        
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
            // Try parsing various date formats
            // Format: "12/10/2025 11:35"
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}/', $dateString)) {
                return Carbon::createFromFormat('m/d/Y H:i', $dateString);
            }
            
            // Fallback to general parsing
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: $dateString - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get daily data for Aliexpress tabulator view
     */
    public function getDailyData(Request $request)
    {
        try {
            // Fetch all data from aliexpress_daily_data
            $aliexpressData = AliexpressDailyData::orderBy('order_date', 'desc')->get();

            Log::info('Aliexpress daily data fetched', [
                'total_records' => $aliexpressData->count()
            ]);

            // Get unique SKUs from the data (filter out null/empty values)
            $skus = $aliexpressData->pluck('sku_code')
                ->filter(function($sku) {
                    return !empty($sku);
                })
                ->unique()
                ->values()
                ->toArray();

            Log::info('Unique SKUs found', [
                'unique_skus_count' => count($skus)
            ]);

            // Fetch LP and Ship from ProductMaster for these SKUs
            $productMasters = [];
            if (!empty($skus)) {
                $productMasters = ProductMaster::whereIn('sku', $skus)
                    ->get()
                    ->keyBy('sku');
            }

            // Get marketplace percentage from ChannelMaster (like other channels)
            $marketplaceData = ChannelMaster::where('channel', 'Aliexpress')->first();
            $percentage = $marketplaceData ? ($marketplaceData->channel_percentage ?? 100) : 100;
            $margin = $percentage / 100; // Convert % to fraction

            $data = [];
            foreach ($aliexpressData as $item) {
                $sku = $item->sku_code;
                $lp = 0;
                $ship = 0;

                // Get LP and Ship from ProductMaster (using normal 'ship' field, not 'temu_ship')
                // Pattern matches Temu extraction logic
                if (!empty($sku) && isset($productMasters[$sku])) {
                    $productMaster = $productMasters[$sku];
                    $values = is_array($productMaster->Values) 
                        ? $productMaster->Values 
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    
                    // Get LP (similar to Temu extraction)
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($productMaster->lp)) {
                        $lp = floatval($productMaster->lp);
                    }
                    
                    // Get Ship (normal ship field for Aliexpress, not temu_ship)
                    $ship = isset($values["ship"]) 
                        ? floatval($values["ship"]) 
                        : (isset($productMaster->ship) ? floatval($productMaster->ship) : 0);
                }

                // Calculate unit price (price per item) - same as eBay
                $quantity = floatval($item->quantity) ?: 1;
                $productTotal = floatval($item->product_total) ?: 0;
                $unitPrice = $quantity > 0 ? $productTotal / $quantity : 0;

                // Calculate PFT Each (per unit) = (unit_price * 0.89) - lp - ship (same as eBay)
                $pftEach = ($unitPrice * $margin) - $lp - $ship;

                // Calculate PFT Each % = (pft_each / unit_price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // Calculate Total PFT = pft_each * quantity
                $tPft = $pftEach * $quantity;

                // COGS = LP * quantity
                $cogs = $lp * $quantity;

                // ROI = (Total PFT / COGS) * 100
                $roi = $cogs > 0 ? ($tPft / $cogs) * 100 : 0;

                $data[] = [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_status' => $item->order_status,
                    'buyer_name' => $item->buyer_name,
                    'order_date' => $item->order_date ? $item->order_date->format('Y-m-d H:i') : null,
                    'payment_time' => $item->payment_time ? $item->payment_time->format('Y-m-d H:i') : null,
                    'payment_method' => $item->payment_method,
                    'supply_price' => $item->supply_price,
                    'product_total' => $item->product_total,
                    'unit_price' => round($unitPrice, 2), // Price per unit (like eBay)
                    'shipping_cost' => $item->shipping_cost,
                    'order_amount' => $item->order_amount,
                    'platform_coupon' => $item->platform_coupon,
                    'sku_code' => $item->sku_code ?? '',
                    'quantity' => $item->quantity ?? 1,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    'pft' => round($tPft, 2),
                    'roi' => round($roi, 2),
                    'margin' => (float)$margin, // Send margin to frontend for calculation
                    'buyer_country' => $item->buyer_country,
                    'state_province' => $item->state_province,
                    'city' => $item->city,
                    'tracking_number' => $item->tracking_number,
                    'shipping_time' => $item->shipping_time ? $item->shipping_time->format('Y-m-d H:i') : null,
                ];
            }

            Log::info('Aliexpress daily data processed', [
                'processed_records' => count($data)
            ]);
            
            // Return JSON response with proper headers
            return response()->json($data)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error fetching Aliexpress daily data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show Aliexpress tabulator view
     */
    public function aliexpressTabulatorView()
    {
        return view('market-places.aliexpress_tabulator_view');
    }

    /**
     * Show separate AliExpress pricing page.
     */
    public function aliexpressPricingView()
    {
        return view('market-places.aliexpress_pricing_view');
    }

    /**
     * Download sample file for AliExpress pricing upload.
     */
    public function downloadPricingPriceSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['sku', 'price'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['SKU-001', '19.99'],
            ['SKU-002', '24.50'],
            ['SKU-003', '13.25'],
        ], null, 'A2');

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(14);

        $fileName = 'aliexpress_pricing_sample.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload AliExpress price sheet.
     * Accepts the native AliExpress seller-export TSV/TXT format
     * (tab-separated, multiple instruction rows at the top) as well as
     * plain CSV / Excel files with headers "sku" and "price".
     *
     * AliExpress TSV column mapping (0-indexed):
     *   col 3 → *Retail price (USD)
     *   col 5 → SKU code
     */
    public function uploadPricingPriceSheet(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file',
        ]);

        try {
            $file = $request->file('price_file');
            $path = $file->getPathName();

            // ── Always try AliExpress TSV detection first ──────────────
            if ($this->isAliexpressTsv($path)) {
                $rows = $this->parseAliexpressTsv($path);
            } else {
                // Load as spreadsheet (xlsx / xls / csv)
                $spreadsheet = IOFactory::load($path);
                $raw         = $spreadsheet->getActiveSheet()->toArray();

                // ── Try AliExpress xlsx format first ───────────────────
                // Look for the header row containing "SKU code" and "Retail price"
                $aeHeaderIdx     = null;
                $aeIdIdx         = null;
                $aeNameIdx       = null;
                $aeSkuIdIdx      = null;
                $aePriceIdx      = null;
                $aeStockIdx      = null;
                $aeSkuIdx        = null;
                $aeSalesAttrIdx  = null;

                foreach ($raw as $i => $row) {
                    $hasSkuCode = false;
                    $hasRetail  = false;
                    foreach ($row as $j => $cell) {
                        $lower = strtolower(trim((string) $cell));
                        if ($lower === 'sku code')                           { $aeSkuIdx      = $j; $hasSkuCode = true; }
                        if ($lower === 'id' && $aeIdIdx === null)            { $aeIdIdx       = $j; }
                        if (str_contains($lower, 'product name'))           { $aeNameIdx     = $j; }
                        if ($lower === 'skuid' || $lower === 'sku id')      { $aeSkuIdIdx    = $j; }
                        if (str_contains($lower, 'retail price'))           { $aePriceIdx    = $j; $hasRetail  = true; }
                        if (str_contains($lower, 'seller warehouse stock')) { $aeStockIdx    = $j; }
                        if (str_contains($lower, 'sales attributes'))       { $aeSalesAttrIdx= $j; }
                    }
                    if ($hasSkuCode && $hasRetail) {
                        $aeHeaderIdx = $i;
                        break;
                    }
                    // Reset partial matches for next row
                    $aeIdIdx = $aeNameIdx = $aeSkuIdIdx = $aePriceIdx = $aeStockIdx = $aeSkuIdx = $aeSalesAttrIdx = null;
                }

                if ($aeHeaderIdx !== null) {
                    // Parse as AliExpress xlsx – skip non-data rows (col 0 must be numeric)
                    $rows = [];
                    foreach (array_slice($raw, $aeHeaderIdx + 1) as $row) {
                        $firstCol = trim((string) ($row[0] ?? ''));
                        if (!is_numeric($firstCol) || $firstCol === '') continue;

                        $c   = static fn(int $i) => trim((string) ($row[$i] ?? ''));
                        $sku = $aeSkuIdx !== null ? $c($aeSkuIdx) : '';
                        if ($sku === '') continue;

                        $rows[] = [
                            'sku'              => $sku,
                            'product_id'       => $aeIdIdx        !== null ? $c($aeIdIdx)        : null,
                            'product_name'     => $aeNameIdx      !== null ? $c($aeNameIdx)      : null,
                            'sku_id'           => $aeSkuIdIdx     !== null ? $c($aeSkuIdIdx)     : null,
                            'price'            => $aePriceIdx     !== null
                                                    ? (float) preg_replace('/[^0-9.\-]/', '', $c($aePriceIdx))
                                                    : 0,
                            'ae_stock'         => $aeStockIdx     !== null ? (int) $c($aeStockIdx)     : 0,
                            'sales_attributes' => $aeSalesAttrIdx !== null ? $c($aeSalesAttrIdx) : null,
                        ];
                    }
                } else {
                    // ── Plain sku/price spreadsheet ───────────────────────
                    $headerIdx  = null;
                    $skuIndex   = null;
                    $priceIndex = null;
                    foreach ($raw as $i => $row) {
                        $normalised = array_map(static fn($v) => strtolower(trim((string) $v)), $row);
                        $si = array_search('sku', $normalised, true);
                        $pi = array_search('price', $normalised, true);
                        if ($si !== false && $pi !== false) {
                            $headerIdx  = $i;
                            $skuIndex   = $si;
                            $priceIndex = $pi;
                            break;
                        }
                    }
                    if ($headerIdx === null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Column headers not recognised. Upload the AliExpress seller-export file (any format) or a spreadsheet with "sku" and "price" columns.',
                        ], 422);
                    }
                    $rows = [];
                    foreach (array_slice($raw, $headerIdx + 1) as $row) {
                        $sku   = trim((string) ($row[$skuIndex]   ?? ''));
                        $price = (float) preg_replace('/[^0-9.\-]/', '', (string) ($row[$priceIndex] ?? ''));
                        if ($sku !== '') {
                            $rows[] = ['sku' => $sku, 'price' => $price];
                        }
                    }
                }
            }

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'No data rows found in the file.'], 422);
            }

            $updated = 0;
            foreach ($rows as $row) {
                $sku   = trim((string) ($row['sku']   ?? ''));
                $price = max(0, (float) ($row['price'] ?? 0));
                if ($sku === '') continue;

                AliexpressPricingPrice::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'price'            => $price,
                        'product_id'       => $row['product_id']       ?? null,
                        'product_name'     => $row['product_name']     ?? null,
                        'sku_id'           => $row['sku_id']           ?? null,
                        'ae_stock'         => (int) ($row['ae_stock']  ?? 0),
                        'sales_attributes' => $row['sales_attributes'] ?? null,
                    ]
                );
                $updated++;
            }

            return response()->json([
                'success' => true,
                'message' => "Price sheet uploaded successfully. {$updated} SKU(s) updated.",
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            Log::error('AliExpress pricing upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Price upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normalise raw file bytes to UTF-8, stripping any BOM.
     * AliExpress exports are sometimes UTF-16 LE/BE or UTF-8 with BOM.
     */
    private function toUtf8(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) return '';

        // UTF-16 LE  (FF FE)
        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }
        // UTF-16 BE  (FE FF)
        elseif (str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }
        // UTF-8 BOM  (EF BB BF)
        elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return $raw;
    }

    /**
     * Detect whether a file is in the native AliExpress seller-export TSV format.
     * Looks for a row that has BOTH "sku code" and "retail price" as cell values
     * within the first 30 lines (handles embedded-newline description rows).
     */
    private function isAliexpressTsv(string $path): bool
    {
        $content = $this->toUtf8($path);
        $lines   = preg_split('/\r\n|\r|\n/', $content);

        foreach (array_slice($lines, 0, 30) as $line) {
            $lower = strtolower($line);
            if (str_contains($lower, 'sku code') && str_contains($lower, 'retail price')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse the native AliExpress seller-export TSV file.
     *
     * Sheet columns (0-indexed):
     *   0 → id              (Product ID)
     *   1 → *Product name
     *   2 → skuId
     *   3 → *Retail price (USD)
     *   4 → *Seller Warehouse Stock
     *   5 → SKU code
     *   6 → Sales attributes
     *
     * Returns array of full row maps keyed by field name.
     */
    private function parseAliexpressTsv(string $path): array
    {
        $content = $this->toUtf8($path);
        $lines   = preg_split('/\r\n|\r|\n/', $content);

        $idIndex        = null;
        $nameIndex      = null;
        $skuIdIndex     = null;
        $priceIndex     = null;
        $stockIndex     = null;
        $skuIndex       = null;
        $salesAttrIndex = null;
        $rows           = [];

        foreach ($lines as $line) {
            $cols = explode("\t", $line);

            // ── Locate the actual header row ──────────────────────────────
            if ($skuIndex === null) {
                $hasSkuCode   = false;
                $hasRetailPrc = false;
                foreach ($cols as $idx => $cell) {
                    // Strip BOM remnants, quotes, non-breaking spaces
                    $clean = trim($cell, " \t\r\n\"\xc2\xa0\xef\xbb\xbf");
                    $lower = strtolower($clean);

                    if ($lower === 'sku code')                           { $skuIndex      = $idx; $hasSkuCode   = true; }
                    if ($lower === 'id' && $idIndex === null)            { $idIndex       = $idx; }
                    if (str_contains($lower, 'product name'))           { $nameIndex     = $idx; }
                    if ($lower === 'skuid' || $lower === 'sku id')      { $skuIdIndex    = $idx; }
                    if (str_contains($lower, 'retail price'))           { $priceIndex    = $idx; $hasRetailPrc = true; }
                    if (str_contains($lower, 'seller warehouse stock')) { $stockIndex    = $idx; }
                    if (str_contains($lower, 'sales attributes'))       { $salesAttrIndex= $idx; }
                }
                // Only mark as "header found" when BOTH key columns are on this row
                if (!$hasSkuCode || !$hasRetailPrc) {
                    $skuIndex = null; // reset – this was not the header row
                }
                continue;
            }

            // ── Skip instruction / description rows ───────────────────────
            // Real data rows always have a numeric product ID in column 0
            $firstCol = trim($cols[0] ?? '', " \t\r\n\"\xc2\xa0");
            if (!is_numeric($firstCol) || $firstCol === '') continue;

            $clean = static fn(int $i) => trim((string) ($cols[$i] ?? ''), " \t\r\n\"\xc2\xa0");

            $sku = $skuIndex !== null ? $clean($skuIndex) : '';
            if ($sku === '') continue;

            $rows[] = [
                'sku'              => $sku,
                'product_id'       => $idIndex        !== null ? $clean($idIndex)        : null,
                'product_name'     => $nameIndex      !== null ? $clean($nameIndex)      : null,
                'sku_id'           => $skuIdIndex     !== null ? $clean($skuIdIndex)     : null,
                'price'            => $priceIndex     !== null
                                        ? (float) preg_replace('/[^0-9.\-]/', '', $clean($priceIndex))
                                        : 0,
                'ae_stock'         => $stockIndex     !== null ? (int) $clean($stockIndex)    : 0,
                'sales_attributes' => $salesAttrIndex !== null ? $clean($salesAttrIndex) : null,
            ];
        }

        return $rows;
    }

    /**
     * Get aggregated SKU pricing data for AliExpress pricing page.
     */
    public function getPricingData(Request $request)
    {
        try {
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed'];

            $salesAgg = AliexpressDailyData::query()
                ->selectRaw('sku_code, SUM(COALESCE(quantity, 0)) as al30, SUM(COALESCE(order_amount, 0)) as sales, SUM(COALESCE(product_total, 0)) as product_total_sum')
                ->whereNotNull('sku_code')
                ->where('sku_code', '!=', '')
                ->where(function ($query) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $status) {
                        $query->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ['%' . $status . '%']);
                    }
                })
                ->groupBy('sku_code')
                ->get();

            $normalizeSku = static function ($value) {
                return strtoupper(trim((string) $value));
            };

            $salesBySku = $salesAgg->keyBy(fn($row) => $normalizeSku($row->sku_code));

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            // Fetch ALL uploaded prices first (no SKU filter yet)
            // so their SKUs can be included in the master list
            $uploadedPriceBySku = AliexpressPricingPrice::all()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            // Union of: sales SKUs + product master SKUs + uploaded pricing SKUs
            $allNormalizedSkus = collect(array_merge(
                $salesBySku->keys()->all(),
                $productMastersBySku->keys()->all(),
                $uploadedPriceBySku->keys()->all()
            ))->unique()->values();

            $viewMetaBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $viewMetaBySku = AliexpressDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn($row) => $normalizeSku($row->sku));
            }

            $shopifyInvBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $shopifyInvBySku = ShopifySku::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn($row) => $normalizeSku($row->sku));
            }

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Aliexpress')
                ->orWhere('marketplace', 'AliExpress')
                ->first();
            $percentage = $marketplaceData ? ($marketplaceData->percentage ?? 100) : 100;
            $margin = ((float) $percentage) / 100;

            $rows = [];
            foreach ($allNormalizedSkus as $normalizedSku) {
                $sale = $salesBySku->get($normalizedSku);
                $productMaster = $productMastersBySku->get($normalizedSku);
                $metaRecord = $viewMetaBySku->get($normalizedSku);
                $meta = $metaRecord ? ($metaRecord->value ?? []) : [];

                $shopifyRow = $shopifyInvBySku->get($normalizedSku);
                $inv   = $shopifyRow ? (int)   ($shopifyRow->inv      ?? 0) : 0;
                $ovL30 = $shopifyRow ? (int)   ($shopifyRow->quantity ?? 0) : 0;

                $values = [];
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                }

                $lp   = isset($values['lp'])   ? (float) $values['lp']   : (isset($productMaster->lp)   ? (float) $productMaster->lp   : 0);
                // Use ae_ship first, then ship (mirrors TikTok's tt_ship → ship fallback)
                $ship = isset($values['ae_ship']) ? (float) $values['ae_ship']
                      : (isset($values['ship'])   ? (float) $values['ship']   : (isset($productMaster->ship) ? (float) $productMaster->ship : 0));

                $al30 = (float) ($sale->al30 ?? 0);
                $sales = (float) ($sale->sales ?? 0);
                $productTotalSum = (float) ($sale->product_total_sum ?? 0);
                $derivedUnitPrice = $al30 > 0 ? ($productTotalSum / $al30) : 0;

                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $priceRow      = $uploadedPriceBySku->get($normalizedSku);
                $uploadedPrice = $priceRow ? (float) $priceRow->price : 0;
                $aeStock       = $priceRow ? (int)   ($priceRow->ae_stock   ?? 0) : 0;
                $productName   = $priceRow ? ($priceRow->product_name ?? null) : null;
                $productId     = $priceRow ? ($priceRow->product_id   ?? null) : null;

                $price  = $uploadedPrice > 0 ? $uploadedPrice : $derivedUnitPrice;
                $profit = ($price * $margin) - $lp - $ship;
                $gpft   = $price > 0 ? ($profit / $price) * 100 : 0;
                $groi   = $lp    > 0 ? ($profit / $lp)    * 100 : 0;
                $sgpft  = $gpft;

                // Prefer original-case SKU: ProductMaster → sale → pricing table → normalized
                $displaySku = $productMaster->sku
                    ?? ($sale->sku_code
                        ?? ($priceRow->sku
                            ?? $normalizedSku));

                // Missing = not listed on AliExpress (not in pricing table) OR price is 0
                // Mirrors TikTok: Missing = M when SKU is not in tiktok_products
                $isMissing = !$priceRow || $price <= 0;

                // MAP logic – mirrors TikTok INV vs TT Stock comparison
                if ($isMissing) {
                    $mapValue = '';
                } elseif ($inv === $aeStock) {
                    $mapValue = 'Map';
                } elseif ($aeStock > $inv) {
                    $diff     = $aeStock - $inv;
                    $mapValue = "N Map|{$diff}";
                } else {
                    $diff     = $inv - $aeStock;
                    $mapValue = "Diff|{$diff}";
                }

                $rows[] = [
                    'sku'          => trim((string) $displaySku),
                    'price'        => round($price, 2),
                    'missing'      => $isMissing ? 'M' : '',
                    'map'          => $mapValue,
                    'gpft'         => round($gpft, 2),
                    'groi'         => round($groi, 2),
                    'profit'       => round($profit, 2),
                    'sales'        => round($sales, 2),
                    'al30'         => (int) round($al30),
                    'lp'           => round($lp, 2),
                    'ship'         => round($ship, 2),
                    'sprice'       => round($sprice, 2),
                    'sgpft'        => round($sgpft, 2),
                    'inv'          => $inv,
                    'ae_stock'     => $aeStock,
                    'ov_l30'       => $ovL30,
                    'dil_percent'  => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                    'product_name' => $productName,
                    'product_id'   => $productId,
                ];
            }

            usort($rows, static function ($a, $b) {
                $av = strtoupper(trim((string) ($a['sku'] ?? '')));
                $bv = strtoupper(trim((string) ($b['sku'] ?? '')));
                $aLetter = preg_match('/^[A-Z]/', $av) === 1;
                $bLetter = preg_match('/^[A-Z]/', $bv) === 1;

                if ($aLetter !== $bLetter) {
                    return $aLetter ? -1 : 1;
                }

                return strnatcasecmp($av, $bv);
            });

            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Error fetching AliExpress pricing data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch pricing data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save column visibility preferences
     */
    public function saveAliexpressColumnVisibility(Request $request)
    {
        try {
            $userId = auth()->id() ?? 'guest';
            $visibility = $request->input('visibility', []);
            
            cache()->put("aliexpress_column_visibility_{$userId}", $visibility, now()->addDays(30));
            
            return response()->json([
                'success' => true,
                'message' => 'Column visibility saved'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save preferences'
            ], 500);
        }
    }

    /**
     * Get column visibility preferences
     */
    public function getAliexpressColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';
        $visibility = cache()->get("aliexpress_column_visibility_{$userId}", []);
        
        return response()->json($visibility);
    }
}
