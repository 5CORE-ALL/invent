<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\MiraklDailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BestBuySalesController extends Controller
{
    public function index()
    {
        return view('sales.bestbuy_daily_sales_data');
    }

    public function getData(Request $request)
    {
        \Log::info('BestBuySalesController getData called');

        $orders = MiraklDailyData::bestBuyUsa()
            ->l30()
            ->orderBy('order_created_at', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' Best Buy orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'BestbuyUSA')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

            // Extract LP, Ship, and Weight Act
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = 0;
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
                $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
            }

            $quantity = floatval($order->quantity);
            $unitPrice = floatval($order->unit_price);
            $saleAmount = $unitPrice * $quantity;

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;

            // PFT Each = (price * percentage/100) - lp - ship_cost
            $pftEach = ($unitPrice * ($percentage / 100)) - $lp - $shipCost;

            // PFT Each % = (pft_each / price) * 100
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / LP) * 100
            $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

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
                'order_date' => $order->order_created_at,
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
            $filePath = storage_path('app/bestbuy_column_visibility.json');
            
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
                't_weight' => false,
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
            Log::error('Error getting Best Buy column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/bestbuy_column_visibility.json');
            
            $visibility = $request->input('visibility', []);
            
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Best Buy column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
