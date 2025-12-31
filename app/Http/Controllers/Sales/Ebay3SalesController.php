<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Ebay3DailyData;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3PriorityReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Ebay3SalesController extends Controller
{
    public function index()
    {
        // Calculate KW Spent (from ebay_3_priority_reports - parent-wise)
        $kwSpent = DB::table('ebay_3_priority_reports')
            ->where('report_range', 'L30')
            ->selectRaw('SUM(CAST(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
            ->value('total_spend') ?? 0;

        // Calculate PMT Spent (from ebay_3_general_reports - item-wise)
        $pmtSpent = DB::table('ebay_3_general_reports')
            ->where('report_range', 'L30')
            ->selectRaw('SUM(CAST(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "") AS DECIMAL(10,2))) as total_spend')
            ->value('total_spend') ?? 0;

        return view('sales.ebay3_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'pmtSpent' => (float) $pmtSpent,
        ]);
    }

    public function getData(Request $request)
    {
        Log::info('Ebay3SalesController getData called');

        // Get L30 orders from ebay3_daily_data
        $orders = Ebay3DailyData::where('period', 'l30')
            ->orderBy('creation_date', 'desc')
            ->get();

        Log::info('Found ' . $orders->count() . ' eBay 3 orders');

        // Get unique SKUs and legacy_item_ids (item_ids)
        $skus = $orders->pluck('sku')->filter()->unique()->toArray();
        $itemIds = $orders->pluck('legacy_item_id')->filter()->unique()->toArray();

        // Fetch ProductMaster data for LP, Ship and Parent info
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Get parents for grouping KW spend (from ProductMaster)
        $parents = [];
        foreach ($productMasters as $sku => $pm) {
            $parents[$sku] = $pm->parent ?? '';
        }

        // Calculate KW Spent per parent (from ebay_3_priority_reports - campaigns are named by parent)
        $kwSpentByParent = [];
        $priorityReports = DB::table('ebay_3_priority_reports')
            ->where('report_range', 'L30')
            ->get();
        
        foreach ($priorityReports as $report) {
            $campaignName = $report->campaign_name ?? '';
            $spent = (float) preg_replace('/[^\d.]/', '', $report->cpc_ad_fees_payout_currency ?? '0');
            // Campaign name contains the parent SKU
            $kwSpentByParent[$campaignName] = ($kwSpentByParent[$campaignName] ?? 0) + $spent;
        }

        // Calculate PMT Spent per item_id (from ebay_3_general_reports)
        $pmtSpentByItemId = [];
        $generalReports = DB::table('ebay_3_general_reports')
            ->whereIn('listing_id', $itemIds)
            ->where('report_range', 'L30')
            ->get();
        
        foreach ($generalReports as $report) {
            $spent = (float) preg_replace('/[^\d.]/', '', $report->ad_fees ?? '0');
            $pmtSpentByItemId[$report->listing_id] = ($pmtSpentByItemId[$report->listing_id] ?? 0) + $spent;
        }

        Log::info('eBay 3 Ads Data - KW campaigns: ' . count($kwSpentByParent) . ', PMT entries: ' . count($pmtSpentByItemId));

        // Get marketplace percentage (same as eBay 2 - 85%)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'EbayThree')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 85;
        $percentageDecimal = $percentage / 100;

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
            $unitPrice = floatval($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;

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

            // PFT Each = (unit_price * 0.85) - lp - ship_cost (eBay 3 uses 85% margin)
            $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;

            // PFT Each % = (pft_each / unit_price) * 100
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / LP) * 100
            $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

            // Get KW Spent for this parent (match campaign name containing parent)
            $kwSpent = 0;
            if (!empty($parent)) {
                $cleanParent = str_replace('PARENT ', '', $parent);
                foreach ($kwSpentByParent as $campaignName => $spent) {
                    if (stripos($campaignName, $cleanParent) !== false || stripos($campaignName, $parent) !== false) {
                        $kwSpent = $spent;
                        break;
                    }
                }
            }

            // Get PMT Spent for this item_id
            $pmtSpent = $pmtSpentByItemId[$order->legacy_item_id] ?? 0;

            $data[] = [
                'order_id' => $order->order_id,
                'item_id' => $order->legacy_item_id,
                'line_item_id' => $order->line_item_id,
                'sku' => $sku,
                'parent' => $parent,
                'title' => $order->title,
                'quantity' => $order->quantity,
                'sale_amount' => round($saleAmount, 2),
                'price' => round($unitPrice, 2),
                'order_date' => $order->creation_date,
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
                'kw_spent' => round($kwSpent, 2),
                'pmt_spent' => round($pmtSpent, 2),
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
                'kw_spent' => true,
                'pmt_spent' => true,
                'total_spent' => true,
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
