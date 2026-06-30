<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\EbayOrder;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $data = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $pm = $productMasters[$item->sku] ?? null;

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

                $quantity = floatval($item->quantity);
                $price = floatval($item->price);

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
                    $shipCost = $ship ;
                }

                // COGS = LP * quantity (same as Amazon)
                $cogs = $lp * $quantity;

                // PFT Each = (price * 0.85) - lp - ship_cost
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

                // PFT Each % = (pft_each / price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT / LP) * 100
                $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

                $data[] = [
                    'order_id' => $order->ebay_order_id,
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($price, 2),
                    'price' => $quantity > 0 ? round($price / $quantity, 2) : 0,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'order_date' => $order->order_date,
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
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            // Read from JSON file (shared for all users)
            $filePath = storage_path('app/ebay_column_visibility.json');
            
            $defaultVisibility = [
                'order_id' => true,
                'item_id' => true,
                'sku' => true,
                'quantity' => true,
                'sale_amount' => true,
                'price' => true,
                'total_amount' => true,
                'order_date' => true,
                'status' => true,
                'period' => true,
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
            Log::error('Error getting eBay column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            // Save to JSON file (shared for all users)
            $filePath = storage_path('app/ebay_column_visibility.json');
            
            $visibility = $request->input('visibility', []);
            
            // Write to JSON file
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    public function getSkuSalesData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $filter = $request->input('filter', 'last30');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }

            // Determine date range based on filter
            $now = \Carbon\Carbon::now();
            $fromDate = null;
            $toDate = $now->format('Y-m-d');
            
            if ($filter === 'custom' && $startDate && $endDate) {
                $fromDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');
                $toDate = \Carbon\Carbon::parse($endDate)->format('Y-m-d');
            } elseif ($filter === 'today') {
                $fromDate = $now->format('Y-m-d');
            } elseif ($filter === 'yesterday') {
                $fromDate = $now->copy()->subDay()->format('Y-m-d');
                $toDate = $fromDate;
            } elseif ($filter === 'last7') {
                $fromDate = $now->copy()->subDays(7)->format('Y-m-d');
            } else { // last30 (default)
                $fromDate = $now->copy()->subDays(30)->format('Y-m-d');
            }
            
            $query = EbayOrder::with(['items' => function($query) use ($sku) {
                $query->where('sku', $sku);
            }])
                ->where('period', 'l30')
                ->whereHas('items', function($query) use ($sku) {
                    $query->where('sku', $sku);
                });
            
            if ($fromDate) {
                $query->whereDate('order_date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('order_date', '<=', $toDate);
            }
            
            $orders = $query->orderBy('order_date', 'desc')->get();

            // Group by date and calculate daily quantities
            $dailyData = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->sku === $sku) {
                        $date = \Carbon\Carbon::parse($order->order_date)->format('Y-m-d');
                        if (!isset($dailyData[$date])) {
                            $dailyData[$date] = [
                                'date' => $date,
                                'quantity' => 0,
                                'orders' => 0
                            ];
                        }
                        $dailyData[$date]['quantity'] += (int) $item->quantity;
                        $dailyData[$date]['orders'] += 1;
                    }
                }
            }

            // Sort by date
            ksort($dailyData);
            $dailyData = array_values($dailyData);

            // Calculate total
            $totalQuantity = array_sum(array_column($dailyData, 'quantity'));
            $totalOrders = array_sum(array_column($dailyData, 'orders'));

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total_quantity' => $totalQuantity,
                'total_orders' => $totalOrders,
                'daily_data' => $dailyData,
                'date_range' => [
                    'from' => $fromDate,
                    'to' => $toDate
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting SKU sales data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }
}
