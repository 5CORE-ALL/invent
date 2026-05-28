<?php

namespace App\Services;

use App\Models\ChannelMaster;
use App\Models\Ebay2Order;
use App\Models\EbayOrder;
use App\Models\MarketplaceDailyMetric;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Shared eBay 1 / 2 / 3 metrics for:
 * - app:update-marketplace-daily-metrics (marketplace_daily_metrics)
 * - ChannelMasterController (getEbay*ChannelData + fast-path overlay)
 *
 * Order windows match app:fetch-ebay-orders / app:fetch-ebay2-orders / ebay3:daily (period l30 / l60, Pacific).
 */
class EbayChannelMetricsService
{
    public const TZ = 'America/Los_Angeles';

    public static function applyActiveOrderFilter(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED');
        });
    }

    /**
     * Exclude cancelled eBay 3 rows (ebay3_daily_data).
     */
    public static function applyActiveEbay3OrderFilter(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('order_fulfillment_status')
                    ->orWhere('order_fulfillment_status', '!=', 'CANCELLED');
            })
            ->where(function ($q) {
                $q->whereNull('cancel_status')
                    ->orWhereRaw('UPPER(cancel_status) NOT LIKE ?', ['%CANCEL%']);
            });
    }

    public static function percentageDecimal(int $which): float
    {
        if ($which === 1) {
            $pct = ChannelMaster::where('channel', 'eBay')->value('channel_percentage') ?? 85;
        } elseif ($which === 2) {
            $pct = ChannelMaster::where('channel', 'EbayTwo')->value('channel_percentage') ?? 85;
        } else {
            $m = MarketplacePercentage::where('marketplace', 'EbayThree')->first();
            $pct = $m ? $m->percentage : 85;
        }

        return ((float) $pct) / 100;
    }

    public static function latestDailyMetrics(string $metricsChannel): ?MarketplaceDailyMetric
    {
        return MarketplaceDailyMetric::where('channel', $metricsChannel)->latest('date')->first();
    }

    /**
     * eBay 2 OPEN BOX / USED → base SKU for ProductMaster (matches UpdateMarketplaceDailyMetrics).
     */
    public static function normalizeEbay2LookupSku(string $sku): string
    {
        if (stripos($sku, 'OPEN BOX') !== false) {
            return trim(str_ireplace('OPEN BOX', '', $sku));
        }
        if (stripos($sku, 'USED') !== false) {
            return trim(str_ireplace('USED', '', $sku));
        }

        return $sku;
    }

    /**
     * @return array<string, ProductMaster>
     */
    private static function productMastersForEbay2Skus(iterable $orders): array
    {
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->sku) {
                    continue;
                }
                $skus[] = self::normalizeEbay2LookupSku($item->sku);
            }
        }
        $skus = array_unique($skus);
        if ($skus === []) {
            return [];
        }

        $skuLowerMap = [];
        foreach ($skus as $sku) {
            $skuLowerMap[strtolower($sku)] = $sku;
        }

        $raw = ProductMaster::whereRaw(
            'LOWER(sku) IN ('.implode(',', array_fill(0, count($skuLowerMap), '?')).')',
            array_keys($skuLowerMap)
        )->get();

        $productMasters = [];
        foreach ($raw as $pm) {
            $pmSkuLower = strtolower($pm->sku);
            if (isset($skuLowerMap[$pmSkuLower])) {
                $productMasters[$skuLowerMap[$pmSkuLower]] = $pm;
            }
        }

        return $productMasters;
    }

    /**
     * L60 from ebay_orders / ebay2_orders (period = l60 after fetch command).
     *
     * @return array{sales: float, orders: int, total_profit: float, total_cogs: float}
     */
    public static function summarizeL60Orders(int $which): array
    {
        if ($which === 1) {
            $query = EbayOrder::with('items')->where('period', 'l60');
            $shipKey = 'ship';
        } else {
            $query = Ebay2Order::with('items')->where('period', 'l60');
            $shipKey = 'ebay2_ship';
        }

        $orders = self::applyActiveOrderFilter($query)->get();
        $percentageDecimal = self::percentageDecimal($which);

        $productMasters = $which === 2
            ? self::productMastersForEbay2Skus($orders)
            : ProductMaster::all()->keyBy(fn ($item) => strtoupper($item->sku));

        $ordersCount = 0;
        $sales = 0.0;
        $totalProfit = 0.0;
        $totalCogs = 0.0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->sku || $item->sku === '') {
                    continue;
                }

                $ordersCount++;
                $quantity = (int) ($item->quantity ?? 1);
                $price = (float) ($item->price ?? 0);
                $sales += $price;

                $unitPrice = $quantity > 0 ? $price / $quantity : 0;
                $lookupSku = $which === 2
                    ? self::normalizeEbay2LookupSku($item->sku)
                    : $item->sku;
                $sku = strtoupper($lookupSku);
                $lp = 0.0;
                $ship = 0.0;

                $pm = $which === 2 ? ($productMasters[$lookupSku] ?? null) : ($productMasters[$sku] ?? null);

                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                    if ($lp === 0.0 && isset($pm->lp)) {
                        $lp = (float) $pm->lp;
                    }

                    if ($which === 2) {
                        $ship = isset($values[$shipKey]) && $values[$shipKey] !== null
                            ? (float) $values[$shipKey]
                            : (isset($values['ship']) ? (float) $values['ship'] : 0.0);
                    } else {
                        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                    }
                }

                $totalCogs += $lp * $quantity;
                $shipCost = $ship;
                $pftEach = ($unitPrice * $percentageDecimal) - $lp - $shipCost;
                $totalProfit += $pftEach * $quantity;
            }
        }

        return [
            'sales' => $sales,
            'orders' => $ordersCount,
            'total_profit' => $totalProfit,
            'total_cogs' => $totalCogs,
        ];
    }

    /**
     * eBay 3 L60 — period = l60 from ebay3:daily --days=60 (same revenue rules as calculateEbay3Metrics).
     *
     * @return array{sales: float, orders: int, total_profit: float, total_cogs: float}
     */
    public static function summarizeEbay3L60(): array
    {
        $percentageDecimal = self::percentageDecimal(3);

        $rows = self::applyActiveEbay3OrderFilter(
            DB::table('ebay3_daily_data')
                ->where('period', 'l60')
                ->where('quantity', '>', 0)
        )->get();

        $productMasters = ProductMaster::all()->keyBy(fn ($item) => strtoupper($item->sku));

        $uniqueOrders = [];
        $ordersCount = 0;
        $sales = 0.0;
        $totalProfit = 0.0;
        $totalCogs = 0.0;

        foreach ($rows as $order) {
            if ($order->order_id && ! in_array($order->order_id, $uniqueOrders, true)) {
                $uniqueOrders[] = $order->order_id;
                $ordersCount++;
            }

            $sku = strtoupper($order->sku ?? '');
            if ($sku === '') {
                continue;
            }

            $quantity = (int) ($order->quantity ?? 1);
            $lineItemTotal = (float) ($order->unit_price ?? 0);
            $perUnitPrice = $quantity > 0 ? $lineItemTotal / $quantity : 0;

            $sales += $lineItemTotal;

            $lp = 0.0;
            $ship = 0.0;
            $weightAct = 0.0;
            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }

                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                if (isset($values['wt_act'])) {
                    $weightAct = (float) $values['wt_act'];
                }
            }

            $tWeight = $weightAct * $quantity;
            if ($quantity === 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $totalCogs += $lp * $quantity;
            $pftEach = ($perUnitPrice * $percentageDecimal) - $lp - $shipCost;
            $totalProfit += $pftEach * $quantity;
        }

        return [
            'sales' => $sales,
            'orders' => $ordersCount,
            'total_profit' => $totalProfit,
            'total_cogs' => $totalCogs,
        ];
    }

    /**
     * L30 line metrics for ebay3_daily_data (used by update-marketplace-daily-metrics).
     *
     * @return array{total_orders: int, total_quantity: int, total_revenue: float, total_cogs: float, total_pft: float, total_weighted_price: float, total_quantity_for_price: int}|null
     */
    public static function summarizeEbay3L30Lines(): ?array
    {
        $rows = self::applyActiveEbay3OrderFilter(
            DB::table('ebay3_daily_data')->where('period', 'l30')
        )->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $percentageDecimal = self::percentageDecimal(3);
        $productMasters = ProductMaster::all()->keyBy(fn ($item) => strtoupper($item->sku));

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;

        foreach ($rows as $order) {
            $sku = strtoupper($order->sku ?? '');
            if ($sku === '') {
                continue;
            }

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $lineItemTotal = (float) ($order->unit_price ?? 0);
            $perUnitPrice = $quantity > 0 ? $lineItemTotal / $quantity : 0;

            $totalQuantity += $quantity;
            $totalRevenue += $lineItemTotal;

            if ($quantity > 0 && $perUnitPrice > 0) {
                $totalWeightedPrice += $perUnitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $lp = 0.0;
            $ship = 0.0;
            $weightAct = 0.0;
            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }

                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                if (isset($values['wt_act'])) {
                    $weightAct = (float) $values['wt_act'];
                }
            }

            $tWeight = $weightAct * $quantity;
            if ($quantity === 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $totalCogs += $lp * $quantity;
            $pftEach = ($perUnitPrice * $percentageDecimal) - $lp - $shipCost;
            $totalPft += $pftEach * $quantity;
        }

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'total_weighted_price' => $totalWeightedPrice,
            'total_quantity_for_price' => $totalQuantityForPrice,
        ];
    }

    /**
     * Live L30 + L60 summary for all-marketplace-master fast overlay.
     *
     * @return array{l30_sales: float, l30_orders: int, qty: int, l60_sales: float, l60_orders: int, l60_profit: float, l60_cogs: float}|null
     */
    public static function liveChannelSummary(int $which): ?array
    {
        $metricsChannel = match ($which) {
            1 => 'eBay',
            2 => 'eBay 2',
            default => 'eBay 3',
        };

        $metrics = self::latestDailyMetrics($metricsChannel);
        if (! $metrics) {
            return null;
        }

        $l60 = $which === 3 ? self::summarizeEbay3L60() : self::summarizeL60Orders($which);

        return [
            'l30_sales' => (float) ($metrics->total_sales ?? 0),
            'l30_orders' => (int) ($metrics->total_orders ?? 0),
            'qty' => (int) ($metrics->total_quantity ?? 0),
            'l60_sales' => (float) $l60['sales'],
            'l60_orders' => (int) $l60['orders'],
            'l60_profit' => (float) $l60['total_profit'],
            'l60_cogs' => (float) $l60['total_cogs'],
        ];
    }
}
