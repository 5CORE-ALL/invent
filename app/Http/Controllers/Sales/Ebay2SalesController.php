<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Ebay2Order;
use App\Models\Ebay2OrderItem;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2Metric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Ebay2SalesController extends Controller
{
    public function index()
    {
        // Calculate PMT Spent (from ebay_2_general_reports)
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
        $pmtSpent = DB::table('ebay_2_general_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        return view('sales.ebay2_daily_sales_data', [
            'pmtSpent' => (float) $pmtSpent,
            'kwSpent' => 0 // eBay 2 may not have priority reports
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('Ebay2SalesController getData called');

        $orders = Ebay2Order::with('items')
            ->where('period', 'l30')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' eBay 2 orders');

        // Get unique SKUs and item_ids
        $skus = [];
        $itemIds = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $skus[] = $item->sku;
                if ($item->item_id) {
                    $itemIds[] = $item->item_id;
                }
            }
        }
        $skus = array_unique($skus);
        $itemIds = array_unique($itemIds);

        // Fetch ProductMaster data for LP and Ship (case-insensitive matching)
        // Build case-insensitive query for better performance
        $skuLowerMap = [];
        foreach ($skus as $sku) {
            $skuLowerMap[strtolower($sku)] = $sku;
        }
        
        $productMastersRaw = ProductMaster::whereRaw('LOWER(sku) IN (' . implode(',', array_fill(0, count($skuLowerMap), '?')) . ')', array_keys($skuLowerMap))->get();
        
        // Key by original order SKU (preserving order SKU case)
        $productMasters = collect();
        foreach ($productMastersRaw as $pm) {
            $pmSkuLower = strtolower($pm->sku);
            if (isset($skuLowerMap[$pmSkuLower])) {
                $productMasters[$skuLowerMap[$pmSkuLower]] = $pm;
            }
        }

        // Calculate PMT Spent per item_id (from ebay_2_general_reports)
        $generalReports = DB::table('ebay_2_general_reports')
            ->whereIn('listing_id', $itemIds)
            ->where('report_range', 'L30')
            ->get();
        
        $pmtSpentByItemId = [];
        foreach ($generalReports as $report) {
            $spent = (float) preg_replace('/[^\d.]/', '', $report->ad_fees ?? '0');
            $pmtSpentByItemId[$report->listing_id] = ($pmtSpentByItemId[$report->listing_id] ?? 0) + $spent;
        }

        \Log::info('eBay 2 PMT Data - Reports found: ' . $generalReports->count() . ', Total PMT entries: ' . count($pmtSpentByItemId));

        // Get marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayTwo')->first();
        // if (!$marketplaceData) {
        //     $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        // }
        $percentage = $marketplaceData ? $marketplaceData->percentage : 85; // Default 85% for eBay 2
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = $percentage - $adUpdates;
        // Convert percentage to decimal (e.g., 85 -> 0.85)
        $percentageDecimal = $percentage / 100;

        $data = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // Skip OPEN BOX and USED items - they don't have ProductMaster entries
                $skuUpper = strtoupper($item->sku);
                if (strpos($skuUpper, 'OPEN BOX') !== false || strpos($skuUpper, 'USED') !== false) {
                    continue;
                }

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
                    // Use ebay2_ship for eBay 2, fallback to regular ship if not set
                    $ship = isset($values["ebay2_ship"]) && $values["ebay2_ship"] !== null ? floatval($values["ebay2_ship"]) : (isset($values["ship"]) ? floatval($values["ship"]) : 0);
                    $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
                }

                $quantity = floatval($item->quantity);
                $price = floatval($item->price);

                // T Weight = Weight Act * Quantity
                $tWeight = $weightAct * $quantity;

                // Ship Cost = ship (not divided by quantity - each unit bears full shipping cost)
                $shipCost = $ship;

                // COGS = LP * quantity
                $cogs = $lp * $quantity;

                // PFT Each = (unit_price * 0.85) - lp - ship_cost (eBay 2 uses 85% margin)
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

                // PFT Each % = (pft_each / unit_price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT / LP) * 100 (same as eBay 1)
                $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

                // Get PMT Spent for this item_id
                $pmtSpent = $pmtSpentByItemId[$item->item_id] ?? 0;

                $data[] = [
                    'order_id' => $order->ebay_order_id,
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($price, 2),
                    'price' => round($unitPrice, 2),
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
                    'pmt_spent' => round($pmtSpent, 2),
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' eBay 2 data items');
        
        // Log sample PMT values for debugging
        $pmtSamples = array_filter(array_column($data, 'pmt_spent'), fn($v) => $v > 0);
        \Log::info('PMT Spent non-zero count: ' . count($pmtSamples) . ', Samples: ' . json_encode(array_slice($pmtSamples, 0, 5)));

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            
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
                'pmt_spent' => true,
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
            Log::error('Error getting eBay 2 column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            $visibility = $request->input('visibility', []);
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay 2 column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
