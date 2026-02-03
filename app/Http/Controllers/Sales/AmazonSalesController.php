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
        // LAST 31 DAYS FROM TODAY (FIXED)
        // Example: today Feb 4 → Jan 5 to Feb 4
        // ============================================================
    
        $endDateCarbon = now()->endOfDay();
        $startDateCarbon = now()->subDays(30)->startOfDay(); // 31 days total
    
        $startDateStr = $startDateCarbon->format('Y-m-d');
        $endDateStr   = $endDateCarbon->format('Y-m-d');
    
        // ============================================================
        // QUERY 1: Inventory Database - Amazon Orders + Items
        // ============================================================
    
        $orderItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$startDateCarbon, $endDateCarbon])
            ->where(function ($query) {
                $query->where('o.status', '!=', 'Canceled')
                    ->orWhereNull('o.status');
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
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(30); // 30 days total
        
        // Match Amazon's date range exactly (Dec 3 to Jan 2)
        $amazonStartDate = \Carbon\Carbon::parse('2025-12-03');
        $amazonEndDate = \Carbon\Carbon::parse('2026-01-02')->endOfDay();
        
        $debug = [];
        
        // 1. Date range info
        $debug['date_ranges'] = [
            'code_uses' => $startDate->format('Y-m-d') . ' to ' . $yesterday->format('Y-m-d'),
            'amazon_shows' => '2025-12-03 to 2026-01-02',
        ];
        
        // 2. Count total orders in date range (matching Amazon's range)
        $totalOrders = DB::table('amazon_orders')
            ->whereBetween('order_date', [$amazonStartDate, $amazonEndDate])
            ->count();
        $debug['total_orders'] = $totalOrders;
        
        // 3. Count non-cancelled orders
        $nonCancelledOrders = DB::table('amazon_orders')
            ->whereBetween('order_date', [$amazonStartDate, $amazonEndDate])
            ->where('status', '!=', 'Canceled')
            ->count();
        $debug['non_cancelled_orders'] = $nonCancelledOrders;
        
        // 4. Orders WITHOUT items (potential missing data)
        $ordersWithoutItems = DB::table('amazon_orders')
            ->whereBetween('order_date', [$amazonStartDate, $amazonEndDate])
            ->where('status', '!=', 'Canceled')
            ->whereNotIn('id', function($q) {
                $q->select('amazon_order_id')->from('amazon_order_items');
            })
            ->count();
        $debug['orders_without_items'] = $ordersWithoutItems;
        
        // 5. Count order items via join
        $itemCount = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$amazonStartDate, $amazonEndDate])
            ->where('o.status', '!=', 'Canceled')
            ->count();
        $debug['total_order_items'] = $itemCount;
        
        // 6. Total quantity
        $totalQty = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$amazonStartDate, $amazonEndDate])
            ->where('o.status', '!=', 'Canceled')
            ->sum('i.quantity');
        $debug['total_quantity'] = $totalQty;
        
        // 7. Sum of all item prices (this should match Amazon's "Ordered product sales")
        $totalSales = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$amazonStartDate, $amazonEndDate])
            ->where('o.status', '!=', 'Canceled')
            ->sum('i.price');
        $debug['total_sales_from_items'] = round($totalSales, 2);
        
        // 8. Items with 0 price (potential missing price data)
        $zeroPriceItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$amazonStartDate, $amazonEndDate])
            ->where('o.status', '!=', 'Canceled')
            ->where('i.price', '=', 0)
            ->count();
        $debug['items_with_zero_price'] = $zeroPriceItems;
        
        // 9. Sample of items with 0 price to check raw_data
        $sampleZeroPrice = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$amazonStartDate, $amazonEndDate])
            ->where('o.status', '!=', 'Canceled')
            ->where('i.price', '=', 0)
            ->select('i.raw_data', 'i.asin', 'i.sku', 'i.quantity', 'o.amazon_order_id')
            ->limit(10)
            ->get();
        
        $zeroPriceSamples = [];
        foreach ($sampleZeroPrice as $item) {
            $rawData = json_decode($item->raw_data, true);
            $zeroPriceSamples[] = [
                'order_id' => $item->amazon_order_id,
                'asin' => $item->asin,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'has_item_price' => isset($rawData['ItemPrice']),
                'item_price_raw' => $rawData['ItemPrice'] ?? 'NOT SET',
            ];
        }
        $debug['zero_price_samples'] = $zeroPriceSamples;
        
        // 10. Amazon's expected values
        $debug['amazon_expected'] = [
            'total_order_items' => 4379,
            'units_ordered' => 4639,
            'ordered_product_sales' => 164459.86,
        ];
        
        // 11. Calculate discrepancies
        $debug['discrepancies'] = [
            'missing_order_items' => 4379 - $itemCount,
            'missing_units' => 4639 - $totalQty,
            'missing_sales' => round(164459.86 - $totalSales, 2),
        ];
        
        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    }
}
