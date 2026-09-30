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

    public function getData(Request $request)
    {
        $start = Carbon::now(self::TZ)->subDays(30);

        $rows = AlibabaOrderMetric::query()
            ->where('order_date', '>=', $start)
            ->orderByDesc('order_date')
            ->get();

        $productIds = $rows->pluck('product_id')->filter()->unique()->values();
        $metricSkus = AlibabaMetric::query()->whereIn('product_id', $productIds)->pluck('sku', 'product_id');
        $sheetSkus = AlibabaSheetPrice::query()->whereIn('product_id', $productIds)->pluck('sku', 'product_id');
        $resolvedSkus = $rows->map(function ($row) use ($metricSkus, $sheetSkus) {
            return $this->catalogSku($row, $metricSkus, $sheetSkus);
        })->filter()->unique()->values()->all();
        $productMasters = ProductMaster::whereIn('sku', $resolvedSkus)->get()->keyBy('sku');
        $margin = MarketplacePercentage::takeHomeDecimal('Alibaba');

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

            $cogs = $lp * $quantity;
            $pftEach = ($unitPrice * $margin) - $lp;
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
