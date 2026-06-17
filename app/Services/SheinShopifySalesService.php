<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shein L30/L60 sales from apicentral.shopify_order_items — same identification as /shopify-orders (Sen Shp).
 */
class SheinShopifySalesService
{
    public const PST = 'America/Los_Angeles';

    /** Apply Shein identification (mirrors ShopifyOrdersController Sen Shp mapping). */
    public static function applyIdentification(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereRaw('LOWER(COALESCE(source_name, "")) LIKE ?', ['%shein%'])
                ->orWhereRaw('LOWER(COALESCE(tags, "")) LIKE ?', ['%shein%']);
        });
    }

    /** Same rolling window as /shopify-orders getData (last 30 days from PST). */
    public static function shopifyOrdersL30Start(): Carbon
    {
        return Carbon::now(self::PST)->subDays(30)->startOfDay();
    }

    /** L30 window for tabulator (matches /shopify-orders). */
    public static function tabulatorL30Window(): array
    {
        return [
            self::shopifyOrdersL30Start(),
            Carbon::now(self::PST)->endOfDay(),
        ];
    }

    /** Prior 30-day window (days 31–60) for L60 badge on tabulator. */
    public static function channelMasterL60Window(): array
    {
        $today = Carbon::now(self::PST);

        return [
            $today->copy()->subDays(59)->startOfDay(),
            $today->copy()->subDays(30)->endOfDay(),
        ];
    }

    public static function sheinMarginDecimal(): float
    {
        $mp = MarketplacePercentage::where('marketplace', 'Shein')->first();

        return $mp && $mp->percentage ? ((float) $mp->percentage / 100) : 1.0;
    }

    /**
     * Row shape for /shein-tabulator (compatible with shein_daily_data column fields).
     */
    public static function getDailyDataRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereBetween('order_date', [$startDate, $endDate])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $productMasters = self::productMastersForSkus($rows->pluck('sku'));
        $result = [];

        foreach ($rows as $item) {
            $sku = trim((string) ($item->sku ?? ''));
            $orderStatus = (string) ($item->financial_status ?: ($item->fulfillment_status ?? ''));
            if (self::isExcludedStatus($orderStatus)) {
                continue;
            }

            [$lp, $ship] = self::lpAndShip($productMasters, $sku);
            $price = (float) ($item->price ?? 0);
            $quantity = max(0, (int) ($item->quantity ?? 0));
            $lineRevenue = $price > 0 ? $price * $quantity : 0.0;

            $result[] = [
                'order_type' => '',
                'order_number' => (string) ($item->order_number ?? ''),
                'exchange_order' => '',
                'order_status' => $orderStatus,
                'shipment_mode' => '',
                'product_name' => (string) ($item->product_title ?? ''),
                'product_description' => '',
                'specification' => '',
                'seller_sku' => $sku,
                'shein_sku' => '',
                'skc' => '',
                'item_id' => '',
                'product_status' => '',
                'tracking_number' => (string) ($item->tracking_number ?? ''),
                'sellers_package' => '',
                'product_price' => round($price, 2),
                'coupon_discount' => 0,
                'store_campaign_discount' => 0,
                'commission' => 0,
                'estimated_merchandise_revenue' => round($lineRevenue, 2),
                'fulfillment_service_fee' => 0,
                'storage_fee' => 0,
                'consumption_tax' => 0,
                'province' => '',
                'city' => '',
                'quantity' => $quantity,
                'lp' => $lp,
                'ship' => $ship,
                'order_processed_on' => $item->order_date
                    ? Carbon::parse($item->order_date)->format('Y-m-d H:i:s')
                    : null,
                'collection_deadline' => null,
                'requested_shipping_time' => null,
                'delivery_deadline' => null,
                'delivery_time' => null,
            ];
        }

        return $result;
    }

    /**
     * @return array{total_sales: float, total_orders: int, total_quantity: int}
     */
    public static function computeL60Totals(Carbon $startDate, Carbon $endDate): array
    {
        $summary = self::computeChannelSummary($startDate, $endDate);

        return [
            'total_sales' => round($summary['total_sales'], 2),
            'total_orders' => $summary['total_orders'],
            'total_quantity' => $summary['total_quantity'],
        ];
    }

    /**
     * Full channel metrics for a date window — used by /all-marketplace-master
     * (getSheinChannelData) and app:update-marketplace-daily-metrics so both reflect the same
     * Shopify Sen Shp source as /shein-tabulator.
     *
     * @return array{total_orders: int, total_quantity: int, total_sales: float, total_cogs: float, total_pft: float, pft_percentage: float, roi_percentage: float, avg_price: float, total_commission: float}
     */
    public static function computeChannelSummary(Carbon $startDate, Carbon $endDate): array
    {
        $rows = self::getDailyDataRows($startDate, $endDate);
        $margin = self::sheinMarginDecimal();

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalSales = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;
        $totalCommission = 0.0;

        foreach ($rows as $row) {
            $orderNum = trim((string) ($row['order_number'] ?? ''));
            $sellerSku = trim((string) ($row['seller_sku'] ?? ''));
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }

            $quantity = max(0, (int) ($row['quantity'] ?? 0));
            $productPrice = (float) ($row['product_price'] ?? 0);
            $estRev = (float) ($row['estimated_merchandise_revenue'] ?? 0);
            $lineRevenue = $productPrice > 0 ? ($productPrice * $quantity) : ($estRev > 0 ? $estRev : 0.0);
            $unitPriceForPft = $productPrice > 0
                ? $productPrice
                : ($quantity > 0 && $estRev > 0 ? $estRev / $quantity : ($estRev > 0 ? $estRev : 0.0));

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalSales += $lineRevenue;
            $totalCommission += (float) ($row['commission'] ?? 0);

            if ($quantity > 0 && $unitPriceForPft > 0) {
                $totalWeightedPrice += $unitPriceForPft * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $lp = (float) ($row['lp'] ?? 0);
            $ship = (float) ($row['ship'] ?? 0);

            $totalCogs += $lp * $quantity;
            $totalPft += ($unitPriceForPft * $margin - $lp - $ship) * $quantity;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;
        $pftPercentage = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0.0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
            'total_commission' => $totalCommission,
        ];
    }

    private static function isExcludedStatus(?string $status): bool
    {
        $s = strtolower((string) $status);
        foreach (['refund', 'return', 'cancel', 'closed', 'exchange'] as $term) {
            if (str_contains($s, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function productMastersForSkus(Collection $skus): Collection
    {
        $list = $skus->filter()->unique()->values()->toArray();

        return ! empty($list)
            ? ProductMaster::whereIn('sku', $list)->get()->keyBy('sku')
            : collect();
    }

    /** @return array{0: float, 1: float} [lp, ship] */
    private static function lpAndShip(Collection $productMasters, string $sku): array
    {
        $lp = 0.0;
        $ship = 0.0;

        if ($sku === '' || ! isset($productMasters[$sku])) {
            return [$lp, $ship];
        }

        $pm = $productMasters[$sku];
        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

        if (is_array($values)) {
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
            if (isset($values['ship'])) {
                $ship = (float) $values['ship'];
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }
        if ($ship === 0.0 && isset($pm->ship)) {
            $ship = (float) $pm->ship;
        }

        return [$lp, $ship];
    }
}
