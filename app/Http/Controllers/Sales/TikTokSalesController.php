<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;

class TikTokSalesController extends Controller
{
    /**
     * Display TikTok daily sales page
     */
    public function index()
    {
        return view('sales.tiktok_daily_sales_data');
    }

    /**
     * Get TikTok sales data from ShipHub (33 days)
     */
    public function getData(Request $request)
    {
        try {
            // Get latest TikTok order date from ShipHub
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'tiktok')
                ->max('order_date');

            if (!$latestDate) {
                return response()->json([]);
            }

            // Calculate date range: 30 days total (California time)
            $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
            $startDate = $latestDateCarbon->copy()->subDays(29); // 30 days total (29 previous days + today)
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $latestDateCarbon->format('Y-m-d');

            // QUERY 1: Get all order items from ShipHub with JOIN (TikTok marketplace only)
            $orderItems = DB::connection('shiphub')
                ->table('orders as o')
                ->join('order_items as i', 'o.id', '=', 'i.order_id')
                ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
                ->where('o.marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('o.order_status', '!=', 'Canceled')
                          ->where('o.order_status', '!=', 'Cancelled')
                          ->where('o.order_status', '!=', 'canceled')
                          ->where('o.order_status', '!=', 'cancelled')
                          ->orWhereNull('o.order_status');
                })
                ->select([
                    DB::raw("COALESCE(o.marketplace_order_id, o.order_number, CONCAT('SH-', o.id)) as order_id"),
                    'o.id as internal_order_id', // For deduplication
                    'o.order_date',
                    'o.order_status as status',
                    'o.order_total as total_amount',
                    'i.currency',
                    DB::raw("'L30' as period"),
                    'i.asin',
                    'i.sku',
                    'i.product_name as title',
                    'i.quantity_ordered as quantity',
                    'i.unit_price as price', // This is TOTAL price for item line in ShipHub
                ])
                ->orderBy('o.order_date', 'desc')
                ->get();

            if ($orderItems->isEmpty()) {
                return response()->json([]);
            }

            // QUERY 2: Get ProductMaster data for LP, Ship, Weight
            $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });

            // QUERY 3: TikTok margin fixed at 80%
            $margin = 0.80; // 80% margin (20% TikTok fees)

            // QUERY 4: Get KW Spent (if TikTok has ads - currently 0)
            $kwSpent = 0; // TikTok doesn't have KW ads yet

            // QUERY 5: Get PT Spent (if TikTok has ads - currently 0)
            $ptSpent = 0; // TikTok doesn't have PT ads yet

            // Group items by order to handle multi-item orders correctly
            $orderGroups = [];
            foreach ($orderItems as $item) {
                $orderId = $item->internal_order_id;
                if (!isset($orderGroups[$orderId])) {
                    $orderGroups[$orderId] = [];
                }
                $orderGroups[$orderId][] = $item;
            }

            // Process data
            $data = [];
            foreach ($orderGroups as $orderId => $items) {
                $orderTotal = floatval($items[0]->total_amount);
                $itemCount = count($items);
                
                // Distribute order_total across all items in the order
                $pricePerItem = $itemCount > 0 ? $orderTotal / $itemCount : $orderTotal;
                
                foreach ($items as $item) {
                    $sku = strtoupper($item->sku ?? '');
                    $quantity = floatval($item->quantity);
                    
                    // TikTok FIX: unit_price is always 0 in ShipHub for TikTok
                    // Use distributed price per item from order_total
                    $totalPrice = $pricePerItem; // Distributed price
                    $unitPrice = $quantity > 0 ? $totalPrice / $quantity : 0;
                    $saleAmount = $totalPrice;

                // Get LP, Ship and wt_act from ProductMaster
                $lp = 0;
                $ship = 0;
                $weightAct = 0;

                if ($sku && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship
                    if (isset($values['ship'])) {
                        $ship = floatval($values['ship']);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                    
                    // Get Weight Act
                    if (isset($values['wt_act'])) {
                        $weightAct = floatval($values['wt_act']);
                    }
                }

                // T Weight = Weight Act * Quantity
                $tWeight = $weightAct * $quantity;

                // Ship Cost calculation (same as Amazon):
                // If quantity is 1: ship_cost = ship
                // If quantity > 1 and t_weight < 20: ship_cost = ship / quantity
                // Otherwise: ship_cost = ship
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }

                // COGS = LP * quantity (only LP, not Ship)
                $cogs = $lp * $quantity;

                // PFT Each = (unitPrice * margin) - lp - ship_cost
                $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
                
                // PFT Each % = (PFT Each / Unit Price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT Each / LP) * 100
                $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

                $data[] = [
                    'order_id' => $item->order_id ?? $item->order_number,
                    'asin' => $item->asin,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($saleAmount, 2),
                    'price' => round($unitPrice, 2), // Per-unit price
                    'total_amount' => round(floatval($item->total_amount), 2),
                    'currency' => $item->currency,
                    'order_date' => $item->order_date,
                    'status' => $item->status,
                    'period' => $item->period,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'ship_cost' => round($shipCost, 2),
                    'weight_act' => round($weightAct, 2),
                    't_weight' => round($tWeight, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                    'margin' => round($margin * 100, 2), // Show as percentage
                ];
                } // End foreach items
            } // End foreach orderGroups

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('TikTok Sales Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
