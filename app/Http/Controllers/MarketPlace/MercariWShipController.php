<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\MercariWShipDataView;
use App\Models\MercariDailyData;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MercariWShipController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

     public function overallMercariWship(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = ChannelMaster::where('channel', 'Mercari w ship')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.mercariwshipAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function mercariWshipPricingCVR(Request $request)
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

    /**
     * Extract potential SKUs from item title and match with ProductMaster
     * Returns the matched ProductMaster SKU or null
     * Examples: 
     * - "Speaker Stand Tripod... SS ECO 2PK BLK WoB" -> matches "ECO 2PK BLK WoB"
     * - "... GRack 3N1" -> matches "GRack 3N1" or "GRACK 3N1"
     * - "... 20R WoB" -> matches "20R WoB"
     */
    private function extractAndMatchSkuFromTitle($itemTitle, $productMastersBySku)
    {
        if (empty($itemTitle)) {
            return null;
        }

        $variations = [];
        
        // Pattern 1: Extract last sequence (highest priority)
        // Match last sequence that contains letters/numbers/dashes/spaces (3+ chars)
        // This handles: "ECO 2PK BLK WoB", "SS ECO 2PK BLK WoB", "GRack 3N1", "20R WoB"
        if (preg_match('/\b([A-Za-z0-9\s\-]{3,})\s*$/', $itemTitle, $matches)) {
            $lastPart = trim($matches[1]);
            
            // Try the full last part first (preserve original case)
            $variations[] = $lastPart; // "GRack 3N1", "20R WoB", "SS ECO 2PK BLK WoB"
            $variations[] = strtoupper($lastPart); // "GRACK 3N1", "20R WOB"
            $variations[] = str_replace(' ', '', $lastPart); // "GRack3N1"
            $variations[] = str_replace(' ', '', strtoupper($lastPart)); // "GRACK3N1"
            $variations[] = str_replace([' ', '-'], '', strtoupper($lastPart)); // "GRACK3N1"
            
            // If last part has multiple words, try without the first word (in case of prefix like "SS")
            $words = explode(' ', $lastPart);
            if (count($words) > 1) {
                // Remove first word if it's short (likely a prefix like "SS")
                if (strlen($words[0]) <= 3) {
                    $withoutPrefix = trim(implode(' ', array_slice($words, 1)));
                    if (strlen($withoutPrefix) >= 3) {
                        $variations[] = $withoutPrefix; // "ECO 2PK BLK WoB", "3N1"
                        $variations[] = strtoupper($withoutPrefix); // "ECO 2PK BLK WOB"
                        $variations[] = str_replace(' ', '', $withoutPrefix);
                        $variations[] = str_replace(' ', '', strtoupper($withoutPrefix));
                        $variations[] = str_replace([' ', '-'], '', strtoupper($withoutPrefix));
                    }
                }
            }
        }

        // Pattern 2: Extract mixed case patterns (e.g., "GRack 3N1", "20R WoB")
        // Match sequences with letters (mixed case) and numbers
        if (preg_match_all('/\b([A-Za-z]{1,}[a-z]*\s*[A-Z0-9]{1,}(?:\s+[A-Za-z0-9]+){0,3})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed; // "GRack 3N1"
                    $variations[] = strtoupper($trimmed); // "GRACK 3N1"
                    $variations[] = str_replace(' ', '', $trimmed); // "GRack3N1"
                    $variations[] = str_replace(' ', '', strtoupper($trimmed)); // "GRACK3N1"
                }
            }
        }

        // Pattern 3: Extract patterns starting with numbers (e.g., "20R WoB")
        // Match sequences starting with digits followed by letters and words
        if (preg_match_all('/\b(\d+[A-Za-z]+\s+[A-Za-z0-9]+(?:\s+[A-Za-z0-9]+){0,2})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 3) {
                    $variations[] = $trimmed; // "20R WoB"
                    $variations[] = strtoupper($trimmed); // "20R WOB"
                    $variations[] = str_replace(' ', '', $trimmed); // "20RWoB"
                    $variations[] = str_replace(' ', '', strtoupper($trimmed)); // "20RWOB"
                }
            }
        }

        // Pattern 4: Extract product code patterns (e.g., "SS ECO 2PK BLK", "HW 405 WH")
        // Match sequences like "XX XXX" or "XXX XX" (2+ uppercase letters followed by alphanumeric)
        if (preg_match_all('/\b([A-Z]{2,}\s+[A-Z0-9]{1,}(?:\s+[A-Z0-9]+){0,4})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                if (strlen($trimmed) >= 4) {
                    $variations[] = $trimmed; // "SS ECO 2PK BLK"
                    $variations[] = str_replace(' ', '', $trimmed); // "SSECO2PKBLK"
                }
            }
        }

        // Pattern 5: Extract all alphanumeric sequences (potential SKUs)
        if (preg_match_all('/\b([A-Za-z0-9\-]{4,})\b/', $itemTitle, $allMatches)) {
            foreach ($allMatches[1] as $match) {
                $trimmed = trim($match);
                $variations[] = $trimmed;
                $variations[] = strtoupper($trimmed);
            }
        }

        // Remove duplicates and empty values
        $variations = array_values(array_unique(array_filter($variations)));

        // Try to match each variation with ProductMaster SKUs
        foreach ($variations as $variation) {
            $normalized = strtoupper(trim($variation));
            $normalizedNoSpaces = str_replace([' ', '-', '_'], '', $normalized);

            // Try exact match first
            if (isset($productMastersBySku[$normalized])) {
                return $productMastersBySku[$normalized]->sku;
            }
            if (isset($productMastersBySku[$normalizedNoSpaces])) {
                return $productMastersBySku[$normalizedNoSpaces]->sku;
            }

            // Try partial match with ProductMaster SKUs
            foreach ($productMastersBySku as $pmSku => $pm) {
                $pmSkuUpper = strtoupper(trim($pmSku));
                $pmSkuNoSpaces = str_replace([' ', '-', '_'], '', $pmSkuUpper);
                
                // Exact match
                if ($normalized === $pmSkuUpper || $normalizedNoSpaces === $pmSkuNoSpaces) {
                    return $pm->sku;
                }
                
                // Partial match (if variation contains or is contained in SKU)
                if (strlen($normalized) >= 3) {
                    if (stripos($pmSkuUpper, $normalized) !== false || 
                        stripos($normalized, $pmSkuUpper) !== false ||
                        stripos($pmSkuNoSpaces, $normalizedNoSpaces) !== false ||
                        stripos($normalizedNoSpaces, $pmSkuNoSpaces) !== false) {
                        return $pm->sku;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get L30 order counts from MercariDailyData for all SKUs
     * Returns an array mapping SKU to order count and matched SKUs
     */
    private function getL30OrderCounts($skus, $productMastersBySku)
    {
        // Get last 30 days date
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Get all Mercari daily data from last 30 days where buyer_shipping_fee is NOT null, excluding cancelled orders
        $mercariOrders = MercariDailyData::whereNotNull('buyer_shipping_fee')
            ->where('sold_date', '>=', $thirtyDaysAgo)
            ->whereNull('canceled_date')
            ->where(function($query) {
                $query->whereNull('order_status')
                      ->orWhere('order_status', 'not like', '%cancelled%')
                      ->orWhere('order_status', 'not like', '%canceled%');
            })
            ->get();

        // Create SKU lookup map (normalized versions)
        $skuLookup = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $skuUpper);
            $skuLookup[$skuUpper] = $sku;
            $skuLookup[$skuNoSpaces] = $sku;
        }

        // Initialize order counts
        $orderCounts = array_fill_keys($skus, 0);
        // Store matched SKUs for each ProductMaster SKU
        $matchedSkus = [];

        // Process each order
        foreach ($mercariOrders as $order) {
            if (empty($order->item_title)) {
                continue;
            }

            // Extract and match SKU from title with ProductMaster
            $matchedSku = $this->extractAndMatchSkuFromTitle($order->item_title, $productMastersBySku);

            // Increment count for matched SKU and store the matched SKU
            if ($matchedSku && isset($orderCounts[$matchedSku])) {
                $orderCounts[$matchedSku]++;
                // Store the exact matched ProductMaster SKU
                $matchedSkus[$matchedSku] = $matchedSku;
            }
        }

        return [
            'orderCounts' => $orderCounts,
            'matchedSkus' => $matchedSkus
        ];
    }

    public function getViewMercariWshipData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('MercariWShip', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'MercariWShip')->first();
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
        $walmartDataViews = MercariWShipDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = [];
        $listedValues = [];
        $liveValues = [];

        foreach ($walmartDataViews as $sku => $dataView) {
            $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
            $nrValues[$sku] = $value['NR'] ?? false;
            $listedValues[$sku] = isset($value['Listed']) ? (int) $value['Listed'] : false;
            $liveValues[$sku] = isset($value['Live']) ? (int) $value['Live'] : false;
        }

        // Create ProductMaster lookup map for SKU matching
        $productMastersBySku = ProductMaster::all()->mapWithKeys(function($pm) {
            $sku = strtoupper(trim($pm->sku));
            $skuNoSpaces = str_replace([' ', '-', '_'], '', $sku);
            return [
                $sku => $pm,
                $skuNoSpaces => $pm,
            ];
        });

        // Get L30 order counts from MercariDailyData for all SKUs at once
        $mercariData = $this->getL30OrderCounts($skus, $productMastersBySku);
        $mercariOrderCounts = $mercariData['orderCounts'];
        $matchedSkus = $mercariData['matchedSkus'];

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
                // Use Mercari order count if available, otherwise use Shopify quantity
                $processedItem['L30'] = isset($mercariOrderCounts[$sku]) && $mercariOrderCounts[$sku] > 0 
                    ? $mercariOrderCounts[$sku] 
                    : ($shopifyItem->quantity ?? 0);
            } else {
                $processedItem['INV'] = 0;
                // Use Mercari order count if available
                $processedItem['L30'] = $mercariOrderCounts[$sku] ?? 0;
            }

            // Add matched SKU from Mercari orders (if found), otherwise use ProductMaster SKU
            $processedItem['Mercari_SKU'] = isset($matchedSkus[$sku]) ? $matchedSkus[$sku] : $sku;

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

    public function updateAllMercariWshipSkus(Request $request)
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
                ['marketplace' => 'MercariWShip'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('MercariWShip', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'MercariWShip',
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

        $dataView = MercariWShipDataView::firstOrNew(['sku' => $sku]);
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
        $product = MercariWShipDataView::firstOrCreate(
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

    public function importMercariWshipAnalytics(Request $request)
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
                MercariWShipDataView::updateOrCreate(
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

    public function exportMercariWshipAnalytics()
    {
        $mercariWShipData = MercariWShipDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($mercariWShipData as $data) {
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
        $fileName = 'MercariWShip_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'MercariWShip_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
