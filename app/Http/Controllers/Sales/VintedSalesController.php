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
                    'source' => ($row->source ?? 'upload') === 'shopify' ? 'Shopify (Fluf)' : 'Upload',
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
