<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AlibabaOrderMetric;
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

    public function getData(Request $request)
    {
        $start = Carbon::now(self::TZ)->subDays(30);

        $rows = AlibabaOrderMetric::query()
            ->where('order_date', '>=', $start)
            ->orderByDesc('order_date')
            ->get();

        $skus = $rows->pluck('sku')->filter()->unique()->values()->all();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

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
            $sku = trim((string) $row->sku);
            if ($sku === '' || $sku === '__order__') {
                continue;
            }

            $pm = $productMasters[$sku] ?? null;
            [$lp, $ship, $weightAct] = $this->productCosts($pm);

            $quantity = (float) $row->quantity;
            $unitPrice = (float) $row->amount;
            $lineAmount = round($unitPrice * max($quantity, 0), 2);
            $tWeight = $weightAct * $quantity;

            if ($quantity == 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $quantity > 0 ? $ship / $quantity : $ship;
            } else {
                $shipCost = $ship;
            }

            $cogs = $lp * $quantity;
            $pftEach = ($unitPrice * 0.85) - $lp - $shipCost;
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
            $pft = $pftEach * $quantity;
            $roi = $lp > 0 ? ($pft / $lp) * 100 : 0;

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
                'ship' => round($ship, 2),
                't_weight' => round($tWeight, 2),
                'ship_cost' => round($shipCost, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
            ];
        }

        return response()->json($data);
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

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function productCosts(?ProductMaster $pm): array
    {
        $lp = 0.0;
        $ship = 0.0;
        $weightAct = 0.0;
        if (! $pm) {
            return [$lp, $ship, $weightAct];
        }

        $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
        if (! is_array($values)) {
            $values = [];
        }
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }
        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
        $weightAct = isset($values['wt_act']) ? (float) $values['wt_act'] : 0;

        return [$lp, $ship, $weightAct];
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
