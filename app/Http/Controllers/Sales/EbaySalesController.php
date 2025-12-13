<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\EbayOrder;
use App\Models\EbayOrderItem;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EbaySalesController extends Controller
{
    public function index()
    {
        return view('sales.ebay_daily_sales_data');
    }

    public function getData(Request $request)
    {
        \Log::info('EbaySalesController getData called');

        $orders = EbayOrder::with('items')
            ->where('period', 'l30')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' orders');

        // Get unique SKUs
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $skus[] = $item->sku;
            }
        }
        $skus = array_unique($skus);

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get marketplace percentage and ad_updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = $percentage - $adUpdates;

        $data = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pm = $productMasters[$item->sku] ?? null;

                // Extract LP and Ship
                $lp = 0;
                $ship = 0;
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
                }

                $quantity = floatval($item->quantity);
                $price = floatval($item->price);

                // COGS = LP * quantity (same as Amazon)
                $cogs = $lp * $quantity;

                // PFT = ((price * margin - lp - ship) * quantity) (same as Amazon)
                $pft = (($price * ($margin / 100) - $lp - $ship) * $quantity);

                // ROI = (PFT / COGS) * 100 (same as Amazon)
                $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

                $data[] = [
                    'order_id' => $order->ebay_order_id,
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'order_date' => $order->order_date,
                    'status' => $order->status,
                    'period' => $order->period,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'cogs' => round($cogs, 2),
                    'pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        // Similar to temu, but for ebay
        $defaultVisibility = [
            'order_id' => true,
            'item_id' => true,
            'sku' => true,
            'quantity' => true,
            'price' => true,
            'total_amount' => true,
            'order_date' => true,
            'status' => true,
            'lp' => true,
            'ship' => true,
            'cogs' => true,
            'pft' => true,
            'roi' => true,
        ];

        $saved = session('ebay_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['ebay_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}
