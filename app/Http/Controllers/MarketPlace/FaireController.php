<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\FaireDataView;
use App\Models\MarketplacePercentage;
use App\Models\FaireDailyData;
use App\Models\FairePricingPrice;
use App\Models\FaireListingStatus;
use App\Models\AmazonChannelSummary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\ProductMaster;
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
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

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

    /**
     * Get daily data for Faire tabulator view
     */
    public function getDailyData(Request $request)
    {
        try {
            $data = FaireDailyData::orderBy('order_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $skus = $data->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = [];

            if (!empty($skus)) {
                $productMasters = ProductMaster::whereIn('sku', $skus)
                    ->get()
                    ->keyBy('sku');
            }

            $data = $data->map(function ($item) use ($productMasters) {
                $sku = $item->sku;
                $lp = 0.0;

                if (!empty($sku) && isset($productMasters[$sku])) {
                    $productMaster = $productMasters[$sku];
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);

                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }

                    if ($lp === 0.0 && isset($productMaster->lp)) {
                        $lp = floatval($productMaster->lp);
                    }
                }

                $wholesale = floatval($item->wholesale_price) ?: 0.0;
                $retail = floatval($item->retail_price) ?: 0.0;
                $price = $wholesale > 0 ? $wholesale : $retail;
                $quantity = floatval($item->quantity) ?: 0.0;

                // PFT each = (wholesale price × 0.75) − LP; falls back to retail if wholesale empty
                $pftEachAmount = ($price * 0.75) - $lp;
                $pftEachPct = $price > 0 ? ($pftEachAmount / $price) * 100 : 0;
                $roi = $lp > 0 ? ($pftEachAmount / $lp) * 100 : 0;
                $totalPft = $pftEachAmount * $quantity;
                $cogs = $lp * $quantity;

                $item->lp = round($lp, 2);
                $item->price = round($price, 2);
                $item->cogs = round($cogs, 2);
                $item->pft_each = round($pftEachAmount, 2);
                $item->pft_each_pct = round($pftEachPct, 2);
                $item->pft = round($totalPft, 2);
                $item->roi = round($roi, 2);

                return $item;
            });

            return response()->json($data)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error fetching Faire daily data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
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

    public function downloadFairePricingPriceSample()
    {
        $fileName = 'faire_pricing_sample.csv';
        $rows = [
            ['sku', 'price', 'stock'],
            ['SKU-001', '19.99', '10'],
            ['SKU-002', '24.50', '25'],
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

    public function uploadFairePricingPriceSheet(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file',
        ]);

        try {
            $file = $request->file('price_file');
            $path = $file->getPathName();
            $extension = strtolower($file->getClientOriginalExtension());

            if (in_array($extension, ['xlsx', 'xls'], true) || $this->isExcelFileForPricing($path)) {
                $spreadsheet = IOFactory::load($path);
                $sheetRows = $spreadsheet->getActiveSheet()->toArray();

                $headerRow = array_shift($sheetRows);
                $indexes = $this->resolveFairePricingColumnIndexes($headerRow);
                $skuIndex = $indexes['sku'];
                $priceIndex = $indexes['price'];
                $stockIndex = $indexes['stock'];

                if ($skuIndex === false || $priceIndex === false) {
                    $preview = implode(', ', array_slice(array_map(
                        fn ($h) => $this->normalizePricingHeaderCell($h),
                        $headerRow
                    ), 0, 25));

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not find SKU and price columns. '
                            . 'Use headers like sku + price, or a Faire export (SKU, USD Unit Wholesale Price, On Hand Inventory). '
                            . 'First columns seen: [' . $preview . '].',
                    ], 422);
                }

                $updated = 0;
                foreach ($sheetRows as $row) {
                    $sku = trim((string) ($row[$skuIndex] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $price = (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$priceIndex] ?? '')));
                    $faireStock = $stockIndex !== false ? (int) trim((string) ($row[$stockIndex] ?? '0')) : 0;
                    FairePricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        ['price' => max(0, $price), 'faire_stock' => max(0, $faireStock)]
                    );
                    $updated++;
                }
            } else {
                $handle = fopen($path, 'r');
                if (!$handle) {
                    return response()->json(['success' => false, 'message' => 'Cannot open uploaded file.'], 422);
                }

                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $firstLine = fgets($handle);
                rewind($handle);
                if ($bom === "\xEF\xBB\xBF") {
                    fread($handle, 3);
                }

                $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';

                $headerRow = fgetcsv($handle, 0, $delimiter);
                if (!$headerRow) {
                    fclose($handle);
                    return response()->json(['success' => false, 'message' => 'Price sheet is empty.'], 422);
                }

                $indexes = $this->resolveFairePricingColumnIndexes($headerRow);
                $skuIndex = $indexes['sku'];
                $priceIndex = $indexes['price'];
                $stockIndex = $indexes['stock'];

                if ($skuIndex === false || $priceIndex === false) {
                    fclose($handle);
                    $preview = implode(', ', array_slice(array_map(
                        fn ($h) => $this->normalizePricingHeaderCell($h),
                        $headerRow
                    ), 0, 25));

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not find SKU and price columns. '
                            . 'Use headers like sku + price, or a Faire export (SKU, USD Unit Wholesale Price, On Hand Inventory). '
                            . 'First columns seen: [' . $preview . '].',
                    ], 422);
                }

                $updated = 0;
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if (!$row || count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                        continue;
                    }
                    $sku = trim((string) ($row[$skuIndex] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $price = (float) preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$priceIndex] ?? '')));
                    $faireStock = $stockIndex !== false ? (int) trim((string) ($row[$stockIndex] ?? '0')) : 0;
                    FairePricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        ['price' => max(0, $price), 'faire_stock' => max(0, $faireStock)]
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
            Log::error('Faire pricing upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Price upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFairePricingData(Request $request)
    {
        try {
            // Same grain as /faire/daily-data (tabulator): include all rows so badge totals match Sales Data.
            $salesAgg = FaireDailyData::query()
                ->selectRaw(
                    'sku, SUM(COALESCE(quantity, 0)) as al30, '
                    . 'SUM(COALESCE(wholesale_price, 0) * COALESCE(quantity, 0)) as sales'
                )
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->groupBy('sku')
                ->get();

            // Match ProductMaster getViewProductData: NBSP → space, then trim + uppercase (ShopifySku::all() keying).
            $normalizeSku = static fn ($value) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));

            $salesBySku = $salesAgg->keyBy(fn ($row) => $normalizeSku($row->sku));

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $uploadedPriceBySku = FairePricingPrice::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $listingSkuKeys = FaireListingStatus::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map($normalizeSku)
                ->unique()
                ->values()
                ->all();

            $dataViewSkuKeys = FaireDataView::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku')
                ->map($normalizeSku)
                ->unique()
                ->values()
                ->all();

            $allNormalizedSkus = collect(array_merge(
                $salesBySku->keys()->all(),
                $productMastersBySku->keys()->all(),
                $uploadedPriceBySku->keys()->all(),
                $listingSkuKeys,
                $dataViewSkuKeys
            ))->unique()->values();

            $viewMetaBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $viewMetaBySku = FaireDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku));
            }

            // Load full Shopify map like Product Master — whereIn(UPPER(TRIM(sku))) misses UTF-8 NBSP / variant spacing.
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            $listingStatusBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $listingStatusBySku = FaireListingStatus::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku));
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 75) : 75;
            $margin = $percentage / 100;

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

                $al30 = (float) ($sale->al30 ?? 0);
                $sales = (float) ($sale->sales ?? 0);

                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $priceRow = $uploadedPriceBySku->get($normalizedSku);
                $uploadedPrice = $priceRow ? (float) $priceRow->price : 0;
                $faireStock = $priceRow ? (int) ($priceRow->faire_stock ?? 0) : 0;

                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
                $ovL30 = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;

                $price = $uploadedPrice;
                $profit = ($price * $margin) - $lp;
                $gpft = $price > 0 ? ($profit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profit / $lp) * 100 : 0;

                $displaySku = $productMaster
                    ? trim((string) $productMaster->sku)
                    : ($sale ? (string) $sale->sku : ($priceRow ? trim((string) $priceRow->sku) : $normalizedSku));
                $isMissing = !$productMaster || $price <= 0;

                if ($isMissing) {
                    $mapValue = '';
                } elseif ($inv === $faireStock) {
                    $mapValue = 'Map';
                } else {
                    $diff = abs($inv - $faireStock);
                    $mapValue = "N Map|{$diff}";
                }

                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp) / $lp) * 100) : 0;

                $listingRecord = $listingStatusBySku->get($normalizedSku);
                $listingPayload = ($listingRecord && is_array($listingRecord->value)) ? $listingRecord->value : [];
                $buyerLink = isset($listingPayload['buyer_link']) ? trim((string) $listingPayload['buyer_link']) : '';
                $sellerLink = isset($listingPayload['seller_link']) ? trim((string) $listingPayload['seller_link']) : '';
                $buyerLink = $buyerLink !== '' ? $buyerLink : null;
                $sellerLink = $sellerLink !== '' ? $sellerLink : null;

                $rows[] = [
                    'sku' => $displaySku,
                    'parent' => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent' => false,
                    'image' => $imageSrc,
                    'price' => round($price, 2),
                    'lmp' => null,
                    'lmp_link' => null,
                    'lmp_entries' => [],
                    'missing' => $isMissing ? 'M' : '',
                    'map' => $mapValue,
                    'buyer_link' => $buyerLink,
                    'seller_link' => $sellerLink,
                    'gpft' => (int) round($gpft),
                    'groi' => (int) round($groi),
                    'profit' => round($profit, 2),
                    'sales' => round($sales, 2),
                    'al30' => (int) round($al30),
                    'lp' => round($lp, 2),
                    'ship' => 0,
                    'sprice' => round($sprice, 2),
                    'sgpft' => $sgpft,
                    'sroi' => $sroi,
                    '_margin' => round($margin, 4),
                    'inv' => $inv,
                    'ov_l30' => $ovL30,
                    'ae_stock' => $faireStock,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                ];
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
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Faire')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 75) : 75;
            $margin = $percentage / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku = $update['sku'] ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) {
                    continue;
                }

                $sprice = (float) $sprice;

                $productMaster = ProductMaster::where('sku', $sku)->first();
                $lp = 0;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $lp = isset($values['lp']) ? (float) $values['lp'] : 0;
                }

                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp) / $lp) * 100) : 0;

                $view = FaireDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                    : (json_decode($view->value, true) ?: []);

                $stored['SPRICE'] = $sprice;
                $stored['SGPFT'] = $sgpft;
                $stored['SROI'] = $sroi;

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

    public function faireBadgeChartData(Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'avg_gpft');
            $days = max(1, (int) $request->input('days', 30));

            $validMetrics = [
                'total_pft', 'total_sales', 'avg_gpft', 'avg_roi',
                'total_al30', 'avg_dil', 'missing_count', 'map_count',
                'total_sku', 'zero_sold', 'more_sold',
            ];
            if (!in_array($metric, $validMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
            $rows = AmazonChannelSummary::where('channel', 'faire')
                ->where('snapshot_date', '>=', $startDate)
                ->orderBy('snapshot_date', 'asc')
                ->get(['snapshot_date', 'summary_data']);

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

        foreach ($childRows as $r) {
            $sumInv += (float) ($r['inv'] ?? 0);
            $sumOvL30 += (float) ($r['ov_l30'] ?? 0);
            $sumAeStock += (float) ($r['ae_stock'] ?? 0);
            $sumAl30 += (float) ($r['al30'] ?? 0);
            $sumSales += (float) ($r['sales'] ?? 0);
            $al30 = (float) ($r['al30'] ?? 0);
            $profit = (float) ($r['profit'] ?? 0);
            $sumProfit += $al30 * $profit;
        }

        $dilPct = $sumInv > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0;
        $gpftPct = $sumSales > 0 ? (int) round(($sumProfit / $sumSales) * 100) : 0;

        $key = 'PARENT ' . $parentName;

        return [
            'sku' => $key,
            'parent' => $key,
            'is_parent' => true,
            'image' => null,
            'price' => '-',
            'missing' => '-',
            'map' => '-',
            'buyer_link' => null,
            'seller_link' => null,
            'gpft' => $gpftPct,
            'groi' => '-',
            'profit' => round($sumProfit, 2),
            'sales' => round($sumSales, 2),
            'al30' => (int) round($sumAl30),
            'lp' => '-',
            'ship' => '-',
            'sprice' => '-',
            'sgpft' => '-',
            'sroi' => '-',
            'inv' => (int) $sumInv,
            'ov_l30' => (int) $sumOvL30,
            'ae_stock' => (int) $sumAeStock,
            'dil_percent' => $dilPct,
            'lmp' => null,
            'lmp_link' => null,
            'lmp_entries' => [],
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
            $missingCount = 0;
            $mapCount = 0;
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
                if (($r['missing'] ?? '') === 'M') {
                    $missingCount++;
                }
                if (($r['map'] ?? '') === 'Map') {
                    $mapCount++;
                }
            }

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
                'zero_sold' => $zeroSold,
                'more_sold' => $moreSold,
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

    private function isExcelFileForPricing(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $magic = fread($handle, 4);
        fclose($handle);

        return str_starts_with($magic, "\x50\x4B\x03\x04")
            || str_starts_with($magic, "\xD0\xCF\x11\xE0");
    }

    /**
     * Normalize a spreadsheet header cell for matching (Faire export, AliExpress-style sheets, etc.).
     */
    private function normalizePricingHeaderCell($value): string
    {
        $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9_ ]/', ' ', (string) $value)));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Map header row to column indexes. Supports Faire product export (SKU, USD Unit Wholesale Price, On Hand Inventory)
     * and simple uploads (sku, price, stock).
     *
     * @return array{sku:int,price:int,stock:int|false}
     */
    private function resolveFairePricingColumnIndexes(array $headerRow): array
    {
        $headers = [];
        foreach ($headerRow as $i => $h) {
            $headers[$i] = $this->normalizePricingHeaderCell($h);
        }

        $findExact = static function (array $headers, array $names) {
            foreach ($names as $name) {
                foreach ($headers as $idx => $h) {
                    if ($h === $name) {
                        return $idx;
                    }
                }
            }

            return false;
        };

        $findContains = static function (array $headers, array $needlesInOrder) {
            foreach ($needlesInOrder as $needle) {
                foreach ($headers as $idx => $h) {
                    if ($h !== '' && str_contains($h, $needle)) {
                        return $idx;
                    }
                }
            }

            return false;
        };

        $skuNames = [
            'sku', 'skus', 'sku code', 'skucode', 'item sku', 'product sku', 'variant sku', 'child sku',
        ];
        $skuIndex = $findExact($headers, $skuNames);
        if ($skuIndex === false) {
            foreach ($headers as $idx => $h) {
                if ($h !== '' && preg_match('/\bsku\b/', $h)) {
                    $skuIndex = $idx;
                    break;
                }
            }
        }

        $priceNames = [
            'usd unit wholesale price',
            'cad unit wholesale price',
            'gbp unit wholesale price',
            'gbr unit wholesale price',
            'eur unit wholesale price',
            'aud unit wholesale price',
            'unit wholesale price',
            'wholesale price',
            'usd unit retail price',
            'retail price',
            'unit retail price',
            'usd retail price',
            'list price',
            'faire price',
            'price',
            'supply price',
            'unit price',
        ];
        $priceIndex = $findExact($headers, $priceNames);
        if ($priceIndex === false) {
            $priceIndex = $findContains($headers, [
                'wholesale price',
                'unit wholesale',
                'retail price',
                ' unit price',
                'list price',
            ]);
        }

        $stockNames = [
            'stock', 'stocks', 'on hand inventory', 'onhand inventory', 'inventory',
            'available inventory', 'faire stock', 'qty', 'quantity', 'available qty',
        ];
        $stockIndex = $findExact($headers, $stockNames);
        if ($stockIndex === false) {
            $stockIndex = $findContains($headers, [
                'on hand inventory',
                'hand inventory',
                'inventory',
            ]);
        }

        return [
            'sku' => $skuIndex,
            'price' => $priceIndex,
            'stock' => $stockIndex !== false ? $stockIndex : false,
        ];
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
}
