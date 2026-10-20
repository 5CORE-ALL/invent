<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ShopifyB2CDailyData;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyB2CSalesController extends Controller
{
    public function index()
    {
        // Calculate Google Ads Spent (L30)
        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29); // 30 days total
        
        $googleSpent = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $yesterday)
            ->where('advertising_channel_type', 'SHOPPING')
            ->sum('metrics_cost_micros') / 1000000; // Convert micros to dollars

        // Y Sales: yesterday's price × qty, same filters as the page (no refunded, no PARENT SKUs).
        $ySales = (float) ShopifyB2CDailyData::query()
            ->whereBetween('order_date', [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
            ->where('financial_status', '!=', 'refunded')
            ->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', 'not like', '%PARENT%');
            })
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');

        return view('sales.shopify_b2c_daily_sales_data', [
            'googleSpent' => (float) $googleSpent,
            'salesYesterday' => round($ySales, 2),
            'yesterdayLabel' => $yesterday->format('M j, Y'),
        ]);
    }

    /**
     * Badge integers painted by /shopify-b2c/daily-sales.
     * Sales = price × qty. GPFT = rounded PFT ÷ rounded sales.
     * GROI = rounded PFT ÷ rounded COGS. Ship is one weight slab, subtracted once.
     *
     * @return array{
     *   l30: int, orders: int, qty: int, pft: int, cogs: int, ship: int,
     *   gpft: int, groi: int, revenue: float, google: float, tacos: float,
     *   n_pft: int, n_roi: int
     * }
     */
    public static function dailySalesBadgeTotals(string $period = 'l30', bool $withAds = true): array
    {
        $lines = app(self::class)->salesLinesForPeriod($period);
        $orderIds = [];
        $qty = 0;
        $revenue = 0.0;
        $pft = 0.0;
        $sales = 0.0;
        $cogs = 0.0;
        $ship = 0.0;

        foreach ($lines as $row) {
            if (($row['sku'] ?? '') === '') {
                continue;
            }
            // Each row is a line item; an order with 3 SKUs must count as 1 order, not 3.
            $orderIds[(string) ($row['order_id'] ?? '')] = true;
            $qty += (int) ($row['quantity'] ?? 0);
            $revenue += (float) ($row['total_amount'] ?? 0);
            $pft += (float) ($row['pft'] ?? 0);
            $sales += (float) ($row['l30_sales'] ?? 0);
            $cogs += (float) ($row['cogs'] ?? 0);
            $ship += (float) ($row['ship_cost'] ?? 0);
        }

        $orders = count($orderIds);
        $pftRounded = (int) round($pft);
        $salesRounded = (int) round($sales);
        $cogsRounded = (int) round($cogs);
        $gpftRaw = $salesRounded !== 0 ? ($pftRounded / $salesRounded) * 100 : 0.0;
        $groiRaw = $cogsRounded !== 0 ? ($pftRounded / $cogsRounded) * 100 : 0.0;

        $google = 0.0;
        if ($withAds && $period === 'l30') {
            $yesterday = \Carbon\Carbon::yesterday();
            $startDate = $yesterday->copy()->subDays(29);
            $google = (float) DB::table('google_ads_campaigns')
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $yesterday)
                ->where('advertising_channel_type', 'SHOPPING')
                ->sum('metrics_cost_micros') / 1000000;
        }
        $tacos = $revenue > 0 ? ($google / $revenue) * 100 : 0.0;
        $nRoi = $cogsRounded !== 0
            ? (int) round((($pftRounded - (int) round($google)) / $cogsRounded) * 100)
            : 0;

        return [
            'l30' => $salesRounded,
            'orders' => $orders,
            'qty' => $qty,
            'pft' => $pftRounded,
            'cogs' => $cogsRounded,
            'ship' => (int) round($ship),
            'gpft' => (int) round($gpftRaw),
            'groi' => (int) round($groiRaw),
            'revenue' => round($revenue, 2),
            'google' => $google,
            'tacos' => $tacos,
            'n_pft' => (int) round($gpftRaw - $tacos),
            'n_roi' => $nRoi,
        ];
    }

    public function getData(Request $request)
    {
        Log::info('ShopifyB2CSalesController getData called');

        $yesterday = \Carbon\Carbon::yesterday();
        $startDate = $yesterday->copy()->subDays(29);
        $startDateStr = $startDate->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        $data = $this->salesLinesForPeriod('l30');
        Log::info('Found ' . count($data) . ' Shopify B2C orders (excluding PARENT SKUs)');

        $skus = array_values(array_unique(array_column($data, 'sku')));
        $googleSpentData = DB::table('google_ads_campaigns')
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $yesterdayStr)
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->selectRaw('UPPER(TRIM(campaign_name)) as sku_key, SUM(metrics_cost_micros) / 1000000 as total_spend')
            ->groupByRaw('UPPER(TRIM(campaign_name))')
            ->pluck('total_spend', 'sku_key')
            ->toArray();

        foreach ($data as &$row) {
            $skuUpper = strtoupper(trim((string) ($row['sku'] ?? '')));
            $row['google_spent'] = round((float) ($googleSpentData[$skuUpper] ?? 0), 2);
        }
        unset($row);

        Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesLinesForPeriod(string $period): array
    {
        $percentageValue = 0.95;

        // Window by order_date, not the stored `period` label. The label is only rewritten at the
        // end of a fully successful app:fetch-shopify-b2c-metrics run, so after a 429/failed run
        // old orders kept their 'l30' label and inflated L30 Orders / Qty / Sales.
        // Boundaries match FetchShopifyB2CMetrics::updatePeriodLabels().
        $today = \Carbon\Carbon::today();
        $l30Start = $today->copy()->subDays(30);
        $query = ShopifyB2CDailyData::query();
        if ($period === 'l60') {
            $query->where('order_date', '>=', $today->copy()->subDays(60))
                ->where('order_date', '<', $l30Start);
        } else {
            $query->where('order_date', '>=', $l30Start);
        }

        $orders = $query
            ->where('financial_status', '!=', 'refunded')
            ->orderBy('order_date', 'desc')
            ->get()
            ->filter(function ($order) {
                return stripos((string) $order->sku, 'PARENT') === false;
            });

        $skus = $orders->pluck('sku')->unique()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

            // Extract LP and the saved product ship. COGS Ship uses the weight slab.
            $lp = 0;
            $ship = 0;
            $values = [];
            $parent = '';
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $values = is_array($values) ? $values : [];
                $parent = (string) ($pm->parent ?? '');
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
            }

            $quantity = floatval($order->quantity);
            $price = floatval($order->price);
            $totalAmount = floatval($order->total_amount);

            $weightAct = EbaySalesController::actWeightLb($values);
            $tWeight = $weightAct * $quantity;
            $shipCost = $quantity > 0
                ? EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, (string) ($order->sku ?? ''), $values, $quantity, $parent)
                : 0.0;

            // COGS = LP × Qty. Price is the unit price, so sales is price × Qty once.
            $cogs = $lp * $quantity;
            $sales = $price * $quantity;
            $pft = ($sales * $percentageValue) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $pftEachPct = $price > 0 ? ($pftEach / $price) * 100 : 0;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $l30Sales = $sales;

            $data[] = [
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'sku' => $order->sku,
                'title' => $order->product_title,
                'quantity' => $order->quantity,
                'original_price' => round(floatval($order->original_price), 2),
                'discount_amount' => round(floatval($order->discount_amount), 2),
                'price' => round($price, 2),
                'total_amount' => round($totalAmount, 2),
                'order_date' => $order->order_date,
                'financial_status' => $order->financial_status,
                'fulfillment_status' => $order->fulfillment_status,
                'customer_name' => $order->customer_name,
                'shipping_city' => $order->shipping_city,
                'shipping_country' => $order->shipping_country,
                'tracking_number' => $order->tracking_number,
                'tags' => $order->tags,
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
                'l30_sales' => round($l30Sales, 2),
            ];
        }

        return $data;
    }

    public function getColumnVisibility(Request $request)
    {
        $defaultVisibility = [
            'order_id' => true,
            'order_number' => true,
            'sku' => true,
            'title' => true,
            'quantity' => true,
            'original_price' => true,
            'discount_amount' => true,
            'price' => true,
            'total_amount' => true,
            'order_date' => true,
            'financial_status' => true,
            'fulfillment_status' => true,
            'customer_name' => true,
            'shipping_city' => true,
            'shipping_country' => true,
            'tracking_number' => true,
            'tags' => true,
            'lp' => true,
            'ship' => true,
            't_weight' => true,
            'ship_cost' => true,
            'cogs' => true,
            'pft_each' => true,
            'pft_each_pct' => true,
            'pft' => true,
            'roi' => true,
            'l30_sales' => true,
            'google_spent' => true,
        ];

        $saved = session('shopify_b2c_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['shopify_b2c_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}
