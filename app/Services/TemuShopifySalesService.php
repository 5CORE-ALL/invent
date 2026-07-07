<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\TemuOrder;
use App\Models\TemuPricing;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Temu L30/L60/L7/Y sales sourced from the `temu_orders` table (Temu API order-wise data,
 * populated by `app:fetch-temu-orders`). Line price comes from temu_pricing (base_price);
 * LP / Temu ship from product_master. (No longer reads apicentral.shopify_order_items.)
 */
class TemuShopifySalesService
{
    public const PST = 'America/Los_Angeles';

    /** L30 window aligned with Faire / Purchasing Power on all-marketplace-master (30 inclusive days). */
    public static function channelMasterL30Window(): array
    {
        $today = Carbon::now(self::PST);

        return [
            $today->copy()->subDays(29)->startOfDay(),
            $today->copy()->endOfDay(),
        ];
    }

    /** Prior 30-day window (days 31–60) for L60 on all-marketplace-master. */
    public static function channelMasterL60Window(): array
    {
        $today = Carbon::now(self::PST);

        return [
            $today->copy()->subDays(59)->startOfDay(),
            $today->copy()->subDays(30)->endOfDay(),
        ];
    }

    public static function temuMarginDecimal(): float
    {
        $mp = MarketplacePercentage::where('marketplace', 'Temu')->first();

        return $mp && $mp->percentage ? ((float) $mp->percentage / 100) : 0.96;
    }

    /**
     * FB Prc: +$2.99 per unit when the per-unit base price is ≤ $26.99.
     *
     * Gating intentionally matches the /temu-decrease page so GPFT% / GROI%
     * stay consistent between the order-wise tabulator and the per-SKU
     * pricing view. Previously this was gated on the line total (base × qty
     * < 27), which produced different FB prices for the same SKU depending
     * on order quantity and made the two pages disagree.
     */
    public static function computeFbPrice(float $basePrice, int $quantity): float
    {
        if ($quantity <= 0 || $basePrice <= 0) {
            return 0.0;
        }

        return $basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice;
    }

    /** Line revenue using FB Prc. */
    public static function lineSales(float $basePrice, int $quantity): float
    {
        $fbPrice = self::computeFbPrice($basePrice, $quantity);

        return $fbPrice > 0 ? $fbPrice * $quantity : 0.0;
    }

    /**
     * Sales/orders/qty/pft/cogs from the temu_orders table (Temu API order-wise data).
     *
     * @return array{sales: float, orders: int, qty: int, pft: float, cogs: float}
     */
    public static function computeMetricsFromOrders(Carbon $startDate, Carbon $endDate): array
    {
        $rows = self::getOrdersTableRows($startDate, $endDate);

        if (empty($rows)) {
            return ['sales' => 0.0, 'base_sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $totalSales = 0.0;
        $totalBaseSales = 0.0;
        $totalQty = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $orderSet = [];

        foreach ($rows as $r) {
            $qty = (int) ($r['quantity_purchased'] ?? 0);
            $base = (float) ($r['base_price_total'] ?? 0);
            if ($qty <= 0 || $base <= 0) {
                continue;
            }

            $fbPrice = self::computeFbPrice($base, $qty);
            // `sales` keeps the FB-adjusted figure (base + $2.99/unit freight recovery) used
            // for margin math (GPFT%, ROI) so it stays consistent with /temu-decrease and the
            // /temu-tabulator profit columns. `base_sales` is the raw base price × qty, which
            // mirrors Temu Seller Central's "Base price sales" tile — use it for reported sales.
            $totalSales += $fbPrice * $qty;
            $totalBaseSales += $base * $qty;
            $totalQty += $qty;
            $totalCogs += ((float) ($r['lp'] ?? 0)) * $qty;
            $totalPft += (float) ($r['pft'] ?? 0);

            $orderId = trim((string) ($r['order_id'] ?? ''));
            if ($orderId !== '') {
                $orderSet[$orderId] = true;
            }
        }

        return [
            'sales' => round($totalSales, 2),
            'base_sales' => round($totalBaseSales, 2),
            'orders' => count($orderSet),
            'qty' => $totalQty,
            'pft' => round($totalPft, 2),
            'cogs' => round($totalCogs, 2),
        ];
    }

    /** Y Sales from temu_orders: base-price revenue on *yesterday* (wall-clock Pacific). */
    public static function computeYSalesFromOrders(): ?float
    {
        if (! TemuOrder::whereNotNull('parent_order_time')->exists()) {
            return null;
        }

        // Anchor to wall-clock *yesterday* Pacific (same as Amazon Y Sales), NOT (latest
        // order − 1). With temu_orders sync lag the latest-order anchor slipped Y Sales back
        // a day or two (e.g. showing Jul 04 instead of Jul 05), which also poisoned the saved
        // daily snapshots the Y Sales trend graph reads.
        $yesterday = Carbon::now(self::PST)->subDay();
        $start = $yesterday->copy()->startOfDay();
        $end = $yesterday->copy()->endOfDay();

        // Y Sales uses the FB-adjusted price (base + $2.99/unit freight) to match Temu's
        // daily sales tile, which is freight-inclusive. (L7/L30 use base_sales to match
        // Temu's "Base price sales" 7-/30-day tiles.)
        return (float) self::computeMetricsFromOrders($start, $end)['sales'];
    }

    /** L7 Sales from temu_orders: seven wall-clock Pacific days ending yesterday. */
    public static function computeL7SalesFromOrders(): ?float
    {
        if (! TemuOrder::whereNotNull('parent_order_time')->exists()) {
            return null;
        }

        // Wall-clock Pacific window ending yesterday (matches Y Sales anchoring above).
        $latestPacific = Carbon::now(self::PST);
        $end = $latestPacific->copy()->subDay()->endOfDay();
        $start = $latestPacific->copy()->subDay()->subDays(6)->startOfDay();

        // Reported base-price sales (matches Temu Seller Central + /temu-tabulator revenue).
        return (float) self::computeMetricsFromOrders($start, $end)['base_sales'];
    }

    /**
     * Row shape for /temu-tabulator sourced from the temu_orders table (Temu API order-wise data).
     * Same output fields as getDailyDataRows so the existing page/columns work unchanged.
     * Unit price comes from temu_pricing (base_price); LP / Temu ship from product_master.
     */
    public static function getOrdersTableRows(Carbon $startDate, Carbon $endDate): array
    {
        $orders = TemuOrder::whereBetween('parent_order_time', [$startDate, $endDate])
            ->orderBy('parent_order_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $margin = self::temuMarginDecimal();
        $skus = $orders->pluck('ext_code');
        $productMasters = self::productMastersForSkus($skus);

        $skuList = $skus->filter()->unique()->values()->toArray();
        $priceBySku = ! empty($skuList)
            ? TemuPricing::whereIn('sku', $skuList)->pluck('base_price', 'sku')
            : collect();

        $result = [];

        foreach ($orders as $o) {
            $sku = $o->ext_code ?? '';
            $pm = ($sku !== '' && isset($productMasters[$sku])) ? $productMasters[$sku] : null;
            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $sku);
            $parent = $pm ? ($pm->parent ?? '') : '';

            $price = (float) ($priceBySku[$sku] ?? 0);
            $quantity = (int) ($o->quantity ?? 0);
            $fbPrice = self::computeFbPrice($price, $quantity);
            $pftDecimal = $fbPrice > 0 ? (($fbPrice * $margin) - $lp - $temuShip) / $fbPrice : 0;
            $pft = $pftDecimal * $fbPrice * $quantity;

            $result[] = [
                'Parent' => $parent,
                'contribution_sku' => $sku,
                'order_id' => $o->parent_order_sn ?: ($o->order_sn ?? ''),
                'product_name_by_customer_order' => $o->goods_name ?? '',
                'variation' => $o->spec ?? '',
                'quantity_purchased' => $quantity,
                'quantity_shipped' => 0,
                'quantity_to_ship' => 0,
                'base_price_total' => round($price, 2),
                'fb_price' => round($fbPrice, 2),
                'lp' => $lp,
                'temu_ship' => $temuShip,
                'pft' => round($pft, 2),
                'order_status' => $o->order_status_text ?? '',
                'fulfillment_mode' => $o->fulfillment_type ?? '',
                'tracking_number' => '',
                'carrier' => '',
                'created_at' => $o->parent_order_time
                    ? $o->parent_order_time->format('Y-m-d H:i:s')
                    : null,
            ];
        }

        return $result;
    }

    private static function productMastersForSkus(Collection $skus): Collection
    {
        $list = $skus->filter()->unique()->values()->toArray();

        return ! empty($list)
            ? ProductMaster::whereIn('sku', $list)->get()->keyBy('sku')
            : collect();
    }

    /** @return array{0: float, 1: float} [lp, temu_ship] */
    private static function lpAndTemuShip(Collection $productMasters, ?string $sku): array
    {
        $lp = 0.0;
        $temuShip = 0.0;

        if ($sku === null || $sku === '' || ! isset($productMasters[$sku])) {
            return [$lp, $temuShip];
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
            if (isset($values['temu_ship'])) {
                $temuShip = (float) $values['temu_ship'];
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }
        if ($temuShip === 0.0 && isset($pm->temu_ship)) {
            $temuShip = (float) $pm->temu_ship;
        }

        return [$lp, $temuShip];
    }
}
