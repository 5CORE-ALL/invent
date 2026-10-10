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

    /**
     * Shipping Master ship slab for the order weight. Missing weight or slab is 0.
     *
     * @param  array<string, array{rate: ?float}>  $shipSlabRates
     */
    private static function cogsShipForOrderWeight(?ShippingSlabRateService $slabs, array $shipSlabRates, float $weightOrder): float
    {
        if ($slabs === null || $weightOrder <= 0 || $shipSlabRates === []) {
            return 0.0;
        }

        $declared = $slabs->roundWeightLbUpToSlab($weightOrder);
        $key = $slabs->resolveSlabKeyForWeight($declared ?? $weightOrder);
        if ($key === null || ! isset($shipSlabRates[$key])) {
            return 0.0;
        }

        $rate = $shipSlabRates[$key]['rate'] ?? null;
        if ($rate === null || ! is_numeric($rate)) {
            return 0.0;
        }

        return round((float) $rate, 2);
    }

    public function index()
    {
        // No KW/PT spent for Doba
        $kwSpent = 0;
        $ptSpent = 0;

        return view('sales.doba_daily_sales_data', [
            'kwSpent' => (float) $kwSpent,
            'ptSpent' => (float) $ptSpent
        ]);
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
            ->select(['sku', 'Values'])
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

            $lineRevenue = $totalPrice > 0 ? $totalPrice : ($itemPrice * $quantity);
            $weightAct = self::actWeightLb($values);
            $tWeight = $weightAct * $quantity;
            $cogs = $lp * $quantity;

            // Pickup prepaid already includes the label, so COGS Ship stays 0.
            $isPrepaid = strtolower(trim((string) $item->order_type)) === 'pickup with a prepaid label';
            $shipCost = $isPrepaid
                ? 0.0
                : self::cogsShipForOrderWeight($slabService, $shipSlabRates, $tWeight);

            // PFT = (Sales × 95%) − COGS − COGS Ship. COGS Ship is subtracted once.
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
                'sale_amount' => $totalPrice,
                'price' => $quantity > 0 ? $totalPrice / $quantity : 0,
                'total_amount' => $totalPrice,
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
                'pft' => round($pft, 0),
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