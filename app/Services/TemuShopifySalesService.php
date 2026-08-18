<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\TemuMetric;
use App\Models\TemuOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Temu L30/L60/L7/Y sales sourced from the `temu_orders` table (Temu API order-wise data,
 * populated by `app:fetch-temu-orders`). Line price comes from temu_metrics (base_price);
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

    /**
     * Take-home decimal from marketplace_percentages (percentage ÷ 100).
     * Tries marketplace name aliases in order. No hardcoded Temu/Temu2 defaults.
     */
    public static function marginDecimalFromMarketplace(string ...$marketplaceNames): float
    {
        foreach ($marketplaceNames as $name) {
            $mp = MarketplacePercentage::where('marketplace', $name)->first();
            if ($mp !== null && $mp->percentage !== null && (float) $mp->percentage > 0) {
                return (float) $mp->percentage / 100;
            }
        }

        throw new \RuntimeException(
            'marketplace_percentages missing for: ' . implode(', ', $marketplaceNames)
        );
    }

    public static function temuMarginDecimal(): float
    {
        return self::marginDecimalFromMarketplace('Temu');
    }

    /** Take-home decimal from marketplace_percentages for Temu 2. */
    public static function temu2MarginDecimal(): float
    {
        return self::marginDecimalFromMarketplace('Temu 2', 'TemuTwo', 'Temu2');
    }

    /** Full Temu Price multiplier — inverse of S Recovery 0.88. */
    public const FULL_PRICE_MULT = 1.1364;

    /** Displayed take-home on /temu-decrease GROI / SGROI (R Price × 0.95). */
    public const DECREASE_TAKEHOME = 0.95;

    /** Displayed Ads% on /temu-decrease (TEMU_FIXED_ADS_PERCENT). */
    public const DECREASE_ADS_PERCENT = 2.2;

    /**
     * Full Temu Price (listing / Sales / GPFT):
     *   (base × 1.1364); if that result ≤ $26.99 then +$2.99.
     * Not the same as Temu R Price (base + $2.99 when base ≤ $26.99).
     */
    public static function computeFullTemuPrice(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        $full = $basePrice * self::FULL_PRICE_MULT;
        if ($full <= 26.99) {
            $full += 2.99;
        }

        return $full;
    }

    /**
     * Temu R Price: base, then +$2.99 if base ≤ $26.99.
     * Same as /temu-decrease temuRPriceFromBase.
     */
    public static function computeRPrice(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        return round($basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice, 2);
    }

    /**
     * S R Price — R-price equivalent of SPRICE (same as /temu-decrease temuSRPriceFromRow).
     * If SPRICE matches listing Full / R Price, reuse listing R Price; otherwise invert SPRICE as Full Price.
     */
    public static function computeSRPrice(float $sprice, float $rPrice = 0.0, float $fullPrice = 0.0): float
    {
        if ($sprice <= 0) {
            return 0.0;
        }
        if ($fullPrice > 0 && abs($sprice - $fullPrice) < 0.02) {
            return $rPrice > 0 ? $rPrice : 0.0;
        }
        if ($rPrice > 0 && abs($sprice - $rPrice) < 0.02) {
            return $rPrice;
        }

        return self::computeRPrice(self::computeBaseFromFullTemuPrice($sprice));
    }

    /**
     * Invert Full Temu Price back to listing base (S Temu B Prc / push base).
     */
    public static function computeBaseFromFullTemuPrice(float $fullPrice): float
    {
        if ($fullPrice <= 0) {
            return 0.0;
        }

        $candidates = [($fullPrice - 2.99) / self::FULL_PRICE_MULT, $fullPrice / self::FULL_PRICE_MULT];
        $best = 0.0;
        $bestErr = INF;
        foreach ($candidates as $base) {
            if ($base <= 0) {
                continue;
            }
            $err = abs(self::computeFullTemuPrice($base) - $fullPrice);
            if ($err < $bestErr - 1e-6) {
                $bestErr = $err;
                $best = $base;
            } elseif (abs($err - $bestErr) <= 1e-6 && $base > $best) {
                $best = $base;
            }
        }

        return $best;
    }

    /**
     * Listing base used for Temu Price / R Price / GPFT.
     * Prefer temu_metrics.base_price; if empty, use recommended_base_price.
     */
    public static function resolveListingBasePrice(mixed $basePrice, mixed $recommendedBasePrice = null): float
    {
        $base = (float) ($basePrice ?? 0);
        if ($base > 0) {
            return $base;
        }
        $rec = (float) ($recommendedBasePrice ?? 0);

        return $rec > 0 ? $rec : 0.0;
    }

    /** S Recovery rate — same as /temu-decrease (not used for push base). */
    public const S_RECOVERY_RATE = 0.88;

    public static function computeSRecovery(float $sprice): float
    {
        return $sprice > 0 ? $sprice * self::S_RECOVERY_RATE : 0.0;
    }

    /**
     * Push base from SPRICE — inverse of Temu Price (same as /temu-decrease S Temu B Prc).
     */
    public static function computePushBaseFromSprice(float $sprice): ?float
    {
        if ($sprice <= 0) {
            return null;
        }
        $base = self::computeBaseFromFullTemuPrice($sprice);

        return $base > 0 ? round($base, 2) : null;
    }

    /**
     * GROI on Temu R Price (no 0.88):
     *   Profit = (R Price × margin) − LP − ship
     *   GROI%  = Profit / LP × 100
     * R Price = Base, then +$2.99 if Base ≤ $26.99.
     */
    public static function computeGroiPercent(float $rPrice, float $margin, float $lp, float $ship): float
    {
        if ($rPrice <= 0 || $lp <= 0) {
            return 0.0;
        }

        return (self::computeGroiProfit($rPrice, $margin, $lp, $ship) / $lp) * 100;
    }

    /** Dollar GROI profit on Temu R Price (no 0.88). */
    public static function computeGroiProfit(float $rPrice, float $margin, float $lp, float $ship): float
    {
        if ($rPrice <= 0) {
            return 0.0;
        }

        return ($rPrice * $margin) - $lp - $ship;
    }

    /** GPFT% on Full Temu Price: (Full × margin − LP − ship) / Full × 100 */
    public static function computeGpftPercent(float $fullPrice, float $margin, float $lp, float $ship): float
    {
        if ($fullPrice <= 0) {
            return 0.0;
        }

        return (($fullPrice * $margin - $lp - $ship) / $fullPrice) * 100;
    }

    /**
     * SGPFT / SROI / SPFT / SNROI — same as /temu-decrease:
     *   SGPFT = (SPRICE × margin − LP − ship) / SPRICE
     *   SROI  = (SPRICE × 0.88 × margin − LP − ship) / LP
     *   SPFT  = SGPFT − Ads% (skip when Ads% = 100 or $skipAds)
     *   SNROI = SROI − Ads%
     *
     * @return array{sgpft: float, sroi: float, spft: float, snroi: float}
     */
    public static function suggestedPercents(
        float $sprice,
        float $margin,
        float $lp,
        float $ship,
        float $adsPercent = 0.0,
        bool $skipAds = false
    ): array {
        if ($sprice <= 0 || $margin <= 0) {
            return ['sgpft' => 0.0, 'sroi' => 0.0, 'spft' => 0.0, 'snroi' => 0.0];
        }
        $pftProfit = ($sprice * $margin) - $lp - $ship;
        $sProfit = ($sprice * self::S_RECOVERY_RATE * $margin) - $lp - $ship;
        $sgpft = ($pftProfit / $sprice) * 100;
        $sroi = $lp > 0 ? ($sProfit / $lp) * 100 : 0.0;
        $ads = $skipAds ? 0.0 : $adsPercent;
        $spft = ($ads == 100.0) ? $sgpft : ($sgpft - $ads);
        $snroi = ($ads == 100.0) ? $sroi : ($sroi - $ads);

        return [
            'sgpft' => round($sgpft, 2),
            'sroi' => round($sroi, 2),
            'spft' => round($spft, 2),
            'snroi' => round($snroi, 2),
        ];
    }

    
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

        // Y Sales = yesterday's reported base-price sales, matching Temu Seller Central's
        // "Base price sales" daily chart (e.g. Jul 12 = $1,814.97) and staying consistent
        // with L7/L30/L60 + the tabulator, which all use base_sales. Using the FB-adjusted
        // figure (base + $2.99/unit freight) here inflated Y Sales above what Temu reports.
        return (float) self::computeMetricsFromOrders($start, $end)['base_sales'];
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

   
    public static function getOrdersTableRows(Carbon $startDate, Carbon $endDate): array
    {
        // FetchTemuOrders stores parent_order_time in Pacific (Temu's reporting tz) and the
        // app timezone is America/Los_Angeles, so Pacific windows already match the stored
        // wall-clock. Align boundaries to the app tz anyway so this stays correct even if the
        // app timezone changes or older rows were written under a different tz.
        $appTz = config('app.timezone');
        $start = $startDate->copy()->setTimezone($appTz);
        $end = $endDate->copy()->setTimezone($appTz);

        $orders = TemuOrder::whereBetween('parent_order_time', [$start, $end])
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
        $priceBySku = ! empty($skuList) && Schema::hasTable('temu_metrics')
            ? TemuMetric::whereIn('sku', $skuList)->pluck('base_price', 'sku')
            : collect();

        $result = [];

        foreach ($orders as $o) {
            $sku = $o->ext_code ?? '';
            $pm = ($sku !== '' && isset($productMasters[$sku])) ? $productMasters[$sku] : null;
            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $sku);
            $parent = $pm ? ($pm->parent ?? '') : '';

            $quantity = (int) ($o->quantity ?? 0);

            // Prefer Temu's ACTUAL reported base amount (bg.order.amount.query, stored on
            // temu_orders.order_base_amount) over catalog price × qty — the same principle
            // as Amazon summing real per-order item price. order_base_amount is the line
            // total for this sub-order, so per-unit = amount / qty. Fall back to temu_pricing
            // when the amount hasn't been fetched yet, so nothing regresses (temu_metrics fallback).
            $price = (float) ($priceBySku[$sku] ?? 0);
            if ($o->order_base_amount !== null && $quantity > 0) {
                $price = ((float) $o->order_base_amount) / $quantity;
            }

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
