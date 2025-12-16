<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ShopifyB2CDailyData;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyB2CSalesController extends Controller
{
    public function index()
    {
        return view('sales.shopify_b2c_daily_sales_data');
    }

    public function getData(Request $request)
    {
        \Log::info('ShopifyB2CSalesController getData called');

        $orders = ShopifyB2CDailyData::where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' Shopify B2C orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

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
            $price = floatval($order->price);
            $totalAmount = floatval($order->total_amount);

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation:
            // If quantity is 1: ship_cost = ship / 1
            // If quantity > 1 and t_weight < 20: ship_cost = ship / quantity
            // Otherwise: ship_cost = ship
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;

            // PFT Each = (price * 0.95) - lp - ship_cost (Shopify B2C uses 95% margin)
            $pftEach = ($price * 0.95) - $lp - $shipCost;

            // PFT Each % = (pft_each / price) * 100
            $pftEachPct = $price > 0 ? ($pftEach / $price) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            // L30 Sales = Quantity * Price
            $l30Sales = $quantity * $price;

            $data[] = [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'sku' => $order->sku,
                'title' => $order->product_title,
                'quantity' => $order->quantity,
                'original_price' => round(floatval($order->original_price), 2),
                'discount_amount' => round(floatval($order->discount_amount), 2),
                'price' => round($price, 2),
                'total_amount' => round($totalAmount, 2),
                'order_date' => $order->order_date,
                'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'customer_name' => $order->customer_name,
                'shipping_city' => $order->shipping_city,
                'shipping_country' => $order->shipping_country,
                'tracking_number' => $order->tracking_number,
                'tags' => $order->tags,
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
                'l30_sales' => round($l30Sales, 2),
            ];
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        $defaultVisibility = [
            'order_id' => true,
            'order_number' => true,
            'sku' => true,
            'title' => true,
            'quantity' => true,
            'original_price' => true,
            'discount_amount' => true,
            'price' => true,
            'total_amount' => true,
            'order_date' => true,
            'financial_status' => true,
            'fulfillment_status' => true,
            'customer_name' => true,
            'shipping_city' => true,
            'shipping_country' => true,
            'tracking_number' => true,
            'tags' => true,
            'lp' => true,
            'ship' => true,
            't_weight' => true,
            'ship_cost' => true,
            'cogs' => true,
            'pft_each' => true,
            'pft_each_pct' => true,
            'pft' => true,
            'roi' => true,
            'l30_sales' => true,
        ];

        $saved = session('shopify_b2c_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['shopify_b2c_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}
