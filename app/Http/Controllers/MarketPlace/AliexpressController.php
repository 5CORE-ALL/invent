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
    /**
     * Insert AliExpress parent summary rows after each group of children — mirrors TikTok insertTikTokParentRows.
     */
    private function insertAeParentRows(array $rows): array
    {
        $result = [];
        $group  = [];
        $currentParent = null;

        foreach ($rows as $row) {
            $p = $row['parent'] ?? null;
            $p = ($p !== null && $p !== '') ? (string) $p : null;

            if ($p === null) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildAeParentRow($currentParent, $group);
                    $group = []; $currentParent = null;
                }
                $result[] = $row;
                continue;
            }

            if ($p !== $currentParent) {
                if (!empty($group)) {
                    foreach ($group as $r) $result[] = $r;
                    $result[] = $this->buildAeParentRow($currentParent, $group);
                    $group = [];
                }
                $currentParent = $p;
            }
            $group[] = $row;
        }

        if (!empty($group)) {
            foreach ($group as $r) $result[] = $r;
            $result[] = $this->buildAeParentRow($currentParent, $group);
        }

        return $result;
    }

    private function buildAeParentRow(string $parentName, array $childRows): array
    {
        $sumInv = $sumOvL30 = $sumAeStock = $sumAl30 = $sumSales = 0;
        $sumProfit = $sumLp = 0;

        foreach ($childRows as $r) {
            $sumInv     += (float) ($r['inv']      ?? 0);
            $sumOvL30   += (float) ($r['ov_l30']   ?? 0);
            $sumAeStock += (float) ($r['ae_stock']  ?? 0);
            $sumAl30    += (float) ($r['al30']      ?? 0);
            $sumSales   += (float) ($r['sales']     ?? 0);
            $sumLp      += (float) ($r['lp']        ?? 0);
            $al30        = (float) ($r['al30']      ?? 0);
            $profit      = (float) ($r['profit']    ?? 0);
            $sumProfit  += $al30 * $profit;
        }

        $dilPct  = $sumInv   > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0;
        $gpftPct = $sumSales > 0 ? round(($sumProfit  / $sumSales) * 100, 2) : 0;

        $key = 'PARENT ' . $parentName;
        return [
            'sku'         => $key,
            'parent'      => $key,
            'is_parent'   => true,
            'image'       => null,
            'price'       => '-',
            'missing'     => '-',
            'map'         => '-',
            'gpft'        => $gpftPct,
            'groi'        => '-',
            'profit'      => round($sumProfit, 2),
            'sales'       => round($sumSales, 2),
            'al30'        => (int) round($sumAl30),
            'lp'          => '-',
            'ship'        => '-',
            'sprice'      => '-',
            'sgpft'       => '-',
            'sroi'        => '-',
            'inv'         => (int) $sumInv,
            'ov_l30'      => (int) $sumOvL30,
            'ae_stock'    => (int) $sumAeStock,
            'dil_percent' => $dilPct,
        ];
    }

    /**
     * Detect if a file is an Excel binary (xlsx = ZIP magic bytes PK, xls = D0CF magic bytes).
     */
    private function isExcelFile(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if (!$handle) return false;
        $magic = fread($handle, 4);
        fclose($handle);
        // xlsx: ZIP (PK\x03\x04)   xls: OLE2 (D0 CF 11 E0)
        return str_starts_with($magic, "\x50\x4B\x03\x04")
            || str_starts_with($magic, "\xD0\xCF\x11\xE0");
    }

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
        $fileName = 'aliexpress_pricing_sample.csv';
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

    /**
     * Upload price sheet and store values in aliexpress_data_views.value.
     */
    public function uploadPricingPriceSheet(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file',
        ]);

        try {
            $file      = $request->file('price_file');
            $path      = $file->getPathName();
            $extension = strtolower($file->getClientOriginalExtension());

            // ── Excel files (xlsx / xls) → PhpSpreadsheet ──────────────
            if (in_array($extension, ['xlsx', 'xls'], true) || $this->isExcelFile($path)) {
                $spreadsheet = IOFactory::load($path);
                $sheetRows   = $spreadsheet->getActiveSheet()->toArray();

                // Normalise headers
                $headerRow = array_shift($sheetRows);
                $headers   = array_map(static fn($h) =>
                    strtolower(trim(preg_replace('/[^a-zA-Z0-9_ ]/', '', (string) $h))),
                    $headerRow
                );

                $skuIndex   = array_search('sku',   $headers, true);
                $priceIndex = array_search('price', $headers, true);
                $stockIndex = array_search('stock', $headers, true);

                if ($skuIndex === false || $priceIndex === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Columns not found. Found: [' . implode(', ', array_filter($headers)) . ']. Expected: sku, price, stock.',
                    ], 422);
                }

                $updated = 0;
                foreach ($sheetRows as $row) {
                    $sku = trim((string) ($row[$skuIndex] ?? ''));
                    if ($sku === '') continue;
                    $price   = (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$priceIndex] ?? '')));
                    $aeStock = $stockIndex !== false ? (int) trim((string) ($row[$stockIndex] ?? '0')) : 0;
                    AliexpressPricingPrice::updateOrCreate(
                        ['sku'   => $sku],
                        ['price' => max(0, $price), 'ae_stock' => max(0, $aeStock)]
                    );
                    $updated++;
                }
            } else {
                // ── CSV / TSV → fgetcsv ───────────────────────────────
                $handle = fopen($path, 'r');
                if (!$handle) {
                    return response()->json(['success' => false, 'message' => 'Cannot open uploaded file.'], 422);
                }

                // Strip UTF-8 BOM
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                // Detect delimiter from first line
                $firstLine = fgets($handle);
                rewind($handle);
                if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

                $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ",")) ? "\t" : ",";

                // Read header row
                $headerRow = fgetcsv($handle, 0, $delimiter);
                if (!$headerRow) {
                    fclose($handle);
                    return response()->json(['success' => false, 'message' => 'Price sheet is empty.'], 422);
                }

                $headers    = array_map(static fn($h) =>
                    strtolower(trim(preg_replace('/[^a-zA-Z0-9_ ]/', '', (string) $h))),
                    $headerRow
                );
                $skuIndex   = array_search('sku',   $headers, true);
                $priceIndex = array_search('price', $headers, true);
                $stockIndex = array_search('stock', $headers, true);

                if ($skuIndex === false || $priceIndex === false) {
                    fclose($handle);
                    return response()->json([
                        'success' => false,
                        'message' => 'Columns not found. Found: [' . implode(', ', array_filter($headers)) . ']. Expected: sku, price, stock.',
                    ], 422);
                }

                $updated = 0;
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if (!$row || count(array_filter($row, fn($v) => $v !== '' && $v !== null)) === 0) continue;
                    $sku = trim((string) ($row[$skuIndex] ?? ''));
                    if ($sku === '') continue;
                    $price   = (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$priceIndex] ?? '')));
                    $aeStock = $stockIndex !== false ? (int) trim((string) ($row[$stockIndex] ?? '0')) : 0;
                    AliexpressPricingPrice::updateOrCreate(
                        ['sku'   => $sku],
                        ['price' => max(0, $price), 'ae_stock' => max(0, $aeStock)]
                    );
                    $updated++;
                }
                fclose($handle);
            }

            return response()->json([
                'success' => true,
                'message' => "Price sheet uploaded successfully. {$updated} SKU rows updated.",
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
     * Get aggregated SKU pricing data for AliExpress pricing page.
     */
    public function getPricingData(Request $request)
    {
        try {
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed'];

            $salesAgg = AliexpressDailyData::query()
                ->selectRaw('sku_code, SUM(COALESCE(quantity, 0)) as al30, SUM(COALESCE(order_amount, 0)) as sales')
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
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            $allNormalizedSkus = collect(array_merge(
                $salesBySku->keys()->all(),
                $productMastersBySku->keys()->all()
            ))->unique()->values();

            $viewMetaBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $viewMetaBySku = AliexpressDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn($row) => $normalizeSku($row->sku));
            }

            // Fetch ALL uploaded prices (no SKU filter) so ae_stock is always correct
            $uploadedPriceBySku = AliexpressPricingPrice::all()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            // INV + OV L30 from shopify_skus
            $shopifyBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $shopifyBySku = ShopifySku::query()
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

                $values = [];
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                }

                $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($productMaster->ship) ? (float) $productMaster->ship : 0);

                $al30  = (float) ($sale->al30  ?? 0);
                $sales = (float) ($sale->sales ?? 0);

                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $priceRow      = $uploadedPriceBySku->get($normalizedSku);
                $uploadedPrice = $priceRow ? (float) $priceRow->price    : 0;
                $aeStock       = $priceRow ? (int)   ($priceRow->ae_stock ?? 0) : 0;

                // INV + OV L30 + image from shopify_skus
                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv       ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity  ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src       ?? null) : null;

                // Price ONLY from aliexpress_pricing_prices — no sales fallback
                $price  = $uploadedPrice;
                $profit = ($price * $margin) - $lp - $ship;
                $gpft = $price > 0 ? ($profit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profit / $lp) * 100 : 0;
                $sgpft = $gpft;

                $displaySku = $productMaster->sku ?? ($sale->sku_code ?? $normalizedSku);
                $isMissing  = !$productMaster || $price <= 0;

                // MAP: INV == AE Stock → "Map" | INV != AE Stock → "N Map|{diff}"
                if ($isMissing) {
                    $mapValue = '';
                } elseif ($inv === $aeStock) {
                    $mapValue = 'Map';
                } else {
                    $diff     = abs($inv - $aeStock);
                    $mapValue = "N Map|{$diff}";
                }

                // Calculate SPRICE derived values (same formulas as GPFT/GROI)
                $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi  = $lp    > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

                $rows[] = [
                    'sku'         => trim((string) $displaySku),
                    'parent'      => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent'   => false,
                    'image'       => $imageSrc,
                    'price'       => round($price, 2),
                    'missing'     => $isMissing ? 'M' : '',
                    'map'         => $mapValue,
                    'gpft'        => round($gpft, 2),
                    'groi'        => round($groi, 2),
                    'profit'      => round($profit, 2),
                    'sales'       => round($sales, 2),
                    'al30'        => (int) round($al30),
                    'lp'          => round($lp, 2),
                    'ship'        => round($ship, 2),
                    'sprice'      => round($sprice, 2),
                    'sgpft'       => round($sgpft, 2),
                    'sroi'        => round($sroi, 2),
                    '_margin'     => round($margin, 4),
                    'inv'         => $inv,
                    'ov_l30'      => $ovL30,
                    'ae_stock'    => $aeStock,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                ];
            }

            // Sort: group by parent (nulls last), children alphabetically within group
            usort($rows, static function ($a, $b) {
                $pa = (string) ($a['parent'] ?? '');
                $pb = (string) ($b['parent'] ?? '');
                if ($pa === '' && $pb === '') {
                    return strnatcasecmp($a['sku'], $b['sku']);
                }
                if ($pa === '') return 1;
                if ($pb === '') return -1;
                $cmp = strnatcasecmp($pa, $pb);
                return $cmp !== 0 ? $cmp : strnatcasecmp($a['sku'], $b['sku']);
            });

            // Insert parent summary rows after each group (mirrors TikTok insertTikTokParentRows)
            $rows = $this->insertAeParentRows($rows);

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
     * Save SPRICE (and calculated SGPFT / SROI) to aliexpress_data_views.value JSON.
     * Mirrors TikTok's saveSpriceUpdates — preserves existing JSON keys.
     */
    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Aliexpress')
                ->orWhere('marketplace', 'AliExpress')
                ->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 100)) : 100;
            $margin     = $percentage / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku    = $update['sku']    ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) continue;

                $sprice = (float) $sprice;

                // Get LP / Ship from ProductMaster
                $productMaster = ProductMaster::where('sku', $sku)->first();
                $lp = $ship = 0;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $lp   = isset($values['lp'])   ? (float) $values['lp']   : 0;
                    $ship = isset($values['ae_ship']) ? (float) $values['ae_ship']
                          : (isset($values['ship']) ? (float) $values['ship'] : 0);
                }

                // Calculate derived values (same formulas as TikTok)
                $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

                // Merge into existing JSON (preserve Listed, Live, etc.)
                $view   = AliexpressDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                        : (json_decode($view->value, true) ?: []);

                $stored['SPRICE'] = $sprice;
                $stored['SGPFT']  = $sgpft;
                $stored['SROI']   = $sroi;

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Exception $e) {
            Log::error('AliExpress SPRICE save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
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
