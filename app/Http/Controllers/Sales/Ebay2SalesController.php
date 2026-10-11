<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Services\EbayChannelMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Ebay2SalesController extends Controller
{
    public function index()
    {
        // Yesterday's sales (Pacific) from real orders — same per-order total and
        // exclusions the Sales badge uses (pricingSummary.total + collect-and-remit
        // tax; skip CANCELED and FULLY_REFUNDED). Mirrors Amazon's "Y Sales" badge.
        $tz = 'America/Los_Angeles';
        $ySales = (float) (EbayChannelMetricsService::computeYSales(2) ?? 0);
        $margin = EbayChannelMetricsService::percentageDecimal(2);

        return view('sales.ebay2_daily_sales_data', [
            'salesYesterday' => round($ySales, 2),
            'yesterdayLabel' => \Carbon\Carbon::yesterday($tz)->format('M j, Y'),
            'marginPercent' => (int) round($margin * 100),
        ]);
    }

    public function getData(Request $request)
    {
        // Same source + L30 window as /all-marketplace-master EbayTwo:
        // ebay2_order_metrics (fallback ebay2_orders), 30 complete Pacific days ending yesterday.
        $orders = EbayChannelMetricsService::l30CompletedOrders(2);
        usort($orders, function ($a, $b) {
            $da = (string) ($a['raw']['creationDate'] ?? '');
            $db = (string) ($b['raw']['creationDate'] ?? '');

            return $db <=> $da;
        });

        $skus = [];
        foreach ($orders as $order) {
            foreach ($this->ebay2LineItems($order['raw']) as $item) {
                $skus[] = EbayChannelMetricsService::normalizeEbay2LookupSku((string) $item['sku']);
            }
        }
        $skus = array_values(array_unique(array_filter($skus)));

        $productMasters = collect();
        if ($skus !== []) {
            $skuLowerMap = [];
            foreach ($skus as $sku) {
                $skuLowerMap[strtolower($sku)] = $sku;
            }

            $productMastersRaw = ProductMaster::whereRaw(
                'LOWER(sku) IN ('.implode(',', array_fill(0, count($skuLowerMap), '?')).')',
                array_keys($skuLowerMap)
            )->get();

            foreach ($productMastersRaw as $pm) {
                $pmSkuLower = strtolower($pm->sku);
                if (isset($skuLowerMap[$pmSkuLower])) {
                    $productMasters[$skuLowerMap[$pmSkuLower]] = $pm;
                }
            }
        }

        $margin = EbayChannelMetricsService::percentageDecimal(2);
        [$slabService, $shipSlabRates] = EbaySalesController::shipSlabLookup();
        $data = [];

        foreach ($orders as $order) {
            $raw = $order['raw'];
            $orderTotal = $order['sale'];
            $currency = (string) ($raw['pricingSummary']['total']['currency'] ?? 'USD');
            $status = (string) ($raw['orderFulfillmentStatus'] ?? $raw['orderPaymentStatus'] ?? '');
            $created = $raw['creationDate'] ?? null;
            $orderDate = $created
                ? Carbon::parse($created)->setTimezone(EbayChannelMetricsService::TZ)->toIso8601String()
                : null;

            foreach ($this->ebay2LineItems($raw) as $item) {
                $sku = (string) $item['sku'];
                $lookupSku = EbayChannelMetricsService::normalizeEbay2LookupSku($sku);
                $pm = $productMasters[$lookupSku] ?? null;

                $lp = 0.0;
                $ship = 0.0;
                $values = [];
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $values = is_array($values) ? $values : [];
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
                }

                $quantity = (float) $item['quantity'];
                $price = (float) $item['price'];
                $weightAct = EbaySalesController::actWeightLb($values);
                $tWeight = $weightAct * $quantity;
                $shipCost = EbaySalesController::cogsShipForSku($slabService, $shipSlabRates, $sku, $values, $quantity, (string) ($pm?->parent ?? ''));

                // Sales AMT ($price) is already this row's amount. COGS Ship is subtracted once.
                $cogs = $lp * $quantity;
                $pft = ($price * $margin) - $cogs - $shipCost;
                $pftEach = $quantity > 0 ? $pft / $quantity : 0;
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

                $data[] = [
                    'order_id' => $order['order_id'],
                    'item_id' => $item['item_id'],
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'sale_amount' => round($price, 2),
                    'price' => round($unitPrice, 2),
                    'total_amount' => $orderTotal,
                    'currency' => $currency,
                    'order_date' => $orderDate,
                    'status' => $status,
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
        }

        return response()->json($data);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return list<array{sku: string, item_id: string, quantity: float, price: float}>
     */
    private function ebay2LineItems(array $raw): array
    {
        $lines = [];
        foreach (($raw['lineItems'] ?? []) as $li) {
            if (! is_array($li)) {
                continue;
            }
            $sku = trim((string) ($li['sku'] ?? ''));
            $itemId = trim((string) ($li['legacyItemId'] ?? $li['lineItemId'] ?? ''));
            if ($sku === '') {
                $sku = $itemId !== '' ? $itemId : '(no sku)';
            }
            $lines[] = [
                'sku' => $sku,
                'item_id' => $itemId,
                'quantity' => (float) ($li['quantity'] ?? 0),
                'price' => (float) ($li['lineItemCost']['value'] ?? 0),
            ];
        }

        if ($lines === []) {
            $lines[] = [
                'sku' => '(no sku)',
                'item_id' => '',
                'quantity' => 0.0,
                'price' => 0.0,
            ];
        }

        return $lines;
    }

    public function getColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            
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
            Log::error('Error getting eBay 2 column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/ebay2_column_visibility.json');
            $visibility = $request->input('visibility', []);
            file_put_contents($filePath, json_encode($visibility, JSON_PRETTY_PRINT));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving eBay 2 column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
