<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ShopifyB2BDailyData;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyB2BSalesController extends Controller
{
    public function index()
    {
        // Y Sales: yesterday (Pacific = app timezone), price × qty, same filter as the page.
        $yesterday = \Carbon\Carbon::yesterday('America/Los_Angeles');
        $salesYesterday = (float) ShopifyB2BDailyData::query()
            ->countableSales()
            ->whereBetween('order_date', [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');

        return view('sales.shopify_b2b_daily_sales_data', [
            'salesYesterday' => round($salesYesterday, 2),
            'yesterdayLabel' => $yesterday->format('M j, Y'),
        ]);
    }

    /**
     * Badge integers painted by /shopify-b2b/daily-sales.
     * Sales = price × qty. GPFT = rounded PFT ÷ rounded sales.
     * GROI = rounded PFT ÷ rounded COGS. Ship is one weight slab, subtracted once.
     *
     * @return array{l30: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int, revenue: float}
     */
    public static function dailySalesBadgeTotals(string $period = 'l30'): array
    {
        $lines = app(self::class)->salesLinesForPeriod($period);
        $orders = 0;
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
            $orders++;
            $qty += (int) ($row['quantity'] ?? 0);
            $revenue += (float) ($row['total_amount'] ?? 0);
            $pft += (float) ($row['pft'] ?? 0);
            $sales += (float) ($row['l30_sales'] ?? 0);
            $cogs += (float) ($row['cogs'] ?? 0);
            $ship += (float) ($row['ship_cost'] ?? 0);
        }

        $pftRounded = (int) round($pft);
        $salesRounded = (int) round($sales);
        $cogsRounded = (int) round($cogs);

        return [
            'l30' => $salesRounded,
            'orders' => $orders,
            'qty' => $qty,
            'pft' => $pftRounded,
            'cogs' => $cogsRounded,
            'ship' => (int) round($ship),
            'gpft' => $salesRounded !== 0 ? (int) round(($pftRounded / $salesRounded) * 100) : 0,
            'groi' => $cogsRounded !== 0 ? (int) round(($pftRounded / $cogsRounded) * 100) : 0,
            'revenue' => round($revenue, 2),
        ];
    }

    public function getData(Request $request)
    {
        \Log::info('ShopifyB2BSalesController getData called');

        $data = $this->salesLinesForPeriod('l30');
        \Log::info('Found ' . count($data) . ' Shopify B2B orders');
        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesLinesForPeriod(string $period): array
    {
        $orders = ShopifyB2BDailyData::query()
            ->where('period', $period)
            ->countableSales()
            ->orderBy('order_date', 'desc')
            ->get();

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

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

            // COGS = LP × Qty. Price is the unit price, so sales is price × Qty once. Margin stays 95%.
            $cogs = $lp * $quantity;
            $sales = $price * $quantity;
            $pft = ($sales * 0.95) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $pftEachPct = $price > 0 ? ($pftEach / $price) * 100 : 0;

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
        ];

        $saved = session('shopify_b2b_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['shopify_b2b_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}
