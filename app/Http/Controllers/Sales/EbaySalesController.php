<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\EbayOrder;
use App\Models\ProductMaster;
use App\Services\EbayChannelMetricsService;
use App\Services\ShippingSlabRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EbaySalesController extends Controller
{
    /**
     * Item WT ACT (lb) from Dim & Wt Master. Uses wt_act, else wt_act_kg × 2.2046226218.
     *
     * @param  array<string, mixed>  $values
     */
    public static function actWeightLb(array $values): float
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
    public static function shipSlabLookup(): array
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
    public static function cogsShipForOrderWeight(?ShippingSlabRateService $slabs, array $shipSlabRates, float $weightOrder): float
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
        // Yesterday's sales (Pacific) from real orders — same per-order total and
        // exclusions the Total Sales badge uses (pricingSummary.total + collect-and-remit
        // tax; skip CANCELED and FULLY_REFUNDED). Mirrors Amazon's "Y Sales" badge.
        $tz = 'America/Los_Angeles';
        $ySales = (float) (EbayChannelMetricsService::computeYSales(1) ?? 0);

        return view('sales.ebay_daily_sales_data', [
            'salesYesterday' => round($ySales, 2),
            'yesterdayLabel' => \Carbon\Carbon::yesterday($tz)->format('M j, Y'),
        ]);
    }

    public function getData(Request $request)
    {
        \Log::info('EbaySalesController getData called');

        $orders = EbayOrder::with('items')
            ->where('period', 'l30')
            ->orderBy('order_date', 'desc')
            ->get();

        \Log::info('Found ' . $orders->count() . ' orders');

        // Get unique SKUs
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $skus[] = $item->sku;
            }
        }
        $skus = array_unique($skus);

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        [$slabService, $shipSlabRates] = self::shipSlabLookup();

        $data = [];
        foreach ($orders as $order) {
            // eBay "Total sales (includes taxes)" per order = pricingSummary.total (the
            // amount the buyer paid, after discounts) PLUS eBay's collect-and-remit tax,
            // which the Fulfillment API reports at the line-item level
            // (lineItems[].ebayCollectAndRemitTaxes) — it is NOT in pricingSummary.
            // The stored total_amount column is stale (old fetches saved the wrong key),
            // so compute the figure straight from raw_data to mirror Seller Hub.
            $raw = is_array($order->raw_data) ? $order->raw_data : json_decode((string) $order->raw_data, true);

            // eBay's "Total sales" only counts orders that resulted in a completed sale.
            // It excludes:
            //   - CANCELED orders (buyer/seller cancelled — the order never happened)
            //   - FULLY_REFUNDED orders (payment fully reversed — no net sale)
            // Partially refunded orders are kept (goods partly retained = still a sale).
            // Verified against Seller Hub: excluding these lands within ~0.1% of eBay's
            // reported Total sales (the tiny residual is day-boundary timezone rounding).
            if (is_array($raw)) {
                $cancelState = $raw['cancelStatus']['cancelState'] ?? '';
                $paymentStatus = $raw['orderPaymentStatus'] ?? '';
                if ($cancelState === 'CANCELED' || $paymentStatus === 'FULLY_REFUNDED') {
                    continue;
                }
            }

            $orderTotal = (float) ($order->total_amount ?? 0);
            if (is_array($raw)) {
                $base = (float) ($raw['pricingSummary']['total']['value'] ?? 0);
                $carTax = 0.0;
                foreach (($raw['lineItems'] ?? []) as $li) {
                    foreach (($li['ebayCollectAndRemitTaxes'] ?? []) as $t) {
                        $carTax += (float) ($t['amount']['value'] ?? 0);
                    }
                }
                $computed = $base + $carTax;
                if ($computed > 0) {
                    $orderTotal = $computed;
                }
            }
            $orderTotal = round($orderTotal, 2);

            foreach ($order->items as $item) {
                $pm = $productMasters[$item->sku] ?? null;

                // Extract LP, Ship, and Weight Act
                $lp = 0;
                $ship = 0;
                $values = [];
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $values = is_array($values) ? $values : [];
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

                $quantity = floatval($item->quantity);
                $price = floatval($item->price);

                // Item price is already this row's Sales AMT. T Weight = ACT lb × Qty.
                $weightAct = self::actWeightLb($values);
                $tWeight = $weightAct * $quantity;
                $shipCost = self::cogsShipForOrderWeight($slabService, $shipSlabRates, $tWeight);

                // COGS = LP × Qty. COGS Ship is subtracted once.
                $cogs = $lp * $quantity;
                $pft = ($price * 0.85) - $cogs - $shipCost;
                $pftEach = $quantity > 0 ? $pft / $quantity : 0;
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

                $data[] = [
                    'order_id' => $order->ebay_order_id,
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($price, 2),
                    'price' => $quantity > 0 ? round($price / $quantity, 2) : 0,
                    'total_amount' => $orderTotal,
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
                ];
            }
        }

        \Log::info('Returning ' . count($data) . ' data items');

        return response()->json($data);
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            // Read from JSON file (shared for all users)
            $filePath = storage_path('app/ebay_column_visibility.json');
            
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
                $json = file_get_contents($filePath);
                $saved = json_decode($json, true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }
            
            return response()->json($defaultVisibility);
        } catch (\Exception $e) {
            Log::error('Error getting eBay column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            // Save to JSON file (shared for all users)
            $filePath = storage_path('app/ebay_column_visibility.json');
            
            $visibility = $request->input('visibility', []);
            
            // Write to JSON file
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }

    public function getSkuSalesData(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $filter = $request->input('filter', 'last30');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }

            // Determine date range based on filter
            $now = \Carbon\Carbon::now();
            $fromDate = null;
            $toDate = $now->format('Y-m-d');
            
            if ($filter === 'custom' && $startDate && $endDate) {
                $fromDate = \Carbon\Carbon::parse($startDate)->format('Y-m-d');
                $toDate = \Carbon\Carbon::parse($endDate)->format('Y-m-d');
            } elseif ($filter === 'today') {
                $fromDate = $now->format('Y-m-d');
            } elseif ($filter === 'yesterday') {
                $fromDate = $now->copy()->subDay()->format('Y-m-d');
                $toDate = $fromDate;
            } elseif ($filter === 'last7') {
                $fromDate = $now->copy()->subDays(7)->format('Y-m-d');
            } else { // last30 (default)
                $fromDate = $now->copy()->subDays(30)->format('Y-m-d');
            }
            
            $query = EbayOrder::with(['items' => function($query) use ($sku) {
                $query->where('sku', $sku);
            }])
                ->where('period', 'l30')
                ->whereHas('items', function($query) use ($sku) {
                    $query->where('sku', $sku);
                });
            
            if ($fromDate) {
                $query->whereDate('order_date', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('order_date', '<=', $toDate);
            }
            
            $orders = $query->orderBy('order_date', 'desc')->get();

            // Group by date and calculate daily quantities
            $dailyData = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->sku === $sku) {
                        $date = \Carbon\Carbon::parse($order->order_date)->format('Y-m-d');
                        if (!isset($dailyData[$date])) {
                            $dailyData[$date] = [
                                'date' => $date,
                                'quantity' => 0,
                                'orders' => 0
                            ];
                        }
                        $dailyData[$date]['quantity'] += (int) $item->quantity;
                        $dailyData[$date]['orders'] += 1;
                    }
                }
            }

            // Sort by date
            ksort($dailyData);
            $dailyData = array_values($dailyData);

            // Calculate total
            $totalQuantity = array_sum(array_column($dailyData, 'quantity'));
            $totalOrders = array_sum(array_column($dailyData, 'orders'));

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'total_quantity' => $totalQuantity,
                'total_orders' => $totalOrders,
                'daily_data' => $dailyData,
                'date_range' => [
                    'from' => $fromDate,
                    'to' => $toDate
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting SKU sales data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }
}
