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
        // 32 days: yesterday se 31 din pehle tak
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(31); // 32 days total
        $startDateStr = $startDate->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        // QUERY 1: Get all order items with JOIN (single query instead of N+1)
        $orderItems = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->whereBetween('o.order_date', [$startDate, $yesterday->endOfDay()])
            ->where('o.status', '!=', 'Canceled')
            ->select([
                'o.amazon_order_id as order_id',
                'o.order_date',
                'o.status',
                'o.total_amount',
                'o.currency',
                'o.period',
                'i.asin',
                'i.sku',
                'i.title',
                'i.quantity',
                'i.price'
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

        // QUERY 3: KW Spent - single query with GROUP BY
        $kwSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $yesterdayStr)
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

        // QUERY 4: PT Spent - single query with GROUP BY
        $ptSpentData = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $startDateStr)
            ->whereDate('report_date_range', '<=', $yesterdayStr)
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
            $price = floatval($item->price);
            $tWeight = $weightAct * $quantity;

            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $cogs = $lp * $quantity;
            $unitPrice = $quantity > 0 ? $price / $quantity : 0;
            $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $pft = $pftEach * $quantity;
            $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

            $data[] = [
                'order_id' => $item->order_id,
                'asin' => $item->asin,
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'sale_amount' => round($price, 2),
                'price' => $quantity > 0 ? round($price / $quantity, 2) : 0,
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
}
