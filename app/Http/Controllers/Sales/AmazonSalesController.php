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
        // Uses date-wise data (last 30 days actual dates), NOT L30 period range
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(31)->format('Y-m-d');
        $yesterday = \Carbon\Carbon::now()->subDay()->format('Y-m-d');

        $kwSpent = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
            ->whereDate('report_date_range', '<=', $yesterday)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where(function($query) {
                $query->whereRaw("campaignName NOT LIKE '%PT'")
                    ->whereRaw("campaignName NOT LIKE '%PT.'");
            })
            ->sum('spend') ?? 0;

        // Calculate PT Spent - same logic as amazonPtAdsView
        // Uses date-wise data (last 30 days actual dates), NOT L30 period range
        $ptSpent = DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
            ->whereDate('report_date_range', '<=', $yesterday)
            ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
            ->where(function($query) {
                $query->whereRaw("campaignName LIKE '%PT'")
                    ->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->sum('spend') ?? 0;

        return view('sales.amazon_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'ptSpent' => (float) $ptSpent
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('AmazonSalesController getData called');

        // 32 days: yesterday se 31 din pehle tak (excluding today)
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(32); // 32 days including yesterday

        $orders = AmazonOrder::with('items')
            ->whereBetween('order_date', [$startDate, $yesterday->endOfDay()])
            ->where('status', '!=', 'Canceled')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' orders (Date: ' . $startDate->toDateString() . ' to ' . $yesterday->toDateString() . ' - 32 days)');

        // Get unique SKUs and ASINs
        $skus = [];
        $asins = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $skus[] = $item->sku;
                if ($item->asin) {
                    $asins[] = $item->asin;
                }
            }
        }
        $skus = array_unique($skus);
        $asins = array_unique($asins);

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        // Calculate KW Spent per SKU (from amazon_sp_campaign_reports - campaigns without PT)
        // Uses date-wise data (last 30 days actual dates), NOT L30 period range
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(31)->format('Y-m-d');
        $yesterday = \Carbon\Carbon::now()->subDay()->format('Y-m-d');

        $kwSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $spReports = AmazonSpCampaignReport::whereNotNull('report_date_range')
                ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
                ->whereDate('report_date_range', '<=', $yesterday)
                ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->where('campaignName', 'NOT LIKE', '%PT')
                ->where('campaignName', 'NOT LIKE', '%PT.')
                ->whereRaw('UPPER(TRIM(campaignName)) = ?', [$skuUpper])
                ->get();
            
            foreach ($spReports as $report) {
                $spent = (float) ($report->spend ?? 0);
                $kwSpentBySku[$sku] = ($kwSpentBySku[$sku] ?? 0) + $spent;
            }
        }

        // Calculate PT Spent per SKU (from amazon_sp_campaign_reports - campaigns ending with PT)
        // Uses date-wise data (last 30 days actual dates), NOT L30 period range
        $ptSpentBySku = [];
        foreach ($skus as $sku) {
            $skuUpper = strtoupper(trim($sku));
            $ptReports = AmazonSpCampaignReport::whereNotNull('report_date_range')
                ->whereDate('report_date_range', '>=', $thirtyDaysAgo)
                ->whereDate('report_date_range', '<=', $yesterday)
                ->whereNotIn('report_date_range', ['L60','L30','L15','L7','L1'])
                ->where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->whereNotNull('campaignName')
                ->where('campaignName', '!=', '')
                ->where(function($q) use ($skuUpper) {
                    $q->whereRaw('UPPER(TRIM(campaignName)) = ?', [$skuUpper . ' PT'])
                      ->orWhereRaw('UPPER(TRIM(campaignName)) = ?', [$skuUpper . 'PT'])
                      ->orWhereRaw('UPPER(TRIM(campaignName)) = ?', [$skuUpper . ' PT.'])
                      ->orWhereRaw('UPPER(TRIM(campaignName)) = ?', [$skuUpper . 'PT.']);
                })
                ->get();
            
            foreach ($ptReports as $report) {
                $spent = (float) ($report->spend ?? 0);
                $ptSpentBySku[$sku] = ($ptSpentBySku[$sku] ?? 0) + $spent;
            }
        }

        // Get marketplace percentage and ad_updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
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

                // Ship Cost calculation (same as eBay):
                // If quantity is 1: ship_cost = ship / 1
                // If quantity > 1 and t_weight < 20: ship_cost = ship / quantity
                // Otherwise: ship_cost = ship
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }

                // COGS = LP * quantity (same as eBay)
                $cogs = $lp * $quantity;

                // PFT Each = (price * 0.80) - lp - ship_cost (Amazon uses 80% margin)
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEach = ($unitPrice * 0.80) - $lp - $shipCost;

                // PFT Each % = (pft_each / price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT / LP) * 100
                $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

                // Get KW Spent and PT Spent for this SKU
                $kwSpent = $kwSpentBySku[$item->sku] ?? 0;
                $ptSpent = $ptSpentBySku[$item->sku] ?? 0;

                $data[] = [
                    'order_id' => $order->amazon_order_id,
                    'asin' => $item->asin,
                    'sku' => $item->sku,
                    'title' => $item->title,
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
                    'pt_spent' => round($ptSpent, 2),
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' data items');

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
