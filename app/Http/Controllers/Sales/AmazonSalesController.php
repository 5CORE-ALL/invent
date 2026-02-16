<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\ProductMaster;
use App\Models\MarketplacePercentage;
use App\Models\AmazonSpCampaignReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmazonSalesController extends Controller
{
    public function index()
    {
        // Calculate KW Spent - same logic as amazonKwAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(spend), then sum
        $kwSpentData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('campaignName, MAX(spend) as max_spend')
            ->where('report_date_range', 'L30')
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'") // Exclude PT and FBA campaigns
            ->groupBy('campaignName')
            ->get();
        
        $kwSpent = $kwSpentData->sum('max_spend') ?? 0;

        // Calculate PT Spent - same logic as amazonPtAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(spend), then sum
        $ptSpentData = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('campaignName, MAX(spend) as max_spend')
            ->where('report_date_range', 'L30')
            ->where(function($query) {
                $query->whereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'") // Exclude FBA PT campaigns
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'") // Exclude FBA PT. campaigns
            ->groupBy('campaignName')
            ->get();
        
        $ptSpent = $ptSpentData->sum('max_spend') ?? 0;

        // Calculate HL Spent - same logic as amazonHlAdsView
        // Uses L30 report_date_range, group by campaignName to get MAX(cost), then sum
        $hlSpentData = DB::table('amazon_sb_campaign_reports')
            ->selectRaw('campaignName, MAX(cost) as max_cost')
            ->where('report_date_range', 'L30')
            ->groupBy('campaignName')
            ->get();
        
        $hlSpent = $hlSpentData->sum('max_cost') ?? 0;

        return view('sales.amazon_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'ptSpent' => (float) $ptSpent,
            'hlSpent' => (float) $hlSpent
        ]);
    }

    public function getData(Request $request)
    {
        // ============================================================
        // LAST 30 DAYS - MATCHING AMAZON'S DATE RANGE
        // Amazon shows yesterday and previous 29 days (30 days total)
        // Example: Today Feb 14 → Show Jan 15 to Feb 13 (30 days)
        // CRITICAL: Must use Pacific Time to match Amazon Seller Central
        // ============================================================
    
        // Use yesterday as the end date (Amazon doesn't include today)
        // FIXED: Use Pacific Time to match Amazon Seller Central
        $yesterday = \Carbon\Carbon::yesterday('America/Los_Angeles');
        $endDateCarbon = $yesterday->endOfDay(); // Yesterday 23:59:59 PT
        $startDateCarbon = $yesterday->copy()->subDays(29)->startOfDay(); // 30 days total (yesterday - 29 days = 30 days)
    
        $startDateStr = $startDateCarbon->format('Y-m-d');
        $endDateStr   = $endDateCarbon->format('Y-m-d');
    
        // ============================================================
        // QUERY 1: Inventory Database - Amazon Orders + Items
        // CRITICAL FIX: Proper status filtering
        // ============================================================
    
        $orderItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                // Only exclude if status is explicitly 'Canceled'
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->select([
                'o.amazon_order_id as order_id',
                'o.order_date',
                'o.status',
                'o.total_amount',
                DB::raw("COALESCE(i.currency, o.currency) as currency"),
                DB::raw("'L30' as period"),
                'i.asin',
                'i.sku',
                'i.title',
                'i.quantity',
                'i.price'
            ])
            ->orderBy('o.order_date', 'desc')
            ->get();
    
        if ($orderItems->isEmpty()) {
            return response()->json([]);
        }
    
        // ============================================================
        // PRODUCT MASTER
        // ============================================================
    
        $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();
    
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->select(['sku', 'Values'])
            ->get()
            ->keyBy('sku');
    
        // ============================================================
        // KW SPEND
        // ============================================================
    
        $kwSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $endDateStr)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->whereRaw("campaignName NOT LIKE '%PT'")
            ->whereRaw("campaignName NOT LIKE '%PT.'")
            ->selectRaw('UPPER(TRIM(campaignName)) as sku_key, SUM(spend) as total_spend')
            ->groupByRaw('UPPER(TRIM(campaignName))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();
    
        // ============================================================
        // PT SPEND
        // ============================================================
    
        $ptSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $endDateStr)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%PT'")
                  ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->selectRaw('
                UPPER(TRIM(
                    REPLACE(REPLACE(REPLACE(REPLACE(campaignName, " PT.", ""), " PT", ""), "PT.", ""), "PT", "")
                )) as sku_key,
                SUM(spend) as total_spend
            ')
            ->groupByRaw('
                UPPER(TRIM(
                    REPLACE(REPLACE(REPLACE(REPLACE(campaignName, " PT.", ""), " PT", ""), "PT.", ""), "PT", "")
                ))
            ')
            ->pluck('total_spend', 'sku_key')
            ->toArray();
    
        // ============================================================
        // MAP SPENDS
        // ============================================================
    
        $kwSpentBySku = [];
        $ptSpentBySku = [];
    
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $kwSpentBySku[$sku] = $kwSpentData[$skuUpper] ?? 0;
            $ptSpentBySku[$sku] = $ptSpentData[$skuUpper] ?? 0;
        }
    
        // ============================================================
        // PROCESS DATA
        // ============================================================
    
        $data = [];
    
        foreach ($orderItems as $item) {
    
            $pm = $productMasters[$item->sku] ?? null;
    
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
    
            if ($pm) {
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : json_decode($pm->Values, true);
    
                $lp = floatval($values['lp'] ?? 0);
                $ship = floatval($values['ship'] ?? 0);
                $weightAct = floatval($values['wt_act'] ?? 0);
            }
    
            $qty = floatval($item->quantity);
    
            $totalPrice = floatval($item->price);
            $unitPrice = $qty > 0 ? $totalPrice / $qty : 0;
    
            $tWeight = $weightAct * $qty;
    
            $shipCost = ($qty == 1 || $tWeight >= 20)
                ? $ship
                : ($ship / max($qty, 1));
    
            $cogs = $lp * $qty;
    
            $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $pft = $pftEach * $qty;
            $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;
    
            $data[] = [
                'order_id' => $item->order_id,
                'asin' => $item->asin,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $qty,
                'sale_amount' => round($totalPrice, 2),
                'price' => round($unitPrice, 2),
                'total_amount' => $item->total_amount,
                'currency' => $item->currency,
                'order_date' => $item->order_date,
                'status' => $item->status,
                'period' => 'L31',
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
                'kw_spent' => round($kwSpentBySku[$item->sku] ?? 0, 2),
                'pt_spent' => round($ptSpentBySku[$item->sku] ?? 0, 2),
            ];
        }
    
        return response()->json($data);
    }
    

    public function getColumnVisibility(Request $request)
    {
        // Similar to ebay, but for amazon
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
            'pt_spent' => true,
        ];

        $saved = session('amazon_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['amazon_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }

    /**
     * Debug endpoint to verify data discrepancy
     */
    public function debugData(Request $request)
    {
        // Use the SAME date calculation as getData() method
        // Amazon shows yesterday and previous 29 days (30 days total)
        // FIXED: Use Pacific Time to match Amazon Seller Central
        $yesterday = \Carbon\Carbon::yesterday('America/Los_Angeles');
        $endDateCarbon = $yesterday->endOfDay();
        $startDateCarbon = $yesterday->copy()->subDays(29)->startOfDay(); // 30 days total
        
        $debug = [];
        
        // 1. Date range info
        $latestOrderInDb = DB::table('amazon_orders')->max('order_date');
        $debug['date_ranges'] = [
            'latest_order_in_db' => $latestOrderInDb,
            'yesterday' => $yesterday->format('Y-m-d'),
            'calculated_start' => $startDateCarbon->format('Y-m-d H:i:s'),
            'calculated_end' => $endDateCarbon->format('Y-m-d H:i:s'),
            'amazon_shows' => 'Jan 15, 2026 to Feb 13, 2026 (from your screenshot)',
            'days_difference' => $startDateCarbon->diffInDays($endDateCarbon) + 1,
            'note' => 'Using yesterday as end date to match Amazon (Amazon excludes today)',
        ];
        
        // 2. Total orders in date range (all statuses)
        $totalOrdersAll = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->count();
        $debug['total_orders_all_statuses'] = $totalOrdersAll;
        
        // 3. Orders by status breakdown
        $ordersByStatus = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        $debug['orders_by_status'] = $ordersByStatus;
        
        // 4. Non-cancelled orders (correct logic)
        $nonCancelledOrders = DB::table('amazon_orders')
            ->where('order_date', '>=', $startDateCarbon)
            ->where('order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'Canceled');
            })
            ->count();
        $debug['non_cancelled_orders'] = $nonCancelledOrders;
        
        // 5. Orders WITHOUT items (orphaned orders - potential data loss)
        $ordersWithoutItems = DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->whereNotExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('amazon_order_items as i')
                  ->whereRaw('i.amazon_order_id = o.id');
            })
            ->count();
        $debug['orders_without_items'] = $ordersWithoutItems;
        
        // 6. Sample orphaned orders
        if ($ordersWithoutItems > 0) {
            $orphanedSample = DB::table('amazon_orders as o')
                ->where('o.order_date', '>=', $startDateCarbon)
                ->where('o.order_date', '<=', $endDateCarbon)
                ->where(function ($query) {
                    $query->whereNull('o.status')
                        ->orWhere('o.status', '!=', 'Canceled');
                })
                ->whereNotExists(function($q) {
                    $q->select(DB::raw(1))
                      ->from('amazon_order_items as i')
                      ->whereRaw('i.amazon_order_id = o.id');
                })
                ->select('o.amazon_order_id', 'o.order_date', 'o.status', 'o.total_amount')
                ->limit(10)
                ->get();
            $debug['orphaned_orders_sample'] = $orphanedSample;
        }
        
        // 7. Count order items via join (what getData() returns)
        $itemCount = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->count();
        $debug['total_order_items_joined'] = $itemCount;
        
        // 8. Total quantity from joined items
        $totalQty = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.quantity');
        $debug['total_quantity_from_items'] = $totalQty;
        
        // 9. Total sales from item prices
        $totalSales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum('i.price');
        $debug['total_sales_from_item_prices'] = round($totalSales, 2);
        
        // 10. Items with 0 or null price (data quality issue)
        $zeroPriceItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startDateCarbon)
            ->where('o.order_date', '<=', $endDateCarbon)
            ->where(function ($query) {
                $query->whereNull('o.status')
                    ->orWhere('o.status', '!=', 'Canceled');
            })
            ->where(function ($query) {
                $query->where('i.price', '=', 0)
                    ->orWhereNull('i.price');
            })
            ->count();
        $debug['items_with_zero_or_null_price'] = $zeroPriceItems;
        
        // 11. Sample of zero price items
        if ($zeroPriceItems > 0) {
            $sampleZeroPrice = DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $startDateCarbon)
                ->where('o.order_date', '<=', $endDateCarbon)
                ->where(function ($query) {
                    $query->whereNull('o.status')
                        ->orWhere('o.status', '!=', 'Canceled');
                })
                ->where(function ($query) {
                    $query->where('i.price', '=', 0)
                        ->orWhereNull('i.price');
                })
                ->select('o.amazon_order_id', 'o.order_date', 'i.asin', 'i.sku', 'i.quantity', 'i.price')
                ->limit(10)
                ->get();
            $debug['zero_price_items_sample'] = $sampleZeroPrice;
        }
        
        // 12. Amazon's expected values (from your screenshots)
        $debug['amazon_expected_values'] = [
            'date_range' => 'Jan 15 to Feb 13, 2026',
            'ordered_product_sales' => 142925.00, // $142.9K
            'units_ordered' => 2609,
            'total_order_items' => 2327,
        ];
        
        // 13. What your system currently shows
        $debug['your_system_shows'] = [
            'total_sales' => 67928.54,
            'total_quantity' => 1599,
            'total_orders' => 1426,
        ];
        
        // 14. Calculate discrepancies
        $debug['discrepancies'] = [
            'missing_sales_amount' => round(142925.00 - $totalSales, 2),
            'missing_sales_percentage' => $totalSales > 0 ? round((142925.00 - $totalSales) / 142925.00 * 100, 1) : 100,
            'missing_units' => 2609 - $totalQty,
            'missing_units_percentage' => $totalQty > 0 ? round((2609 - $totalQty) / 2609 * 100, 1) : 100,
            'missing_order_items' => 2327 - $itemCount,
        ];
        
        // 15. Recommendations
        $recommendations = [];
        if ($ordersWithoutItems > 0) {
            $recommendations[] = "CRITICAL: {$ordersWithoutItems} orders have NO items in amazon_order_items table. Run FetchAmazonOrders command to sync missing items.";
        }
        if ($zeroPriceItems > 0) {
            $recommendations[] = "WARNING: {$zeroPriceItems} items have zero or null price. Check data import process.";
        }
        if ($totalSales < 142925.00 * 0.9) {
            $recommendations[] = "CRITICAL: Sales are more than 10% lower than Amazon's report. Check date range and data completeness.";
        }
        $debug['recommendations'] = $recommendations;
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    }
}
