<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\DobaDailyData;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DobaSalesController extends Controller
{
    public function index()
    {
        // No KW/PT spent for Doba
        $kwSpent = 0;
        $ptSpent = 0;

        return view('sales.doba_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'ptSpent' => (float) $ptSpent
        ]);
    }

    public function getData(Request $request)
    {
        // Get data for L30 period
        $data = DobaDailyData::where('period', 'L30')
            ->orderBy('order_time', 'desc')
            ->get();

        // Get unique SKUs
        $skus = $data->pluck('sku')->filter()->unique()->values()->toArray();

        // QUERY: ProductMaster for ship values
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->select(['sku', 'Values'])
            ->get()
            ->keyBy('sku');

        // L60 sales per SKU — for the L60 Sales badge.
        // total_price already represents quantity * item_price.
        $l60SalesBySku = DobaDailyData::where('period', 'L60')
            ->selectRaw('sku, SUM(total_price) as l60_total')
            ->groupBy('sku')
            ->pluck('l60_total', 'sku')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // L60 window: same convention as FetchDobaDailyData — orders older than
        // 30 days but within the last 60 days (i.e. day 60 → day 30 back from now).
        $now = Carbon::now();
        $l60Start = $now->copy()->subDays(60)->startOfDay();
        $l60End   = $now->copy()->subDays(30)->endOfDay();
        $l60Range = [
            'start'   => $l60Start->toDateString(),
            'end'     => $l60End->toDateString(),
            'display' => $l60Start->format('M d, Y') . ' – ' . $l60End->format('M d, Y'),
        ];

        // Process data to match Amazon structure
        $processedData = [];
        foreach ($data as $item) {
            $quantity = (int) $item->quantity;
            $itemPrice = (float) $item->item_price;
            $totalPrice = (float) $item->total_price;
            $shippingFee = (float) $item->shipping_fee;
            $platformFee = (float) $item->platform_fee;
            $anticipatedIncome = (float) $item->anticipated_income;

            // Get ship and lp from ProductMaster
            $ship = 0;
            $lp = 0;
            $pm = $productMasters[$item->sku] ?? null;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : 0;
                $lp = isset($values["lp"]) ? floatval($values["lp"]) : 0;
            }

            // Calculate COGS
            $cogs = $lp * $quantity;

            // Calculate ship cost similar to Amazon logic
            $tWeight = 0; // No weight info for Doba
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // Calculate profit per unit: (price * 0.95) - ship - lp
            // If order type is "Pickup with a prepaid label", don't reduce shipping cost
            if (strtolower($item->order_type) === 'pickup with a prepaid label') {
                $pftEach = ($itemPrice * 0.95) - $lp;
            } else {
                $pftEach = ($itemPrice * 0.95) - $ship - $lp;
            }
            
            // Calculate total profit
            $pft = $pftEach * $quantity;
            
            // Calculate profit percentage
            $pftEachPct = $itemPrice > 0 ? ($pftEach / $itemPrice) * 100 : 0;
            
            // Calculate ROI
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $processedData[] = [
                'order_id' => $item->order_no,
                'asin' => $item->item_no, // Using item_no as asin
                'sku' => $item->sku,
                'title' => $item->product_name,
                'quantity' => $quantity,
                'sale_amount' => $totalPrice,
                'price' => $quantity > 0 ? $totalPrice / $quantity : 0,
                'total_amount' => $totalPrice,
                'currency' => $item->currency,
                'order_date' => $item->order_time ? $item->order_time->format('Y-m-d H:i:s') : null,
                'status' => $item->order_status,
                'order_type' => $item->order_type,
                'period' => $item->period,
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 0),
                'pft' => round($pft, 0),
                'roi' => round($roi, 0),
                'kw_spent' => 0,
                'pt_spent' => 0,
            ];
        }

        return response()->json([
            'data' => $processedData,
            'l60_sales_by_sku' => $l60SalesBySku,
            'l60_sales_total' => array_sum($l60SalesBySku),
            'l60_range' => $l60Range,
        ]);
    }

    public function getColumnVisibility(Request $request)
    {
        $defaultVisibility = [
            'order_id' => true,
            'asin' => true,
            'sku' => true,
            'title' => true,
            'quantity' => true,
            'sale_amount' => true,
            'price' => true,
            'total_amount' => true,
            'order_date' => true,
            'status' => true,
            'order_type' => true,
            'lp' => true,
            'ship' => true,
            't_weight' => true,
            'ship_cost' => true,
            'cogs' => true,
            'pft_each' => true,
            'pft_each_pct' => true,
            'roi' => true,
        ];

        $saved = session('doba_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['doba_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}