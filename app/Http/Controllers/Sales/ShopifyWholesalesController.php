<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShopifyWholesalesController extends Controller
{
    public function index()
    {
        return view('sales.shopify_wholesales_sales');
    }

    public function getData(Request $request)
    {
        \Log::info('ShopifyWholesalesController getData called');

        // Get marketplace percentage from database
        $marketplacePercentage = DB::table('marketplace_percentages')
            ->where('marketplace', 'ShopifyWholesale')
            ->value('percentage');
        
        // Convert percentage to decimal (e.g., 95 -> 0.95)
        $marginMultiplier = $marketplacePercentage ? ($marketplacePercentage / 100) : 0.95;
        
        \Log::info('Using marketplace percentage: ' . $marketplacePercentage . '% (' . $marginMultiplier . ')');

        // Get date 30 days ago in PST
        $pstTimezone = 'America/Los_Angeles';
        $thirtyDaysAgo = Carbon::now($pstTimezone)->subDays(30)->startOfDay();

        // Query shopify_order_items from apicentral connection
        // Filter by source_name = 'shopify_draft_order'
        $orders = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->where('order_date', '>=', $thirtyDaysAgo)
            ->where('source_name', '=', 'shopify_draft_order')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' Shopify Wholesale orders with source_name=shopify_draft_order');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->filter()->toArray();

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

            $quantity = floatval($order->quantity ?? 0);
            $price = floatval($order->price ?? 0);
            $totalAmount = floatval($order->total_amount ?? ($price * $quantity));
            $originalPrice = $price; // No separate original_price column in this table
            $discountAmount = 0; // No separate discount_amount column in this table

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

            // PFT Each = (price * marginMultiplier) - lp - ship_cost
            // Using marketplace percentage from database
            $pftEach = ($price * $marginMultiplier) - $lp - $shipCost;

            // PFT Each % = (pft_each / price) * 100
            $pftEachPct = $price > 0 ? ($pftEach / $price) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            // L30 Sales = Quantity * Price
            $l30Sales = $quantity * $price;

            $data[] = [
                'order_id' => $order->order_id ?? '',
                'order_number' => $order->order_number ?? '',
                'sku' => $order->sku ?? '',
                'title' => $order->product_title ?? '',
                'quantity' => $order->quantity ?? 0,
                'original_price' => round($originalPrice, 2),
                'discount_amount' => round($discountAmount, 2),
                'price' => round($price, 2),
                'total_amount' => round($totalAmount, 2),
                'order_date' => $order->order_date ?? '',
                'financial_status' => $order->financial_status ?? 'Unknown',
                'fulfillment_status' => $order->fulfillment_status ?? 'Pending',
                'customer_name' => $order->customer_name ?? '',
                'shipping_city' => $order->shipping_city ?? '',
                'shipping_country' => $order->shipping_country ?? '',
                'tracking_number' => $order->tracking_number ?? '',
                'tracking_company' => $order->tracking_company ?? '',
                'tags' => $order->tags ?? '',
                'source_name' => $order->source_name ?? '',
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
            'source_name' => true,
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

        $saved = session('shopify_wholesales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['shopify_wholesales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}
