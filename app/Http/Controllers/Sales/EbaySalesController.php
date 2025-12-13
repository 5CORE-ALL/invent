<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\EbayOrder;
use App\Models\EbayOrderItem;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\EbayGeneralReport;
use App\Models\EbayPriorityReport;
use App\Models\EbayMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EbaySalesController extends Controller
{
    public function index()
    {
        // Calculate PMT Spent (from ebay_general_reports) - matching PMT ads calculation
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
        $pmtSpent = DB::table('ebay_general_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        // Calculate KW Spent (from ebay_priority_reports) - matching KW ads calculation
        $kwSpent = DB::table('ebay_priority_reports')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->selectRaw('SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as total_spend')
            ->value('total_spend') ?? 0;

        return view('sales.ebay_daily_sales_data', [
            'pmtSpent' => (float) $pmtSpent,
            'kwSpent' => (float) $kwSpent
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('EbaySalesController getData called');

        $orders = EbayOrder::with('items')
            ->where('period', 'l30')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' orders');

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

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate PMT Spent per item_id (from ebay_general_reports)
        // PMT spent is tied to listing_id (which is item_id)
        $generalReports = EbayGeneralReport::whereIn('listing_id', $itemIds)
            ->where('report_range', 'L30')
            ->get();
        
        $pmtSpentByItemId = [];
        foreach ($generalReports as $report) {
            $spent = (float) preg_replace('/[^\d.]/', '', $report->ad_fees ?? '0');
            $pmtSpentByItemId[$report->listing_id] = ($pmtSpentByItemId[$report->listing_id] ?? 0) + $spent;
        }

        // Calculate KW Spent per SKU (from ebay_priority_reports)
        // Match campaigns where campaign_name exactly matches SKU (case-insensitive)
        $kwSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $priorityReports = EbayPriorityReport::where('report_range', 'L30')
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuUpper])
                ->get();
            
            foreach ($priorityReports as $report) {
                $spent = (float) str_replace(['USD ', ','], '', $report->cpc_ad_fees_payout_currency ?? '0');
                $kwSpentBySku[$sku] = ($kwSpentBySku[$sku] ?? 0) + $spent;
            }
        }

        // Get marketplace percentage and ad_updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Ebay')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;
        $margin = $percentage - $adUpdates;

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

                // Get PMT Spent for this item_id and KW Spent for this SKU
                $pmtSpent = $pmtSpentByItemId[$item->item_id] ?? 0;
                $kwSpent = $kwSpentBySku[$item->sku] ?? 0;

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
                    'kw_spent' => round($kwSpent, 2),
                    'pmt_spent' => round($pmtSpent, 2),
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            // Use a global cache key (shared for all users)
            $key = 'ebay_daily_sales_column_visibility_global';
            
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
                'lp' => true,
                'ship' => true,
                't_weight' => true,
                'ship_cost' => true,
                'cogs' => true,
                'pft_each' => true,
                'pft_each_pct' => true,
                'pft' => true,
                'roi' => true,
                'kw_spent' => true,
                'pmt_spent' => true,
            ];

            $saved = Cache::get($key, $defaultVisibility);
            return response()->json($saved);
        } catch (\Exception $e) {
            Log::error('Error getting eBay column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            // Use a global cache key (shared for all users)
            $key = 'ebay_daily_sales_column_visibility_global';
            
            $visibility = $request->input('visibility', []);
            
            // Store in cache for 1 year
            Cache::put($key, $visibility, now()->addDays(365));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
