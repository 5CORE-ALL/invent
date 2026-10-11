<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\MiraklDailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MacysSalesController extends Controller
{
    public function index()
    {
        return view('sales.macys_daily_sales_data');
    }

    public function getData(Request $request)
    {
        \Log::info('MacysSalesController getData called');

        $orders = MiraklDailyData::macys()
            ->l30()
            ->whereNotIn('status', ['CLOSED', 'CHANNEL_SPECIFIC'])
            ->orderBy('order_created_at', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' Macys orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 76;
        $margin = $percentage / 100;
        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

            $lp = 0;
            $ship = 0;
            $values = [];
            $parent = '';
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $values = is_array($values) ? $values : [];
                $parent = (string) ($pm->parent ?? '');
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            }

            $quantity = floatval($order->quantity);
            $unitPrice = floatval($order->unit_price);
            $saleAmount = $unitPrice * $quantity;

            // Mirakl stores UTC wall-clock. Show California so a 10:50 PM ET 17th
            // (02:50 UTC on the 18th) stays on the 17th — same as the Macy's seller page.
            $orderDatePt = MiraklDailyData::pacificDateTime($order->getRawOriginal('order_created_at'));

            $weightAct = EbaySalesController::actWeightLb($values);
            $tWeight = $weightAct * $quantity;
            $shipCost = $quantity > 0
                ? EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, (string) ($order->sku ?? ''), $values, $quantity, $parent)
                : 0.0;

            // COGS = LP × Qty. unit_price is the unit, so sales is unit_price × Qty once.
            $cogs = $lp * $quantity;
            $pft = ($saleAmount * $margin) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $data[] = [
                'order_id' => $order->order_id,
                'channel_order_id' => $order->channel_order_id,
                'order_line_id' => $order->order_line_id,
                'sku' => $order->sku,
                'product_title' => $order->product_title,
                'quantity' => $order->quantity,
                'unit_price' => round($unitPrice, 2),
                'sale_amount' => round($saleAmount, 2),
                'currency' => $order->currency,
                'order_date' => $orderDatePt,
                'status' => $order->status,
                'period' => $order->period,
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
                'shipping_state' => $order->shipping_state,
                'shipping_city' => $order->shipping_city,
            ];
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/macys_column_visibility.json');
            
            $defaultVisibility = [
                'order_id' => true,
                'channel_order_id' => false,
                'order_line_id' => false,
                'sku' => true,
                'product_title' => true,
                'quantity' => true,
                'unit_price' => true,
                'sale_amount' => true,
                'currency' => false,
                'order_date' => true,
                'status' => true,
                'period' => false,
                'lp' => true,
                'ship' => true,
                't_weight' => true,
                'ship_cost' => true,
                'cogs' => true,
                'pft_each' => true,
                'pft_each_pct' => true,
                'pft' => true,
                'roi' => true,
                'shipping_state' => false,
                'shipping_city' => false,
            ];

            if (file_exists($filePath)) {
                $json = file_get_contents($filePath);
                $saved = json_decode($json, true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }
            
            return response()->json($defaultVisibility);
        } catch (\Exception $e) {
            Log::error('Error getting Macys column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/macys_column_visibility.json');
            
            $visibility = $request->input('visibility', []);
            
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Macys column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
