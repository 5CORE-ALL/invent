<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\Temu3DataView;
use App\Models\Temu3Pricing;
use App\Services\TemuShopifySalesService;
use App\Support\Marketplace\Temu3OrderSheet;
use App\Support\TemuGoodsIdHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Temu 3 Analytics — same UI/features as Temu / Temu 2, sheet-only (no Open API).
 * Price sheet upload truncates temu3_pricing. Order export upload truncates temu3_orders.
 */
class Temu3Controller extends TemuController
{
    public function temu3DecreaseView()
    {
        $temuMargin = TemuShopifySalesService::temuMarginDecimal();

        return view('market-places.temu3_decrease', compact('temuMargin'));
    }

    public function getTemu3DecreaseData(Request $request)
    {
        return $this->buildTemuDecreaseDataResponse($request, 'temu3');
    }

    public function getTemu3DecreaseDataL7(Request $request)
    {
        $request->query->set('period', 'L7');

        return $this->buildTemuDecreaseDataResponse($request, 'temu3');
    }

    /**
     * Upload Temu 3 listing / price sheet. Every upload TRUNCATES temu3_pricing first.
     */
    public function uploadTemu3Pricing(Request $request)
    {
        @set_time_limit(120);
        $request->validate([
            'pricing_file' => 'required|file|max:20480',
        ]);

        $file = $request->file('pricing_file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['xlsx', 'xls', 'csv', 'tsv', 'txt'];
        if (! in_array($ext, $allowed, true)) {
            $msg = 'Invalid file type. Upload .xlsx, .xls, .csv, or .tsv (Temu listing export).';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        try {
            $rows = $this->readTemu2PricingUploadRows($file->getRealPath(), $ext);
            if (count($rows) < 2) {
                throw new \RuntimeException('File has no data rows.');
            }

            $rawHeaders = array_shift($rows);
            $headerMap = [];
            foreach ($rawHeaders as $idx => $header) {
                if ($header instanceof RichText) {
                    $header = $header->getPlainText();
                }
                $key = strtolower(trim(preg_replace('/\s+/', ' ', (string) $header)));
                if ($key !== '') {
                    $headerMap[$key] = (int) $idx;
                }
            }

            $col = static function (array $aliases) use ($headerMap) {
                foreach ($aliases as $alias) {
                    $k = strtolower(trim(preg_replace('/\s+/', ' ', $alias)));
                    if (array_key_exists($k, $headerMap)) {
                        return $headerMap[$k];
                    }
                }

                return null;
            };

            $goodsIdCol = $col(['Goods ID', 'GoodsID', 'goods_id']);
            $basePriceCol = $col(['Base price', 'Base Price', 'base_price', 'Price']);
            $skuCol = $col(['SKU', 'Contribution SKU']);
            $skuIdCol = $col(['SKU ID', 'sku_id']);
            $qtyCol = $col(['Quantity']);
            $categoryCol = $col(['Category']);
            $categoryIdCol = $col(['Category id', 'Category ID']);
            $productNameCol = $col(['Product name', 'Product Name']);
            $contribCol = $col(['Contribution Goods']);
            $variationCol = $col(['Variation']);
            $statusCol = $col(['Status']);
            $detailStatusCol = $col(['Detail status', 'Detail Status']);
            $extTypeCol = $col(['External Product ID Type']);
            $extIdCol = $col(['External product ID', 'External Product ID']);
            $incompleteCol = $col(['Incomplete product information']);

            if ($goodsIdCol === null) {
                throw new \RuntimeException('Missing required column: Goods ID.');
            }

            $val = static function (array $row, $idx) {
                if ($idx === null || ! array_key_exists($idx, $row)) {
                    return null;
                }
                $v = $row[$idx];
                if ($v instanceof RichText) {
                    $v = $v->getPlainText();
                }

                return is_string($v) ? trim($v) : $v;
            };

            $normId = static function ($v) {
                if ($v === null || $v === '') {
                    return '';
                }
                if (is_float($v) || (is_numeric($v) && preg_match('/[eE]/', (string) $v))) {
                    return TemuGoodsIdHelper::normalizeKey(number_format((float) $v, 0, '.', '')) ?? '';
                }

                return TemuGoodsIdHelper::normalizeKey($v) ?? '';
            };

            $now = now()->format('Y-m-d H:i:s');
            $pricingRows = [];
            $skipped = 0;
            $nextPricingId = 1;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;
                    continue;
                }

                $goodsId = $normId($val($row, $goodsIdCol));
                $baseRaw = $basePriceCol !== null ? $val($row, $basePriceCol) : null;
                $basePrice = 0.0;
                if ($baseRaw !== null && $baseRaw !== '') {
                    $basePrice = is_numeric($baseRaw)
                        ? (float) $baseRaw
                        : (float) preg_replace('/[^0-9.\-]/', '', (string) $baseRaw);
                }
                if (! is_finite($basePrice) || $basePrice < 0) {
                    $basePrice = 0.0;
                }
                $sku = trim((string) ($val($row, $skuCol) ?? ''));
                $skuId = $normId($val($row, $skuIdCol));
                $qty = (int) ($val($row, $qtyCol) ?? 0);

                if ($goodsId === '' || ($sku === '' && $skuId === '')) {
                    $skipped++;
                    continue;
                }

                $skuOut = $sku !== '' ? $sku : $skuId;
                $pricingRows[] = [
                    'id' => $nextPricingId++,
                    'category' => (string) ($val($row, $categoryCol) ?? ''),
                    'category_id' => (string) ($val($row, $categoryIdCol) ?? ''),
                    'product_name' => (string) ($val($row, $productNameCol) ?? ''),
                    'contribution_goods' => (string) ($val($row, $contribCol) ?? ''),
                    'sku' => $skuOut,
                    'goods_id' => $goodsId,
                    'sku_id' => $skuId,
                    'variation' => (string) ($val($row, $variationCol) ?? ''),
                    'quantity' => $qty,
                    'base_price' => round($basePrice, 2),
                    'external_product_id_type' => (string) ($val($row, $extTypeCol) ?? ''),
                    'external_product_id' => (string) ($val($row, $extIdCol) ?? ''),
                    'status' => (string) ($val($row, $statusCol) ?? ''),
                    'detail_status' => (string) ($val($row, $detailStatusCol) ?? ''),
                    'date_created' => null,
                    'incomplete_product_information' => (string) ($val($row, $incompleteCol) ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $imported = count($pricingRows);

            if (Schema::hasTable('temu3_pricing')) {
                DB::table('temu3_pricing')->truncate();
            }

            DB::beginTransaction();
            try {
                if (Schema::hasTable('temu3_pricing')) {
                    foreach (array_chunk($pricingRows, 500) as $chunk) {
                        DB::table('temu3_pricing')->insert($chunk);
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $message = "Imported {$imported} pricing row(s) (old temu3_pricing truncated)"
                .($skipped > 0 ? ", skipped {$skipped}" : '')
                .($basePriceCol === null ? ' No Price / Base price column — analytics Price cleared.' : '')
                .'.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                    'skipped' => $skipped,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Temu 3 pricing upload failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $msg = 'Error uploading Temu 3 pricing: '.$e->getMessage();

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->with('error', $msg);
        }
    }

    public function downloadTemu3PricingSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Category',
            'Category id',
            'Product name',
            'Contribution Goods',
            'SKU',
            'Goods ID',
            'SKU ID',
            'Variation',
            'Quantity',
            'Base price',
            'External Product ID Type',
            'External product ID',
            'Status',
            'Detail status',
            'Date created',
            'Incomplete product information',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            [
                'Musical Instruments/Electronic Music',
                '18434',
                '5Core Speaker Stand 2Pc Heavy Duty',
                'SS SQ WH',
                'SS SQ WH',
                '603239688828956',
                '47514283725096',
                'White',
                '100',
                '9.64',
                '',
                '',
                'Active',
                'Active',
                '01/09/2026 10:58:58',
                '',
            ],
        ], null, 'A2');

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        $fileName = 'Temu3_Pricing_Sample_'.date('Y-m-d').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    /**
     * Upload Temu Seller Center order export. Every upload TRUNCATES temu3_orders first.
     * Format: Order ID, order status, contribution sku, SKU ID, quantity purchased,
     * purchase date, goods base price, …
     */
    public function uploadTemu3Orders(Request $request)
    {
        @set_time_limit(180);
        $request->validate([
            'orders_file' => 'required|file|max:40960',
        ]);

        $file = $request->file('orders_file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['xlsx', 'xls', 'csv', 'tsv', 'txt'];
        if (! in_array($ext, $allowed, true)) {
            $msg = 'Invalid file type. Upload the Temu Seller Center order export (.xlsx, .xls, .csv, .tsv, or .txt).';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        try {
            $rows = $this->readTemu2PricingUploadRows($file->getRealPath(), $ext);
            if (count($rows) < 2) {
                throw new \RuntimeException('File has no data rows.');
            }

            $rawHeaders = array_shift($rows);
            $headers = [];
            foreach ($rawHeaders as $header) {
                if ($header instanceof RichText) {
                    $header = $header->getPlainText();
                }
                $headers[] = Temu3OrderSheet::normalizeHeader($header);
            }

            if (! in_array('order_id', $headers, true)) {
                throw new \RuntimeException('Missing required column: Order ID. This upload expects the Temu Seller Center order export.');
            }

            $now = now()->format('Y-m-d H:i:s');
            $insertRows = [];
            $skipped = 0;

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $skipped++;
                    continue;
                }
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = @array_combine($headers, $rowData);
                if (! is_array($data)) {
                    $skipped++;
                    continue;
                }

                $mapped = Temu3OrderSheet::mapInsertRow($data);
                if ($mapped === null) {
                    $skipped++;
                    continue;
                }
                $mapped['created_at'] = $now;
                $mapped['updated_at'] = $now;
                $insertRows[] = $mapped;
            }

            $imported = count($insertRows);

            if (Schema::hasTable('temu3_orders')) {
                DB::table('temu3_orders')->truncate();
            }

            DB::beginTransaction();
            try {
                if (Schema::hasTable('temu3_orders')) {
                    foreach (array_chunk($insertRows, 500) as $chunk) {
                        DB::table('temu3_orders')->insert($chunk);
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $message = "Imported {$imported} order row(s) (old temu3_orders truncated)"
                .($skipped > 0 ? ", skipped {$skipped}" : '')
                .'.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'imported' => $imported,
                    'skipped' => $skipped,
                ]);
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::error('Temu 3 orders upload failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $msg = 'Error uploading Temu 3 orders: '.$e->getMessage();

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => $msg], 500)
                : back()->with('error', $msg);
        }
    }

    public function downloadTemu3OrdersSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Order ID',
            'order status',
            'Fulfillment mode',
            'Order item ID',
            'order item status',
            'product name by customer order',
            'product name',
            'variation',
            'contribution sku',
            'SKU ID',
            'quantity purchased',
            'quantity to ship',
            'quantity shipped',
            'quantity canceled',
            'purchase date',
            'goods base price',
            'tracking number',
            'carrier',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            [
                'PO-211-10467286595191067',
                'Unshipped',
                'Seller fulfillment',
                '211-10467265623671067',
                'Unshipped',
                '5 Core 4 Inch Subwoofer Pair',
                '5 Core 4 Inch Subwoofer Pair',
                '4 Inch',
                'WF 4INCH',
                '170467251982528',
                '1',
                '1',
                '0',
                '0',
                'Sep 2, 2026, 2:04 am IST(UTC+5)',
                '$9.64',
                '',
                '',
            ],
        ], null, 'A2');

        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setWidth(22);
        }

        $fileName = 'Temu3_Orders_Sample_'.date('Y-m-d').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function uploadTemu3ViewData(Request $request)
    {
        try {
            $result = $this->replaceTemuViewTableFromUploads(
                'temu3_view_data',
                $this->collectTemuViewUploadFiles($request),
                ! $request->boolean('merge')
            );

            return $this->temuViewUploadResult($request, $result, 'temu3_view_data');
        } catch (\Exception $e) {
            Log::error('Error uploading Temu 3 view data: '.$e->getMessage());

            return $this->temuViewUploadError($request, $e);
        }
    }

    public function downloadTemu3ViewDataSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'Date',
            'Goods ID',
            'Goods Name',
            'Product impressions',
            'Number of visitor impressions of the product',
            'Product clicks',
            'Number of visitor clicks on the product',
            'CTR',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['2025-11-01', '603163444796046', '5Core 6.5 Inch Midrange Car Door Speaker', '98493', '71393', '3188', '2825', '3.24%'],
        ], null, 'A2');

        $fileName = 'Temu3_View_Data_Sample_'.date('Y-m-d').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function saveTemu3Sprice(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'sprice' => 'required|numeric|min:0',
            ]);

            $sku = trim((string) $request->sku);
            $sprice = (float) $request->sprice;
            $metrics = $this->temuSpriceMetricsForSku($sku, $sprice);
            $this->writeTemu3Sprice($sku, $sprice, $metrics['sgprft'], $metrics['sroi']);

            return response()->json([
                'success' => true,
                'message' => 'SPRICE saved successfully',
                'sprice' => $sprice,
                'sgprft_percent' => $metrics['sgprft'],
                'sroi_percent' => $metrics['sroi'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Temu 3 SPRICE: '.$e->getMessage());

            return response()->json(['error' => 'Failed to save SPRICE'], 500);
        }
    }

    public function saveTemu3SpriceBatch(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (! is_array($updates)) {
                $updates = [];
            }

            $ok = 0;
            $cleared = 0;
            $skus = [];
            foreach ($updates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $skus[] = $sku;
            }

            if ($request->boolean('clear_first')) {
                $cleared += $this->clearTemu3SpriceForSkus($skus);
            }

            foreach ($updates as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sku = trim((string) ($row['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $sprice = isset($row['sprice']) ? (float) $row['sprice'] : 0.0;
                if ($sprice > 0) {
                    $metrics = $this->temuSpriceMetricsForSku($sku, $sprice);
                    $this->writeTemu3Sprice($sku, $sprice, $metrics['sgprft'], $metrics['sroi']);
                    $ok++;
                } elseif (! $request->boolean('clear_first')) {
                    $this->writeTemu3Sprice($sku, 0, 0, 0);
                    $cleared++;
                }
            }

            return response()->json([
                'success' => true,
                'ok' => $ok,
                'cleared' => $cleared,
            ]);
        } catch (\Exception $e) {
            Log::error('Error batch-saving Temu 3 SPRICE: '.$e->getMessage());

            return response()->json(['error' => 'Failed to save SPRICE batch'], 500);
        }
    }

    public function clearAllTemu3Sprice(Request $request)
    {
        $skus = $request->input('skus', []);
        if (! is_array($skus) || $skus === []) {
            return response()->json(['success' => false, 'message' => 'No SKUs selected'], 400);
        }

        $cleared = $this->clearTemu3SpriceForSkus($skus);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'message' => "Successfully cleared SPRICE for {$cleared} SKU(s)",
        ]);
    }

    public function updateTemu3Price(Request $request)
    {
        try {
            $request->validate([
                'sku' => 'required|string',
                'base_price' => 'required|numeric|min:0',
            ]);

            if (! Schema::hasTable('temu3_pricing')) {
                return response()->json(['error' => 'temu3_pricing table missing'], 500);
            }

            $updated = Temu3Pricing::where('sku', $request->sku)->update([
                'base_price' => $request->base_price,
                'updated_at' => now(),
            ]);

            if ($updated === 0) {
                return response()->json(['error' => 'SKU not found in temu3_pricing'], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Temu 3 price: '.$e->getMessage());

            return response()->json(['error' => 'Failed to update price'], 500);
        }
    }

    /**
     * Temu 3 has no API — persist SPRICE→base locally on the price sheet only.
     */
    public function pushTemu3Price(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'price' => 'required|numeric|min:0.01',
        ]);

        $sku = trim((string) $request->input('sku'));
        $price = (float) $request->input('price');

        if (Schema::hasTable('temu3_pricing')) {
            Temu3Pricing::where('sku', $sku)->update([
                'base_price' => $price,
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Price saved to Temu 3 sheet (no API). Re-upload the price sheet to replace all rows.',
            'data' => ['sku' => $sku, 'price' => $price],
        ]);
    }

    public function saveTemu3ListingFieldsToDataView(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'nr_req' => 'nullable|string|in:REQ,NRL,NR',
            'listed' => 'nullable|string|in:Listed,Pending',
            'buyer_link' => 'nullable|url',
            'seller_link' => 'nullable|url',
        ]);

        $sku = trim((string) $validated['sku']);
        $row = Temu3DataView::firstOrNew(['sku' => $sku]);
        $row->sku = $sku;
        $existing = $this->decodeDataViewValue($row);

        $fields = ['nr_req', 'listed', 'buyer_link', 'seller_link'];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                if ($field === 'nr_req' && isset($validated[$field]) && $validated[$field] === 'NR') {
                    $existing[$field] = 'NRL';
                } else {
                    $existing[$field] = $validated[$field];
                }
            }
        }

        $row->value = $existing;
        $row->save();

        return response()->json([
            'status' => 'success',
            'message' => 'NR/REQ updated (temu3_data_view)',
            'nr_req' => $existing['nr_req'] ?? null,
        ]);
    }

    public function saveTemu3DecreaseLinks(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'buyer_link' => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim((string) $validated['sku']);
        $buyerLink = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)).' must be a valid URL.',
                ], 422);
            }
        }

        $row = Temu3DataView::firstOrNew(['sku' => $sku]);
        $row->sku = $sku;
        $existing = $this->decodeDataViewValue($row);
        $existing['buyer_link'] = $buyerLink;
        $existing['seller_link'] = $sellerLink;
        $row->value = $existing;
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Links saved.',
            'buyer_link' => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    public function saveTemu3DecreaseColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        Cache::put("temu3_decrease_column_visibility_{$userId}", $request->input('visibility', []), now()->addDays(30));

        return response()->json(['success' => true]);
    }

    public function getTemu3DecreaseColumnVisibility()
    {
        $userId = auth()->id() ?? 'guest';

        return response()->json(Cache::get("temu3_decrease_column_visibility_{$userId}", []));
    }

    private function writeTemu3Sprice(string $sku, float $sprice, float $sgprftPercent, float $sroiPercent): void
    {
        if (! Schema::hasTable('temu3_data_view')) {
            return;
        }

        $dataView = Temu3DataView::firstOrNew(['sku' => $sku]);
        $dataView->sku = $sku;
        $existingValue = $this->decodeDataViewValue($dataView);

        if ($sprice > 0) {
            $existingValue['sprice'] = $sprice;
            $existingValue['SPRICE'] = $sprice;
            $existingValue['sgprft_percent'] = round($sgprftPercent, 2);
            $existingValue['sroi_percent'] = round($sroiPercent, 2);
            $existingValue['SGPFT'] = round($sgprftPercent, 2);
            $existingValue['SROI'] = round($sroiPercent, 2);
        } else {
            unset(
                $existingValue['sprice'], $existingValue['SPRICE'],
                $existingValue['sgprft_percent'], $existingValue['sroi_percent'],
                $existingValue['SGPFT'], $existingValue['SROI'],
                $existingValue['SPFT'], $existingValue['spft']
            );
        }

        $dataView->value = $existingValue;
        if (! $dataView->exists && empty($dataView->id)) {
            $dataView->id = ((int) (Temu3DataView::query()->max('id') ?? 0)) + 1;
        }
        $dataView->save();
    }

    /**
     * @param  array<int, string>  $skus
     */
    private function clearTemu3SpriceForSkus(array $skus): int
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === [] || ! Schema::hasTable('temu3_data_view')) {
            return 0;
        }

        $cleared = 0;
        foreach (Temu3DataView::whereIn('sku', $skus)->get() as $record) {
            $value = is_array($record->value) ? $record->value : [];
            $fieldsToRemove = [
                'sprice', 'SPRICE', 'sgprft_percent', 'sroi_percent',
                'SGPFT', 'SROI', 'SPFT', 'spft', 'ship',
                'amazon_price_applied_at', 'r_price_applied_at', 'sprice_status',
            ];
            $wasModified = false;
            foreach ($fieldsToRemove as $field) {
                if (isset($value[$field])) {
                    unset($value[$field]);
                    $wasModified = true;
                }
            }
            if (! $wasModified) {
                continue;
            }
            if ($value === []) {
                $record->delete();
            } else {
                $record->update(['value' => $value, 'updated_at' => now()]);
            }
            $cleared++;
        }

        return $cleared;
    }

    private function decodeDataViewValue(Temu3DataView $row): array
    {
        $existing = is_array($row->value)
            ? $row->value
            : (is_string($row->value) ? json_decode($row->value, true) : []);
        if (! is_array($existing)) {
            $raw = $row->getRawOriginal('value');
            $existing = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        }

        return is_array($existing) ? $existing : [];
    }
}
