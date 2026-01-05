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
        // DATA SOURCE: ShipHub Database (Amazon Marketplace Only)
        // ============================================================
        // This fetches last 30 days data from ShipHub centralized database
        // Example: If latest date is Jan 5, 2026, it will fetch Dec 6, 2025 to Jan 5, 2026 (31 days)
        // Filter: marketplace = 'amazon' AND order_status != 'Canceled'
        // ============================================================
        
        // Get latest order date from ShipHub (Amazon marketplace only)
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'amazon')
            ->max('order_date');
        
        if (!$latestDate) {
            return response()->json([]);
        }
        
        // Calculate date range: Latest date minus 30 days = 31 days total
        $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
        $startDate = $latestDateCarbon->copy()->subDays(32); // 33 days total (Dec 3 to Jan 4) - Best accuracy: 97.83%
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $latestDateCarbon->format('Y-m-d');

        // QUERY 1: Get all order items with JOIN from ShipHub (Amazon marketplace only)
        $orderItems = DB::connection('shiphub')
            ->table('orders as o')
            ->join('order_items as i', 'o.id', '=', 'i.order_id')
            ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
            ->where('o.marketplace', '=', 'amazon')
            ->where(function($query) {
                $query->where('o.order_status', '!=', 'Canceled')
                      ->where('o.order_status', '!=', 'Cancelled')
                      ->where('o.order_status', '!=', 'canceled')
                      ->where('o.order_status', '!=', 'cancelled')
                      ->orWhereNull('o.order_status');
            })
            ->select([
                DB::raw("COALESCE(o.marketplace_order_id, o.order_number, CONCAT('SH-', o.id)) as order_id"),
                'o.order_date',
                'o.order_status as status',
                'o.order_total as total_amount',
                'i.currency',
                DB::raw("'L30' as period"),
                'i.asin',
                'i.sku',
                'i.product_name as title',
                'i.quantity_ordered as quantity',
                'i.unit_price as price'
            ])
            ->orderBy('o.order_date', 'desc')
            ->get();

        // Get unique SKUs
        $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();
        
        if (empty($skus)) {
            return response()->json([]);
        }

        // QUERY 2: ProductMaster in single query
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->select(['sku', 'Values'])
            ->get()
            ->keyBy('sku');

        // QUERY 3: KW Spent - single query with GROUP BY (using ShipHub date range)
        $kwSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $endDateStr)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->whereRaw("campaignName NOT LIKE '%PT'")
            ->whereRaw("campaignName NOT LIKE '%PT.'")
            ->selectRaw('UPPER(TRIM(campaignName)) as sku_key, SUM(spend) as total_spend')
            ->groupByRaw('UPPER(TRIM(campaignName))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();

        // QUERY 4: PT Spent - single query with GROUP BY (using ShipHub date range)
        $ptSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $endDateStr)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->where(function($q) {
                $q->whereRaw("campaignName LIKE '%PT'")
                  ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->selectRaw('UPPER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(campaignName, " PT.", ""), " PT", ""), "PT.", ""), "PT", ""))) as sku_key, SUM(spend) as total_spend')
            ->groupByRaw('UPPER(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(campaignName, " PT.", ""), " PT", ""), "PT.", ""), "PT", "")))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();

        // Build lookup maps
        $kwSpentBySku = [];
        $ptSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $kwSpentBySku[$sku] = $kwSpentData[$skuUpper] ?? 0;
            $ptSpentBySku[$sku] = $ptSpentData[$skuUpper] ?? 0;
        }

        // Process data (in-memory, fast)
        $data = [];
        foreach ($orderItems as $item) {
            $pm = $productMasters[$item->sku] ?? null;

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
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : 0;
                $weightAct = isset($values["wt_act"]) ? floatval($values["wt_act"]) : 0;
            }

            $quantity = floatval($item->quantity);
            // NOTE: ShipHub's "unit_price" is misleading - it's actually TOTAL price for the item
            // So we DON'T multiply by quantity (that would be double counting)
            $totalPrice = floatval($item->price); // This is already the total sale amount
            $unitPrice = $quantity > 0 ? $totalPrice / $quantity : 0; // Calculate actual per-unit price
            $tWeight = $weightAct * $quantity;

            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $cogs = $lp * $quantity;
            $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $pft = $pftEach * $quantity;
            $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

            $data[] = [
                'order_id' => $item->order_id,
                'asin' => $item->asin,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'sale_amount' => round($totalPrice, 2), // Total price (don't multiply again)
                'price' => round($unitPrice, 2), // Per-unit price
                'total_amount' => $item->total_amount,
                'currency' => $item->currency,
                'order_date' => $item->order_date,
                'status' => $item->status,
                'period' => $item->period,
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
        $startDate = $yesterday->copy()->subDays(31); // 32 days total
        
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
