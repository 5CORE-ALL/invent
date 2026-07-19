<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\VintedController;
use App\Models\VintedSalesData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VintedSalesController extends Controller
{
    public function index()
    {
        $vintedPercentage = VintedController::marginPercent();

        return view('sales.vinted_sheet_data', [
            'vintedPercentage' => $vintedPercentage,
        ]);
    }

    /**
     * Get Vinted sales data. PFT = sales × margin from marketplace_percentages (Vinted).
     */
    public function getData(Request $request)
    {
        try {
            $rows = VintedSalesData::orderBy('sale_date', 'desc')->get();
            if ($rows->isEmpty()) {
                return response()->json([]);
            }

            $margin = VintedController::marginFactor();
            $data = [];
            foreach ($rows as $row) {
                $quantity = (int) $row->quantity ?: 1;
                if ($quantity < 1) {
                    $quantity = 1;
                }
                $unitPrice = (float) $row->item_price;
                $saleAmount = $unitPrice * $quantity;
                $pft = $saleAmount * $margin;
                $pftEach = $unitPrice * $margin;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                $data[] = [
                    'id' => $row->id,
                    'product_name' => $row->description ? substr(str_replace(["\r", "\n"], ' ', $row->description), 0, 120) : '',
                    'size' => $row->size,
                    'sku' => $row->sku_code ?? '',
                    'quantity' => $quantity,
                    'price' => round($unitPrice, 2),
                    'sale_amount' => round($saleAmount, 2),
                    'sale_date' => $row->sale_date?->format('Y-m-d'),
                    'buyer' => $row->buyer,
                    'lp' => 0,
                    'ship' => 0,
                    'ship_cost' => 0,
                    'weight_act' => 0,
                    't_weight' => 0,
                    'cogs' => 0,
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => 0,
                    'margin' => round($margin * 100, 2),
                ];
            }
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Vinted Sales Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload Vinted sales export (TSV/CSV): 1 header row, then data rows. Min 15 columns.
     * Column map (0-based) — same shape as Depop sales export:
     * 0=Date of sale, 1=Time of sale, 4=Bundle amount, 5=Buyer, 6=Brand, 7=SKU,
     * 8=Description, 9=Size, 10=Item price, 11=Buyer shipping, 12=Total, 13=USPS Cost, 14=Vinted fee
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $handle = null;
        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json(['error' => 'Could not open file'], 400);
            }

            $colDate = 0;
            $colTime = 1;
            $colBundleQty = 4;
            $colBuyer = 5;
            $colSKU = 7;
            $colDescription = 8;
            $colSize = 9;
            $colItemPrice = 10;
            $colTotal = 12;
            $colUspsCost = 13;
            $colVintedFee = 14;
            $minCols = 15;

            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                return response()->json(['error' => 'File is empty'], 400);
            }
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine); // strip BOM
            $delimiter = "\t";
            foreach (["\t", ",", ";"] as $d) {
                $test = str_getcsv($firstLine, $d);
                if (count($test) >= $minCols) {
                    $delimiter = $d;
                    break;
                }
            }
            $header = str_getcsv($firstLine, $delimiter);
            if (count($header) < $minCols) {
                fclose($handle);
                return response()->json([
                    'error' => 'Invalid or short header row. Need at least ' . $minCols . ' columns (Vinted sales export). Got ' . count($header) . '. Use tab or comma delimiter.',
                ], 400);
            }

            DB::beginTransaction();
            VintedSalesData::query()->delete();

            $inserted = 0;
            while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
                $numCols = count($cells);
                if ($numCols < $minCols) {
                    continue;
                }
                $dateStr = isset($cells[$colDate]) ? trim($cells[$colDate], " \t\"") : '';
                $timeStr = isset($cells[$colTime]) ? trim($cells[$colTime], " \t\"") : '';
                $buyer = isset($cells[$colBuyer]) ? trim($cells[$colBuyer], " \t\"") : '';
                $sku = isset($cells[$colSKU]) ? trim($cells[$colSKU], " \t\"") : null;
                $description = isset($cells[$colDescription]) ? trim($cells[$colDescription], " \t\"") : '';
                $size = isset($cells[$colSize]) ? trim($cells[$colSize], " \t\"") : null;
                $qtyRaw = isset($cells[$colBundleQty]) ? trim($cells[$colBundleQty], " \t\"") : 'N/A';
                $itemPrice = isset($cells[$colItemPrice]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colItemPrice]) : 0;
                $total = isset($cells[$colTotal]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colTotal]) : 0;
                $uspsCost = isset($cells[$colUspsCost]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colUspsCost]) : null;
                $vintedFee = isset($cells[$colVintedFee]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colVintedFee]) : null;

                if ($qtyRaw === '' || $qtyRaw === 'N/A' || $qtyRaw === '-') {
                    $quantity = 1;
                } else {
                    $quantity = (int) preg_replace('/[^0-9-]/', '', $qtyRaw) ?: 1;
                }

                $saleDate = null;
                if ($dateStr) {
                    try {
                        $dt = $timeStr
                            ? \Carbon\Carbon::createFromFormat('m/d/Y h:i A', $dateStr . ' ' . $timeStr)
                            : \Carbon\Carbon::createFromFormat('m/d/Y', $dateStr);
                        $saleDate = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        try {
                            $saleDate = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
                        } catch (\Exception $e2) {
                            // leave null
                        }
                    }
                }

                if ($itemPrice <= 0 && $total <= 0) {
                    continue;
                }

                VintedSalesData::create([
                    'sale_date' => $saleDate,
                    'buyer' => $buyer,
                    'sku_code' => $sku ?: null,
                    'description' => $description,
                    'size' => $size ?: null,
                    'quantity' => $quantity,
                    'item_price' => $itemPrice,
                    'total' => $total,
                    'usps_cost' => $uspsCost,
                    'vinted_fee' => $vintedFee,
                ]);
                $inserted++;
            }
            fclose($handle);
            $handle = null;
            DB::commit();

            // Push L30 order qty into vinted_pricing so Analytics stays in sync.
            $syncedL30 = 0;
            try {
                $syncedL30 = VintedController::syncL30FromSales();
            } catch (\Throwable $e) {
                Log::warning('Vinted L30 sync after sales upload failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => "Upload complete. {$inserted} sales rows imported. L30 synced for {$syncedL30} SKU(s).",
                'rows' => $inserted,
                'l30_synced' => $syncedL30,
            ]);
        } catch (\Exception $e) {
            if ($handle && is_resource($handle)) {
                fclose($handle);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Vinted Sales Upload Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getColumnVisibility()
    {
        $visibility = Cache::get('vinted_sheet_column_visibility', []);
        return response()->json($visibility);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        Cache::put('vinted_sheet_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }
}
