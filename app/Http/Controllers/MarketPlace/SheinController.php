<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\SheinDataView;
use App\Models\SheinDailyData;
use App\Models\SheinDailyDataL60;
use App\Models\ShopifySku;
use App\Services\SheinShopifySalesService;
use App\Services\LmpSkuGroupService;
use App\Models\AmazonChannelSummary;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
     * Robust SKU normalization for cross-table joins.
     *
     * Folds non-breaking spaces (NBSP / narrow NBSP / \xA0) to regular spaces,
     * strips invalid UTF-8, collapses any internal whitespace runs, then uppercases.
     * Without this, `shein_pricing_prices.sku` rarely matches `product_masters.sku`
     * because Excel/Shein CSV exports leak NBSP and double-spaces, and LP/Ship
     * silently fall back to 0. Mirrors AliexpressController::normalizeAeSkuExact.
     */
    private function normalizeSheinSkuExact(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper(preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
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
        )->keyBy(fn(ProductMaster $r) => $this->normalizeSheinSkuExact((string) $r->sku));
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

    /**
     * Load a Shein order export. Accepts any extension — auto-detects spreadsheet vs
     * delimited text (Seller Hub CSVs are often tab-separated and may be .csv/.txt/.xlsx).
     */
    private function loadSheinOrderSpreadsheet(string $filePath)
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $spreadsheetExts = ['xlsx', 'xls', 'xlsm', 'ods'];

        // Try Excel/ODS by extension first
        if (in_array($ext, $spreadsheetExts, true)) {
            return IOFactory::load($filePath);
        }

        // Delimited text (csv/tsv/txt/unknown): sniff delimiter and use CSV reader
        if ($this->looksLikeDelimitedText($filePath) || in_array($ext, ['csv', 'txt', 'tsv', ''], true) || $ext === '') {
            try {
                $delimiter = $this->detectSheinCsvDelimiter($filePath);
                $reader = IOFactory::createReader('Csv');
                $reader->setDelimiter($delimiter);
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);

                return $reader->load($filePath);
            } catch (\Throwable $e) {
                // Fall through to generic IOFactory
            }
        }

        // Last resort: let PhpSpreadsheet guess (xlsx/xls/csv/etc.)
        return IOFactory::load($filePath);
    }

    private function looksLikeDelimitedText(string $filePath): bool
    {
        $handle = @fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }
        $chunk = fread($handle, 4096) ?: '';
        fclose($handle);
        // Binary Excel/zip files start with PK or other non-text signatures
        if (str_starts_with($chunk, 'PK') || str_starts_with($chunk, "\xD0\xCF")) {
            return false;
        }
        $sample = substr($chunk, 0, 800);

        return str_contains($sample, "\t") || str_contains($sample, ',') || str_contains(strtolower($sample), 'order');
    }

    private function detectSheinCsvDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ',';
        }
        $line = fgets($handle) ?: '';
        fclose($handle);
        $tabs = substr_count($line, "\t");
        $commas = substr_count($line, ',');

        return $tabs > $commas ? "\t" : ',';
    }

    /**
     * Map one Shein Seller Hub order-export row (header row 2) into shein_daily_data columns.
     * Format matches sheinorders.csv: Number of items sold @ 38, Province @ 39, City @ 40.
     */
    private function mapSheinOrderExportRow(array $row): ?array
    {
        if (empty($row[1])) {
            return null;
        }

        $qty = isset($row[38]) && is_numeric($row[38]) ? max(1, (int) $row[38]) : 1;

        return [
            'order_type' => isset($row[0]) && $row[0] !== '' ? trim((string) $row[0]) : null,
            'order_number' => isset($row[1]) && $row[1] !== '' ? trim((string) $row[1]) : null,
            'exchange_order' => isset($row[2]) && $row[2] !== '' ? trim((string) $row[2]) : null,
            'order_status' => isset($row[3]) && $row[3] !== '' ? trim((string) $row[3]) : null,
            'shipment_mode' => isset($row[4]) && $row[4] !== '' ? trim((string) $row[4]) : null,
            'urged_or_not' => isset($row[5]) && $row[5] !== '' ? trim((string) $row[5]) : null,
            'is_it_lost' => isset($row[6]) && $row[6] !== '' ? trim((string) $row[6]) : null,
            'whether_to_stay' => isset($row[7]) && $row[7] !== '' ? trim((string) $row[7]) : null,
            'order_issue' => isset($row[8]) && $row[8] !== '' ? trim((string) $row[8]) : null,
            'product_name' => isset($row[9]) && $row[9] !== '' ? trim((string) $row[9]) : null,
            'product_description' => isset($row[10]) && $row[10] !== '' ? trim((string) $row[10]) : null,
            'specification' => isset($row[11]) && $row[11] !== '' ? trim((string) $row[11]) : null,
            'seller_sku' => isset($row[12]) && $row[12] !== '' ? trim((string) $row[12]) : null,
            'shein_sku' => isset($row[13]) && $row[13] !== '' ? trim((string) $row[13]) : null,
            'skc' => isset($row[14]) && $row[14] !== '' ? trim((string) $row[14]) : null,
            'item_id' => isset($row[15]) && $row[15] !== '' ? trim((string) $row[15]) : null,
            'product_status' => isset($row[16]) && $row[16] !== '' ? trim((string) $row[16]) : null,
            'inventory_id' => isset($row[17]) && $row[17] !== '' ? trim((string) $row[17]) : null,
            'exchange_id' => isset($row[18]) && $row[18] !== '' ? trim((string) $row[18]) : null,
            'reason_for_replacement' => isset($row[19]) && $row[19] !== '' ? trim((string) $row[19]) : null,
            'product_id_to_be_exchanged' => isset($row[20]) && $row[20] !== '' ? trim((string) $row[20]) : null,
            'locked_or_not' => isset($row[21]) && $row[21] !== '' ? trim((string) $row[21]) : null,
            'order_processed_on' => isset($row[22]) ? $this->parseDate($row[22]) : null,
            'collection_deadline' => isset($row[23]) ? $this->parseDate($row[23]) : null,
            'requested_shipping_time' => isset($row[24]) ? $this->parseDate($row[24]) : null,
            'delivery_deadline' => isset($row[25]) ? $this->parseDate($row[25]) : null,
            'delivery_time' => isset($row[26]) ? $this->parseDate($row[26]) : null,
            'tracking_number' => isset($row[27]) && $row[27] !== '' ? trim((string) $row[27]) : null,
            'sellers_package' => isset($row[28]) && $row[28] !== '' ? trim((string) $row[28]) : null,
            'seller_currency' => isset($row[29]) && $row[29] !== '' ? trim((string) $row[29]) : null,
            'product_price' => isset($row[30]) ? $this->sanitizePrice($row[30]) : null,
            'coupon_discount' => isset($row[31]) ? $this->sanitizePrice($row[31]) : null,
            'store_campaign_discount' => isset($row[32]) ? $this->sanitizePrice($row[32]) : null,
            'commission' => isset($row[33]) ? $this->sanitizePrice($row[33]) : null,
            'estimated_merchandise_revenue' => isset($row[34]) ? $this->sanitizePrice($row[34]) : null,
            'fulfillment_service_fee' => isset($row[35]) ? $this->sanitizePrice($row[35]) : null,
            'storage_fee' => isset($row[36]) ? $this->sanitizePrice($row[36]) : null,
            'consumption_tax' => isset($row[37]) ? $this->sanitizePrice($row[37]) : null,
            'quantity' => $qty,
            'province' => isset($row[39]) && $row[39] !== '' ? trim((string) $row[39]) : null,
            'city' => isset($row[40]) && $row[40] !== '' ? trim((string) $row[40]) : null,
        ];
    }

    /**
     * Upload Shein daily data file in chunks
     */
    public function uploadDailyDataChunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:51200',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);

            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('shein_upload_'));

            $tempPath = storage_path('app/temp');
            if (! file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $fileName = $uploadId.'_'.$file->getClientOriginalName();
            $filePath = $tempPath.'/'.$fileName;

            if ($chunk == 0) {
                $file->move($tempPath, $fileName);

                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                SheinDailyData::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                Log::info('Shein daily data table truncated before import');
            }

            $spreadsheet = $this->loadSheinOrderSpreadsheet($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            unset($rows[0], $rows[1]);

            $totalRows = count($rows);
            $chunkSize = max(1, (int) ceil($totalRows / $totalChunks));
            $startRow = $chunk * $chunkSize;
            $chunkRows = array_slice($rows, $startRow, $chunkSize, true);

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($chunkRows as $row) {
                    $insertData = $this->mapSheinOrderExportRow(is_array($row) ? $row : []);
                    if (! $insertData) {
                        $skipped++;
                        continue;
                    }
                    SheinDailyData::create($insertData);
                    $imported++;
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            if ($chunk == $totalChunks - 1 && file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Shein daily data chunk: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
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
     * Upload L60 sales daily data file in chunks (same format as L30, stored in shein_daily_data_l60)
     */
    public function uploadDailyDataL60Chunk(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:51200',
                'chunk' => 'required|integer|min:0',
                'totalChunks' => 'required|integer|min:1',
            ]);

            $file = $request->file('file');
            $chunk = $request->input('chunk');
            $totalChunks = $request->input('totalChunks');
            $uploadId = $request->input('uploadId', uniqid('shein_l60_upload_'));

            $tempPath = storage_path('app/temp');
            if (! file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $fileName = $uploadId.'_'.$file->getClientOriginalName();
            $filePath = $tempPath.'/'.$fileName;

            if ($chunk == 0) {
                $file->move($tempPath, $fileName);

                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                SheinDailyDataL60::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                Log::info('Shein L60 daily data table truncated before import');
            }

            $spreadsheet = $this->loadSheinOrderSpreadsheet($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            unset($rows[0], $rows[1]);

            $totalRows = count($rows);
            $chunkSize = max(1, (int) ceil($totalRows / $totalChunks));
            $chunkRows = array_slice($rows, $chunk * $chunkSize, $chunkSize, true);

            $imported = 0;
            $skipped = 0;

            DB::beginTransaction();
            try {
                foreach ($chunkRows as $row) {
                    $insertData = $this->mapSheinOrderExportRow(is_array($row) ? $row : []);
                    if (! $insertData) {
                        $skipped++;
                        continue;
                    }
                    SheinDailyDataL60::create($insertData);
                    $imported++;
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            if ($chunk == $totalChunks - 1 && file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk $chunk processed successfully",
                'chunk' => $chunk,
                'totalChunks' => $totalChunks,
                'imported' => $imported,
                'skipped' => $skipped,
                'progress' => round((($chunk + 1) / $totalChunks) * 100, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Error uploading Shein L60 daily data chunk: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get L60 sales statistics from uploaded shein_daily_data_l60 (Seller Hub export).
     */
    public function getL60Sales(Request $request)
    {
        try {
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $rows = SheinDailyDataL60::query()
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->get();

            $totalOrders = 0;
            $totalQuantity = 0;
            $totalSales = 0.0;
            foreach ($rows as $row) {
                $orderNum = trim((string) ($row->order_number ?? ''));
                $sellerSku = trim((string) ($row->seller_sku ?? ''));
                if ($orderNum === '' && $sellerSku === '') {
                    continue;
                }
                $totalOrders++;
                $quantity = max(1, (int) ($row->quantity ?? 0));
                $productPrice = (float) ($row->product_price ?? 0);
                // Sales = Product Price × qty (Seller Hub GMV)
                $lineRevenue = $productPrice * $quantity;
                $totalQuantity += $quantity;
                $totalSales += $lineRevenue;
            }

            $totals = [
                'total_sales' => round($totalSales, 2),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity,
            ];

            return response()->json([
                'success' => true,
                'data' => $totals,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Shein L60 sales from shein_daily_data_l60: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily data for Shein tabulator view.
     * Source: uploaded Seller Hub order export only → shein_daily_data
     * (NOT Shopify / apicentral).
     */
    public function getDailyData(Request $request)
    {
        try {
            $productMasters = $this->productMasterByNormalizedSku();
            $normalizeSku = fn ($v) => $this->normalizeSheinSkuExact((string) $v);

            $data = SheinDailyData::query()
                ->orderByDesc('order_processed_on')
                ->orderByDesc('id')
                ->get()
                ->map(function ($item) use ($productMasters, $normalizeSku) {
                    $key = $item->seller_sku ? $normalizeSku($item->seller_sku) : '';
                    $pm = $key !== '' ? $productMasters->get($key) : null;
                    if (! $pm instanceof ProductMaster) {
                        $pm = null;
                    }
                    $resolved = $this->lpAndShipFromProductMaster($pm);
                    $row = $item->toArray();
                    $row['lp'] = $resolved['lp'];
                    $row['ship'] = $resolved['ship'];
                    // Ensure dates are plain strings for Tabulator
                    foreach (['order_processed_on', 'collection_deadline', 'requested_shipping_time', 'delivery_deadline', 'delivery_time'] as $dateField) {
                        if (! empty($row[$dateField]) && ! is_string($row[$dateField])) {
                            try {
                                $row[$dateField] = Carbon::parse($row[$dateField])->format('Y-m-d H:i:s');
                            } catch (\Throwable $e) {
                                $row[$dateField] = (string) $row[$dateField];
                            }
                        }
                    }

                    return $row;
                })
                ->values()
                ->all();

            Log::info('Shein daily data fetched from shein_daily_data upload', [
                'result_count' => count($data),
            ]);

            return response()->json([
                'data' => $data,
                'source' => 'shein_daily_data_upload',
                'marketplace_margin_decimal' => SheinShopifySalesService::sheinMarginDecimal(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Shein daily data from upload: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage(), 'data' => []], 500);
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
            // Normalize at upload time so shein_pricing_prices.sku matches product_masters.sku
            // even when the sheet contains NBSP or stray whitespace (a frequent Shein export issue).
            $sku = $this->normalizeSheinSkuExact((string) ($row[$skuIdx] ?? ''));
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
            $normalizeSku = fn($v) => $this->normalizeSheinSkuExact((string) $v);

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

            // ── 3. Shein sales → al30 / sales from uploaded shein_daily_data (Seller Hub CSV)
            $excludedStatuses = ['refund', 'return', 'cancel', 'closed', 'exchange'];
            $salesAgg = new SupportCollection();
            SheinDailyData::query()
                ->whereNotNull('seller_sku')->where('seller_sku', '!=', '')
                ->where(function ($q) use ($excludedStatuses) {
                    foreach ($excludedStatuses as $s) {
                        $q->whereRaw('LOWER(COALESCE(order_status, "")) NOT LIKE ?', ["%{$s}%"]);
                    }
                })
                ->get(['seller_sku', 'quantity', 'product_price', 'estimated_merchandise_revenue'])
                ->each(function ($row) use ($salesAgg, $normalizeSku) {
                    $key = $normalizeSku($row->seller_sku);
                    if ($key === '') {
                        return;
                    }
                    $qty = max(1, (int) ($row->quantity ?? 0));
                    $price = (float) ($row->product_price ?? 0);
                    // Sales = Product Price × qty (Seller Hub GMV)
                    $rev = $price * $qty;
                    $existing = $salesAgg->get($key);
                    if ($existing) {
                        $existing->al30 += $qty;
                        $existing->sales += $rev;
                    } else {
                        $salesAgg->put($key, (object) [
                            'al30' => $qty,
                            'sales' => $rev,
                        ]);
                    }
                });

            // ── 4. Shopify → INV / OV L30
            // Load full tables and key in PHP — SQL UPPER(TRIM(sku)) does not fold NBSP / multi-space variants.
            $shopifyBySku = ShopifySku::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5. SPRICE from shein_data_views
            $viewMetaBySku = SheinDataView::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5b. Buyer / Seller links from shein_listing_statuses
            $linksBySku = \App\Models\SheinListingStatus::all()->keyBy(fn($r) => $normalizeSku($r->sku));

            // ── 5c. LMP competitor prices/links from shein_lmp
            $lmpBySku = new SupportCollection();
            if (Schema::hasTable('shein_lmp')) {
                $lmpBySku = SupportCollection::make(\App\Models\SheinLmp::all()->all())
                    ->keyBy(fn($r) => $normalizeSku($r->sku));
            }

            $allNormalizedSkus = collect(array_merge(
                $pricingBySku->keys()->all(),
                $productMasterBySku->keys()->all()
            ))->unique()->values();

            // Sku Link LMP — same shared lmp_sku_links groups as /ebay-tabulator-view
            $lmpGroupService = new LmpSkuGroupService();
            try {
                $prepSkus = [];
                foreach ($productMasterBySku as $pm) {
                    if ($pm && trim((string) ($pm->sku ?? '')) !== '') {
                        $prepSkus[] = (string) $pm->sku;
                    }
                }
                foreach ($pricingBySku as $pr) {
                    if ($pr && trim((string) ($pr->sku ?? '')) !== '') {
                        $prepSkus[] = (string) $pr->sku;
                    }
                }
                $lmpGroupService->prepareForSkus($prepSkus);
            } catch (\Throwable $e) {
                Log::warning('LmpSkuGroupService prepare failed (Shein): ' . $e->getMessage());
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
                // Actual L30 revenue from uploaded shein_daily_data (Seller Hub CSV).
                // Fall back to theoretical al30 × special_offer only when qty exists but revenue missing.
                $sales = $sale ? (float) ($sale->sales ?? 0) : 0;
                if ($sales <= 0 && $al30 > 0 && $spOffer > 0) {
                    $sales = $al30 * $spOffer;
                }

                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv      ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src      ?? null) : null;

                $metaRecord = $viewMetaBySku->get($normalizedSku);
                $meta       = $metaRecord ? ($metaRecord->value ?? []) : [];
                if (! is_array($meta)) {
                    $meta = [];
                }
                $nr         = $this->resolveSheinNrFromMeta($meta, $productMaster !== null);
                $sprice     = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;

                // Use special_offer_price only for all calculations
                $calcPrice  = $spOffer;
                $profit = ($calcPrice * $margin) - $lp - $ship;
                $gpft   = $calcPrice > 0 ? ($profit / $calcPrice) * 100 : 0;
                $groi   = $lp        > 0 ? ($profit / $lp)         * 100 : 0;
                $sgpft  = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                $sroi   = ($sprice > 0 && $lp > 0) ? round((($sprice * $margin - $lp - $ship) / $lp) * 100, 2) : 0;

                $displaySku = $productMaster?->sku ?? ($priceRow->sku ?? $normalizedSku);
                $isMissingShein = ! $priceRow || $spOffer <= 0;

                if ($isMissingShein) {
                    $mapValue = '';
                } else {
                    $adiff = abs($inv - $sheinStock);
                    $mapValue = $this->sheinInvWithinMapTolerance((float) $inv, (float) $sheinStock)
                        ? 'Map'
                        : 'N Map|' . (int) round($adiff);
                }

                // Buyer / Seller links
                $linkRecord = $linksBySku->get($normalizedSku);
                $linkVal = $linkRecord
                    ? (is_array($linkRecord->value) ? $linkRecord->value : (json_decode($linkRecord->value, true) ?: []))
                    : [];
                $buyerLink  = $linkVal['buyer_link']  ?? '';
                $sellerLink = $linkVal['seller_link'] ?? '';
                // NR/REQ status — prefer shein_listing_statuses.nr_req (same source as listing-shein),
                // fall back to meta-derived value, then INV-based default.
                $nrReq = $linkVal['nr_req'] ?? $nr ?? ($inv > 0 ? 'REQ' : 'NR');

                // LMP competitor entries merged across Sku Link LMP group (same as ebay-tabulator-view)
                $linkedLmpSkus = $this->sheinLinkedLmpSkusFor($lmpGroupService, (string) $displaySku);
                $lmpEntries = [];
                $seenLmp = [];
                foreach ($linkedLmpSkus as $linkedSku) {
                    $linkedNorm = $normalizeSku($linkedSku);
                    foreach ($this->sheinLmpEntriesFrom($lmpBySku->get($linkedNorm)) as $entry) {
                        $dedupeKey = ((string) ($entry['price'] ?? '')) . '|' . strtoupper(trim((string) ($entry['link'] ?? '')));
                        if (isset($seenLmp[$dedupeKey])) {
                            continue;
                        }
                        $seenLmp[$dedupeKey] = true;
                        $entry['source_sku'] = $linkedSku;
                        $lmpEntries[] = $entry;
                    }
                }
                $lmpPrice = null;
                $lmpLink  = null;
                foreach ($lmpEntries as $entry) {
                    if ($lmpPrice === null || $entry['price'] < $lmpPrice) {
                        $lmpPrice = $entry['price'];
                        $lmpLink  = $entry['link'];
                    }
                }

                $rows[] = [
                    'sku'          => trim((string) $displaySku),
                    'parent'       => $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null,
                    'is_parent'    => false,
                    'image'        => $imageSrc,
                    'B Link'       => $buyerLink,
                    'S Link'       => $sellerLink,
                    'NR'           => $nr,
                    'nr_req'       => $nrReq,
                    'is_missing_shein' => $isMissingShein,
                    'missing'      => $isMissingShein ? 'M' : '',
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
                    'lmp_price'    => $lmpPrice,
                    'lmp_link'     => $lmpLink,
                    'lmp_entries'  => $lmpEntries,
                    'linked_lmp_skus' => $linkedLmpSkus,
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

            $salesPage = SheinShopifySalesService::computeSalesPageTotals();
            $this->saveSheinPricingSnapshot($rows, $salesPage);

            $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE;
            if (defined('JSON_UNESCAPED_UNICODE')) {
                $jsonFlags |= JSON_UNESCAPED_UNICODE;
            }

            // Wrap rows + sales-page totals so pricing badges match /shein-tabulator
            return response()->json([
                'data' => $rows,
                'sales_page' => $salesPage,
            ], 200, [], $jsonFlags);
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
     * NR for map/missing rules — mirrors Amazon NRL → NR (REQ vs NR).
     *
     * @param  array<string, mixed>  $meta
     */
    private function resolveSheinNrFromMeta(array $meta, bool $hasProductMaster): ?string
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

    /** INV vs Shein stock = Map if diff ≤ 3 units OR ≤ 3% of Shopify INV (amazon INV vs INV_AMZ). */
    private function sheinInvWithinMapTolerance(float $inv, float $sheinStock): bool
    {
        if ($inv <= 0) {
            return true;
        }
        $diff = abs($inv - $sheinStock);
        if ($diff <= 3.0) {
            return true;
        }

        return $diff <= ($inv * 0.03);
    }

    /**
     * Map / Miss / NMap — same rules as shein_pricing_view badges (ebay2-aligned):
     * Map/NMap only when listed + both INV and Shein stock > 0.
     */
    public static function countSheinPricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row) || ! empty($row['is_parent'])) {
                continue;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $nrValue = strtoupper(trim((string) (($row['nr_req'] ?? '') ?: ($row['NR'] ?? ''))));
            $isMissingShein = (bool) ($row['is_missing_shein'] ?? false)
                || strtoupper(trim((string) ($row['missing'] ?? ''))) === 'M';
            $rowPrice = (float) ($row['special_offer'] ?? 0);
            $sheinStock = (float) ($row['shein_stock'] ?? 0);

            if ($inv <= 0 || $nrValue !== 'REQ') {
                continue;
            }

            if ($isMissingShein || $rowPrice <= 0) {
                $miss++;
                continue;
            }

            // Both sides need stock (same as sheinRowIsListedForMap)
            if ($sheinStock <= 0) {
                continue;
            }

            $diff = abs($inv - $sheinStock);
            $within = $diff <= 3.0 || $diff <= ($inv * 0.03);
            if ($within) {
                $map++;
            } else {
                $nmap++;
            }
        }

        return [
            'map' => $map,
            'miss' => $miss,
            'nmap' => $nmap,
            'total_views' => 0,
        ];
    }

    /**
     * Persist daily summary for badge charts.
     * Sales / GPFT / GROI use /shein-tabulator sales_page totals; other counts use pricing rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $salesPage
     */
    private function saveSheinPricingSnapshot(array $rows, array $salesPage = []): void
    {
        try {
            $today = now()->toDateString();
            $children = collect($rows)->filter(fn ($r) => empty($r['is_parent']));
            if ($children->isEmpty()) {
                return;
            }

            $totalAl30 = 0.0;
            $zeroSold = 0;
            $moreSold = 0;
            $dilSum = 0.0;
            $dilCount = 0;
            $badgeTotals = self::countSheinPricingBadgeTotals($children);
            $missingCount = $badgeTotals['miss'];
            $mapCount = $badgeTotals['map'];
            $nmapCount = $badgeTotals['nmap'];

            foreach ($children as $row) {
                $inv = (float) ($row['inv'] ?? 0);
                $al30 = (float) ($row['al30'] ?? 0);
                $ovL30 = (float) ($row['ov_l30'] ?? 0);

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
            }

            if ($salesPage === []) {
                $salesPage = SheinShopifySalesService::computeSalesPageTotals();
            }

            $totalSku = $children->count();
            $avgDil = $dilCount > 0 ? $dilSum / $dilCount : 0.0;

            $summaryData = [
                'total_sku' => $totalSku,
                'total_sales' => round((float) ($salesPage['total_sales'] ?? 0), 2),
                'total_pft' => round((float) ($salesPage['total_pft'] ?? 0), 2),
                'total_cogs' => round((float) ($salesPage['total_cogs'] ?? 0), 2),
                'total_al30' => (int) ($salesPage['total_quantity'] ?? round($totalAl30)),
                'avg_gpft' => round((float) ($salesPage['pft_percentage'] ?? 0), 2),
                'avg_dil' => round($avgDil, 2),
                'avg_roi' => round((float) ($salesPage['roi_percentage'] ?? 0), 2),
                'missing_count' => $missingCount,
                'map_count' => $mapCount,
                'nmap_count' => $nmapCount,
                'zero_sold' => $zeroSold,
                'more_sold' => $moreSold,
                'calculated_at' => now()->toDateTimeString(),
            ];

            AmazonChannelSummary::updateOrCreate(
                ['channel' => 'shein', 'snapshot_date' => $today],
                ['summary_data' => $summaryData, 'notes' => 'Auto-saved Shein pricing snapshot (sales-page Sales/GPFT/GROI)']
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
            'lmp_price'   => null, 'lmp_link' => null, 'lmp_entries' => [],
            'linked_lmp_skus' => [],
        ];
    }

    /**
     * Sku Link LMP group for a Shein row — same shared service as /ebay-tabulator-view.
     *
     * @return list<string>
     */
    private function sheinLinkedLmpSkusFor(LmpSkuGroupService $lmpGroupService, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        try {
            $group = $lmpGroupService->groupContaining($sku);
        } catch (\Throwable $e) {
            $group = [];
        }

        $members = $group !== [] ? $group : [$sku];
        $seen = [];
        $out = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $display;
        }

        return $out;
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

                $n = $this->normalizeSheinSkuExact((string) $sku);
                $pm = null;
                if (Schema::hasTable((new ProductMaster)->getTable())) {
                    // SQL UPPER(TRIM) won't fold NBSP / multi-space variants — match in PHP.
                    $pm = ProductMaster::query()
                        ->whereNotNull('sku')->where('sku', '!=', '')
                        ->get()
                        ->first(fn ($r) => $this->normalizeSheinSkuExact((string) $r->sku) === $n);
                }
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

    /**
     * Save buyer / seller links for a SKU into shein_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = \App\Models\SheinListingStatus::where('sku', $sku)->first();
        $existing = $status
            ? (is_array($status->value) ? $status->value : (json_decode($status->value, true) ?: []))
            : [];

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        \App\Models\SheinListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    /**
     * Build the LMP competitor entries (slot, price, link) from a shein_lmp row.
     * Only non-empty price slots are returned.
     *
     * @return array<int, array{slot:int, price:float, link:string|null}>
     */
    private function sheinLmpEntriesFrom($lmpRow): array
    {
        $entries = [];
        if (! $lmpRow) {
            return $entries;
        }
        for ($i = 1; $i <= 4; $i++) {
            $p = $lmpRow->{'price_' . $i};
            $u = $lmpRow->{'url_' . $i};
            if ($p !== null && (float) $p > 0) {
                $entries[] = [
                    'slot'  => $i,
                    'price' => round((float) $p, 2),
                    'link'  => $u ?: null,
                ];
            }
        }
        return $entries;
    }

    /** Locate an existing shein_lmp row by normalized SKU. */
    private function findSheinLmpRow(string $normalizedSku)
    {
        if (! Schema::hasTable('shein_lmp')) {
            return null;
        }
        return \App\Models\SheinLmp::all()
            ->first(fn($r) => $this->normalizeSheinSkuExact((string) $r->sku) === $normalizedSku);
    }

    /**
     * Add a competitor LMP (price + link) into the next free slot for a SKU.
     * Creates the shein_lmp row if it does not exist yet.
     */
    public function saveLmpEntry(Request $request)
    {
        $sku   = trim((string) $request->input('sku'));
        $price = $request->input('price');
        $link  = trim((string) $request->input('link', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if (! is_numeric($price) || (float) $price <= 0) {
            return response()->json(['success' => false, 'message' => 'A valid price greater than 0 is required'], 422);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Invalid product link URL'], 422);
        }
        if (! Schema::hasTable('shein_lmp')) {
            return response()->json(['success' => false, 'message' => 'shein_lmp table does not exist'], 500);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized) ?? new \App\Models\SheinLmp(['sku' => $sku]);

        // Find the next empty slot (price_1 … price_4).
        $slot = null;
        for ($i = 1; $i <= 4; $i++) {
            if ($row->{'price_' . $i} === null) {
                $slot = $i;
                break;
            }
        }
        if ($slot === null) {
            return response()->json(['success' => false, 'message' => 'Maximum of 4 LMP entries reached for this SKU'], 422);
        }

        $row->{'price_' . $slot} = round((float) $price, 2);
        $row->{'url_' . $slot}   = $link !== '' ? $link : null;
        $row->is_not_found       = false;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP added',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }

    /**
     * Update an existing competitor LMP slot (price + link) for a SKU.
     */
    public function updateLmpEntry(Request $request)
    {
        $sku   = trim((string) $request->input('sku'));
        $slot  = (int) $request->input('slot');
        $price = $request->input('price');
        $link  = trim((string) $request->input('link', ''));

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if ($slot < 1 || $slot > 4) {
            return response()->json(['success' => false, 'message' => 'Invalid slot'], 422);
        }
        if (! is_numeric($price) || (float) $price <= 0) {
            return response()->json(['success' => false, 'message' => 'A valid price greater than 0 is required'], 422);
        }
        if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
            return response()->json(['success' => false, 'message' => 'Invalid product link URL'], 422);
        }
        if (! Schema::hasTable('shein_lmp')) {
            return response()->json(['success' => false, 'message' => 'shein_lmp table does not exist'], 500);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'No LMP data found for this SKU'], 404);
        }
        if ($row->{'price_' . $slot} === null) {
            return response()->json(['success' => false, 'message' => 'LMP slot is empty'], 404);
        }

        $row->{'price_' . $slot} = round((float) $price, 2);
        $row->{'url_' . $slot}   = $link !== '' ? $link : null;
        $row->is_not_found       = false;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP updated',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }

    /** Remove a single competitor LMP slot for a SKU. */
    public function deleteLmpEntry(Request $request)
    {
        $sku  = trim((string) $request->input('sku'));
        $slot = (int) $request->input('slot');

        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if ($slot < 1 || $slot > 4) {
            return response()->json(['success' => false, 'message' => 'Invalid slot'], 422);
        }

        $normalized = $this->normalizeSheinSkuExact($sku);
        $row = $this->findSheinLmpRow($normalized);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'No LMP data found for this SKU'], 404);
        }

        $row->{'price_' . $slot} = null;
        $row->{'url_' . $slot}   = null;

        $hasPrice = false;
        for ($i = 1; $i <= 4; $i++) {
            if ($row->{'price_' . $i} !== null) {
                $hasPrice = true;
                break;
            }
        }
        $row->is_not_found = ! $hasPrice;
        $row->save();

        $entries = $this->sheinLmpEntriesFrom($row->fresh());
        $lowest  = collect($entries)->min('price');

        return response()->json([
            'success'   => true,
            'message'   => 'LMP removed',
            'entries'   => $entries,
            'lmp_price' => $lowest,
        ]);
    }
}
