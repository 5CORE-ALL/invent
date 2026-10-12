<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AlibabaMetric;
use App\Models\AlibabaOrderMetric;
use App\Models\AlibabaSheetPrice;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AlibabaSalesController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    public function index()
    {
        $yesterday = Carbon::yesterday(self::TZ);

        return view('sales.alibaba_daily_sales_data', [
            'salesYesterday' => round($this->salesForDate($yesterday), 2),
            'yesterdayLabel' => $yesterday->format('M j, Y'),
        ]);
    }

    /**
     * Same L30 / profit math as /alibaba/daily-sales, for the Active Channel row.
     *
     * @return array{
     *   ok: bool, l30_sales: float, l30_orders: int, qty: int,
     *   l60_sales: float, l60_orders: int,
     *   total_pft: float, total_cogs: float, total_cogs_ship: int,
     *   gpft_percent: float, roi_percent: float,
     *   y_sales: float, l7_sales: float
     * }
     */
    public static function channelSnapshot(): array
    {
        $self = app(self::class);
        $now = Carbon::now(self::TZ);
        $l30Start = $now->copy()->subDays(30);
        $l30 = $self->summarizeLines($self->buildLineRows($self->ordersBetween($l30Start, null)));
        $l60 = $self->summarizeLines($self->buildLineRows($self->ordersBetween($now->copy()->subDays(60), $l30Start)));
        $yesterday = Carbon::yesterday(self::TZ);

        $pft = (int) round($l30['pft']);
        $cogs = (int) round($l30['cogs']);
        $lineSales = (int) round($l30['line_sales']);

        return [
            'ok' => true,
            'l30_sales' => (int) round($l30['order_sales']),
            'l30_orders' => $l30['orders'],
            'qty' => $l30['qty'],
            'l60_sales' => (int) round($l60['order_sales']),
            'l60_orders' => $l60['orders'],
            'total_pft' => $pft,
            'total_cogs' => $cogs,
            'total_cogs_ship' => (int) round($l30['ship']),
            'gpft_percent' => self::badgePercent($pft, $lineSales),
            'roi_percent' => self::badgePercent($pft, $cogs),
            'y_sales' => $self->salesForDate($yesterday),
            'l7_sales' => $self->orderSalesBetween(
                $yesterday->copy()->subDays(6)->startOfDay(),
                $yesterday->copy()->endOfDay()
            ),
        ];
    }

    public function getData(Request $request)
    {
        $start = Carbon::now(self::TZ)->subDays(30);

        return response()->json($this->buildLineRows($this->ordersBetween($start, null)));
    }

    /**
     * Same last-30-day lines as /alibaba/daily-sales, summed by catalog SKU.
     *
     * @return array<string, array{qty: int, sales: float}>
     */
    public function l30SkuTotals(): array
    {
        $start = Carbon::now(self::TZ)->subDays(30);
        $totals = [];
        foreach ($this->buildLineRows($this->ordersBetween($start, null)) as $line) {
            $key = strtoupper(trim((string) ($line['sku'] ?? '')));
            if ($key === '') {
                continue;
            }
            $totals[$key]['qty'] = ($totals[$key]['qty'] ?? 0) + (int) ($line['quantity'] ?? 0);
            $totals[$key]['sales'] = ($totals[$key]['sales'] ?? 0) + (float) ($line['sale_amount'] ?? 0);
        }

        return $totals;
    }

    /**
     * @return \Illuminate\Support\Collection<int, AlibabaOrderMetric>
     */
    private function ordersBetween(Carbon $start, ?Carbon $end)
    {
        $query = AlibabaOrderMetric::query()
            ->where('order_date', '>=', $start)
            ->orderByDesc('order_date');
        if ($end !== null) {
            $query->where('order_date', '<', $end);
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AlibabaOrderMetric>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildLineRows($rows): array
    {

        $productIds = $rows->pluck('product_id')->filter()->unique()->values();
        $metricSkus = AlibabaMetric::query()->whereIn('product_id', $productIds)->pluck('sku', 'product_id');
        $sheetSkus = AlibabaSheetPrice::query()->whereIn('product_id', $productIds)->pluck('sku', 'product_id');
        $resolvedSkus = $rows->map(function ($row) use ($metricSkus, $sheetSkus) {
            return $this->catalogSku($row, $metricSkus, $sheetSkus);
        })->filter()->unique()->values()->all();
        $productMasters = ProductMaster::whereIn('sku', $resolvedSkus)->get()->keyBy('sku');
        $margin = MarketplacePercentage::takeHomeDecimal('Alibaba');
        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();

        $orderTotals = [];
        foreach ($rows as $row) {
            $orderId = (string) $row->order_id;
            if ($orderId === '' || isset($orderTotals[$orderId])) {
                continue;
            }
            $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
            $orderTotals[$orderId] = $this->orderGrandTotal($raw);
        }

        $data = [];
        foreach ($rows as $row) {
            $sku = $this->catalogSku($row, $metricSkus, $sheetSkus);
            if ($sku === '' || $sku === '__order__') {
                continue;
            }

            $pm = $productMasters[$sku] ?? null;
            $lp = $this->productLp($pm);

            $quantity = (float) $row->quantity;
            $unitPrice = (float) $row->amount;
            $lineAmount = round($unitPrice * max($quantity, 0), 2);

            // COGS Ship: one Shipping Master slab for the line's total weight (same rule as
            // the eBay / Shopify daily-sales pages), subtracted once from the line profit.
            $values = $this->productValues($pm);
            $parent = $pm ? (string) ($pm->parent ?? '') : '';
            $weightAct = EbaySalesController::actWeightLb($values);
            $tWeight = $weightAct * max($quantity, 0);
            $shipCost = $quantity > 0
                ? EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, $sku, $values, $quantity, $parent)
                : 0.0;
            $savedShip = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);

            $cogs = $lp * $quantity;
            $pft = ($lineAmount * $margin) - $cogs - $shipCost;
            $pftEach = $quantity > 0 ? $pft / $quantity : 0;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            $orderId = (string) $row->order_id;
            $orderTotal = $orderTotals[$orderId] ?? 0;
            if ($orderTotal <= 0) {
                $orderTotal = $lineAmount;
            }

            $data[] = [
                'order_id' => $orderId,
                'item_id' => (string) ($row->product_id ?? ''),
                'sku' => $sku,
                'quantity' => (int) $row->quantity,
                'sale_amount' => $lineAmount,
                'price' => round($unitPrice, 2),
                'total_amount' => round($orderTotal, 2),
                'order_date' => $row->order_date,
                'status' => (string) ($row->status ?? ''),
                'period' => 'l30',
                'lp' => round($lp, 2),
                'ship' => round($savedShip, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
            ];
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{order_sales: float, line_sales: float, orders: int, qty: int, pft: float, cogs: float, ship: float}
     */
    private function summarizeLines(array $lines): array
    {
        $seen = [];
        $orderSales = 0.0;
        $lineSales = 0.0;
        $orders = 0;
        $qty = 0;
        $pft = 0.0;
        $cogs = 0.0;
        $ship = 0.0;
        foreach ($lines as $line) {
            $orderId = (string) ($line['order_id'] ?? '');
            $sku = (string) ($line['sku'] ?? '');
            if ($sku === '' || $orderId === '') {
                continue;
            }
            if (! isset($seen[$orderId])) {
                $seen[$orderId] = true;
                $orderSales += (float) ($line['total_amount'] ?? 0);
            }
            $orders++;
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            $qty += $quantity;
            $lineSales += (float) ($line['price'] ?? 0) * $quantity;
            $pft += (float) ($line['pft'] ?? 0);
            $cogs += (float) ($line['cogs'] ?? 0);
            $ship += (float) ($line['ship_cost'] ?? 0);
        }

        return [
            'order_sales' => round($orderSales, 2),
            'line_sales' => round($lineSales, 2),
            'orders' => $orders,
            'qty' => $qty,
            'pft' => round($pft, 2),
            'cogs' => round($cogs, 2),
            'ship' => round($ship, 2),
        ];
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/alibaba_daily_sales_column_visibility.json');
            $defaultVisibility = [
                'order_id' => true,
                'item_id' => true,
                'sku' => true,
                'quantity' => true,
                'sale_amount' => true,
                'price' => true,
                'total_amount' => true,
                'order_date' => true,
                'status' => true,
                'period' => true,
                'lp' => true,
                'ship' => true,
                't_weight' => true,
                'ship_cost' => true,
                'cogs' => true,
                'pft_each' => true,
                'pft_each_pct' => true,
                'pft' => true,
                'roi' => true,
            ];

            if (file_exists($filePath)) {
                $saved = json_decode((string) file_get_contents($filePath), true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }

            return response()->json($defaultVisibility);
        } catch (\Exception $e) {
            Log::error('Error getting Alibaba column visibility: '.$e->getMessage());

            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/alibaba_daily_sales_column_visibility.json');
            $visibility = $request->input('visibility', []);
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Alibaba column visibility: '.$e->getMessage());

            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    private function salesForDate(Carbon $day): float
    {
        $rows = AlibabaOrderMetric::query()
            ->whereDate('order_date', $day->toDateString())
            ->get();

        $seen = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $orderId = (string) $row->order_id;
            if ($orderId === '' || isset($seen[$orderId])) {
                continue;
            }
            $seen[$orderId] = true;
            $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
            $orderTotal = $this->orderGrandTotal($raw);
            if ($orderTotal <= 0) {
                $orderTotal = (float) $row->amount * max((float) $row->quantity, 0);
            }
            $total += $orderTotal;
        }

        return $total;
    }

    private function orderSalesBetween(Carbon $start, Carbon $end): float
    {
        $rows = AlibabaOrderMetric::query()
            ->where('order_date', '>=', $start)
            ->where('order_date', '<=', $end)
            ->get();

        $seen = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $orderId = (string) $row->order_id;
            if ($orderId === '' || isset($seen[$orderId])) {
                continue;
            }
            $seen[$orderId] = true;
            $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
            $orderTotal = $this->orderGrandTotal($raw);
            if ($orderTotal <= 0) {
                $orderTotal = (float) $row->amount * max((float) $row->quantity, 0);
            }
            $total += $orderTotal;
        }

        return round($total, 2);
    }

    private static function badgePercent(int $part, int $whole): int
    {
        if ($whole === 0) {
            return 0;
        }

        return (int) round(($part / $whole) * 100);
    }

    /**
     * Orders without sku_code store the Alibaba product id in sku. Use the sheet/metric SKU.
     *
     * @param  \Illuminate\Support\Collection<string, mixed>  $metricSkus
     * @param  \Illuminate\Support\Collection<string, mixed>  $sheetSkus
     */
    private function catalogSku(AlibabaOrderMetric $row, $metricSkus, $sheetSkus): string
    {
        $sku = trim((string) $row->sku);
        $productId = trim((string) $row->product_id);
        if ($this->isCatalogSku($sku, $productId)) {
            return $sku;
        }

        foreach ([$metricSkus[$productId] ?? null, $sheetSkus[$productId] ?? null, $this->modelNumber($row)] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($this->isCatalogSku($candidate, $productId)) {
                return $candidate;
            }
        }

        return $sku;
    }

    private function isCatalogSku(string $sku, string $productId): bool
    {
        return $sku !== '' && $sku !== '__order__' && $sku !== $productId && ! ctype_digit($sku);
    }

    private function modelNumber(AlibabaOrderMetric $row): string
    {
        $raw = is_array($row->raw_payload) ? $row->raw_payload : [];
        $product = $raw['order_products']['trade_ecology_order_product'] ?? $raw['order_products'] ?? [];
        if (isset($product[0]) && is_array($product[0])) {
            $product = $product[0];
        }

        return trim((string) (is_array($product) ? ($product['model_number'] ?? '') : ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function productValues(?ProductMaster $pm): array
    {
        if (! $pm) {
            return [];
        }
        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

        return is_array($values) ? $values : [];
    }

    private function productLp(?ProductMaster $pm): float
    {
        if (! $pm) {
            return 0.0;
        }

        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        if (! is_array($values)) {
            $values = [];
        }
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                return (float) $v;
            }
        }
        if (isset($pm->lp)) {
            return (float) $pm->lp;
        }

        return 0.0;
    }

    private function orderGrandTotal(array $raw): float
    {
        $amount = $raw['total_amount']['amount'] ?? null;
        if (is_numeric($amount) && (float) $amount > 0) {
            return (float) $amount;
        }

        return 0.0;
    }
}
