<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Ebay3DailyData;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay3SalesController extends Controller
{
    public function index()
    {
        return view('sales.ebay3_daily_sales_data');
    }

    public function getData(Request $request)
    {
        Log::info('Ebay3SalesController getData called');

        // Get L30 orders from ebay3_daily_data
        $orders = Ebay3DailyData::where('period', 'l30')
            ->orderBy('creation_date', 'desc')
            ->get();

        Log::info('Found ' . $orders->count() . ' eBay 3 orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->filter()->unique()->toArray();

        // Fetch ProductMaster data for LP, Ship and Parent info
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get parents for display (from ProductMaster)
        $parents = [];
        foreach ($productMasters as $sku => $pm) {
            $parents[$sku] = $pm->parent ?? '';
        }

        $data = [];
        foreach ($orders as $order) {
            $sku = $order->sku ?? '';
            if (empty($sku)) continue;

            $pm = $productMasters[$sku] ?? null;
            $parent = $parents[$sku] ?? '';

            // Extract LP, Ship, and Weight Act
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                // Use regular ship (same as tabulator view)
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
                $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
            }

            $quantity = floatval($order->quantity ?? 1);
            // IMPORTANT: unit_price in DB is the TOTAL line item cost (not per unit)
            $lineItemTotal = floatval($order->unit_price ?? 0);
            $perUnitPrice = $quantity > 0 ? $lineItemTotal / $quantity : 0;
            $saleAmount = $lineItemTotal; // Already the total

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // Ship Cost calculation (same as eBay 1 & 2)
            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            // COGS = LP * quantity
            $cogs = $lp * $quantity;

            // PFT Each = (per_unit_price * 0.85) - lp - ship_cost (eBay 3 uses 85% margin)
            $pftEach = ($perUnitPrice * 0.85) - $lp - $shipCost;

            // PFT Each % = (pft_each / per_unit_price) * 100
            $pftEachPct = $perUnitPrice > 0 ? ($pftEach / $perUnitPrice) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $data[] = [
                'order_id' => $order->order_id,
                'item_id' => $order->legacy_item_id,
                'line_item_id' => $order->line_item_id,
                'sku' => $sku,
                'parent' => $parent,
                'title' => $order->title,
                'quantity' => $order->quantity,
                'sale_amount' => round($saleAmount, 2), // Total for line item
                'price' => round($perUnitPrice, 2), // Per unit price
                'order_date' => $order->creation_date ? \Carbon\Carbon::parse($order->creation_date)->setTimezone('America/Los_Angeles')->toIso8601String() : null,
                'status' => $order->order_fulfillment_status,
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
            ];
        }

        Log::info('Returning ' . count($data) . ' eBay 3 data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay3_daily_sales_column_visibility.json');
            
            $defaultVisibility = [
                'order_id' => false,
                'item_id' => false,
                'line_item_id' => false,
                'sku' => true,
                'parent' => true,
                'title' => false,
                'quantity' => true,
                'sale_amount' => true,
                'price' => true,
                'order_date' => false,
                'status' => false,
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
            Log::error('Error getting eBay 3 daily sales column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Get L60 sales statistics from ebay3_orders table
     */
    public function getL60Sales(Request $request)
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('ebay3_orders')) {
                return response()->json([
                    'success' => false,
                    'error' => 'eBay 3 orders table not found'
                ], 404);
            }

            // Calculate L60 date range (last 60 days)
            $l60EndDate = Carbon::now()->toDateString();
            $l60StartDate = Carbon::now()->subDays(60)->toDateString();

            // Fetch L60 data
            $ebayData = DB::table('ebay3_orders')
                ->whereNotNull('order_date')
                ->whereBetween('order_date', [$l60StartDate, $l60EndDate])
                // Exclude cancelled orders
                ->where(function($query) {
                    $query->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%']);
                })
                ->get();

            $totalOrders = $ebayData->count();
            $totalQuantity = $ebayData->sum('quantity');
            $totalSales = $ebayData->sum('sale_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_sales' => round($totalSales, 2),
                    'total_orders' => $totalOrders,
                    'total_quantity' => $totalQuantity,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching eBay 3 L60 sales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay3_daily_sales_column_visibility.json');
            $visibility = $request->input('visibility', []);
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay 3 daily sales column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
