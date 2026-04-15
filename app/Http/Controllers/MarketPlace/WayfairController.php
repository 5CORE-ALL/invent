<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\ProductMaster;
use App\Models\WayfairDataView;
use App\Models\WayfairDailyData;
use App\Models\WayfairPricingPrice;
use App\Models\WayfairListingStatus;
use App\Models\AmazonChannelSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WayfairController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function wayfairView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        // $percentage = Cache::remember('wayfair_marketplace_percentage', now()->addDays(30), function () {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        //     return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        // });

        $marketplaceData = ChannelMaster::where('channel', 'Wayfair')->first();

        $percentage = $marketplaceData ? $marketplaceData->channel_percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.Wayfair', [
            'mode' => $mode,
            'demo' => $demo,
            'wayfairPercentage' => $percentage

        ]);
    }

    public function getAllData()
    {
        $amazonDatas = $this->apiController->fetchExternalData2();
        return response()->json($amazonDatas);
    }

    public function getViewWayfairData(Request $request)
    {
        $response = $this->apiController->fetchDataFromWayfairMasterGoogleSheet();

        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

            // Get JungleScout data with proper price handling
            $jungleScoutData = JungleScoutProductData::all()
                ->groupBy('parent')
                ->map(function ($group) {
                    // Get all valid numeric prices > 0
                    $validPrices = $group->filter(function ($item) {
                        $price = $item->data['price'] ?? null;
                        return is_numeric($price) && $price > 0;
                    })->pluck('data.price');

                    return [
                        'scout_parent' => $group->first()->parent,
                        'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                        'product_count' => $group->count(),
                        'all_data' => $group->map(function ($item) {
                            // Ensure price is properly formatted
                            $data = $item->data;
                            if (isset($data['price'])) {
                                $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                            }
                            return $data;
                        })->toArray()
                    ];
                });

            $skus = collect($data->data)
                ->filter(function ($item) {
                    $childSku = $item->{'(Child) sku'} ?? '';
                    return !empty($childSku) && stripos($childSku, 'PARENT') === false;
                })
                ->pluck('(Child) sku')
                ->unique()
                ->toArray();

            $shopifyData = ShopifySku::mapByProductSkus($skus);

            // Fetch NR values before processing data
            $nrValues = WayfairDataView::pluck('value', 'sku');

            $filteredData = array_filter($data->data, function ($item) {
                $parent = $item->Parent ?? '';
                $childSku = $item->{'(Child) sku'} ?? '';
                return !(empty(trim($parent)) && empty(trim($childSku)));
            });

            $processedData = array_map(function ($item) use ($shopifyData, $jungleScoutData, $nrValues) {
                $childSku = $item->{'(Child) sku'} ?? '';
                $parentAsin = $item->Parent ?? '';

                // Add JungleScout data if parent ASIN matches
                if (!empty($parentAsin) && $jungleScoutData->has($parentAsin)) {
                    $scoutData = $jungleScoutData[$parentAsin];
                    $item->scout_data = [
                        'scout_parent' => $scoutData['scout_parent'],
                        'min_price' => $scoutData['min_price'],
                        'product_count' => $scoutData['product_count'],
                        'all_data' => $scoutData['all_data']
                    ];
                }

                if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                    if ($shopifyData->has($childSku)) {
                        $item->INV = $shopifyData[$childSku]->inv;
                        $item->L30 = $shopifyData[$childSku]->quantity;
                    } else {
                        $item->INV = 0;
                        $item->L30 = 0;
                    }

                    // NR value
                    $item->NR = false;
                    $item->Listed = false;
                    $item->Live = false;

                    if ($childSku && isset($nrValues[$childSku])) {
                        $val = $nrValues[$childSku];
                        if (is_array($val)) {
                            $item->NR = $val['NR'] ?? '';
                            $item->Listed = !empty($val['Listed']) ? (int)$val['Listed'] : false;
                            $item->Live = !empty($val['Live']) ? (int)$val['Live'] : false;
                        } else {
                            $decoded = json_decode($val, true);
                            $item->NR = $decoded['NR'] ?? '';
                            $item->Listed = !empty($decoded['Listed']) ? (int)$decoded['Listed'] : false;
                            $item->Live = !empty($decoded['Live']) ? (int)$decoded['Live'] : false;
                        }
                    }
                }

                return $item;
            }, $filteredData);

            $processedData = array_values($processedData);

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $processedData,
                'status' => 200,
                'debug' => [
                    'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                    'matched_parents' => collect($processedData)
                        ->filter(fn($item) => isset($item->scout_data))
                        ->pluck('Parent')
                        ->unique()
                        ->values()
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
    }


    public function updateAllWayfairSkus(Request $request)
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
                ['marketplace' => 'Wayfair'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('wayfair_marketplace_percentage', $percent, now()->addDays(30));

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

        $dataView = WayfairDataView::firstOrNew(['sku' => $sku]);
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
        $product = WayfairDataView::firstOrCreate(
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

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Wayfair')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function importWayfairAnalytics(Request $request)
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
                WayfairDataView::updateOrCreate(
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

    public function exportWayfairAnalytics()
    {
        $wayfairData = WayfairDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($wayfairData as $data) {
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
        $fileName = 'Wayfair_Analytics_Export_' . date('Y-m-d') . '.xlsx';

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
        $fileName = 'Wayfair_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function wayfairPricingView()
    {
        return view('market-places.wayfair_pricing_view');
    }

    public function downloadWayfairPricingPriceSample()
    {
        $fileName = 'wayfair_pricing_sample.csv';
        // Maps to wayfair_pricing_prices: sku ← Supplier Part Number (or column "sku"), price, wayfair_stock
        $rows = [
            ['Supplier Part Number', 'price', 'wayfair stock'],
            ['WF-DEMO-001', '24.99', '180'],
            ['WF-DEMO-002', '19.50', '0'],
            ['BREQ-EXAMPLE-03', '42.00', '25'],
            ['MY-PART-KEY-04', '12.25', '1000'],
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
     * Excel/CSV exports often embed NBSP (U+00A0) or raw 0xA0 bytes; invalid as lone bytes in utf8mb4.
     * Map NBSP variants to ASCII space; drop invalid UTF-8 (aligned with getWayfairPricingData $normalizeSku).
     */
    private function normalizeWayfairPricingSku(string $raw): string
    {
        $s = (string) $raw;
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', $s);
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        $s = trim($clean !== false ? $clean : $s);

        return preg_replace('/\s+/u', ' ', $s);
    }

    public function uploadWayfairPricingPriceSheet(Request $request)
    {
        $request->validate([
            'price_file' => 'required|file',
        ]);

        try {
            $file = $request->file('price_file');
            $path = $file->getPathName();
            $extension = strtolower($file->getClientOriginalExtension());

            if (in_array($extension, ['xlsx', 'xls'], true) || $this->isExcelFileForWayfairPricing($path)) {
                $spreadsheet = IOFactory::load($path);
                $sheetRows = $spreadsheet->getActiveSheet()->toArray();
                $sheetRows = $this->dropWayfairPricingHeaderRows($sheetRows);
                if (empty($sheetRows)) {
                    return response()->json(['success' => false, 'message' => 'No data rows after headers.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }
                $headerRow = array_shift($sheetRows);
                $indexes = $this->resolveWayfairPricingColumnIndexes($headerRow);
                $skuIndex = $indexes['sku'];
                $priceIndex = $indexes['price'];
                $stockIndex = $indexes['stock'];

                if ($skuIndex === false || $priceIndex === false) {
                    $preview = implode(', ', array_slice(array_map(
                        fn ($h) => $this->normalizeWayfairPricingHeaderCell($h),
                        $headerRow
                    ), 0, 30));

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not find Supplier Part Number (or SKU) and price columns. Expected Wayfair pricing sheet (Supplier Part Number, New Base Cost / Current Base Cost). Seen: [' . $preview . '].',
                    ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $updated = 0;
                foreach ($sheetRows as $row) {
                    $sku = $this->normalizeWayfairPricingSku(trim((string) ($row[$skuIndex] ?? '')));
                    if ($sku === '') {
                        continue;
                    }
                    $rawPrice = trim((string) ($row[$priceIndex] ?? ''));
                    $price = (float) preg_replace('/[^0-9.\-]/', '', $rawPrice);
                    $wfStock = $stockIndex !== false ? (int) preg_replace('/[^0-9\-]/', '', trim((string) ($row[$stockIndex] ?? '0'))) : 0;
                    WayfairPricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        ['price' => max(0, $price), 'wayfair_stock' => max(0, $wfStock)]
                    );
                    $updated++;
                }
            } else {
                $handle = fopen($path, 'r');
                if (!$handle) {
                    return response()->json(['success' => false, 'message' => 'Cannot open uploaded file.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
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

                $allRows = [];
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $allRows[] = $row;
                }
                fclose($handle);

                $allRows = $this->dropWayfairPricingHeaderRows($allRows);
                if (empty($allRows)) {
                    return response()->json(['success' => false, 'message' => 'Price sheet is empty.'], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $headerRow = array_shift($allRows);
                $indexes = $this->resolveWayfairPricingColumnIndexes($headerRow);
                $skuIndex = $indexes['sku'];
                $priceIndex = $indexes['price'];
                $stockIndex = $indexes['stock'];

                if ($skuIndex === false || $priceIndex === false) {
                    $preview = implode(', ', array_slice(array_map(
                        fn ($h) => $this->normalizeWayfairPricingHeaderCell($h),
                        $headerRow
                    ), 0, 30));

                    return response()->json([
                        'success' => false,
                        'message' => 'Could not find Supplier Part Number (or SKU) and price columns. Seen: [' . $preview . '].',
                    ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
                }

                $updated = 0;
                foreach ($allRows as $row) {
                    if (!$row || count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0) {
                        continue;
                    }
                    $sku = $this->normalizeWayfairPricingSku(trim((string) ($row[$skuIndex] ?? '')));
                    if ($sku === '') {
                        continue;
                    }
                    $rawPrice = trim((string) ($row[$priceIndex] ?? ''));
                    $price = (float) preg_replace('/[^0-9.\-]/', '', $rawPrice);
                    $wfStock = $stockIndex !== false ? (int) preg_replace('/[^0-9\-]/', '', trim((string) ($row[$stockIndex] ?? '0'))) : 0;
                    WayfairPricingPrice::updateOrCreate(
                        ['sku' => $sku],
                        ['price' => max(0, $price), 'wayfair_stock' => max(0, $wfStock)]
                    );
                    $updated++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Price sheet uploaded successfully. {$updated} SKU rows updated.",
                'updated' => $updated,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Wayfair pricing upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Price upload failed: ' . $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    public function getWayfairPricingData(Request $request)
    {
        try {
            $salesAgg = WayfairDailyData::query()
                ->selectRaw(
                    'sku, SUM(COALESCE(quantity, 0)) as al30, '
                    . 'SUM(COALESCE(unit_price, 0) * COALESCE(quantity, 0)) as sales'
                )
                ->where('period', 'l30')
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

            $uploadedPriceBySku = WayfairPricingPrice::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $allNormalizedSkus = collect(array_merge(
                $salesBySku->keys()->all(),
                $productMastersBySku->keys()->all(),
                $uploadedPriceBySku->keys()->all()
            ))->unique()->values();

            $viewMetaBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $viewMetaBySku = WayfairDataView::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku));
            }

            // Load full Shopify map like Product Master — whereIn(UPPER(TRIM(sku))) misses UTF-8 NBSP / variant spacing.
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            $listingStatusBySku = collect();
            if ($allNormalizedSkus->isNotEmpty()) {
                $listingStatusBySku = WayfairListingStatus::query()
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus)
                    ->get()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku));
            }

            // Same as Faire / Forecast Analysis: forecast_analysis.nr (NRP).
            $forecastNrBySku = [];
            if ($allNormalizedSkus->isNotEmpty()) {
                $faRows = DB::table('forecast_analysis')
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $allNormalizedSkus->values()->all())
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
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 100) : 100;
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
                $wfStock = $priceRow ? (int) ($priceRow->wayfair_stock ?? 0) : 0;

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
                // Same as AliExpress pricing: missing when no product master or no positive uploaded channel price.
                $isMissing = ! $productMaster || $price <= 0;

                if ($isMissing) {
                    $mapValue = '';
                } elseif ($inv === $wfStock) {
                    $mapValue = 'Map';
                } else {
                    $diff = abs($inv - $wfStock);
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

                $faRec = $forecastNrBySku[$normalizedSku] ?? null;
                $nrOut = '';
                if ($faRec && $faRec->nr !== null && trim((string) $faRec->nr) !== '') {
                    $nrOut = strtoupper(trim((string) $faRec->nr));
                    if (! in_array($nrOut, ['REQ', 'NR', 'LATER'], true)) {
                        $nrOut = 'REQ';
                    }
                }

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
                    'ae_stock' => $wfStock,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                    'nr' => $nrOut,
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

            $rows = $this->insertWayfairParentRows($rows);
            $this->saveWayfairPricingSnapshot($rows);

            return response()->json($rows, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Error fetching Wayfair pricing data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch pricing data: ' . $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    public function saveWayfairSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
            $percentage = $marketplaceData ? (float) ($marketplaceData->percentage ?? 100) : 100;
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

                $view = WayfairDataView::firstOrNew(['sku' => $sku]);
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
            Log::error('Wayfair SPRICE save failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function wayfairBadgeChartData(Request $request)
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
            $rows = AmazonChannelSummary::where('channel', 'wayfair')
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
            Log::error('Wayfair badge chart data error: ' . $e->getMessage());

            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    /**
     * Remove Wayfair export title / instruction rows; return rows starting at the real header
     * (Supplier Part Number and/or SKU column).
     *
     * @param  array<int, array>  $rows
     * @return array<int, array>
     */
    private function dropWayfairPricingHeaderRows(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $joined = strtolower(implode(' ', array_map(fn ($c) => (string) $c, $row)));
            if (str_contains($joined, 'pre-filled')
                || str_contains($joined, 'do not edit')
                || (str_contains($joined, 'enter a numerical') && str_contains($joined, 'decimal'))) {
                continue;
            }
            $norm = array_map(fn ($h) => $this->normalizeWayfairPricingHeaderCell($h), $row);
            $hasSkuHeader = false;
            foreach ($norm as $cell) {
                if ($cell === 'supplier part number' || ($cell !== '' && str_contains($cell, 'supplier part number'))) {
                    $hasSkuHeader = true;
                    break;
                }
                if ($cell === 'sku' || ($cell !== '' && preg_match('/\bsku\b/', $cell))) {
                    $hasSkuHeader = true;
                    break;
                }
            }
            if ($hasSkuHeader) {
                return array_slice($rows, $i);
            }
        }

        return $rows;
    }

    private function normalizeWayfairPricingHeaderCell($value): string
    {
        $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9_ ]/', ' ', (string) $value)));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * @return array{sku:int|false,price:int|false,stock:int|false}
     */
    private function resolveWayfairPricingColumnIndexes(array $headerRow): array
    {
        $headers = [];
        foreach ($headerRow as $i => $h) {
            $headers[$i] = $this->normalizeWayfairPricingHeaderCell($h);
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

        // Primary key for matching inventory/sales: Supplier Part Number (Wayfair export).
        $supplierPartExact = ['supplier part number', 'supplier part no', 'supplier part #'];
        $skuIndex = $findExact($headers, $supplierPartExact);
        if ($skuIndex === false) {
            $skuIndex = $findContains($headers, ['supplier part number']);
        }
        if ($skuIndex === false) {
            $skuNames = ['sku', 'wayfair sku', 'supplier sku', 'child sku'];
            $skuIndex = $findExact($headers, $skuNames);
        }
        if ($skuIndex === false) {
            foreach ($headers as $idx => $h) {
                if ($h !== '' && preg_match('/\bsku\b/', $h)) {
                    $skuIndex = $idx;
                    break;
                }
            }
        }

        $priceNames = [
            'new base cost',
            'new base cost usd',
            'current base cost',
            'current base cost usd',
            'new map usd',
            'current map usd',
            'new msrp usd',
            'base cost',
            'price',
        ];
        $priceIndex = $findExact($headers, $priceNames);
        if ($priceIndex === false) {
            $priceIndex = $findContains($headers, [
                'new base cost',
                'current base cost',
                'new map',
                'base cost',
            ]);
        }

        $stockNames = [
            'wayfair stock',
            'wayfair_stock',
            'stock',
            'inventory',
            'on hand',
            'quantity',
            'qty',
        ];
        $stockIndex = $findExact($headers, $stockNames);
        if ($stockIndex === false) {
            $stockIndex = $findContains($headers, ['wayfair stock', 'wayfair_stock', 'inventory', 'on hand']);
        }

        return [
            'sku' => $skuIndex,
            'price' => $priceIndex,
            'stock' => $stockIndex !== false ? $stockIndex : false,
        ];
    }

    private function insertWayfairParentRows(array $rows): array
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
                    $result[] = $this->buildWayfairParentRow($currentParent, $group);
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
                    $result[] = $this->buildWayfairParentRow($currentParent, $group);
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
            $result[] = $this->buildWayfairParentRow($currentParent, $group);
        }

        return $result;
    }

    private function buildWayfairParentRow(string $parentName, array $childRows): array
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
            'nr' => '',
        ];
    }

    private function saveWayfairPricingSnapshot(array $rows): void
    {
        try {
            $today = now()->toDateString();

            $allChildRows = collect($rows)->filter(fn ($r) => !($r['is_parent'] ?? false));
            if ($allChildRows->isEmpty()) {
                return;
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
            $pct = $marketplaceData ? (float) ($marketplaceData->percentage ?? 100) : 100;
            $orderKeep = $pct / 100;

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
                ['channel' => 'wayfair', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved Wayfair pricing snapshot']
            );
        } catch (\Exception $e) {
            Log::error('Wayfair daily snapshot save failed: ' . $e->getMessage());
        }
    }

    private function isExcelFileForWayfairPricing(string $path): bool
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

    public function getWayfairPricingColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "wayfair_pricing_tabulator_column_visibility_{$userId}";

        return response()->json(Cache::get($key, []));
    }

    public function setWayfairPricingColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "wayfair_pricing_tabulator_column_visibility_{$userId}";
        $visibility = $request->input('visibility', []);
        Cache::put($key, $visibility, now()->addDays(365));

        return response()->json(['success' => true]);
    }
}
