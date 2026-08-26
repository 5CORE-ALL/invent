<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\AliexpressDataView;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressDailyData;
use App\Models\AliexpressDailyDataL60;
use App\Models\AliexpressLmpDataSheet;
use App\Models\AliexpressPricingPrice;
use App\Models\AliexpressMetric;
use App\Models\ChannelMaster;
use App\Services\AliExpressApiService;
use App\Services\ChannelPromoPricingService;
use App\Models\AmazonChannelSummary;
use App\Models\AmazonDataView;
use App\Models\ChannelMasterCalculatedData;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $shopifyData = ShopifySku::mapByProductSkus($skus);

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
     * Upload Aliexpress L30 daily data (aliexpress_daily_data).
     */
    public function uploadDailyDataChunk(Request $request)
    {
        return $this->uploadAliexpressSalesSheetChunk($request, 'l30');
    }

    /**
     * Upload Aliexpress L60 daily data (aliexpress_daily_data_l60) — same export format as L30.
     */
    public function uploadDailyDataL60Chunk(Request $request)
    {
        return $this->uploadAliexpressSalesSheetChunk($request, 'l60');
    }

    /**
     * @param  'l30'|'l60'  $period
     */
    private function uploadAliexpressSalesSheetChunk(Request $request, string $period)
    {
        try {
            $file = $request->file('file');
            $chunk = (int) $request->input('chunk', 0);
            $totalChunks = (int) $request->input('totalChunks', 1);

            if (! $file) {
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
            }

            $spreadsheet = IOFactory::load($file->getPathname());
            $rows = $spreadsheet->getActiveSheet()->toArray();

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'File is empty'], 400);
            }

            $headers = $this->trimSheetHeaders(array_shift($rows));
            $modelClass = $period === 'l60' ? AliexpressDailyDataL60::class : AliexpressDailyData::class;

            if ($chunk === 0) {
                $modelClass::truncate();
                Log::info('Aliexpress '.$period.' daily data table truncated');
            }

            $imported = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                try {
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $rowData = $this->sheetRowFromHeaders($headers, $row);
                    $modelClass::create($this->mapAliexpressSheetRowToAttributes($rowData));
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
                    Log::error('Error importing Aliexpress '.$period.' row '.($index + 2).': '.$e->getMessage());
                }
            }

            $isLastChunk = ($chunk + 1) >= $totalChunks;

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk uploaded. Imported: $imported records".($errors ? ', Errors: '.count($errors) : ''),
                'imported' => $imported,
                'errors' => $errors,
                'isLastChunk' => $isLastChunk,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('Aliexpress '.$period.' upload error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map one AliExpress order-export row to daily-data attributes (L30 or L60 table).
     *
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    private function mapAliexpressSheetRowToAttributes(array $rowData): array
    {
        $skuCodeRaw = $this->sheetCell($rowData, ['SKU code', 'SKU Code']);
        [$sku, $quantity] = $this->parseAliexpressSkuAndQuantity($skuCodeRaw ?? '');

        return [
            'order_id' => $this->sheetCell($rowData, ['Order Number', 'Order ID']),
            'order_status' => $this->sheetCell($rowData, ['Order Status']),
            'owner' => $this->sheetCell($rowData, ['Owner']),
            'buyer_name' => $this->sheetCell($rowData, ['Buyer Name']),
            'order_date' => $this->parseDate($this->sheetCell($rowData, ['Order date', 'Order Date'])),
            'payment_time' => $this->parseDate($this->sheetCell($rowData, ['Payment time'])),
            'payment_method' => $this->sheetCell($rowData, ['Payment method']),
            'supply_price' => $this->firstSanitizedPriceFromRow($rowData, [
                'Supply Price',
                'Supply price',
            ]),
            'product_total' => $this->firstSanitizedPriceFromRow($rowData, [
                'Product Total',
                'Product total',
                'PRODUCT TOTAL',
            ]),
            'shipping_cost' => $this->sanitizePrice($this->sheetCell($rowData, ['Shipping Cost'])),
            'estimated_vat' => $this->sanitizePrice($this->sheetCell($rowData, ['Estimated VAT'])),
            'platform_collects' => $this->sheetCell($rowData, ['Whether the platform collects and pays for itself']),
            'order_amount' => $this->sanitizePrice($this->sheetCell($rowData, ['Order Amount', 'Order amount'])),
            'ddp_tariff' => $this->sanitizePrice($this->sheetCell($rowData, ['DDP tariff'])),
            'store_promotion' => $this->sanitizePrice($this->sheetCell($rowData, ['Store Promotion'])),
            'store_direct_discount' => $this->sanitizePrice($this->sheetCell($rowData, ['Store Direct Discount'])),
            'platform_coupon' => $this->sanitizePrice($this->sheetCell($rowData, ['Platform Coupon'])),
            'item_id' => $this->sheetCell($rowData, ['Item ID']),
            'product_information' => $this->sheetCell($rowData, ['Product Information']),
            'ean_code' => $this->sheetCell($rowData, ['EANcode']),
            'sku_code' => $sku,
            'quantity' => $quantity,
            'order_note' => $this->sheetCell($rowData, ['Order Note']),
            'complete_shipping_address' => $this->sheetCell($rowData, ['Complete shipping address']),
            'receiver_name' => $this->sheetCell($rowData, ['Receiver name', 'Receiver Name']),
            'buyer_country' => $this->sheetCell($rowData, ['Buyer Country']),
            'state_province' => $this->sheetCell($rowData, ['State/Province']),
            'city' => $this->sheetCell($rowData, ['City']),
            'detailed_address' => $this->sheetCell($rowData, ['Detailed address']),
            'zip_code' => $this->sheetCell($rowData, ['Zip code', 'Zip Code']),
            'national_address' => $this->sheetCell($rowData, ['National address (used only in SA)']),
            'email' => $this->sheetCell($rowData, ['Email']),
            'phone' => $this->sheetCell($rowData, ['Phone', 'Phone ']),
            'mobile' => $this->sheetCell($rowData, ['Mobile']),
            'tax_number' => $this->sheetCell($rowData, ['Tax number']),
            'shipping_method' => $this->sheetCell($rowData, ['Shipping Method']),
            'shipping_deadline' => $this->parseDate($this->sheetCell($rowData, ['Shipping Deadline'])),
            'tracking_number' => $this->sheetCell($rowData, ['Tracking number']),
            'shipping_time' => $this->parseDate($this->sheetCell($rowData, ['Shipping Time'])),
            'buyer_confirmation_time' => $this->parseDate($this->sheetCell($rowData, ['Buyer Confirmation Time'])),
            'order_type' => $this->sheetCell($rowData, ['Order type']),
        ];
    }

    /**
     * Channel master L30/L60 totals (same rules as tabulator summaries).
     *
     * @return array<string, float|int>|null
     */
    public function aggregateOrderRowsForChannelMaster(string $period = 'l30'): ?array
    {
        $rows = $period === 'l60'
            ? (Schema::hasTable('aliexpress_daily_data_l60') ? AliexpressDailyDataL60::all() : collect())
            : AliexpressDailyData::all();

        if ($rows->isEmpty()) {
            return null;
        }

        return $this->aggregateAliexpressOrderRows($rows);
    }

    /**
     * L60 summary badges for aliexpress-tabulator (from aliexpress_daily_data_l60).
     */
    public function getL60Sales(Request $request)
    {
        try {
            if (! Schema::hasTable('aliexpress_daily_data_l60')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_sales' => 0,
                        'total_orders' => 0,
                        'total_quantity' => 0,
                    ],
                ]);
            }

            $agg = $this->aggregateAliexpressOrderRows(AliexpressDailyDataL60::all());

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sales' => round($agg['total_sales'], 2),
                    'total_orders' => $agg['total_orders'],
                    'total_quantity' => $agg['total_quantity'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Aliexpress L60 sales error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load L60 sales: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AliexpressDailyData|AliexpressDailyDataL60>  $rows
     * @return array{total_orders: int, total_quantity: int, total_sales: float, total_cogs: float, total_pft: float, pft_percentage: float, roi_percentage: float, avg_price: float}
     */
    private function aggregateAliexpressOrderRows($rows): array
    {
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper(trim((string) $item->sku));
        });

        // Margin from Active Channel Master (same as /aliexpress-pricing).
        $percentage = $this->resolveAliexpressMarginPercent();
        $margin = $percentage / 100.0;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;

        foreach ($rows as $row) {
            $status = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($status, 'refund') || str_contains($status, 'return')
                || str_contains($status, 'cancel') || str_contains($status, 'closed')) {
                continue;
            }

            if (empty($row->sku_code) || empty($row->order_id)) {
                continue;
            }

            $quantity = max(1, (int) ($row->quantity ?? 1));

            $lineRevenue = (float) ($row->product_total ?? 0);
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->supply_price ?? 0);
            }
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->order_amount ?? 0);
            }

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $lineRevenue;

            $unitPrice = $quantity > 0 ? $lineRevenue / $quantity : 0.0;
            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $sku = strtoupper(trim((string) ($row->sku_code ?? '')));
            $lp = 0.0;
            $ship = 0.0;

            if ($sku !== '' && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }

                $ship = isset($values['ship'])
                    ? (float) $values['ship']
                    : (isset($pm->ship) ? (float) $pm->ship : 0.0);
            }

            $totalCogs += $lp * $quantity;
            $totalPft += (($unitPrice * $margin) - $lp - $ship) * $quantity;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0.0,
            'roi_percentage' => $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0,
            'avg_price' => $avgPrice,
        ];
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
        $sumInv = $sumOvL30 = $sumAeStock = $sumAl30 = $sumSales = $sumViews = $sumOutputOrder = 0;
        $sumReviews = $ratingWeight = $ratingAcc = 0;
        $sumProfit = $sumLp = 0;
        $seenViewProducts = [];

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

            $views = (int) ($r['views'] ?? 0);
            $outputOrder = (int) ($r['output_order'] ?? 0);
            $reviews = (int) ($r['reviews'] ?? 0);
            $rating = (float) ($r['avg_rating'] ?? 0);
            $pid = trim((string) ($r['ae_product_id'] ?? ''));
            if ($pid !== '') {
                if (! isset($seenViewProducts[$pid])) {
                    $seenViewProducts[$pid] = true;
                    $sumViews += $views;
                    $sumOutputOrder += $outputOrder;
                    $sumReviews += $reviews;
                    if ($rating > 0) {
                        $w = max(1, $reviews);
                        $ratingAcc += $rating * $w;
                        $ratingWeight += $w;
                    }
                }
            } else {
                $sumViews += $views;
                $sumOutputOrder += $outputOrder;
                $sumReviews += $reviews;
                if ($rating > 0) {
                    $w = max(1, $reviews);
                    $ratingAcc += $rating * $w;
                    $ratingWeight += $w;
                }
            }
        }

        $dilPct  = $sumInv   > 0 ? round(($sumOvL30 / $sumInv) * 100, 2) : 0;
        $gpftPct = $sumSales > 0 ? (int) round(($sumProfit / $sumSales) * 100) : 0;

        $key = 'PARENT ' . $parentName;
        return [
            'sku'         => $key,
            'parent'      => $key,
            'is_parent'   => true,
            'is_parent_summary' => true,
            'is_parent_row' => true,
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
            'views'       => (int) $sumViews,
            'output_order' => (int) $sumOutputOrder,
            'cvr'         => $sumViews > 0 ? round(($sumOutputOrder / $sumViews) * 100, 2) : 0,
            'reviews'     => (int) $sumReviews,
            'avg_rating'  => $ratingWeight > 0 ? round($ratingAcc / $ratingWeight, 2) : 0,
            'ae_stock'    => (int) $sumAeStock,
            'dil_percent' => $dilPct,
            'lmp'         => null,
            'lmp_link'    => null,
            'lmp_entries' => [],
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
     * First numeric price found under any of the given export column names (handles header spelling variants).
     */
    private function firstSanitizedPriceFromRow(array $rowData, array $headerCandidates): ?float
    {
        foreach ($headerCandidates as $name) {
            if (!array_key_exists($name, $rowData)) {
                continue;
            }
            $v = $this->sanitizePrice($rowData[$name]);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function trimSheetHeaders(array $headers): array
    {
        return array_map(static function ($h) {
            $s = is_string($h) ? $h : (string) $h;

            return trim(preg_replace('/^\x{FEFF}/u', '', $s));
        }, $headers);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function sheetRowFromHeaders(array $headers, array $row): array
    {
        $out = [];
        $len = min(count($headers), count($row));
        for ($i = 0; $i < $len; $i++) {
            $h = trim((string) ($headers[$i] ?? ''));
            if ($h === '') {
                continue;
            }
            $out[$h] = $row[$i];
        }

        return $out;
    }

    /**
     * Read export cell by exact or case-insensitive header name.
     *
     * @param  array<string, mixed>  $rowData
     * @param  array<int, string>  $keys
     */
    private function sheetCell(array $rowData, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $rowData)) {
                $v = $rowData[$key];
                if ($v !== null && $v !== '') {
                    return trim((string) $v);
                }
            }
        }

        $lower = [];
        foreach ($rowData as $k => $v) {
            $lower[strtolower(trim((string) $k))] = $v;
        }
        foreach ($keys as $key) {
            $lk = strtolower(trim($key));
            if (! array_key_exists($lk, $lower)) {
                continue;
            }
            $v = $lower[$lk];
            if ($v !== null && $v !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }

    /**
     * "LS 120 CRANK * 2" → sku LS 120 CRANK, quantity 2 (trailing " * N" only).
     *
     * @return array{0: string, 1: int}
     */
    private function parseAliexpressSkuAndQuantity(string $skuCodeRaw): array
    {
        $skuCodeRaw = trim($skuCodeRaw);
        if ($skuCodeRaw === '') {
            return ['', 1];
        }

        if (preg_match('/\s*\*\s*(\d+)\s*$/u', $skuCodeRaw, $m)) {
            $sku = trim((string) preg_replace('/\s*\*\s*\d+\s*$/u', '', $skuCodeRaw));

            return [$sku, max(1, (int) $m[1])];
        }

        return [$skuCodeRaw, 1];
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

            // Fetch LP and Ship from ProductMaster for these SKUs (exact + normalized keys)
            $productMasters = [];
            $productMastersByNorm = [];
            if (! empty($skus)) {
                $pmRows = ProductMaster::whereIn('sku', $skus)->get();
                $productMasters = $pmRows->keyBy('sku');
                $productMastersByNorm = $pmRows->keyBy(static function ($pm) {
                    return strtoupper(trim((string) $pm->sku));
                });
            }

            // Net revenue % after fees: Active Channel Master (same as /aliexpress-pricing).
            $percentage = $this->resolveAliexpressMarginPercent();
            $margin = $percentage / 100.0;

            $data = [];
            foreach ($aliexpressData as $item) {
                $sku = $item->sku_code;
                $lp = 0;
                $ship = 0;

                // Get LP and Ship from ProductMaster (using normal 'ship' field, not 'temu_ship')
                // Pattern matches Temu extraction logic
                $productMaster = $productMasters[$sku]
                    ?? $productMastersByNorm[strtoupper(trim((string) $sku))] ?? null;
                if ($productMaster !== null) {
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

                // Line total: prefer product_total; some exports only fill supply_price;
                // older exports drop both and only fill order_amount. Without the
                // order_amount fallback those rows ended up with unit_price = 0,
                // making per-row PFT = −(lp+ship)*qty (a loss) even though revenue
                // existed — which made /aliexpress-tabulator's PFT % drift below
                // /all-marketplace-master's number. Same fallback as
                // aggregateAliexpressOrderRows() — keep them aligned.
                $quantity = max(1, (int) $item->quantity);
                $lineTotal = (float) ($item->product_total ?? 0);
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($item->supply_price ?? 0);
                }
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($item->order_amount ?? 0);
                }
                $unitPrice = $lineTotal > 0 ? $lineTotal / $quantity : 0;

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
        return view('market-places.aliexpress_pricing_view', [
            'marginPercent' => $this->resolveAliexpressMarginPercent(),
        ]);
    }

    /**
     * AliExpress API Sync card / endpoints — software@5core.com only.
     */
    protected function canUseAliexpressApiSync(): bool
    {
        return strtolower(trim((string) (auth()->user()->email ?? ''))) === 'software@5core.com';
    }

    /**
     * Keep-rate % from marketplace_percentages (marketplace = Aliexpress / AliExpress).
     */
    protected function resolveAliexpressMarginPercent(): float
    {
        $mp = MarketplacePercentage::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(marketplace)) = ?', ['aliexpress']);
            })
            ->orderBy('id')
            ->first();

        $pct = ($mp !== null && is_numeric($mp->percentage ?? null) && (float) $mp->percentage > 0)
            ? (float) $mp->percentage
            : 89.0;

        return $pct;
    }

    /**
     * Sync price and/or orders from AliExpress Open Platform API into pricing tables.
     *
     * mode=price  → listed products → aliexpress_pricing_prices (+ aliexpress_metric)
     * mode=orders → order L30/L60   → aliexpress_metric
     * mode=views  → L30 page views  → aliexpress_metric.views
     * mode=reviews → review count + rating → aliexpress_metric.reviews
     * mode=both   → price + orders (default)
     */
    public function syncPricingFromApi(Request $request)
    {
        if (! $this->canUseAliexpressApiSync()) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized.',
            ], 403, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (empty(config('services.aliexpress.access_token'))) {
            return response()->json([
                'success' => false,
                'message' => 'ALIEXPRESS_ACCESS_TOKEN is missing in .env. Run: php artisan aliexpress:auth-url',
            ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        try {
            @set_time_limit(0);

            $mode = strtolower(trim((string) $request->input('mode', 'both')));
            if (! in_array($mode, ['price', 'orders', 'views', 'reviews', 'both'], true)) {
                $mode = 'both';
            }

            $options = [];
            if ($mode === 'price') {
                $options['--listed'] = true;
            } elseif ($mode === 'orders') {
                $options['--orders'] = true;
            } elseif ($mode === 'views') {
                $options['--views'] = true;
            } elseif ($mode === 'reviews') {
                $options['--reviews'] = true;
            } else {
                $options['--listed'] = true;
                $options['--orders'] = true;
            }

            if ($request->boolean('replace') && ($mode === 'price' || $mode === 'both')) {
                $options['--replace'] = true;
            }

            $days = (int) $request->input('days', 60);
            if ($days > 0 && ($mode === 'orders' || $mode === 'both')) {
                $options['--days'] = max(1, min(180, $days));
            }

            $exitCode = Artisan::call('app:fetch-aliexpress-metrics', $options);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => $output !== '' ? $output : 'AliExpress API sync failed.',
                    'mode' => $mode,
                ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $fallback = match ($mode) {
                'price' => 'AliExpress price/stock synced from API.',
                'orders' => 'AliExpress orders (AL30) synced from API.',
                'views' => 'AliExpress page views + CVR (L30) synced from API.',
                'reviews' => 'AliExpress reviews synced from API.',
                default => 'AliExpress price and orders synced from API.',
            };

            return response()->json([
                'success' => true,
                'message' => $output !== '' ? $output : $fallback,
                'mode' => $mode,
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('AliExpress API pricing sync failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'API sync failed: '.$e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Push selected SKU prices live to AliExpress (same pattern as Newegg / TopDawg pricing pages).
     *
     * Request: { updates: [ { sku, price|sprice }, ... ] }
     * Uses SPRICE when provided; resolves product_id from aliexpress_metric.
     */
    public function pushPricingPrice(Request $request, AliExpressApiService $api)
    {
        if (empty(config('services.aliexpress.access_token'))) {
            return response()->json([
                'success' => false,
                'message' => 'ALIEXPRESS_ACCESS_TOKEN is missing in .env.',
                'pushed' => 0,
                'failed' => 0,
                'results' => [],
            ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $updates = $request->input('updates', []);
        if (! is_array($updates) || $updates === []) {
            return response()->json([
                'success' => false,
                'message' => 'No updates provided.',
                'pushed' => 0,
                'failed' => 0,
                'results' => [],
            ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        try {
            @set_time_limit(0);

            if (! Schema::hasTable('aliexpress_metric')) {
                return response()->json([
                    'success' => false,
                    'message' => 'aliexpress_metric table missing. Run Sync Price first.',
                    'pushed' => 0,
                    'failed' => 0,
                    'results' => [],
                ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $metricByNorm = [];
            AliexpressMetric::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->get(['sku', 'product_id'])
                ->each(function (AliexpressMetric $row) use (&$metricByNorm) {
                    $norm = $this->normalizeAeSkuExact((string) $row->sku);
                    if ($norm === '' || isset($metricByNorm[$norm])) {
                        return;
                    }
                    $metricByNorm[$norm] = [
                        'sku' => (string) $row->sku,
                        'product_id' => (string) $row->product_id,
                    ];
                });

            $priceRows = [];
            $results = [];
            $skuByKey = [];

            foreach ($updates as $u) {
                if (! is_array($u)) {
                    continue;
                }
                $sku = trim((string) ($u['sku'] ?? ''));
                $price = isset($u['price']) ? (float) $u['price'] : (float) ($u['sprice'] ?? 0);
                if ($sku === '') {
                    continue;
                }
                if ($price <= 0) {
                    $results[] = ['sku' => $sku, 'success' => false, 'error' => 'Price must be > 0', 'price' => null];
                    continue;
                }

                $norm = $this->normalizeAeSkuExact($sku);
                $metric = $metricByNorm[$norm] ?? null;
                if ($metric === null) {
                    $results[] = [
                        'sku' => $sku,
                        'success' => false,
                        'error' => 'No AliExpress listing (product_id) — run Sync Price first',
                        'price' => null,
                    ];
                    continue;
                }

                $rounded = round($price, 2);
                $priceRows[] = [
                    'product_id' => $metric['product_id'],
                    'sku_code' => $metric['sku'],
                    'price' => $rounded,
                ];
                $skuByKey[$metric['product_id'].'|'.$this->normalizeAeSkuExact($metric['sku'])] = [
                    'sku' => $sku,
                    'price' => $rounded,
                    'metric_sku' => $metric['sku'],
                ];
            }

            if ($priceRows === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid SKU/price pairs to push.',
                    'pushed' => 0,
                    'failed' => count($results),
                    'results' => $results,
                ], 422, [], JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $apiResult = $api->batchUpdatePrice($priceRows);
            $apiOk = ! empty($apiResult['success']);
            $pushed = 0;

            if ($apiOk) {
                foreach ($priceRows as $row) {
                    $skuCode = (string) $row['sku_code'];
                    $price = (float) $row['price'];
                    $norm = $this->normalizeAeSkuExact($skuCode);

                    AliexpressMetric::query()
                        ->where(function ($q) use ($skuCode, $norm) {
                            $q->where('sku', $skuCode);
                            if ($norm !== '') {
                                $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                            }
                        })
                        ->update(['price' => $price]);

                    AliexpressPricingPrice::updateOrCreate(
                        ['sku' => $norm !== '' ? $norm : $skuCode],
                        ['price' => $price]
                    );

                    $localSku = $skuByKey[$row['product_id'].'|'.$norm]['sku'] ?? $skuCode;
                    $results[] = [
                        'sku' => $localSku,
                        'success' => true,
                        'price' => $price,
                        'error' => null,
                    ];
                    $pushed++;
                }
            } else {
                $err = (string) ($apiResult['message'] ?? 'AliExpress price push failed.');
                foreach ($priceRows as $row) {
                    $norm = $this->normalizeAeSkuExact((string) $row['sku_code']);
                    $localSku = $skuByKey[$row['product_id'].'|'.$norm]['sku'] ?? $row['sku_code'];
                    $results[] = [
                        'sku' => $localSku,
                        'success' => false,
                        'price' => (float) $row['price'],
                        'error' => $err,
                    ];
                }
            }

            $failed = count(array_filter($results, static fn ($r) => empty($r['success'])));

            return response()->json([
                'success' => $pushed > 0,
                'message' => $apiOk
                    ? "Pushed {$pushed} price(s) to AliExpress."
                    : ($apiResult['message'] ?? 'AliExpress price push failed.'),
                'pushed' => $pushed,
                'failed' => $failed,
                'results' => array_values($results),
            ], $pushed > 0 ? 200 : 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('AliExpress pricing push failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Push failed: '.$e->getMessage(),
                'pushed' => 0,
                'failed' => 0,
                'results' => [],
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Get aggregated SKU pricing data (uploaded sheet + daily order data).
     */
    public function getPricingData(Request $request)
    {
        try {
            // #region agent log
            $__dbgEntry = json_encode([
                'sessionId' => '1b30dc',
                'runId' => 'post-fix',
                'hypothesisId' => 'E',
                'location' => 'AliexpressController.php:getPricingData:entry',
                'message' => 'getPricingData called',
                'data' => ['include_parents' => $request->boolean('include_parents')],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n";
            @file_put_contents(base_path('.cursor/debug-1b30dc.log'), $__dbgEntry, FILE_APPEND);
            @file_put_contents(storage_path('logs/debug-1b30dc.log'), $__dbgEntry, FILE_APPEND);
            // #endregion
            $normalizeSku = fn ($value) => $this->normalizeAeSkuExact((string) $value);

            $normalizeLmpSku = static function ($value) {
                $s = strtoupper(trim((string) $value));
                $s = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $s);
                $s = preg_replace('/\s+/', ' ', $s);

                return $s;
            };

            $excludedStatuses = ['refund', 'return', 'cancel', 'closed'];
            $salesBySku = collect();

            if (Schema::hasTable('aliexpress_daily_data')) {
                $salesAgg = AliexpressDailyData::query()
                    ->selectRaw(
                        'sku_code, SUM(COALESCE(quantity, 0)) as al30, '
                        .'SUM(COALESCE(NULLIF(product_total, 0), NULLIF(supply_price, 0), order_amount, 0)) as sales'
                    )
                    ->whereNotNull('sku_code')
                    ->where('sku_code', '!=', '')
                    ->where(function ($query) use ($excludedStatuses) {
                        foreach ($excludedStatuses as $status) {
                            $query->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ['%'.$status.'%']);
                        }
                    })
                    ->groupBy('sku_code')
                    ->get();

                $salesBySku = $salesAgg->keyBy(fn ($row) => $normalizeSku($row->sku_code));
            }

            $productMasters = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get();

            $productMastersBySku = $productMasters->keyBy(fn ($row) => $normalizeSku($row->sku));

            $pmSkus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $promoMap = app(ChannelPromoPricingService::class)->mapForSkus('aliexpress', $pmSkus);
            $amazonStandardPrices = [];
            foreach (AmazonDataView::whereIn('sku', $pmSkus)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? null;
                if (is_numeric($std) && (float) $std > 0) {
                    $amazonStandardPrices[strtoupper(trim((string) $adv->sku))] = round((float) $std, 2);
                }
            }

            $uploadedPriceBySku = collect();
            if (Schema::hasTable('aliexpress_pricing_prices')) {
                $uploadedPriceBySku = AliexpressPricingPrice::all()
                    ->keyBy(fn ($row) => $normalizeSku($row->sku));
            }

            $viewMetaBySku = AliexpressDataView::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Buyer / Seller links from AliexpressListingStatus.value JSON
            $linksBySku = AliexpressListingStatus::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Same row universe as Amazon tabulator: all product masters + AE upload + daily sales (exact SKU).
            $allNormalizedSkus = collect(array_merge(
                $productMastersBySku->keys()->all(),
                $uploadedPriceBySku->keys()->all(),
                $salesBySku->keys()->all()
            ))->unique()->values();

            // Full Shopify map like Product Master — whereIn(UPPER(TRIM(sku))) misses UTF-8 NBSP / spacing variants.
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            $aeLmpByNormalizedSku = [];
            foreach (AliexpressLmpDataSheet::all() as $lmpRow) {
                $nk = $normalizeLmpSku($lmpRow->sku);
                if (!isset($aeLmpByNormalizedSku[$nk])) {
                    $aeLmpByNormalizedSku[$nk] = $lmpRow;
                }
            }

            $viewsBySku = [];
            $cvrBySku = [];
            $outputOrderBySku = [];
            $reviewsBySku = [];
            $avgRatingBySku = [];
            $productIdBySku = [];
            $listingStatusBySku = [];
            if (Schema::hasTable('aliexpress_metric')) {
                $metricCols = ['sku', 'product_id'];
                $hasViewsCol = Schema::hasColumn('aliexpress_metric', 'views');
                $hasCvrCol = Schema::hasColumn('aliexpress_metric', 'cvr');
                $hasOutputCol = Schema::hasColumn('aliexpress_metric', 'output_order');
                $hasReviewsCol = Schema::hasColumn('aliexpress_metric', 'reviews');
                $hasRatingCol = Schema::hasColumn('aliexpress_metric', 'avg_rating');
                $hasListingStatusCol = Schema::hasColumn('aliexpress_metric', 'listing_status');
                if ($hasViewsCol) {
                    $metricCols[] = 'views';
                }
                if ($hasCvrCol) {
                    $metricCols[] = 'cvr';
                }
                if ($hasOutputCol) {
                    $metricCols[] = 'output_order';
                }
                if ($hasReviewsCol) {
                    $metricCols[] = 'reviews';
                }
                if ($hasRatingCol) {
                    $metricCols[] = 'avg_rating';
                }
                if ($hasListingStatusCol) {
                    $metricCols[] = 'listing_status';
                }
                foreach (AliexpressMetric::query()->get($metricCols) as $metric) {
                    $nk = $normalizeSku($metric->sku);
                    if ($nk === '') {
                        continue;
                    }
                    $pid = trim((string) ($metric->product_id ?? ''));
                    $status = $hasListingStatusCol
                        ? strtolower(trim((string) ($metric->listing_status ?? '')))
                        : '';
                    if ($pid !== '' && ! isset($productIdBySku[$nk])) {
                        $productIdBySku[$nk] = $pid;
                    }
                    if ($status !== '' && ! isset($listingStatusBySku[$nk])) {
                        $listingStatusBySku[$nk] = $status;
                    }
                    if ($status === 'onselling') {
                        $listingStatusBySku[$nk] = $status;
                        if ($pid !== '') {
                            $productIdBySku[$nk] = $pid;
                        }
                    }
                    $views = $hasViewsCol ? (int) ($metric->views ?? 0) : 0;
                    $reviews = $hasReviewsCol ? (int) ($metric->reviews ?? 0) : 0;
                    if (! isset($viewsBySku[$nk]) || $views >= (int) ($viewsBySku[$nk] ?? 0)) {
                        $viewsBySku[$nk] = $views;
                        if ($hasCvrCol) {
                            $cvrBySku[$nk] = (float) ($metric->cvr ?? 0);
                        }
                        if ($hasOutputCol) {
                            $outputOrderBySku[$nk] = (int) ($metric->output_order ?? 0);
                        }
                    }
                    if (! isset($reviewsBySku[$nk]) || $reviews >= (int) ($reviewsBySku[$nk] ?? 0)) {
                        $reviewsBySku[$nk] = $reviews;
                        if ($hasRatingCol) {
                            $avgRatingBySku[$nk] = (float) ($metric->avg_rating ?? 0);
                        }
                    }
                }
            }

            $percentage = $this->resolveAliexpressMarginPercent();
            $margin = ((float) $percentage) / 100;

            $rows = [];
            foreach ($allNormalizedSkus as $normalizedSku) {
                $sale = $salesBySku->get($normalizedSku);
                $productMaster = $productMastersBySku->get($normalizedSku);
                $pmSku = $productMaster->sku ?? '';
                if (stripos((string) $normalizedSku, 'PARENT') !== false
                    || stripos((string) $pmSku, 'PARENT') !== false) {
                    continue;
                }
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
                $priceRow = $uploadedPriceBySku->get($normalizedSku);
                $price = $priceRow ? (float) $priceRow->price : 0;
                $aeStock = $priceRow ? (int) ($priceRow->ae_stock ?? 0) : 0;
                $listingStatus = strtolower(trim((string) ($listingStatusBySku[$normalizedSku] ?? '')));
                $isOfflineAe = in_array($listingStatus, ['offline', 'service_delete'], true);
                if ($isOfflineAe) {
                    $price = 0;
                    $aeStock = 0;
                }

                // INV + OV L30 + image from shopify_skus
                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv       ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity  ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src       ?? null) : null;

                $profit = ($price * $margin) - $lp - $ship;
                $gpft = $price > 0 ? ($profit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profit / $lp) * 100 : 0;

                $displaySku = $productMaster->sku
                    ?? ($priceRow?->sku)
                    ?? ($sale->sku_code ?? $normalizedSku);

                $metaArray = is_array($meta) ? $meta : (is_string($meta) ? json_decode($meta, true) ?: [] : []);
                $nr = $this->resolveAeNrFromMeta($metaArray, $productMaster !== null);

                // Listed on AE = live onSelling row with price (offline / deleted listings stay blank).
                $isMissingAe = $priceRow === null || $price <= 0 || $isOfflineAe;

                $missing = '';
                $mapValue = '';
                if ($inv > 0 && $nr === 'REQ') {
                    if ($isMissingAe || $price <= 0) {
                        $missing = 'M';
                    } elseif ($price > 0) {
                        if ($this->aeInvWithinMapTolerance((float) $inv, (float) $aeStock)) {
                            $mapValue = 'Map';
                        } else {
                            $mapValue = 'N Map|'.(int) round(abs($inv - $aeStock));
                        }
                    }
                }

                // Calculate SPRICE derived values (whole-number %, matches grid)
                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $sprice) * 100) : 0;
                $sroi  = $lp    > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $lp)     * 100) : 0;

                $aeLmpRow = $aeLmpByNormalizedSku[$normalizeLmpSku($displaySku)] ?? $aeLmpByNormalizedSku[$normalizeLmpSku($normalizedSku)] ?? null;
                $lmpEntries = [];
                if ($aeLmpRow) {
                    $entries = $aeLmpRow->lmp_entries;
                    if (is_array($entries) && count($entries) > 0) {
                        $lmpEntries = $entries;
                    } else {
                        if ($aeLmpRow->lmp !== null || $aeLmpRow->lmp_link) {
                            $lmpEntries[] = ['price' => $aeLmpRow->lmp, 'link' => $aeLmpRow->lmp_link];
                        }
                        if ($aeLmpRow->lmp_2 !== null || $aeLmpRow->lmp_link_2) {
                            $lmpEntries[] = ['price' => $aeLmpRow->lmp_2, 'link' => $aeLmpRow->lmp_link_2];
                        }
                    }
                }
                $lmpPrices = array_values(array_filter(array_map(static function ($e) {
                    $p = $e['price'] ?? null;

                    return $p !== null && $p !== '' ? (float) $p : null;
                }, $lmpEntries)));
                $lmp = count($lmpPrices) > 0 ? min($lmpPrices) : ($aeLmpRow ? $aeLmpRow->lmp : null);
                $lmpLink = $lmpEntries[0]['link'] ?? ($aeLmpRow ? $aeLmpRow->lmp_link : null);

                // Buyer / Seller links
                $linkRecord = $linksBySku->get($normalizedSku);
                $linkVal = $linkRecord
                    ? (is_array($linkRecord->value) ? $linkRecord->value : (json_decode($linkRecord->value, true) ?: []))
                    : [];
                $buyerLink = $linkVal['buyer_link'] ?? '';
                $sellerLink = $linkVal['seller_link'] ?? '';
                [$buyerLink, $sellerLink] = $this->aliexpressLinksForProductId(
                    $productIdBySku[$normalizedSku] ?? null,
                    (string) $buyerLink,
                    (string) $sellerLink
                );

                    $views = (int) ($viewsBySku[$normalizedSku] ?? 0);
                    $outputOrder = (int) ($outputOrderBySku[$normalizedSku] ?? 0);
                    $storedCvr = (float) ($cvrBySku[$normalizedSku] ?? 0);
                    // API-only: stored CVR, else output_order ÷ views from AliExpress metrics.
                    if ($views > 0) {
                        $cvr = $storedCvr > 0
                            ? $storedCvr
                            : round(($outputOrder / $views) * 100, 2);
                    } else {
                        $cvr = 0.0;
                    }

                    $row = [
                    'buyer_link'  => $buyerLink,
                    'seller_link' => $sellerLink,
                    'sku'         => trim((string) $displaySku),
                    'parent'      => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent'   => false,
                    'image'       => $imageSrc,
                    'price'       => round($price, 2),
                    'lmp'         => $lmp !== null ? round((float) $lmp, 2) : null,
                    'lmp_link'    => $lmpLink,
                    'lmp_entries' => $lmpEntries,
                    'is_missing_aliexpress' => $isMissingAe,
                    'NR'          => $nr,
                    'missing'     => $missing,
                    'map'         => $mapValue,
                    'gpft'        => (int) round($gpft),
                    'groi'        => (int) round($groi),
                    'profit'      => round($profit, 2),
                    'sales'       => round($sales, 2),
                    'al30'        => (int) round($al30),
                    'lp'          => round($lp, 2),
                    'ship'        => round($ship, 2),
                    'sprice'      => round($sprice, 2),
                    'sgpft'       => $sgpft,
                    'sroi'        => $sroi,
                    '_margin'     => round($margin, 4),
                    'inv'         => $inv,
                    'ov_l30'      => $ovL30,
                    'views'       => $views,
                    'cvr'         => $cvr,
                    'output_order' => $outputOrder,
                    'reviews'     => (int) ($reviewsBySku[$normalizedSku] ?? 0),
                    'avg_rating'  => (float) ($avgRatingBySku[$normalizedSku] ?? 0),
                    'ae_product_id' => $productIdBySku[$normalizedSku] ?? null,
                    'ae_stock'    => $aeStock,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                    'STANDARD_PRICE' => $amazonStandardPrices[strtoupper(trim((string) $displaySku))] ?? null,
                ];
                $rows[] = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $displaySku);
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

            // SKUs is the default view — do not inject "PARENT …" summary rows unless asked.
            $includeParents = $request->boolean('include_parents');
            if ($includeParents) {
                $rows = $this->insertAeParentRows($rows);
            }
            // #region agent log
            $__dbgParent = 0;
            $__dbgSample = [];
            foreach ($rows as $__r) {
                $__sku = strtoupper(trim((string) ($__r['sku'] ?? '')));
                if (! empty($__r['is_parent']) || str_starts_with($__sku, 'PARENT')) {
                    $__dbgParent++;
                    if (count($__dbgSample) < 5) {
                        $__dbgSample[] = $__sku;
                    }
                }
            }
            $__dbgLine = json_encode([
                'sessionId' => '1b30dc',
                'runId' => 'post-fix',
                'hypothesisId' => 'E',
                'location' => 'AliexpressController.php:pricing-data',
                'message' => 'pricing-data parent injection gate',
                'data' => [
                    'include_parents' => $includeParents,
                    'total' => count($rows),
                    'parentCount' => $__dbgParent,
                    'sample' => $__dbgSample,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ])."\n";
            @file_put_contents(base_path('.cursor/debug-1b30dc.log'), $__dbgLine, FILE_APPEND);
            @file_put_contents(storage_path('logs/debug-1b30dc.log'), $__dbgLine, FILE_APPEND);
            // #endregion

            // Auto-save daily snapshot (non-blocking, same as TikTok)
            $this->saveDailySnapshot($rows);

            return response()->json($rows, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Error fetching AliExpress pricing data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch pricing data: ' . $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Save buyer / seller links for a SKU into aliexpress_listing_statuses.value JSON.
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

        $status = AliexpressListingStatus::where('sku', $sku)
            ->orderBy('updated_at', 'desc')
            ->first();

        $existing = $status
            ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?? []))
            : [];

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        // Delete any duplicates and create a fresh record (mirrors listing save pattern)
        AliexpressListingStatus::where('sku', $sku)->delete();
        AliexpressListingStatus::create([
            'sku' => $sku,
            'value' => $existing,
        ]);

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
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

            $percentage = $this->resolveAliexpressMarginPercent();
            $margin     = $percentage / 100;

            $updatedCount = 0;
            $lastSgpft = null;
            $lastSroi = null;
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
                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $sprice) * 100) : 0;
                $sroi  = $lp     > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $lp)     * 100) : 0;

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
                $lastSgpft = $sgpft;
                $lastSroi = $sroi;
            }

            return response()->json([
                'success' => true,
                'updated' => $updatedCount,
                'sgpft_percent' => $lastSgpft,
                'sroi_percent' => $lastSroi,
            ]);
        } catch (\Exception $e) {
            Log::error('AliExpress SPRICE save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save daily AliExpress pricing snapshot (called automatically from getPricingData).
     * Mirrors TikTok's saveDailySummaryIfNeeded — stores to amazon_channel_summary_data
     * with channel = 'aliexpress'.
     */
    private function saveDailySnapshot(array $rows): void
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();

            // Real child SKUs only — never parent summaries (their profit is already AL30×unit).
            $allChildRows = collect($rows)->filter(function ($r) {
                if (! empty($r['is_parent'])) {
                    return false;
                }
                $sku = strtoupper(trim((string) ($r['sku'] ?? '')));
                if ($sku === '' || str_starts_with($sku, 'PARENT')) {
                    return false;
                }

                return true;
            })->unique(function ($r) {
                return strtoupper(trim((string) ($r['sku'] ?? '')));
            })->values();
            if ($allChildRows->isEmpty()) return;

            $totalSales  = 0; $totalProfit = 0; $totalAl30 = 0;
            $gpftSum     = 0; $gpftCount   = 0;
            $roiSum      = 0; $roiCount    = 0;
            $dilSum      = 0; $dilCount    = 0;
            $totalCogs   = 0;
            $missingCount= 0; $mapCount    = 0; $nmapCount = 0;
            $zeroSold    = 0; $moreSold    = 0;

            // Same math as JS updateSummary — every child row, including missing.
            foreach ($allChildRows as $r) {
                $profit = (float) ($r['profit'] ?? 0);
                $lp     = (float) ($r['lp']     ?? 0);
                $gpft   = (float) ($r['gpft']   ?? 0);
                $groi   = (float) ($r['groi']   ?? 0);
                $sales  = (float) ($r['sales']  ?? 0);
                $al30r  = (float) ($r['al30']   ?? 0);

                $totalSales  += $sales;
                $totalProfit += $al30r * $profit;
                $totalCogs   += $lp * $al30r;

                $gpftSum += $gpft; $gpftCount++;
                $roiSum  += $groi; $roiCount++;
            }

            // 0 Sold / Sold >0 badges only count INV > 0 (same as JS).
            foreach ($allChildRows as $r) {
                $inv   = (float) ($r['inv']    ?? 0);
                $ovL30 = (float) ($r['ov_l30'] ?? 0);
                $al30  = (float) ($r['al30']   ?? 0);
                $nrValue = (string) ($r['NR'] ?? '');
                $isMissingAe = (bool) ($r['is_missing_aliexpress'] ?? false);
                $rowPrice = (float) ($r['price'] ?? 0);
                $aeStock = (float) ($r['ae_stock'] ?? 0);

                $totalAl30 += $al30;
                if ($inv > 0) {
                    if ($al30 === 0.0) {
                        $zeroSold++;
                    } else {
                        $moreSold++;
                    }
                }
                if ($inv > 0) {
                    $dilSum += ($ovL30 / $inv) * 100;
                    $dilCount++;
                }

                // Align with amazon_tabulator_view badge counts
                if ($inv > 0 && $nrValue === 'REQ') {
                    if ($isMissingAe || $rowPrice <= 0) {
                        $missingCount++;
                    } elseif (! $isMissingAe && $rowPrice > 0) {
                        if ($this->aeInvWithinMapTolerance($inv, $aeStock)) {
                            $mapCount++;
                        } else {
                            $nmapCount++;
                        }
                    }
                }
            }

            $totalSkuCount = $allChildRows->count();
            $viewTotals = self::countAliexpressPricingBadgeTotals($allChildRows);

            $summaryData = [
                'total_sku'    => $totalSkuCount,
                'total_sales'  => round($totalSales,  2),
                'total_pft'    => round($totalProfit, 2),
                'total_al30'   => round($totalAl30,   0),
                'total_cogs'   => round($totalCogs,   2),
                'avg_gpft'     => $gpftCount > 0 ? round($gpftSum  / $gpftCount, 2) : 0,
                'avg_roi'      => $roiCount  > 0 ? round($roiSum   / $roiCount,  2) : 0,
                'avg_dil'      => $dilCount  > 0 ? round($dilSum   / $dilCount,  2) : 0,
                'missing_count'=> $missingCount,
                'map_count'    => $mapCount,
                'nmap_count'   => $nmapCount,
                'zero_sold'    => $zeroSold,
                'more_sold'    => $moreSold,
                'total_views'  => (int) ($viewTotals['total_views'] ?? 0),
                'cvr'          => (float) ($viewTotals['cvr_pct'] ?? 0),
                'calculated_at'=> now()->toDateTimeString(),
            ];

            AmazonChannelSummary::updateOrCreate(
                ['channel' => 'aliexpress', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved daily snapshot']
            );

            if (Schema::hasTable('channel_master_calculated_data')) {
                $calcUpdate = ['total_views' => (int) ($viewTotals['total_views'] ?? 0)];
                if (Schema::hasColumn('channel_master_calculated_data', 'listing_cvr')) {
                    $calcUpdate['listing_cvr'] = (float) ($viewTotals['cvr_pct'] ?? 0);
                }
                ChannelMasterCalculatedData::query()
                    ->where('channel', 'Aliexpress')
                    ->update($calcUpdate);
            }
        } catch (\Exception $e) {
            Log::error('AliExpress daily snapshot save failed: ' . $e->getMessage());
        }
    }

    /**
     * Return daily badge chart data from AliExpress snapshots.
     * GET /aliexpress/badge-chart-data?metric=avg_gpft&days=30
     */
    public function badgeChartData(Request $request)
    {
        try {
            $metric = (string) $request->input('metric', 'avg_gpft');
            $days   = max(0, (int) $request->input('days', 30));

            $validMetrics = [
                'total_pft', 'total_sales', 'avg_gpft', 'avg_roi',
                'total_al30', 'avg_dil', 'total_cogs', 'missing_count', 'map_count', 'nmap_count',
                'total_sku', 'zero_sold', 'more_sold', 'total_views', 'cvr',
            ];
            if (!in_array($metric, $validMetrics, true)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            if (in_array($metric, ['total_sales', 'total_al30', 'total_pft'], true)) {
                return response()->json([
                    'success' => true,
                    'data' => $this->aliexpressDailyOrderChartPoints($metric, $days),
                ]);
            }

            $query = AmazonChannelSummary::where('channel', 'aliexpress')
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
                $value = $metric === 'cvr'
                    ? (float) ($sd['cvr'] ?? $sd['cvr_pct'] ?? 0)
                    : (float) ($sd[$metric] ?? 0);
                $data[] = [
                    'date'  => optional($row->snapshot_date)->format('M d'),
                    'value' => $value,
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('AliExpress badge chart data error: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    /**
     * Daily Sales / AL30 / Profit from aliexpress_daily_data (not L30 snapshots).
     *
     * @return list<array{date: string, value: float}>
     */
    private function aliexpressDailyOrderChartPoints(string $metric, int $days): array
    {
        $tz = 'America/Los_Angeles';
        $end = now($tz)->startOfDay();
        $start = $days > 0 ? $end->copy()->subDays(max(0, $days - 1)) : null;

        if (! Schema::hasTable('aliexpress_daily_data')) {
            return $this->aliexpressFillDailyChartPoints([], $start, $end, $metric);
        }

        $excludedStatuses = ['refund', 'return', 'cancel', 'closed'];
        $query = AliexpressDailyData::query()
            ->whereNotNull('order_date')
            ->where(function ($q) use ($excludedStatuses) {
                foreach ($excludedStatuses as $status) {
                    $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ['%'.$status.'%']);
                }
            });
        if ($start) {
            $query->where('order_date', '>=', $start->copy()->timezone('UTC')->startOfDay());
        }

        $needPft = $metric === 'total_pft';
        $margin = 0.0;
        $productMasters = collect();
        if ($needPft) {
            $margin = ((float) $this->resolveAliexpressMarginPercent()) / 100.0;
            $productMasters = ProductMaster::query()->get()->keyBy(function ($item) {
                return strtoupper(trim((string) $item->sku));
            });
        }

        $byDate = [];
        foreach ($query->get(['order_date', 'quantity', 'product_total', 'supply_price', 'order_amount', 'sku_code']) as $row) {
            $day = optional($row->order_date)->timezone($tz)->toDateString();
            if ($day === null || $day === '') {
                continue;
            }
            $line = (float) ($row->product_total ?? 0);
            if ($line <= 0) {
                $line = (float) ($row->supply_price ?? 0);
            }
            if ($line <= 0) {
                $line = (float) ($row->order_amount ?? 0);
            }
            $qty = max(0, (int) ($row->quantity ?? 0));
            if (! isset($byDate[$day])) {
                $byDate[$day] = ['sales' => 0.0, 'qty' => 0, 'pft' => 0.0];
            }
            $byDate[$day]['sales'] += $line;
            $byDate[$day]['qty'] += $qty;

            if ($needPft) {
                $lineQty = $qty > 0 ? $qty : 1;
                $unitPrice = $lineQty > 0 ? ($line / $lineQty) : 0.0;
                $sku = strtoupper(trim((string) ($row->sku_code ?? '')));
                $lp = 0.0;
                $ship = 0.0;
                if ($sku !== '' && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($pm->lp) ? (float) $pm->lp : 0.0);
                    $ship = isset($values['ae_ship']) ? (float) $values['ae_ship']
                        : (isset($values['ship']) ? (float) $values['ship']
                        : (isset($pm->ship) ? (float) $pm->ship : 0.0));
                }
                $byDate[$day]['pft'] += (($unitPrice * $margin) - $lp - $ship) * $lineQty;
            }
        }

        if ($start === null) {
            $keys = array_keys($byDate);
            $start = $keys !== []
                ? Carbon::parse(min($keys), $tz)->startOfDay()
                : $end->copy()->subDays(29);
        }

        return $this->aliexpressFillDailyChartPoints($byDate, $start, $end, $metric);
    }

    /**
     * @param  array<string, array{sales: float, qty: int, pft?: float}>  $byDate
     * @return list<array{date: string, value: float}>
     */
    private function aliexpressFillDailyChartPoints(array $byDate, ?Carbon $start, Carbon $end, string $metric): array
    {
        if ($start === null) {
            $start = $end->copy()->subDays(29);
        }
        $data = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $bucket = $byDate[$key] ?? ['sales' => 0.0, 'qty' => 0, 'pft' => 0.0];
            if ($metric === 'total_al30') {
                $value = (float) $bucket['qty'];
            } elseif ($metric === 'total_pft') {
                $value = round((float) ($bucket['pft'] ?? 0), 2);
            } else {
                $value = round((float) $bucket['sales'], 2);
            }
            $data[] = [
                'date' => $cursor->format('M d'),
                'value' => $value,
            ];
        }

        return $data;
    }

    /**
     * AliExpress LMP sheet page (same layout as Temu LMP).
     */
    public function aliexpressLmpPage()
    {
        $records = AliexpressLmpDataSheet::orderBy('sku')->paginate(100);

        return view('market-places.aliexpress_lmp', compact('records'));
    }

    /**
     * Sample LMP file: tab-separated .csv matching routes/alicsv format —
     * (Child) sku, LMP, C link, LMP, C link (same column order as upload).
     */
    public function downloadAliexpressLmpSample()
    {
        $fileName = 'Aliexpress_LMP_sample.csv';
        $rows = [
            ['(Child) sku', 'LMP', 'C link', 'LMP', 'C link'],
            [
                'YOUR-SKU-001',
                '19.99',
                'https://www.aliexpress.com/item/example-first-listing.html',
                '12.50',
                'https://www.aliexpress.com/item/example-second-listing.html',
            ],
            ['YOUR-SKU-002', '', '', '', ''],
            ['PARENT YOUR-PARENT', '', '', '', ''],
        ];

        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($buffer, $row, "\t");
        }
        rewind($buffer);
        $body = stream_get_contents($buffer);
        fclose($buffer);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF".$body;
        exit;
    }

    /**
     * Upload AliExpress LMP (Excel/CSV/TSV) — truncate then insert; columns by index like Temu.
     */
    public function uploadAliexpressLmp(Request $request)
    {
        $request->validate([
            'lmp_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        try {
            $file = $request->file('lmp_file');
            $path = $file->getPathname();
            $ext = strtolower($file->getClientOriginalExtension());

            $rows = [];
            if (in_array($ext, ['xlsx', 'xls'], true)) {
                $spreadsheet = IOFactory::load($path);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } else {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $delimiter = (strpos($lines[0] ?? '', "\t") !== false) ? "\t" : ',';
                foreach ($lines as $line) {
                    $rows[] = str_getcsv($line, $delimiter);
                }
            }

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            AliexpressLmpDataSheet::truncate();
            $imported = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sku = isset($row[0]) ? trim((string) $row[0]) : '';
                if ($sku === '') {
                    continue;
                }
                $lmp = isset($row[1]) && $row[1] !== '' ? $this->sanitizePrice($row[1]) : null;
                $lmpLink = isset($row[2]) && trim((string) $row[2]) !== '' ? trim((string) $row[2]) : null;
                $lmp2 = isset($row[3]) && $row[3] !== '' ? $this->sanitizePrice($row[3]) : null;
                $lmpLink2 = isset($row[4]) && trim((string) $row[4]) !== '' ? trim((string) $row[4]) : null;

                try {
                    AliexpressLmpDataSheet::create([
                        'sku' => $sku,
                        'lmp' => $lmp,
                        'lmp_link' => $lmpLink,
                        'lmp_2' => $lmp2,
                        'lmp_link_2' => $lmpLink2,
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = 'Row ' . ($i + 1) . ': ' . $e->getMessage();
                }
            }

            $msg = "Successfully imported {$imported} AliExpress LMP records.";
            if (!empty($errors)) {
                $msg .= ' ' . count($errors) . ' row(s) had errors.';
            }

            return back()->with('success', $msg)->with('upload_errors', $errors);
        } catch (\Exception $e) {
            Log::error('AliExpress LMP upload error: ' . $e->getMessage());

            return back()->with('error', 'Error uploading file: ' . $e->getMessage());
        }
    }

    /**
     * Save LMP entries from grid modal (normalized SKU match; same rules as Temu saveTemuLmp).
     */
    /**
     * LMP drawer data for /price-increase (same shape as /cvr-master-temu-lmp).
     * Source: aliexpress_lmp_data_sheet (/aliexpress-lmp).
     */
    public function getAliexpressLmpData(Request $request)
    {
        try {
            $sku = trim((string) $request->query('sku', $request->input('sku', '')));
            if ($sku === '') {
                return response()->json(['success' => false, 'error' => 'SKU required'], 400);
            }

            $row = $this->findAliexpressLmpRow($sku);
            $entries = $row ? $this->extractAliexpressLmpEntries($row) : [];
            $competitors = [];
            $landedPrices = [];
            foreach ($entries as $entry) {
                $price = isset($entry['price']) && $entry['price'] !== '' && $entry['price'] !== null
                    ? (float) $entry['price']
                    : 0.0;
                if ($price <= 0) {
                    continue;
                }
                $ship = isset($entry['ship']) && $entry['ship'] !== '' && $entry['ship'] !== null
                    ? max(0, (float) $entry['ship'])
                    : (isset($entry['delivery']) && $entry['delivery'] !== '' && $entry['delivery'] !== null
                        ? max(0, (float) $entry['delivery'])
                        : 0.0);
                $total = round($price + $ship, 2);
                if (empty($entry['ignored'])) {
                    $landedPrices[] = $total;
                }
                $competitors[] = [
                    'id' => 'aliexpress-'.(count($competitors) + 1),
                    'price' => round($price, 2),
                    'base_price' => round($price, 2),
                    'delivery' => round($ship, 2),
                    'shipping_cost' => round($ship, 2),
                    'ship' => round($ship, 2),
                    'total_price' => $total,
                    'ignored' => ! empty($entry['ignored']),
                    'product_link' => $entry['link'] ?? null,
                    'link' => $entry['link'] ?? null,
                    'source_sku' => $entry['source_sku'] ?? ($row->sku ?? $sku),
                    'product_title' => '',
                    'image' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'lmp' => count($landedPrices) > 0 ? round(min($landedPrices), 2) : null,
                'competitors' => $competitors,
            ]);
        } catch (\Throwable $e) {
            Log::error('AliExpress LMP data error: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to fetch AliExpress LMP'], 500);
        }
    }

    public function saveAliexpressLmp(Request $request)
    {
        $request->validate([
            'sku' => 'required|string|max:255',
            'lmp_entries' => 'nullable|array',
            'lmp_entries.*.price' => 'nullable|numeric|min:0',
            'lmp_entries.*.ship' => 'nullable|numeric|min:0',
            'lmp_entries.*.delivery' => 'nullable|numeric|min:0',
            'lmp_entries.*.link' => 'nullable|string|max:2000',
        ]);

        $sku = trim($request->sku);
        $rawEntries = $request->input('lmp_entries', []);
        // Explicit empty array means clear all LMP for this SKU (do not resurrect legacy columns)
        if (! is_array($rawEntries)) {
            $rawEntries = [];
        }
        $lmpEntries = [];
        foreach ($rawEntries as $e) {
            if (! is_array($e)) {
                continue;
            }
            $price = isset($e['price']) && $e['price'] !== '' && $e['price'] !== null
                ? $this->sanitizePrice($e['price'])
                : null;
            // Do not use sanitizePrice() for ship — PHP empty(0) would drop free shipping
            $shipRaw = $e['ship'] ?? $e['delivery'] ?? null;
            if ($shipRaw === '' || $shipRaw === null) {
                $ship = 0.0;
            } else {
                $cleanedShip = preg_replace('/US\s*\$|[$,\s]/', '', (string) $shipRaw);
                $ship = is_numeric($cleanedShip) ? max(0, (float) $cleanedShip) : 0.0;
            }
            $link = isset($e['link']) && trim((string) $e['link']) !== '' ? trim((string) $e['link']) : null;
            if ($price !== null || $link !== null) {
                $entry = [
                    'price' => $price,
                    'ship' => round($ship, 2),
                    'delivery' => round($ship, 2),
                    'link' => $link,
                ];
                if (! empty($e['ignored'])) {
                    $entry['ignored'] = true;
                }
                if (! empty($e['source_sku'])) {
                    $entry['source_sku'] = trim((string) $e['source_sku']);
                }
                $lmpEntries[] = $entry;
            }
        }
        // Store landed LMP (price + ship) so OV L30 matches Del / P+S
        $activePrices = array_values(array_filter(array_map(static function ($e) {
            if (! empty($e['ignored'])) {
                return null;
            }
            $p = isset($e['price']) ? (float) $e['price'] : 0;
            if ($p <= 0) {
                return null;
            }
            $s = isset($e['ship']) ? max(0, (float) $e['ship']) : 0;

            return round($p + $s, 2);
        }, $lmpEntries)));
        $firstPrice = count($activePrices) > 0 ? min($activePrices) : null;
        $firstLink = null;
        foreach ($lmpEntries as $e) {
            if (empty($e['ignored']) && ! empty($e['link'])) {
                $firstLink = $e['link'];
                break;
            }
        }
        if ($firstLink === null) {
            $firstLink = $lmpEntries[0]['link'] ?? null;
        }

        $existing = $this->findAliexpressLmpRow($sku);

        if ($lmpEntries === []) {
            if ($existing) {
                $existing->delete();
            }

            return response()->json(['success' => true, 'message' => 'AliExpress LMP cleared']);
        }

        $payload = [
            'sku' => $existing ? $existing->sku : $sku,
            'lmp' => $firstPrice,
            'lmp_link' => $firstLink,
            'lmp_entries' => $lmpEntries,
            'lmp_2' => null,
            'lmp_link_2' => null,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            AliexpressLmpDataSheet::create($payload);
        }

        return response()->json(['success' => true, 'message' => 'LMP saved successfully']);
    }

    /**
     * Normalize SKU for AliExpress LMP sheet matching (same as pricing page).
     */
    private function normalizeAliexpressLmpSku(string $value): string
    {
        $s = strtoupper(trim($value));
        $s = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        return $s;
    }

    private function findAliexpressLmpRow(string $sku): ?AliexpressLmpDataSheet
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('aliexpress_lmp_data_sheet')) {
            return null;
        }

        $exact = AliexpressLmpDataSheet::where('sku', $sku)->first();
        if ($exact) {
            return $exact;
        }

        $target = $this->normalizeAliexpressLmpSku($sku);

        return AliexpressLmpDataSheet::query()
            ->whereRaw(
                'UPPER(REPLACE(REPLACE(REPLACE(TRIM(sku), CHAR(10), " "), CHAR(13), " "), CHAR(9), " ")) LIKE ?',
                ['%'.str_replace(' ', '%', $target).'%']
            )
            ->get()
            ->first(function ($row) use ($target) {
                return $this->normalizeAliexpressLmpSku((string) $row->sku) === $target;
            });
    }

    /**
     * @return list<array{price:?float,ship?:float,delivery?:float,link:?string,ignored?:bool,source_sku?:string}>
     */
    private function extractAliexpressLmpEntries($row): array
    {
        if (! $row) {
            return [];
        }

        // Trust explicit empty JSON array — do not resurrect legacy lmp / lmp_2
        if (is_array($row->lmp_entries)) {
            if ($row->lmp_entries === []) {
                return [];
            }
            $out = [];
            foreach ($row->lmp_entries as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $price = isset($e['price']) && $e['price'] !== '' && $e['price'] !== null
                    ? (float) $e['price']
                    : null;
                $shipRaw = $e['ship'] ?? $e['delivery'] ?? 0;
                $ship = ($shipRaw !== '' && $shipRaw !== null) ? max(0, (float) $shipRaw) : 0.0;
                $link = isset($e['link']) && trim((string) $e['link']) !== '' ? trim((string) $e['link']) : null;
                if ($price === null && $link === null) {
                    continue;
                }
                $entry = [
                    'price' => $price,
                    'ship' => round($ship, 2),
                    'delivery' => round($ship, 2),
                    'link' => $link,
                ];
                if (! empty($e['ignored'])) {
                    $entry['ignored'] = true;
                }
                if (! empty($e['source_sku'])) {
                    $entry['source_sku'] = trim((string) $e['source_sku']);
                }
                $out[] = $entry;
            }

            return $out;
        }

        $legacy = [];
        if ($row->lmp !== null || $row->lmp_link) {
            $legacy[] = [
                'price' => $row->lmp !== null ? (float) $row->lmp : null,
                'link' => $row->lmp_link ? trim((string) $row->lmp_link) : null,
            ];
        }
        if ($row->lmp_2 !== null || $row->lmp_link_2) {
            $legacy[] = [
                'price' => $row->lmp_2 !== null ? (float) $row->lmp_2 : null,
                'link' => $row->lmp_link_2 ? trim((string) $row->lmp_link_2) : null,
            ];
        }

        return $legacy;
    }

    /**
     * Exact AliExpress seller SKU key (no prefix / package stripping — 4PCS variants stay distinct).
     */
    private function normalizeAeSkuExact(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
    }

    /**
     * Prefer the current AliExpress product_id over a stored link to an older / offline listing.
     *
     * @return array{0: string, 1: string}
     */
    private function aliexpressLinksForProductId(?string $productId, string $buyerLink, string $sellerLink): array
    {
        $pid = trim((string) $productId);
        if ($pid === '' || ! ctype_digit($pid)) {
            return [trim($buyerLink), trim($sellerLink)];
        }

        $autoBuyer = 'https://www.aliexpress.com/item/'.$pid.'.html';
        $autoSeller = 'https://gsp.aliexpress.com/m_apps/product-publish/publish?productId='.$pid;
        $buyerLink = trim($buyerLink);
        $sellerLink = trim($sellerLink);
        $buyerPid = $this->productIdFromAliexpressUrl($buyerLink);
        $sellerPid = $this->productIdFromAliexpressUrl($sellerLink);

        if ($buyerLink === '' || ($buyerPid !== '' && $buyerPid !== $pid)) {
            $buyerLink = $autoBuyer;
        }
        if ($sellerLink === '' || ($sellerPid !== '' && $sellerPid !== $pid)) {
            $sellerLink = $autoSeller;
        }

        return [$buyerLink, $sellerLink];
    }

    private function productIdFromAliexpressUrl(string $url): string
    {
        if (preg_match('#/item/(\d+)#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]productId=(\d+)#i', $url, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * NR for map/missing rules — mirrors Amazon NRL → NR (REQ vs NR).
     *
     * @param  array<string, mixed>  $meta
     */
    private function resolveAeNrFromMeta(array $meta, bool $hasProductMaster): ?string
    {
        $nrl = strtoupper(trim((string) ($meta['NRL'] ?? '')));
        if ($nrl === 'NRL') {
            return 'NR';
        }
        if ($nrl === 'REQ') {
            return 'REQ';
        }

        $nr = $meta['NR'] ?? $meta['NRP'] ?? null;
        if (is_bool($nr)) {
            return $nr ? 'NR' : ($hasProductMaster ? 'REQ' : null);
        }
        $nrOut = strtoupper(trim((string) $nr));
        if ($nrOut === 'NR' || $nrOut === 'NRL') {
            return 'NR';
        }
        if ($nrOut === 'REQ' || $nrOut === 'TRUE' || $nrOut === '1') {
            return $nrOut === 'REQ' ? 'REQ' : ($hasProductMaster ? 'REQ' : null);
        }

        return $hasProductMaster ? 'REQ' : null;
    }

    /** INV vs AE stock = Map if diff ≤ 3 units OR ≤ 3% of Shopify INV (amazon INV vs INV_AMZ). */
    private function aeInvWithinMapTolerance(float $inv, float $aeStock): bool
    {
        if ($inv <= 0) {
            return true;
        }
        $diff = abs($inv - $aeStock);
        if ($diff <= 3.0) {
            return true;
        }

        return $diff <= ($inv * 0.03);
    }

    /**
     * Map / Miss / NMap — same rules as aliexpress_pricing_view badges (Amazon-aligned).
     */
    public static function countAliexpressPricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;
        $sumViews = 0;
        $sumOutputOrder = 0;
        $seenViewProducts = [];

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }

            $views = (int) ($row['views'] ?? 0);
            $outputOrder = (int) ($row['output_order'] ?? 0);
            $pid = trim((string) ($row['ae_product_id'] ?? ''));
            if ($pid !== '') {
                if (! isset($seenViewProducts[$pid])) {
                    $seenViewProducts[$pid] = true;
                    $sumViews += $views;
                    $sumOutputOrder += $outputOrder;
                }
            } else {
                $sumViews += $views;
                $sumOutputOrder += $outputOrder;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $nrValue = (string) ($row['NR'] ?? '');
            $isMissingAe = (bool) ($row['is_missing_aliexpress'] ?? false);
            $rowPrice = (float) ($row['price'] ?? 0);
            $aeStock = (float) ($row['ae_stock'] ?? 0);

            if ($inv > 0 && $nrValue === 'REQ') {
                if ($isMissingAe || $rowPrice <= 0) {
                    $miss++;
                } elseif (! $isMissingAe && $rowPrice > 0) {
                    $diff = abs($inv - $aeStock);
                    $within = $inv <= 0 || $diff <= 3.0 || $diff <= ($inv * 0.03);
                    if ($within) {
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
            'total_views' => $sumViews,
            'cvr_pct' => $sumViews > 0 ? round(($sumOutputOrder / $sumViews) * 100, 2) : 0.0,
        ];
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
