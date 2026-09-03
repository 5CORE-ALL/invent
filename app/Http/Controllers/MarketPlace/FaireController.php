<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\FaireDataView;
use App\Models\MarketplacePercentage;
use App\Models\FaireDailyData;
use App\Models\FaireMetric;
use App\Models\FaireListingStatus;
use App\Models\FaireProductSheet;
use App\Models\AmazonChannelSummary;
use App\Services\ChannelPromoPricingService;
use App\Services\FaireApiService;
use App\Services\MarketplaceManager\FaireLinkMapSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductMaster;
use App\Models\AmazonDataView;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class FaireController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

     public function overallFaire(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Faire')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.faireAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    public function fairePricingCVR(Request $request)
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

    public function getViewFaireData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('Faire', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Fetch NR values for these SKUs from walmartDataView
        $walmartDataViews = FaireDataView::whereIn('sku', $skus)->get()->keyBy('sku');
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

    public function updateAllFaireSkus(Request $request)
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
                ['marketplace' => 'Faire'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('Faire', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Faire',
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


    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        // Flatten properly
        $nrValue = is_array($nr) && isset($nr['NR']) ? $nr['NR'] : $nr;

        $dataView = FaireDataView::firstOrNew(['sku' => $sku]);
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
        $product = FaireDataView::firstOrCreate(
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

    public function importFaireAnalytics(Request $request)
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
                FaireDataView::updateOrCreate(
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

    public function exportFaireAnalytics()
    {
        $faireData = FaireDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($faireData as $data) {
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
        $fileName = 'Faire_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Faire_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload Faire daily data file in chunks
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

            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'File is empty'], 400);
            }

            $headers = array_shift($rows);
            $normalizedHeaders = array_map(function ($header) {
                return strtolower(trim((string) $header));
            }, $headers);

            if ($chunk == 0) {
                FaireDailyData::truncate();
                Log::info('Faire daily data table truncated');
            }

            $imported = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                try {
                    if (empty(array_filter($row, function ($value) {
                        return $value !== null && trim((string) $value) !== '';
                    }))) {
                        continue;
                    }

                    $rowData = [];
                    foreach ($normalizedHeaders as $i => $key) {
                        $rowData[$key] = $row[$i] ?? null;
                    }

                    $data = [
                        'order_date' => $this->parseFaireDate($rowData['order date'] ?? null),
                        'order_number' => $rowData['order number'] ?? null,
                        'purchase_order_number' => $rowData['purchase order number'] ?? null,
                        'retailer_name' => $rowData['retailer name'] ?? null,
                        'address_1' => $rowData['address 1'] ?? null,
                        'address_2' => $rowData['address 2'] ?? null,
                        'city' => $rowData['city'] ?? null,
                        'state' => $rowData['state'] ?? null,
                        'zip_code' => $rowData['zip code'] ?? null,
                        'country' => $rowData['country'] ?? null,
                        'product_name' => $rowData['product name'] ?? null,
                        'option_name' => $rowData['option name'] ?? null,
                        'sku' => $rowData['sku'] ?? null,
                        'gtin' => $rowData['gtin'] ?? null,
                        'status' => $rowData['status'] ?? null,
                        'quantity' => (int) ($rowData['quantity'] ?? 0),
                        'wholesale_price' => $this->sanitizeFairePrice($rowData['wholesale price'] ?? null),
                        'retail_price' => $this->sanitizeFairePrice($rowData['retail price'] ?? null),
                        'ship_date' => $this->parseFaireDate($rowData['ship date'] ?? null),
                        'scheduled_order_date' => $this->parseFaireDate($rowData['scheduled order date'] ?? null),
                        'notes' => $rowData['notes'] ?? null,
                    ];

                    FaireDailyData::create($data);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    Log::error("Error importing Faire row " . ($index + 2) . ": " . $e->getMessage());
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
            Log::error('Faire upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public static function applyFaireShopifyOrderFilter($query): void
    {
        $query->where('source_name', 'faire')
            ->orWhere('source_name', 'LIKE', '%faire%')
            ->orWhere('tags', 'LIKE', '%Faire%');
    }

    /** Last 30 Pacific days — same window as /faire-tabulator. */
    public static function faireShopifyL30Start(): Carbon
    {
        return Carbon::now('America/Los_Angeles')->subDays(30)->startOfDay();
    }

    /**
     * Per-SKU AL30 qty + sales from shopify_raw_orders (Faire source).
     * Replaces faire_daily_data Excel dumps so pricing / CVR move with live orders.
     */
    public static function queryFaireShopifyL30SalesBySku()
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return collect();
        }

        return DB::table('shopify_raw_orders')
            ->where('order_date', '>=', self::faireShopifyL30Start())
            ->where(fn ($q) => self::applyFaireShopifyOrderFilter($q))
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('quantity', '>', 0)
            ->selectRaw('sku, SUM(COALESCE(quantity, 0)) as al30, SUM(COALESCE(price, 0) * COALESCE(quantity, 0)) as sales')
            ->groupBy('sku')
            ->get();
    }

    /**
     * Get daily data for Faire tabulator view
     */
    public function getDailyData(Request $request)
    {
        try {
            // Source: shopify_raw_orders on the default (inventory_db) connection. Faire orders
            // are identified the same way the Shopify Orders page identifies them:
            // source_name='faire' OR tags contain "Faire".
            // Window: last 30 days (Pacific time) to match the L30 view used elsewhere.
            $thirtyDaysAgo = \Carbon\Carbon::now('America/Los_Angeles')->subDays(30)->startOfDay();
            $rows = DB::table('shopify_raw_orders')
                ->where('order_date', '>=', $thirtyDaysAgo)
                ->where(fn ($q) => self::applyFaireShopifyOrderFilter($q))
                ->orderBy('order_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $skus = $rows->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = collect();
            if (!empty($skus)) {
                $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');
            }

            // Keep-rate from marketplace_percentages (Faire), not hardcoded. Stored as a whole
            // number (e.g. 70 = 70%), same convention as the eBay daily-sales page.
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            $keepRate = ($marketplaceData ? (float) $marketplaceData->percentage : 100) / 100;

            $mapped = $rows->map(function ($item) use ($productMasters, $keepRate) {
                $sku = $item->sku;
                $lp = 0.0;

                if (!empty($sku) && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                    if (is_array($values)) {
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
                }

                // Shopify stores the actual selling price on `price`; no separate wholesale/retail
                // split exists for Faire-via-Shopify orders, so use it directly.
                $price    = (float) ($item->price ?? 0);
                $quantity = (float) ($item->quantity ?? 0);

                // PFT each = (price × keep-rate) − LP. Keep-rate comes from marketplace_percentages
                // (Faire). No ship cost on Faire.
                $pftEachAmount = ($price * $keepRate) - $lp;
                $pftEachPct    = $price > 0 ? ($pftEachAmount / $price) * 100 : 0;
                $totalPft      = $pftEachAmount * $quantity;
                $cogs          = $lp * $quantity;
                // ROI = (T PFT / COGS) × 100. Dividing by total COGS (lp × qty) keeps ROI
                // quantity-independent and consistent with the ROI summary badge.
                $roi           = $cogs > 0 ? ($totalPft / $cogs) * 100 : 0;

                return [
                    // Core identifiers
                    'order_date'    => $item->order_date,
                    'order_number'  => $item->order_number,
                    'sku'           => $sku,
                    'product_name'  => $item->product_title,
                    'status'        => $item->financial_status ?: $item->fulfillment_status,
                    'quantity'      => (int) $quantity,
                    // Pricing & profit
                    'price'         => round($price, 2),
                    'lp'            => round($lp, 2),
                    'cogs'          => round($cogs, 2),
                    'pft_each'      => round($pftEachAmount, 2),
                    'pft_each_pct'  => round($pftEachPct, 2),
                    'pft'           => round($totalPft, 2),
                    'roi'           => round($roi, 2),
                    // Shipping (no Faire-style ship_date; expose tracking instead)
                    'ship_date'         => null,
                    'tracking_company'  => $item->tracking_company,
                    'tracking_number'   => $item->tracking_number,
                    // Customer / shipping address (limited columns on shopify_order_items)
                    'retailer_name' => $item->customer_name,
                    'city'          => $item->shipping_city,
                    'country'       => $item->shipping_country,
                    // Legacy columns kept for back-compat with the tabulator (left empty)
                    'purchase_order_number' => null,
                    'address_1' => null,
                    'address_2' => null,
                    'state'     => null,
                    'zip_code'  => null,
                    'option_name' => null,
                    'gtin'      => null,
                    'scheduled_order_date' => null,
                    'notes'     => null,
                ];
            });

            return response()->json($mapped->values())->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error fetching Faire data from shopify_order_items: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show Faire tabulator view
     */
    public function faireTabulatorView()
    {
        return view('market-places.faire_tabulator_view');
    }

    public function fairePricingView()
    {
        return view('market-places.faire_pricing_view');
    }

    /**
     * Upload Faire Products → Performance export.
     * Columns: Product name, SKU, Type, Page views, Orders, Units sold.
     * Always truncates faire_products_sheets before insert.
     */
    public function uploadViews(Request $request)
    {
        $request->validate([
            'views_file' => 'required|file',
        ]);

        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $rows = $this->parseFaireViewsFile($request->file('views_file'));
            if ($rows === []) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            $headerIndex = $this->findFaireViewsHeaderRow($rows);
            $rawHeaders = array_values($rows[$headerIndex] ?? []);
            $fieldByIndex = $this->mapFaireViewsHeaders($rawHeaders);
            if (! in_array('sku', $fieldByIndex, true) || ! in_array('views', $fieldByIndex, true)) {
                $fieldByIndex = $this->applyFaireViewsPositionalFallback($rawHeaders, $fieldByIndex);
            }
            if (! in_array('sku', $fieldByIndex, true)) {
                return response()->json([
                    'error' => 'Could not find SKU column. Headers: '.$this->faireViewsHeaderPreview($rawHeaders),
                ], 400);
            }
            if (! in_array('views', $fieldByIndex, true)) {
                return response()->json([
                    'error' => 'Could not find Page views column. Expected Faire Performance export (Product name, SKU, Type, Page views, Orders, Units sold). Headers: '.$this->faireViewsHeaderPreview($rawHeaders),
                ], 400);
            }

            $aggregated = [];
            $skipped = 0;
            foreach (array_slice($rows, $headerIndex + 1) as $row) {
                $row = array_values(is_array($row) ? $row : []);
                if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    $skipped++;
                    continue;
                }

                $mapped = [];
                foreach ($fieldByIndex as $idx => $field) {
                    if ($field === null) {
                        continue;
                    }
                    $mapped[$field] = $row[$idx] ?? '';
                }

                $sku = trim((string) ($mapped['sku'] ?? ''));
                if ($sku === '' || strcasecmp($sku, 'Multiple') === 0 || preg_match('#^https?://#i', $sku)) {
                    $skipped++;
                    continue;
                }

                $key = $this->normalizeFaireSkuExact($sku);
                if ($key === '') {
                    $skipped++;
                    continue;
                }

                if (! isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'sku' => $sku,
                        'product_name' => $this->nullableFaireViewsString($mapped['product_name'] ?? null),
                        'type' => $this->nullableFaireViewsString($mapped['type'] ?? null),
                        'views' => 0,
                        'orders' => 0,
                        'units_sold' => 0,
                    ];
                }

                $aggregated[$key]['views'] += $this->toFaireViewsInt($mapped['views'] ?? 0);
                $aggregated[$key]['orders'] += $this->toFaireViewsInt($mapped['orders'] ?? 0);
                $aggregated[$key]['units_sold'] += $this->toFaireViewsInt($mapped['units_sold'] ?? 0);
                if (empty($aggregated[$key]['product_name']) && ! empty($mapped['product_name'])) {
                    $aggregated[$key]['product_name'] = $this->nullableFaireViewsString($mapped['product_name']);
                }
                if (empty($aggregated[$key]['type']) && ! empty($mapped['type'])) {
                    $aggregated[$key]['type'] = $this->nullableFaireViewsString($mapped['type']);
                }
            }

            if ($aggregated === []) {
                return response()->json([
                    'error' => 'No SKU rows found. Headers: '.$this->faireViewsHeaderPreview($rawHeaders),
                ], 400);
            }

            $this->ensureFaireProductsSheetTable();

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            FaireProductSheet::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            Log::info('Faire products sheets table truncated before views import');

            $imported = 0;
            $nextId = 1;
            $hasOrders = Schema::hasColumn('faire_products_sheets', 'orders');
            $hasUnits = Schema::hasColumn('faire_products_sheets', 'units_sold');
            $hasName = Schema::hasColumn('faire_products_sheets', 'product_name');
            $hasType = Schema::hasColumn('faire_products_sheets', 'type');
            $hasFl30 = Schema::hasColumn('faire_products_sheets', 'f_l30');

            foreach (array_chunk(array_values($aggregated), 200) as $chunk) {
                $now = now();
                $insert = [];
                foreach ($chunk as $row) {
                    $payload = [
                        'id' => $nextId++,
                        'sku' => $row['sku'],
                        'views' => (int) $row['views'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($hasName) {
                        $payload['product_name'] = $row['product_name'];
                    }
                    if ($hasType) {
                        $payload['type'] = $row['type'];
                    }
                    if ($hasOrders) {
                        $payload['orders'] = (int) $row['orders'];
                    }
                    if ($hasUnits) {
                        $payload['units_sold'] = (int) $row['units_sold'];
                    }
                    if ($hasFl30) {
                        $payload['f_l30'] = (int) $row['units_sold'];
                    }
                    $insert[] = $payload;
                }
                FaireProductSheet::insert($insert);
                $imported += count($insert);
            }

            return response()->json([
                'success' => "Imported {$imported} view row(s) (previous upload truncated, skipped {$skipped})",
                'imported' => $imported,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error importing Faire views sheet: '.$e->getMessage());

            return response()->json(['error' => 'Error importing file: '.$e->getMessage()], 500);
        }
    }

    public function downloadViewsSample()
    {
        $tsv = implode("\n", [
            "Product name\tSKU\tType\tPage views\tOrders\tUnits sold",
            "5 Core Guitar Stand Floor Adjustable Heavy Duty w Neck Holder for Acoustic Electric Classic Bass\tGSH HD RED\tMusic Accessory\t0\t1\t5",
            "5 Core 8 Subwoofer Dual 2 Ohm 1000W Car Audio Woofer Driver\tWF 8140 DBL D2\tSpeakers\t5\t1\t4",
        ])."\n";

        return response($tsv, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="faire-views-sample.txt"',
        ]);
    }

    /**
     * Sync Faire listings / wholesale price / stock from Faire products API into faire_metric.
     */
    public function syncPricingFromApi(Request $request)
    {
        @set_time_limit(300);

        $page = max(1, (int) $request->input('page', 1));
        $reset = $request->boolean('reset', $page === 1);

        $result = app(FaireLinkMapSyncService::class)->syncPage($page, 50, $reset);

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }


    public function getFairePricingData(Request $request)
    {
        try {
            // Same grain as /faire/daily-data (tabulator): last 30 Pacific days from
            // shopify_raw_orders. faire_daily_data is a manual Excel dump and stays frozen
            // until someone re-uploads (it was stuck for a week).
            $salesAgg = self::queryFaireShopifyL30SalesBySku();

            // Robust SKU normalizer (mirrors AliexpressController::normalizeAeSkuExact).
            // The previous `strtoupper(trim(str_replace(NBSP, ' ', $v)))` missed narrow NBSP
            // (\xE2\x80\xAF), raw \xA0, and inner multi-space runs — common in Faire/Excel
            // exports — which silently broke the product_master join and made LP show as 0.
            $normalizeSku = fn ($value) => $this->normalizeFaireSkuExact((string) $value);

            $salesBySku = $salesAgg->keyBy(fn ($row) => $normalizeSku($row->sku));

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Faire products API only (faire_metric via link-map) — no sheet / pricing_prices fallback.
            $metricBySku = Schema::hasTable('faire_metric')
                ? FaireMetric::query()
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku))
                : collect();

            // Load full tables and key in PHP — SQL UPPER(TRIM(sku)) cannot fold NBSP / inner whitespace,
            // and the previous `pluck()->whereIn(UPPER(TRIM(sku)))` two-step had the same blind spot.
            $listingStatusBySku = FaireListingStatus::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $viewMetaBySku = FaireDataView::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $viewsBySku = Schema::hasTable('faire_products_sheets')
                ? FaireProductSheet::query()
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku))
                : collect();

            $allNormalizedSkus = collect(array_merge(
                $salesBySku->keys()->all(),
                $productMastersBySku->keys()->all(),
                $metricBySku->keys()->all(),
                $listingStatusBySku->keys()->all(),
                $viewMetaBySku->keys()->all()
            ))->unique()->values();

            // Load full Shopify map like Product Master — whereIn(UPPER(TRIM(sku))) misses UTF-8 NBSP / variant spacing.
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Same source as Forecast Analysis: forecast_analysis.nr (NRP), keyed by normalized SKU.
            // Load full table; SQL UPPER(TRIM(sku)) won't fold NBSP/multi-space the way PHP does.
            $forecastNrBySku = [];
            $faRows = DB::table('forecast_analysis')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'parent', 'nr', 'stage']);
            foreach ($faRows->groupBy(fn ($r) => $normalizeSku($r->sku)) as $k => $group) {
                $withStage = $group->first(function ($r) {
                    return $r->stage !== null && trim((string) $r->stage) !== '';
                });
                if ($withStage) {
                    $forecastNrBySku[$k] = $withStage;

                    continue;
                }
                $withNr = $group->first(function ($r) {
                    return $r->nr !== null && trim((string) $r->nr) !== '';
                });
                $forecastNrBySku[$k] = $withNr ?? $group->first();
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 75) : 75;
            $margin = $percentage / 100;

            // STD PRC — amazon_data_view.STANDARD_PRICE (same as /pricing-errors-form)
            $amazonStandardPrices = AmazonDataView::all()
                ->keyBy(fn ($r) => $normalizeSku($r->sku))
                ->map(function ($r) {
                    $val = is_array($r->value) ? $r->value : (json_decode((string) $r->value, true) ?: []);
                    $std = $val['STANDARD_PRICE'] ?? null;

                    return (is_numeric($std) && floatval($std) > 0) ? round(floatval($std), 2) : 0;
                });

            $promoSkus = $productMastersBySku->map(fn ($pm) => trim((string) $pm->sku))->filter()->values()->all();
            if ($promoSkus === []) {
                $promoSkus = $allNormalizedSkus->all();
            }
            $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('faire', $promoSkus);

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

                $lp = isset($values['lp']) ? (float) $values['lp'] : ($productMaster && isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($productMaster && isset($productMaster->ship) ? (float) $productMaster->ship : 0);

                $al30 = (float) ($sale->al30 ?? 0);
                $sales = (float) ($sale->sales ?? 0);
                $viewSheet = $viewsBySku->get($normalizedSku);
                $pageViews = $viewSheet ? (int) ($viewSheet->views ?? 0) : 0;
                $sheetOrders = $viewSheet ? (int) ($viewSheet->orders ?? 0) : 0;
                $sheetUnits = $viewSheet ? (int) ($viewSheet->units_sold ?? $viewSheet->f_l30 ?? 0) : 0;
                $cvr = $pageViews > 0 ? round(($sheetUnits / $pageViews) * 100, 2) : 0;

                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $pushStatus = isset($meta['PUSH_STATUS']) ? trim((string) $meta['PUSH_STATUS']) : null;
                if ($pushStatus === '') {
                    $pushStatus = null;
                }
                $metric = $metricBySku->get($normalizedSku);
                $price = $metric ? (float) ($metric->price ?? 0) : 0;
                $faireStock = $metric ? (int) ($metric->inventory ?? 0) : 0;
                $productId = $metric ? trim((string) ($metric->product_id ?? '')) : '';
                $productId = $productId !== '' ? $productId : null;

                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
                $ovL30 = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;

                $profit = ($price * $margin) - $lp;
                $gpft = $price > 0 ? ($profit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profit / $lp) * 100 : 0;

                $displaySku = $productMaster
                    ? trim((string) $productMaster->sku)
                    : ($sale ? (string) $sale->sku : ($metric ? trim((string) $metric->sku) : $normalizedSku));

                $faRec = $forecastNrBySku[$normalizedSku] ?? null;
                $nrOut = '';
                if ($faRec && $faRec->nr !== null && trim((string) $faRec->nr) !== '') {
                    $nrOut = strtoupper(trim((string) $faRec->nr));
                    if (! in_array($nrOut, ['REQ', 'NR', 'LATER'], true)) {
                        $nrOut = 'REQ';
                    }
                }

                // Listed on Faire = row in faire_metric from products API (no sheet fallback).
                $isMissingFaire = $metric === null;
                $nrForRules = $this->resolveFaireNrForRules($nrOut, is_array($meta) ? $meta : [], $productMaster !== null);

                $missing = '';
                $mapValue = '';
                if ($inv > 0 && $nrForRules === 'REQ') {
                    if ($isMissingFaire) {
                        $missing = 'M';
                    // Both sides need stock (same as Shein / Amazon map-issues).
                    } elseif ($faireStock > 0) {
                        if (self::faireInvWithinMapTolerance((float) $inv, (float) $faireStock)) {
                            $mapValue = 'Map';
                        } else {
                            $mapValue = 'N Map|'.(int) round(abs($inv - $faireStock));
                        }
                    }
                }

                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp) / $lp) * 100) : 0;

                $listingRecord = $listingStatusBySku->get($normalizedSku);
                $listingPayload = ($listingRecord && is_array($listingRecord->value)) ? $listingRecord->value : [];
                $buyerLink = isset($listingPayload['buyer_link']) ? trim((string) $listingPayload['buyer_link']) : '';
                $sellerLink = isset($listingPayload['seller_link']) ? trim((string) $listingPayload['seller_link']) : '';
                $buyerLink = $buyerLink !== '' ? $buyerLink : null;
                $sellerLink = $sellerLink !== '' ? $sellerLink : null;

                $row = [
                    'sku' => $displaySku,
                    'parent' => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent' => false,
                    'image' => $imageSrc,
                    'price' => round($price, 2),
                    'standard_price' => isset($amazonStandardPrices[$normalizedSku])
                        ? floatval($amazonStandardPrices[$normalizedSku])
                        : 0,
                    'product_id' => $productId,
                    'lmp' => null,
                    'lmp_link' => null,
                    'lmp_entries' => [],
                    'is_missing_faire' => $isMissingFaire,
                    'missing' => $missing,
                    'map' => $mapValue,
                    'nr' => $nrForRules ?? $nrOut,
                    'buyer_link' => $buyerLink,
                    'seller_link' => $sellerLink,
                    'gpft' => (int) round($gpft),
                    'groi' => (int) round($groi),
                    'profit' => round($profit, 2),
                    'sales' => round($sales, 2),
                    'al30' => (int) round($al30),
                    'views' => $pageViews,
                    'orders' => $sheetOrders,
                    'units_sold' => $sheetUnits,
                    'cvr' => $cvr,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'sprice' => round($sprice, 2),
                    'sgpft' => $sgpft,
                    'sroi' => $sroi,
                    'push_status' => $pushStatus,
                    '_margin' => round($margin, 4),
                    'inv' => $inv,
                    'ov_l30' => $ovL30,
                    'ae_stock' => $faireStock,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                ];
                $row['STANDARD_PRICE'] = ($row['standard_price'] ?? 0) > 0 ? $row['standard_price'] : null;
                $row['SPRICE'] = $row['sprice'];
                $rows[] = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $displaySku);
            }

            usort($rows, static function ($a, $b) {
                $pa = (string) ($a['parent'] ?? '');
                $pb = (string) ($b['parent'] ?? '');
                if ($pa === '' && $pb === '') {
                    return strnatcasecmp($a['sku'], $b['sku']);
                }
                if ($pa === '') {
                    return 1;
                }
                if ($pb === '') {
                    return -1;
                }
                $cmp = strnatcasecmp($pa, $pb);

                return $cmp !== 0 ? $cmp : strnatcasecmp($a['sku'], $b['sku']);
            });

            $rows = $this->insertFaireParentRows($rows);
            $this->saveFairePricingSnapshot($rows);

            return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Error fetching Faire pricing data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch pricing data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function saveFaireSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [[
                    'sku' => $request->input('sku'),
                    'sprice' => $request->input('sprice'),
                    'push_status' => $request->input('push_status'),
                ]];
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 75) : 75;
            $margin = $percentage / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                if (! $sku) {
                    continue;
                }

                $hasSprice = array_key_exists('sprice', $update) && $update['sprice'] !== null && $update['sprice'] !== '';
                $hasPushStatus = array_key_exists('push_status', $update);

                if (! $hasSprice && ! $hasPushStatus) {
                    continue;
                }

                $view = FaireDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                    : (json_decode($view->value, true) ?: []);

                if ($hasSprice) {
                    $sprice = (float) $update['sprice'];

                    // Robust SKU match — exact `WHERE sku = ?` misses NBSP / multi-space variants
                    // that frequently exist between faire_data_views and product_masters.
                    $normalizedSku = $this->normalizeFaireSkuExact((string) $sku);
                    $productMaster = ProductMaster::query()
                        ->whereNotNull('sku')->where('sku', '!=', '')
                        ->get()
                        ->first(fn ($r) => $this->normalizeFaireSkuExact((string) $r->sku) === $normalizedSku);
                    $lp = 0;
                    if ($productMaster) {
                        $values = is_array($productMaster->Values)
                            ? $productMaster->Values
                            : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                        $lp = isset($values['lp']) ? (float) $values['lp'] : 0;
                    }

                    $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp) / $sprice) * 100) : 0;
                    $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp) / $lp) * 100) : 0;

                    $stored['SPRICE'] = $sprice;
                    $stored['SGPFT'] = $sgpft;
                    $stored['SROI'] = $sroi;

                    // New SPRICE invalidates prior push success (unless this save also sets push_status).
                    if (! $hasPushStatus) {
                        unset($stored['PUSH_STATUS'], $stored['PUSH_STATUS_UPDATED_AT']);
                    }
                }

                if ($hasPushStatus) {
                    $pushStatus = $update['push_status'];
                    if ($pushStatus === null || $pushStatus === '') {
                        unset($stored['PUSH_STATUS'], $stored['PUSH_STATUS_UPDATED_AT']);
                    } else {
                        $stored['PUSH_STATUS'] = (string) $pushStatus;
                        $stored['PUSH_STATUS_UPDATED_AT'] = now()->format('Y-m-d H:i:s');
                    }
                }

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Exception $e) {
            Log::error('Faire SPRICE save failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Push SPRICE as Faire wholesale price via External API v2 variant prices endpoint.
     */
    public function pushPriceToFaire(Request $request)
    {
        $sku = trim((string) $request->input('sku', ''));
        $price = $request->input('price');
        $productId = trim((string) $request->input('product_id', ''));

        if ($sku === '') {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'SKU is required.']],
            ], 400);
        }

        if (! is_numeric($price) || (float) $price <= 0) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Price must be greater than 0.']],
            ], 400);
        }

        try {
            $result = app(FaireApiService::class)->updateSkuWholesalePrice(
                $sku,
                (float) $price,
                $productId !== '' ? $productId : null
            );

            if (empty($result['success'])) {
                Log::warning('Faire price push failed', [
                    'sku' => $sku,
                    'price' => $price,
                    'result' => $result,
                ]);

                return response()->json([
                    'success' => false,
                    'errors' => [['message' => $result['message'] ?? 'Faire price push failed']],
                    'data' => $result,
                ], 400);
            }

            // Keep local faire_metric.price in sync with what we pushed.
            $normalizedSku = $this->normalizeFaireSkuExact($sku);
            if (Schema::hasTable('faire_metric')) {
                $metric = FaireMetric::query()
                    ->whereNotNull('sku')->where('sku', '!=', '')
                    ->get()
                    ->first(fn ($r) => $this->normalizeFaireSkuExact((string) $r->sku) === $normalizedSku);
                if ($metric) {
                    $metric->price = round((float) $price, 2);
                    $metric->save();
                }
            }

            Log::info('Faire price push successful', [
                'sku' => $sku,
                'price' => $price,
                'product_id' => $result['product_id'] ?? null,
                'variant_id' => $result['variant_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Price pushed to Faire successfully',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Faire price push exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'errors' => [['message' => $e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Save buyer / seller links for a SKU into faire_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = FaireListingStatus::firstOrNew(['sku' => $sku]);
        $existing = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        $status->value = $existing;
        $status->save();

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    public function faireBadgeChartData(Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'avg_gpft');
            $days = (int) $request->input('days', 30);

            $validMetrics = [
                'total_pft', 'total_sales', 'avg_gpft', 'avg_roi',
                'total_al30', 'avg_dil', 'missing_count', 'map_count', 'nmap_count',
                'total_sku', 'zero_sold', 'more_sold',
            ];
            if (!in_array($metric, $validMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $query = AmazonChannelSummary::where('channel', 'faire')
                ->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }
            $rows = $query->get(['snapshot_date', 'summary_data']);

            $data = [];
            foreach ($rows as $row) {
                $sd = is_array($row->summary_data)
                    ? $row->summary_data
                    : (json_decode($row->summary_data ?? '{}', true) ?: []);
                $value = (float) ($sd[$metric] ?? 0);
                $data[] = [
                    'date' => optional($row->snapshot_date)->format('M d'),
                    'value' => $value,
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Faire badge chart data error: ' . $e->getMessage());

            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    private function insertFaireParentRows(array $rows): array
    {
        $result = [];
        $group = [];
        $currentParent = null;

        foreach ($rows as $row) {
            $p = $row['parent'] ?? null;
            $p = ($p !== null && $p !== '') ? (string) $p : null;

            if ($p === null) {
                if (!empty($group)) {
                    foreach ($group as $r) {
                        $result[] = $r;
                    }
                    $result[] = $this->buildFaireParentRow($currentParent, $group);
                    $group = [];
                    $currentParent = null;
                }
                $result[] = $row;
                continue;
            }

            if ($p !== $currentParent) {
                if (!empty($group)) {
                    foreach ($group as $r) {
                        $result[] = $r;
                    }
                    $result[] = $this->buildFaireParentRow($currentParent, $group);
                    $group = [];
                }
                $currentParent = $p;
            }
            $group[] = $row;
        }

        if (!empty($group)) {
            foreach ($group as $r) {
                $result[] = $r;
            }
            $result[] = $this->buildFaireParentRow($currentParent, $group);
        }

        return $result;
    }

    private function buildFaireParentRow(string $parentName, array $childRows): array
    {
        $sumInv = $sumOvL30 = $sumAeStock = $sumAl30 = $sumSales = 0;
        $sumProfit = 0;
        $sumViews = $sumOrders = $sumUnits = 0;

        foreach ($childRows as $r) {
            $sumInv += (float) ($r['inv'] ?? 0);
            $sumOvL30 += (float) ($r['ov_l30'] ?? 0);
            $sumAeStock += (float) ($r['ae_stock'] ?? 0);
            $sumAl30 += (float) ($r['al30'] ?? 0);
            $sumSales += (float) ($r['sales'] ?? 0);
            $sumViews += (float) ($r['views'] ?? 0);
            $sumOrders += (float) ($r['orders'] ?? 0);
            $sumUnits += (float) ($r['units_sold'] ?? 0);
            $al30 = (float) ($r['al30'] ?? 0);
            $profit = (float) ($r['profit'] ?? 0);
            $sumProfit += $al30 * $profit;
        }

        $dilPct = $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0;
        $gpftPct = $sumSales > 0 ? (int) round(($sumProfit / $sumSales) * 100) : 0;
        $parentCvr = $sumViews > 0 ? round(($sumUnits / $sumViews) * 100, 2) : 0;

        $key = 'PARENT ' . $parentName;

        return [
            'sku' => $key,
            'parent' => $key,
            'is_parent' => true,
            'image' => null,
            'price' => '-',
            'standard_price' => '-',
            'missing' => '-',
            'map' => '-',
            'buyer_link' => null,
            'seller_link' => null,
            'gpft' => $gpftPct,
            'groi' => '-',
            'profit' => round($sumProfit, 2),
            'sales' => round($sumSales, 2),
            'al30' => (int) round($sumAl30),
            'views' => (int) $sumViews,
            'orders' => (int) $sumOrders,
            'units_sold' => (int) $sumUnits,
            'cvr' => $parentCvr,
            'lp' => '-',
            'ship' => '-',
            'sprice' => '-',
            'sgpft' => '-',
            'sroi' => '-',
            'push_status' => null,
            'product_id' => null,
            'inv' => (int) $sumInv,
            'ov_l30' => (int) $sumOvL30,
            'ae_stock' => (int) $sumAeStock,
            'dil_percent' => $dilPct,
            'lmp' => null,
            'lmp_link' => null,
            'lmp_entries' => [],
            'nr' => '',
        ];
    }

    /**
     * NR for map/missing rules — mirrors Amazon / AliExpress (REQ vs NR, default REQ when in product master).
     *
     * @param  array<string, mixed>  $meta
     */
    private function resolveFaireNrForRules(string $forecastNr, array $meta, bool $hasProductMaster): ?string
    {
        $nrl = strtoupper(trim((string) ($meta['NRL'] ?? $meta['NR'] ?? '')));
        if (in_array($nrl, ['NRL', 'NR'], true) || ($meta['NRL'] ?? null) === true || ($meta['NR'] ?? null) === true) {
            return 'NR';
        }
        if ($nrl === 'REQ') {
            return 'REQ';
        }

        $nrp = strtoupper(trim((string) ($meta['NRP'] ?? '')));
        if ($nrp === 'NR') {
            return 'NR';
        }

        $nr = strtoupper(trim($forecastNr));
        if ($nr === 'NR' || $nr === 'LATER') {
            return 'NR';
        }
        if ($nr === 'REQ') {
            return 'REQ';
        }

        return $hasProductMaster ? 'REQ' : null;
    }

    /** INV vs Faire stock = Map if diff ≤ 3 units OR ≤ 3% of Shopify INV (Amazon INV vs INV_AMZ). */
    private static function faireInvWithinMapTolerance(float $inv, float $faireStock): bool
    {
        if ($inv <= 0) {
            return true;
        }
        $diff = abs($inv - $faireStock);
        if ($diff <= 3.0) {
            return true;
        }

        return $diff <= ($inv * 0.03);
    }

    /**
     * Map / Miss / NMap — same rules as faire_pricing_view (Amazon / AliExpress aligned).
     */
    public static function countFairePricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;
        $totalViews = 0;
        $totalUnits = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $nrValue = (string) ($row['nr'] ?? '');
            $isMissingFaire = (bool) ($row['is_missing_faire'] ?? false);
            $rowPrice = (float) ($row['price'] ?? 0);
            $faireStock = (float) ($row['ae_stock'] ?? 0);
            $totalViews += (int) ($row['views'] ?? 0);
            $totalUnits += (int) ($row['units_sold'] ?? 0);

            if ($inv > 0 && $nrValue === 'REQ') {
                if ($isMissingFaire) {
                    $miss++;
                // Both sides need stock (same as Shein countSheinPricingBadgeTotals).
                } elseif ($faireStock > 0) {
                    if (self::faireInvWithinMapTolerance($inv, $faireStock)) {
                        $map++;
                    } else {
                        $nmap++;
                    }
                }
            }
        }

        return [
            'map' => $map,
            'miss' => $miss,
            'nmap' => $nmap,
            'total_views' => $totalViews,
            'cvr' => $totalViews > 0 ? round(($totalUnits / $totalViews) * 100, 2) : 0,
        ];
    }

    private function saveFairePricingSnapshot(array $rows): void
    {
        try {
            $today = now()->toDateString();

            $allChildRows = collect($rows)->filter(fn ($r) => !($r['is_parent'] ?? false));
            if ($allChildRows->isEmpty()) {
                return;
            }

            $orderKeep = 0.75;

            $totalSales = 0;
            $totalProfit = 0;
            $totalCogs = 0;
            $totalAl30 = 0;
            $dilSum = 0;
            $dilCount = 0;
            $zeroSold = 0;
            $moreSold = 0;

            foreach ($allChildRows as $r) {
                $sales = (float) ($r['sales'] ?? 0);
                $al30r = (float) ($r['al30'] ?? 0);
                $lp = (float) ($r['lp'] ?? 0);
                $listProfit = (float) ($r['profit'] ?? 0);
                $isMissing = (($r['missing'] ?? '') === 'M');

                $totalSales += $sales;
                $totalCogs += $lp * $al30r;

                if ($sales > 0 && $al30r > 0) {
                    $totalProfit += ($orderKeep * $sales) - ($lp * $al30r);
                } elseif ($al30r > 0 && ! $isMissing) {
                    $totalProfit += $al30r * $listProfit;
                }
            }

            foreach ($allChildRows as $r) {
                $inv = (float) ($r['inv'] ?? 0);
                $ovL30 = (float) ($r['ov_l30'] ?? 0);
                $al30 = (float) ($r['al30'] ?? 0);

                $totalAl30 += $al30;
                if ($al30 === 0.0) {
                    $zeroSold++;
                } else {
                    $moreSold++;
                }
                if ($inv > 0) {
                    $dilSum += ($ovL30 / $inv) * 100;
                    $dilCount++;
                }
            }

            $badgeTotals = self::countFairePricingBadgeTotals($allChildRows);
            $missingCount = $badgeTotals['miss'];
            $mapCount = $badgeTotals['map'];
            $nmapCount = $badgeTotals['nmap'];

            $totalSkuCount = $allChildRows->count();

            $pftPct = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;
            $roiPct = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;

            $summaryData = [
                'total_sku' => $totalSkuCount,
                'total_sales' => round($totalSales, 2),
                'total_pft' => round($totalProfit, 2),
                'total_cogs' => round($totalCogs, 2),
                'total_al30' => round($totalAl30, 0),
                'avg_gpft' => round($pftPct, 2),
                'avg_roi' => round($roiPct, 2),
                'avg_dil' => $dilCount > 0 ? round($dilSum / $dilCount, 2) : 0,
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $nmapCount,
                'zero_sold' => $zeroSold,
                'more_sold' => $moreSold,
                'total_views' => (int) ($badgeTotals['total_views'] ?? 0),
                'cvr' => (float) ($badgeTotals['cvr'] ?? 0),
                'calculated_at' => now()->toDateTimeString(),
            ];

            AmazonChannelSummary::updateOrCreate(
                ['channel' => 'faire', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved Faire pricing snapshot']
            );
        } catch (\Exception $e) {
            Log::error('Faire daily snapshot save failed: ' . $e->getMessage());
        }
    }



    /**
     * Robust SKU normalization for cross-table joins.
     *
     * Folds non-breaking spaces (NBSP / narrow NBSP / \xA0) to regular spaces,
     * strips invalid UTF-8, collapses internal whitespace runs, then uppercases.
     * Without this, `faire_metric.sku` / `forecast_analysis.sku` rarely
     * match `product_masters.sku` because Faire API / Excel exports leak NBSP and
     * double-spaces, and LP silently falls back to 0. Mirrors
     * AliexpressController::normalizeAeSkuExact.
     */
    private function normalizeFaireSkuExact(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
    }


    private function sanitizeFairePrice($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function parseFaireDate($dateString)
    {
        if (empty($dateString) || $dateString === null || $dateString === '') {
            return null;
        }

        $dateString = trim((string) $dateString);
        $lower = strtolower($dateString);

        if ($lower === 'no ship date' || $lower === 'no scheduled order date') {
            return null;
        }

        try {
            if (is_numeric($dateString)) {
                $baseDate = Carbon::create(1899, 12, 30);
                return $baseDate->addDays((int) $dateString);
            }

            $formats = [
                'd-M-y',
                'd-M-Y',
                'Y-m-d',
                'm/d/Y',
                'd/m/Y',
                'Y-m-d H:i:s',
                'd-M-y H:i',
            ];

            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $dateString);
                    if ($parsed) {
                        return $parsed;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse Faire date: {$dateString}");
            return null;
        }
    }

    public function getFairePricingColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "faire_pricing_tabulator_column_visibility_{$userId}";

        return response()->json(Cache::get($key, []));
    }

    public function setFairePricingColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "faire_pricing_tabulator_column_visibility_{$userId}";
        $visibility = $request->input('visibility', []);
        Cache::put($key, $visibility, now()->addDays(365));

        return response()->json(['success' => true]);
    }

    /**
     * @return list<list<mixed>>
     */
    private function parseFaireViewsFile($file): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $firstLine = '';
        if (is_file($path)) {
            $fh = fopen($path, 'r');
            if ($fh !== false) {
                $firstLine = (string) fgets($fh);
                fclose($fh);
            }
        }

        $looksTsv = str_contains($firstLine, "\t")
            || in_array($ext, ['txt', 'tsv'], true);
        if ($looksTsv && ! in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->readFaireDelimitedFile($path, "\t");
        }
        if ($ext === 'csv') {
            $delimiter = str_contains($firstLine, "\t") ? "\t" : ',';

            return $this->readFaireDelimitedFile($path, $delimiter);
        }

        $spreadsheet = IOFactory::load($path);

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    /**
     * @return list<list<mixed>>
     */
    private function readFaireDelimitedFile(string $path, string $delimiter): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }
            $rows[] = $row;
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function findFaireViewsHeaderRow(array $rows): int
    {
        $best = 0;
        $bestScore = -1;
        $limit = min(count($rows), 15);
        for ($i = 0; $i < $limit; $i++) {
            $map = $this->mapFaireViewsHeaders(array_values($rows[$i] ?? []));
            $fields = array_values(array_filter($map));
            $score = count($fields);
            if (in_array('sku', $fields, true)) {
                $score += 10;
            }
            if (in_array('views', $fields, true)) {
                $score += 20;
            }
            if (in_array('units_sold', $fields, true) || in_array('orders', $fields, true)) {
                $score += 5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * @param  list<mixed>  $headers
     * @return array<int, string|null>
     */
    private function mapFaireViewsHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $header) {
            $n = $this->normalizeFaireViewsHeader((string) $header);
            $field = match (true) {
                $n === 'sku' || $n === 'seller_sku' || $n === 'variant_sku' => 'sku',
                $n === 'product_name' || $n === 'name' || $n === 'title' => 'product_name',
                $n === 'type' || $n === 'product_type' || $n === 'category' => 'type',
                $n === 'page_views' || $n === 'pageviews' || $n === 'views' => 'views',
                $n === 'orders' || $n === 'order' => 'orders',
                $n === 'units_sold' || $n === 'units' || $n === 'unit_sold' => 'units_sold',
                default => null,
            };
            $map[(int) $idx] = $field;
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $rawHeaders
     * @param  array<int, string|null>  $fieldByIndex
     * @return array<int, string|null>
     */
    private function applyFaireViewsPositionalFallback(array $rawHeaders, array $fieldByIndex): array
    {
        $joined = strtolower(implode(' ', array_map(fn ($h) => (string) $h, $rawHeaders)));
        $looksLikePerformance = str_contains($joined, 'product')
            && str_contains($joined, 'sku')
            && (str_contains($joined, 'page') || str_contains($joined, 'view'));
        if (! $looksLikePerformance && count($rawHeaders) < 4) {
            return $fieldByIndex;
        }

        $defaults = [
            0 => 'product_name',
            1 => 'sku',
            2 => 'type',
            3 => 'views',
            4 => 'orders',
            5 => 'units_sold',
        ];
        foreach ($defaults as $idx => $field) {
            if (! isset($fieldByIndex[$idx]) || $fieldByIndex[$idx] === null) {
                $fieldByIndex[$idx] = $field;
            }
        }

        return $fieldByIndex;
    }

    private function normalizeFaireViewsHeader(string $header): string
    {
        $header = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function faireViewsHeaderPreview(array $headers): string
    {
        $parts = [];
        foreach ($headers as $h) {
            $t = trim((string) $h);
            if ($t !== '') {
                $parts[] = $t;
            }
        }

        return implode(' | ', array_slice($parts, 0, 12));
    }

    private function toFaireViewsInt($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $cleaned = preg_replace('/[^\d\-]/', '', (string) $value);

        return is_numeric($cleaned) ? (int) $cleaned : 0;
    }

    private function nullableFaireViewsString($value): ?string
    {
        $t = trim((string) $value);

        return $t !== '' ? mb_substr($t, 0, 500) : null;
    }

    private function ensureFaireProductsSheetTable(): void
    {
        if (Schema::hasTable('faire_products_sheets')) {
            return;
        }

        Schema::create('faire_products_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->nullable();
            $table->string('product_name', 500)->nullable();
            $table->string('type', 191)->nullable();
            $table->integer('f_l30')->nullable();
            $table->integer('f_l60')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('views')->nullable();
            $table->unsignedInteger('orders')->nullable();
            $table->unsignedInteger('units_sold')->nullable();
            $table->timestamps();
        });
    }
}
