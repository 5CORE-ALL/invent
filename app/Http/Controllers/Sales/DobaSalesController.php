<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\DobaDailyData;
use App\Models\ProductMaster;
use App\Services\ShippingSlabRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DobaSalesController extends Controller
{
    /**
     * Item WT ACT (lb) from Dim & Wt Master. Uses wt_act, else wt_act_kg × 2.2046226218.
     *
     * @param  array<string, mixed>  $values
     */
    private static function actWeightLb(array $values): float
    {
        $lb = $values['wt_act'] ?? null;
        if (is_numeric($lb) && (float) $lb > 0) {
            return round((float) $lb, 2);
        }

        $kg = $values['wt_act_kg'] ?? null;
        if (is_numeric($kg) && (float) $kg > 0) {
            return round((float) $kg * 2.2046226218, 2);
        }

        return 0.0;
    }

    /**
     * @return array{0: ?ShippingSlabRateService, 1: array<string, array{rate: ?float}>}
     */
    private static function shipSlabLookup(): array
    {
        try {
            $slabs = app(ShippingSlabRateService::class);

            return [$slabs, $slabs->getAllSlabCarrierRates('ship')];
        } catch (\Throwable $e) {
            return [null, []];
        }
    }

    public function index()
    {
        // No KW/PT spent for Doba
        $kwSpent = 0;
        $ptSpent = 0;

        $yesterday = Carbon::yesterday('America/Los_Angeles');

        return view('sales.doba_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'ptSpent' => (float) $ptSpent,
            'salesYesterday' => $this->salesForPacificDay($yesterday),
            'yesterdayLabel' => $yesterday->format('M j, Y'),
        ]);
    }

    /**
     * Y Sales: one complete Pacific day. Same sales definition as the page
     * (item price × qty once), excluding cancelled orders.
     */
    private function salesForPacificDay(Carbon $day): float
    {
        $cancelled = ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'];

        return round((float) DobaDailyData::query()
            ->whereBetween('order_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->selectRaw('COALESCE(SUM(item_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    public function getData(Request $request)
    {
        // Get data for L30 period
        $data = DobaDailyData::where('period', 'L30')
            ->orderBy('order_time', 'desc')
            ->get();

        // Get unique SKUs
        $skus = $data->pluck('sku')->filter()->unique()->values()->toArray();

        // QUERY: ProductMaster for ship values
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->select(['sku', 'parent', 'Values'])
            ->get()
            ->keyBy('sku');

        // L60 sales per SKU — for the L60 Sales badge.
        // total_price already represents quantity * item_price.
        $l60SalesBySku = DobaDailyData::where('period', 'L60')
            ->selectRaw('sku, SUM(total_price) as l60_total')
            ->groupBy('sku')
            ->pluck('l60_total', 'sku')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // L60 window: same convention as FetchDobaDailyData — orders older than
        // 30 days but within the last 60 days (i.e. day 60 → day 30 back from now).
        $now = Carbon::now();
        $l60Start = $now->copy()->subDays(60)->startOfDay();
        $l60End   = $now->copy()->subDays(30)->endOfDay();
        $l60Range = [
            'start'   => $l60Start->toDateString(),
            'end'     => $l60End->toDateString(),
            'display' => $l60Start->format('M d, Y') . ' – ' . $l60End->format('M d, Y'),
        ];

        [$slabService, $shipSlabRates] = self::shipSlabLookup();

        // Process data to match Amazon structure
        $processedData = [];
        foreach ($data as $item) {
            $quantity = (int) $item->quantity;
            $itemPrice = (float) $item->item_price;
            $totalPrice = (float) $item->total_price;

            // Get ship and lp from ProductMaster
            $ship = 0;
            $lp = 0;
            $values = [];
            $pm = $productMasters[$item->sku] ?? null;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $values = is_array($values) ? $values : [];
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : 0;
                $lp = isset($values["lp"]) ? floatval($values["lp"]) : 0;
            }

            // Sales AMT is unit price × qty once. total_price is the order total and
            // is repeated on every line, so it is not this row's sales.
            $lineRevenue = round($itemPrice * $quantity, 2);
            $weightAct = self::actWeightLb($values);
            $tWeight = $weightAct * $quantity;
            $cogs = $lp * $quantity;

            // Every order uses the weight slab, including pickup with a prepaid label.
            $shipCost = EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, (string) ($item->sku ?? ''), $values, $quantity, (string) ($pm?->parent ?? ''));

            // GPFT$ = (Sales AMT × 95%) − COGS − COGS Ship. COGS Ship is subtracted once.
            $pft = ($lineRevenue * 0.95) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $unitPrice = $quantity > 0 ? $lineRevenue / $quantity : 0;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $processedData[] = [
                'order_id' => $item->order_no,
                'asin' => $item->item_no, // Using item_no as asin
                'sku' => $item->sku,
                'title' => $item->product_name,
                'quantity' => $quantity,
                'sale_amount' => $lineRevenue,
                'price' => round($itemPrice, 2),
                'total_amount' => $lineRevenue,
                'currency' => $item->currency,
                'order_date' => $item->order_time ? $item->order_time->format('Y-m-d H:i:s') : null,
                'status' => $item->order_status,
                'order_type' => $item->order_type,
                'period' => $item->period,
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 0),
                'pft' => round($pft, 2),
                'roi' => round($roi, 0),
                'kw_spent' => 0,
                'pt_spent' => 0,
            ];
        }

        return response()->json([
            'data' => $processedData,
            'l60_sales_by_sku' => $l60SalesBySku,
            'l60_sales_total' => array_sum($l60SalesBySku),
            'l60_range' => $l60Range,
        ]);
    }

    /**
     * Same integers the /doba/daily-sales badges paint.
     * Sales is item price × qty once. Ship is one weight slab. Prepaid-label orders stay in.
     *
     * @return array{l30: int, l60: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int}
     */
    public function dailySalesBadgeTotals(): array
    {
        $payload = $this->getData(request())->getData(true);
        $lines = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $l60BySku = is_array($payload['l60_sales_by_sku'] ?? null) ? $payload['l60_sales_by_sku'] : [];

        return $this->badgeFromSalesLines($lines, $l60BySku);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $l60BySku
     * @return array{l30: int, l60: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int}
     */
    private function badgeFromSalesLines(array $lines, array $l60BySku): array
    {
        $orders = 0;
        $qty = 0;
        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $ship = 0.0;
        $activeSkus = [];

        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            $orderId = trim((string) ($row['order_id'] ?? ''));
            if ($sku === '' || $orderId === '') {
                continue;
            }

            $orders++;
            $activeSkus[strtolower($sku)] = true;
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($quantity === 0) {
                continue;
            }

            $qty += $quantity;
            $sales += (float) ($row['sale_amount'] ?? 0);
            $pft += (float) ($row['pft'] ?? 0);
            $cogs += (float) ($row['cogs'] ?? 0);
            $ship += (float) ($row['ship_cost'] ?? 0);
        }

        $l60Lookup = [];
        foreach ($l60BySku as $sku => $amount) {
            $l60Lookup[strtolower((string) $sku)] = (float) $amount;
        }
        $l60Sales = 0.0;
        foreach (array_keys($activeSkus) as $sku) {
            if (array_key_exists($sku, $l60Lookup)) {
                $l60Sales += $l60Lookup[$sku];
            }
        }

        $pftRounded = (int) round($pft);
        $salesRounded = (int) round($sales);
        $cogsRounded = (int) round($cogs);

        return [
            'l30' => $salesRounded,
            'l60' => (int) round($l60Sales),
            'orders' => $orders,
            'qty' => $qty,
            'pft' => $pftRounded,
            'cogs' => $cogsRounded,
            'ship' => (int) round($ship),
            'gpft' => $salesRounded !== 0 ? (int) round(($pftRounded / $salesRounded) * 100) : 0,
            'groi' => $cogsRounded !== 0 ? (int) round(($pftRounded / $cogsRounded) * 100) : 0,
        ];
    }

    public function getColumnVisibility(Request $request)
    {
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
            'order_type' => true,
            'lp' => true,
            'ship' => true,
            't_weight' => true,
            'ship_cost' => true,
            'cogs' => true,
            'pft_each' => true,
            'pft_each_pct' => true,
            'roi' => true,
        ];

        $saved = session('doba_sales_column_visibility', $defaultVisibility);
        return response()->json($saved);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        session(['doba_sales_column_visibility' => $visibility]);
        return response()->json(['success' => true]);
    }
}