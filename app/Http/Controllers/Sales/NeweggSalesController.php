<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\NeweggOrder;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NeweggSalesController extends Controller
{
    public function index()
    {
        $yesterday = Carbon::yesterday('America/Los_Angeles');

        return view('sales.newegg_daily_sales_data', [
            'salesYesterday' => $this->salesForPacificDay($yesterday),
            'yesterdayLabel' => $yesterday->format('M j, Y'),
        ]);
    }

    /**
     * Y Sales: one complete Pacific day, using the page's sales definition
     * (line amount, else unit price × qty; voided orders excluded).
     */
    private function salesForPacificDay(Carbon $day): float
    {
        $lines = $this->salesLines('l30', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);

        $sales = 0.0;
        foreach ($lines as $row) {
            if (($row['sku'] ?? '') === '' || (int) ($row['quantity'] ?? 0) === 0) {
                continue;
            }
            $sale = (float) ($row['sale_amount'] ?? 0);
            if ($sale == 0.0) {
                $sale = (float) ($row['price'] ?? 0) * (int) $row['quantity'];
            }
            $sales += $sale;
        }

        return round($sales, 2);
    }

    /**
     * Badge integers painted by /newegg/daily-sales.
     * Sales is the line amount. GPFT = rounded PFT ÷ rounded sales.
     * GROI = rounded PFT ÷ rounded COGS. Ship is one weight slab, subtracted once.
     *
     * @return array{l30: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int}
     */
    public static function dailySalesBadgeTotals(string $period = 'l30', ?array $window = null): array
    {
        return self::totalsFromLines(app(self::class)->salesLines($period, $window));
    }

    /**
     * L30 totals for several chart days with one query. Chart day X covers the
     * 30 complete Pacific days ending X (same window the snapshot saved on X+1 should hold).
     *
     * @param  list<string>  $chartDays  YYYY-MM-DD
     * @return array<string, array{l30: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int}>
     */
    public static function l30TotalsForChartDays(array $chartDays): array
    {
        if ($chartDays === []) {
            return [];
        }
        sort($chartDays);
        $tz = 'America/Los_Angeles';
        $from = Carbon::parse($chartDays[0], $tz)->subDays(29)->startOfDay();
        $to = Carbon::parse(end($chartDays), $tz)->endOfDay();

        $lines = app(self::class)->salesLines('l30', [$from, $to]);

        $out = [];
        foreach ($chartDays as $day) {
            $dayFrom = Carbon::parse($day, $tz)->subDays(29)->format('Y-m-d').' 00:00:00';
            $dayTo = $day.' 23:59:59';
            $out[$day] = self::totalsFromLines(array_filter(
                $lines,
                fn ($row) => ($row['order_date'] ?? '') >= $dayFrom && ($row['order_date'] ?? '') <= $dayTo
            ));
        }

        return $out;
    }

    /**
     * @param  iterable<array<string, mixed>>  $lines
     * @return array{l30: int, orders: int, qty: int, pft: int, cogs: int, ship: int, gpft: int, groi: int}
     */
    private static function totalsFromLines(iterable $lines): array
    {
        $orders = 0;
        $qty = 0;
        $sales = 0.0;
        $pft = 0.0;
        $cogs = 0.0;
        $ship = 0.0;

        foreach ($lines as $row) {
            if (($row['sku'] ?? '') === '') {
                continue;
            }
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $orders++;
            $qty += $quantity;
            $sale = (float) ($row['sale_amount'] ?? 0);
            if ($sale == 0.0) {
                $sale = (float) ($row['price'] ?? 0) * $quantity;
            }
            $sales += $sale;
            $pft += (float) ($row['pft'] ?? 0);
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
        ];
    }

    public function getData(Request $request)
    {
        return response()->json($this->salesLines('l30'));
    }

    /**
     * 30 complete Pacific days ending yesterday (matches ChannelMasterController::completePacificL30L60Windows).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function l30Window(): array
    {
        $end = Carbon::yesterday('America/Los_Angeles');

        return [
            $end->copy()->subDays(29)->startOfDay(),
            $end->copy()->endOfDay(),
        ];
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $window  Override [from, to] (Pacific) instead of the default L30.
     * @return list<array<string, mixed>>
     */
    private function salesLines(string $period = 'l30', ?array $window = null): array
    {
        // Margin comes from marketplace_percentages (Neweggb2c): margin = percentage - ad_updates.
        // This margin (net % the seller keeps) drives the profit calculation.
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
        $percentage = $marketplaceData ? (float) $marketplaceData->percentage : 80;
        $adUpdates  = $marketplaceData ? (float) $marketplaceData->ad_updates : 0;
        $margin     = $percentage - $adUpdates;
        $factor     = $margin > 0 ? $margin / 100 : 0.80;

        // Same L30 window and void filter as /all-marketplace-master (ChannelMasterController::computeNeweggWindow).
        $query = NeweggOrder::with('items')
            ->where(function ($q) {
                $q->whereNull('order_status')->orWhere('order_status', '!=', 4);
            });
        if ($period === 'l30') {
            [$from, $to] = $window ?? self::l30Window();
            $query->whereBetween('order_date', [$from, $to]);
        }
        $orders = $query->orderBy('order_date', 'desc')->get();

        // Collect SKUs (Newegg seller part number) for ProductMaster lookup.
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->seller_part_number) {
                    $skus[] = $item->seller_part_number;
                }
            }
        }
        $skus = array_values(array_unique($skus));
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $sku = $item->seller_part_number;
                $pm  = $sku ? ($productMasters[$sku] ?? null) : null;

                [$lp, $ship] = $this->extractCosts($pm);
                $values = [];
                $parent = '';
                if ($pm) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
                    $values = is_array($values) ? $values : [];
                    $parent = (string) ($pm->parent ?? '');
                }

                $quantity  = (float) ($item->ordered_qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $saleAmount = (float) ($item->extend_unit_price ?? 0);
                if ($saleAmount <= 0) {
                    $saleAmount = $unitPrice * $quantity;
                }

                $weightAct = EbaySalesController::actWeightLb($values);
                $tWeight = $weightAct * $quantity;
                $shipCost = $quantity > 0
                    ? EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, (string) ($sku ?? ''), $values, $quantity, $parent)
                    : 0.0;

                $cogs       = $lp * $quantity;
                $pft        = ($saleAmount * $factor) - $cogs - $shipCost;
                $pftEach    = $quantity > 0 ? $pft / $quantity : 0;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $roi        = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

                $data[] = [
                    'order_id'     => $order->order_number,
                    'sku'          => $sku,
                    'description'  => $item->description,
                    'quantity'     => (int) $quantity,
                    'price'        => round($unitPrice, 2),
                    'sale_amount'  => round($saleAmount, 2),
                    'total_amount' => round((float) $order->order_total_amount, 2),
                    'currency'     => $order->currency_code ?: 'USD',
                    'order_date'   => optional($order->order_date)->format('Y-m-d H:i:s'),
                    'status'       => $order->order_status_description,
                    'customer'     => $order->customer_name,
                    'lp'           => round($lp, 2),
                    'ship'         => round($ship, 2),
                    't_weight'     => round($tWeight, 2),
                    'ship_cost'    => round($shipCost, 2),
                    'cogs'         => round($cogs, 2),
                    'pft_each'     => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    'pft'          => round($pft, 2),
                    'roi'          => round($roi, 2),
                ];
            }
        }

        return $data;
    }

    /**
     * Pull LP, Ship and Weight from a ProductMaster row (Values JSON or columns).
     *
     * @return array{0:float,1:float,2:float}
     */
    private function extractCosts(?ProductMaster $pm): array
    {
        if (!$pm) {
            return [0.0, 0.0, 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
        $weightAct = isset($values['wt_act']) ? (float) $values['wt_act'] : 0.0;

        return [$lp, $ship, $weightAct];
    }

    public function getColumnVisibility()
    {
        try {
            $filePath = storage_path('app/newegg_sales_column_visibility.json');

            $default = [
                'order_id' => true, 'sku' => true, 'description' => false, 'quantity' => true,
                'price' => true, 'sale_amount' => true, 'total_amount' => false, 'currency' => false,
                'order_date' => true, 'status' => true, 'customer' => false, 'lp' => true, 'ship' => true,
                't_weight' => false, 'ship_cost' => false, 'cogs' => true, 'pft_each' => true,
                'pft_each_pct' => true, 'pft' => true, 'roi' => true,
            ];

            if (file_exists($filePath)) {
                $saved = json_decode(file_get_contents($filePath), true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }

            return response()->json($default);
        } catch (\Exception $e) {
            Log::error('Error getting Newegg column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/newegg_sales_column_visibility.json');
            file_put_contents($filePath, json_encode($request->input('visibility', []), JSON_PRETTY_PRINT));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
