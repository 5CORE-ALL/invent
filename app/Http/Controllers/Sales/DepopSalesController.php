<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\DepopSalesData;

class DepopSalesController extends Controller
{
    const MARGIN = 0.87; // 87% margin for Depop

    public function index()
    {
        return view('sales.depop_sheet_data');
    }

    /**
     * Get Depop sales data. No SKU / Product Master — PFT = sales × margin (87%).
     */
    public function getData(Request $request)
    {
        try {
            $rows = DepopSalesData::orderBy('sale_date', 'desc')->get();
            if ($rows->isEmpty()) {
                return response()->json([]);
            }

            $margin = self::MARGIN;
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
            Log::error('Depop Sales Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload Depop sales export (TSV): 1 header row, then data rows.
     * Columns: 0=Date of sale, 1=Time of sale, 4=Bundle amount, 5=Buyer, 7=Description, 8=Size, 9=Item price, 11=Total, 12=USPS Cost, 13=Depop fee
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
            $colDescription = 7;
            $colSize = 8;
            $colItemPrice = 9;
            $colTotal = 11;
            $colUspsCost = 12;
            $colDepopFee = 13;
            $minCols = 14;

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
                    'error' => 'Invalid or short header row. Need at least ' . $minCols . ' columns (Depop sales export). Got ' . count($header) . '. Use tab or comma delimiter.',
                ], 400);
            }

            DB::beginTransaction();
            DepopSalesData::query()->delete();

            $inserted = 0;
            while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
                $numCols = count($cells);
                if ($numCols < $minCols) {
                    continue;
                }
                $dateStr = isset($cells[$colDate]) ? trim($cells[$colDate], " \t\"") : '';
                $timeStr = isset($cells[$colTime]) ? trim($cells[$colTime], " \t\"") : '';
                $buyer = isset($cells[$colBuyer]) ? trim($cells[$colBuyer], " \t\"") : '';
                $description = isset($cells[$colDescription]) ? trim($cells[$colDescription], " \t\"") : '';
                $size = isset($cells[$colSize]) ? trim($cells[$colSize], " \t\"") : null;
                $qtyRaw = isset($cells[$colBundleQty]) ? trim($cells[$colBundleQty], " \t\"") : 'N/A';
                $itemPrice = isset($cells[$colItemPrice]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colItemPrice]) : 0;
                $total = isset($cells[$colTotal]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colTotal]) : 0;
                $uspsCost = isset($cells[$colUspsCost]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colUspsCost]) : null;
                $depopFee = isset($cells[$colDepopFee]) ? (float) preg_replace('/[^0-9.]/', '', $cells[$colDepopFee]) : null;

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

                DepopSalesData::create([
                    'sale_date' => $saleDate,
                    'buyer' => $buyer,
                    'description' => $description,
                    'size' => $size ?: null,
                    'quantity' => $quantity,
                    'item_price' => $itemPrice,
                    'total' => $total,
                    'usps_cost' => $uspsCost,
                    'depop_fee' => $depopFee,
                ]);
                $inserted++;
            }
            fclose($handle);
            $handle = null;
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Upload complete. {$inserted} sales rows imported.",
                'rows' => $inserted,
            ]);
        } catch (\Exception $e) {
            if ($handle && is_resource($handle)) {
                fclose($handle);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Depop Sales Upload Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getColumnVisibility()
    {
        $visibility = Cache::get('depop_sheet_column_visibility', []);
        return response()->json($visibility);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        Cache::put('depop_sheet_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }
}
