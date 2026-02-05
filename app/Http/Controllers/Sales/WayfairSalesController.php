<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\WayfairDailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WayfairSalesController extends Controller
{
    public function index()
    {
        return view('sales.wayfair_daily_sales_data');
    }

    public function testCalculation(Request $request)
    {
        $sku = $request->input('sku', null);
        
        // Fetch one order (or specific SKU if provided)
        $query = WayfairDailyData::where('period', 'l30');
        if ($sku) {
            $query->where('sku', $sku);
        }
        $order = $query->first();
        
        if (!$order) {
            return response()->json(['error' => 'No data found']);
        }

        // Fetch ProductMaster data
        $pm = ProductMaster::where('sku', $order->sku)->first();

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

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
            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
        }

        $quantity = floatval($order->quantity);
        $price = floatval($order->unit_price);
        $tWeight = $weightAct * $quantity;

        // COGS
        $cogs = $lp * $quantity;

        // PFT Each calculation (without ship cost)
        $unitPrice = $price;
        $pftEach = ($unitPrice * ($percentage / 100)) - $lp;
        $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

        // Total PFT
        $pft = $pftEach * $quantity;

        // ROI
        $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

        return response()->json([
            'order_data' => [
                'po_number' => $order->po_number,
                'sku' => $order->sku,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $order->total_price,
                'period' => $order->period,
            ],
            'product_master' => [
                'lp' => $lp,
                'ship' => $ship,
                'weight_act' => $weightAct,
            ],
            'marketplace' => [
                'percentage' => $percentage,
            ],
            'calculations' => [
                'step_1_t_weight' => $tWeight . ' lbs',
                'step_2_revenue_after_fees' => '$' . number_format($unitPrice * ($percentage / 100), 2) . ' (price × ' . $percentage . '%)',
                'step_3_pft_each' => '$' . number_format($pftEach, 2) . ' = ($' . $price . ' × ' . ($percentage/100) . ') - $' . $lp,
                'step_4_pft_each_pct' => number_format($pftEachPct, 2) . '%',
                'step_5_total_pft' => '$' . number_format($pft, 2) . ' = $' . $pftEach . ' × ' . $quantity,
                'step_6_cogs' => '$' . number_format($cogs, 2) . ' = $' . $lp . ' × ' . $quantity,
                'step_7_roi' => number_format($roi, 2) . '% = ($' . $pft . ' / $' . $cogs . ') × 100',
            ],
            'final_results' => [
                'pft_each' => round($pftEach, 2),
                'total_pft' => round($pft, 2),
                'roi_percentage' => round($roi, 2),
                'cogs' => round($cogs, 2),
            ]
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('WayfairSalesController getData called');

        $orders = WayfairDailyData::where('period', 'l30')
            ->orderBy('po_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get marketplace percentage and ad_updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = $percentage - $adUpdates;

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
            $price = floatval($order->unit_price); // Use unit_price instead of price

            // T Weight = Weight Act * Quantity
            $tWeight = $weightAct * $quantity;

            // COGS = LP * quantity
            $cogs = $lp * $quantity;

            // PFT Each = (price * (percentage / 100)) - lp
            // Use marketplace percentage from database
            // Note: Ship cost is NOT included in Wayfair profit calculation
            $unitPrice = $price; // unit_price is already per unit
            $pftEach = ($unitPrice * ($percentage / 100)) - $lp;

            // PFT Each % = (pft_each / price) * 100
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (T PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $data[] = [
                'po_number' => $order->po_number,
                'po_date' => $order->po_date,
                'sku' => $order->sku,
                'quantity' => $order->quantity,
                'sale_amount' => round($price * $quantity, 2),
                'price' => round($price, 2),
                'total_price' => round($order->total_price, 2),
                'status' => $order->status,
                'period' => $order->period,
                'lp' => round($lp, 2),
                't_weight' => round($tWeight, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
            ];
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            // Read from JSON file (shared for all users)
            $filePath = storage_path('app/wayfair_column_visibility.json');
            
            $defaultVisibility = [
                'po_number' => true,
                'po_date' => true,
                'sku' => true,
                'quantity' => true,
                'sale_amount' => true,
                'price' => true,
                'total_price' => true,
                'status' => true,
                'period' => true,
                'lp' => true,
                't_weight' => true,
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
            Log::error('Error getting Wayfair column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            // Save to JSON file (shared for all users)
            $filePath = storage_path('app/wayfair_column_visibility.json');
            
            $visibility = $request->input('visibility', []);
            
            // Write to JSON file
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Wayfair column visibility: ' . $e->getMessage());
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
            
            $query = WayfairDailyData::where('period', 'l30')
                ->where('sku', $sku);
            
            if ($fromDate) {
                $query->whereDate('po_date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('po_date', '<=', $toDate);
            }
            
            $orders = $query->orderBy('po_date', 'desc')->get();

            // Group by date and calculate daily quantities
            $dailyData = [];
            foreach ($orders as $order) {
                $date = \Carbon\Carbon::parse($order->po_date)->format('Y-m-d');
                if (!isset($dailyData[$date])) {
                    $dailyData[$date] = [
                        'date' => $date,
                        'quantity' => 0,
                        'orders' => 0
                    ];
                }
                $dailyData[$date]['quantity'] += (int) $order->quantity;
                $dailyData[$date]['orders'] += 1;
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
