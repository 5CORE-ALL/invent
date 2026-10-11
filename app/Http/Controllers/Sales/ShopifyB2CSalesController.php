<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ShopifyB2CDailyData;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyB2CSalesController extends Controller
{
    public function index()
    {
        // Calculate Google Ads Spent (L30)
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29); // 30 days total
        
        $googleSpent = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $yesterday)
            ->where('advertising_channel_type', 'SHOPPING')
            ->sum('metrics_cost_micros') / 1000000; // Convert micros to dollars

        return view('sales.shopify_b2c_daily_sales_data', [
            'googleSpent' => (float) $googleSpent
        ]);
    }

    public function getData(Request $request)
    {
        Log::info('ShopifyB2CSalesController getData called');

        // Hardcoded 95% margin for Shopify B2C
        $percentageValue = 0.95;

        // Calculate date range
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29); // 30 days total
        $startDateStr = $startDate->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        $orders = ShopifyB2CDailyData::where('period', 'l30')
            ->where('financial_status', '!=', 'refunded')
            ->orderBy('order_date', 'desc')
            ->get()
            ->filter(function ($order) {
                // Filter out PARENT SKUs
                return stripos($order->sku, 'PARENT') === false;
            });

        Log::info('Found ' . $orders->count() . ' Shopify B2C orders (excluding PARENT SKUs)');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch Google Ads spend per SKU (campaign_name = SKU)
        $googleSpentData = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $yesterdayStr)
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->selectRaw('UPPER(TRIM(campaign_name)) as sku_key, SUM(metrics_cost_micros) / 1000000 as total_spend')
            ->groupByRaw('UPPER(TRIM(campaign_name))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();

        // Build Google spend lookup by SKU
        $googleSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $googleSpentBySku[$sku] = $googleSpentData[$skuUpper] ?? 0;
        }

        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

            // Extract LP and the saved product ship. COGS Ship uses the weight slab.
            $lp = 0;
            $ship = 0;
            $values = [];
            $parent = '';
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $values = is_array($values) ? $values : [];
                $parent = (string) ($pm->parent ?? '');
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
            }

            $quantity = floatval($order->quantity);
            $price = floatval($order->price);
            $totalAmount = floatval($order->total_amount);

            $weightAct = EbaySalesController::actWeightLb($values);
            $tWeight = $weightAct * $quantity;
            $shipCost = $quantity > 0
                ? EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, (string) ($order->sku ?? ''), $values, $quantity, $parent)
                : 0.0;

            // COGS = LP × Qty. Price is the unit price, so sales is price × Qty once.
            $cogs = $lp * $quantity;
            $sales = $price * $quantity;
            $pft = ($sales * $percentageValue) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $pftEachPct = $price > 0 ? ($pftEach / $price) * 100 : 0;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $l30Sales = $sales;

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
                'google_spent' => round($googleSpentBySku[$order->sku] ?? 0, 2),
            ];
        }

        Log::info('Returning ' . count($data) . ' data items');

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
            'google_spent' => true,
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
