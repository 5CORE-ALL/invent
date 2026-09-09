<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\Temu2Metric;
use App\Models\Temu2Order;
use App\Models\Temu2Pricing;
use App\Models\Temu3DailyData;
use App\Models\Temu3DailyDataL60;
use App\Models\Temu3Order;
use App\Models\Temu3Pricing;
use App\Models\TemuMetric;
use App\Models\TemuOrder;
use App\Services\MarketplaceManager\TemuOrderAmountParser;
use App\Support\Marketplace\Temu3OrderSheet;
use App\Support\ProductMasterTemuShip;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Temu L30/L60/L7/Y sales from `temu_orders` (app:fetch-temu-orders).
 * Temu 2 L30/L60/L7/Y sales from `temu2_orders` (app:fetch-temu2-orders) — not sheet uploads, not Shopify.
 * Temu 3 L30/L60/L7 sales from uploaded Seller Center export (`temu3_orders.purchase_date`).
 * Line price from temu_metrics / temu2_metrics / temu2_pricing / goods base price; LP / ship from product_master.
 */
class TemuShopifySalesService
{
    public const PST = 'America/Los_Angeles';

    /** L30: 30 complete Pacific days ending yesterday (no partial today). */
    public static function channelMasterL30Window(): array
    {
        $end = Carbon::yesterday(self::PST);

        return [
            $end->copy()->subDays(29)->startOfDay(),
            $end->copy()->endOfDay(),
        ];
    }

    /** Prior 30 complete Pacific days (days 31–60 before tomorrow). */
    public static function channelMasterL60Window(): array
    {
        $end = Carbon::yesterday(self::PST);

        return [
            $end->copy()->subDays(59)->startOfDay(),
            $end->copy()->subDays(30)->endOfDay(),
        ];
    }

    /** L7: 7 complete Pacific days ending yesterday (no partial today). */
    public static function channelMasterL7Window(): array
    {
        $end = Carbon::yesterday(self::PST);

        return [
            $end->copy()->subDays(6)->startOfDay(),
            $end->copy()->endOfDay(),
        ];
    }

    /**
     * Temu 3 sheet L30 includes today. Seller Center exports already contain
     * today's orders; excluding yesterday-only (Temu 1/2 API) hides every just-uploaded row.
     */
    public static function temu3SheetL30Window(): array
    {
        $end = Carbon::now(self::PST)->endOfDay();

        return [
            $end->copy()->subDays(29)->startOfDay(),
            $end,
        ];
    }

    /** 30 Pacific days immediately before the Temu 3 sheet L30 window. */
    public static function temu3SheetL60Window(): array
    {
        $end = Carbon::now(self::PST);

        return [
            $end->copy()->subDays(59)->startOfDay(),
            $end->copy()->subDays(30)->endOfDay(),
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

    /**
     * Strip freight from API/stored unit — same as /temu-tabulator Base Price.
     * Base = unit − $2.99 when unit < $26.99.
     */
    public static function goodsBaseFromUnit(float $unit): float
    {
        if ($unit <= 0) {
            return 0.0;
        }
        if ($unit < 26.99) {
            return max(0.0, round($unit - 2.99, 2));
        }

        return round($unit, 2);
    }

    /**
     * Temu Price sales + profit for one line — same as /temu-tabulator GPFT / GROI.
     * Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99.
     *
     * @return array{base: float, temu_price: float, sales: float, profit: float}
     */
    public static function temuPriceSalesAndProfit(float $rawUnit, int $qty, float $margin, float $lp, float $ship, bool $stripFreightFromUnit = true): array
    {
        $base = $stripFreightFromUnit
            ? self::goodsBaseFromUnit($rawUnit)
            : ($rawUnit > 0 ? round($rawUnit, 2) : 0.0);
        $temuPrice = self::computeFullTemuPrice($base);
        $sales = $temuPrice * $qty;
        $profit = $temuPrice > 0 ? ($temuPrice * $margin - $lp - $ship) * $qty : 0.0;

        return [
            'base' => $base,
            'temu_price' => $temuPrice,
            'sales' => $sales,
            'profit' => $profit,
        ];
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
    public static function computeMetricsFromOrders(Carbon $startDate, Carbon $endDate, bool $isTemu2 = false): array
    {
        $rows = $isTemu2
            ? self::getTemu2OrdersTableRows($startDate, $endDate)
            : self::getOrdersTableRows($startDate, $endDate);

        if (empty($rows)) {
            return ['sales' => 0.0, 'base_sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'gpft' => 0.0, 'cogs' => 0.0];
        }

        $margin = self::temuMarginDecimal();
        $totalSales = 0.0;
        $totalBaseSales = 0.0;
        $totalQty = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $orderSet = [];

        foreach ($rows as $r) {
            $qty = (int) ($r['quantity_purchased'] ?? 0);
            $base = (float) ($r['base_price_total'] ?? 0);
            $lineSales = (float) ($r['line_sales'] ?? 0);
            $rawUnit = ($lineSales > 0 && $qty > 0) ? ($lineSales / $qty) : $base;
            if ($qty <= 0 || $rawUnit <= 0) {
                continue;
            }

            $lp = (float) ($r['lp'] ?? 0);
            $ship = (float) ($r['temu_ship'] ?? 0);
            $calc = self::temuPriceSalesAndProfit($rawUnit, $qty, $margin, $lp, $ship, ! $isTemu2);

            // L30 Sales / GPFT / GROI on Temu Price (same as /temu-tabulator).
            $totalSales += $calc['sales'];
            $totalPft += $calc['profit'];
            $totalCogs += $lp * $qty;
            $totalQty += $qty;

            // Keep API line sales for Seller Central Y Sales (base + freight).
            $totalBaseSales += $lineSales > 0 ? $lineSales : ($rawUnit * $qty);

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
            'gpft' => round($totalPft, 2),
            'cogs' => round($totalCogs, 2),
        ];
    }

    /**
     * Per Pacific-day Temu Price sales from temu2_orders (no −$2.99 on Base).
     *
     * @return array<string, array{sales: float, base_sales: float, qty: int, orders: int}>
     */
    public static function temu2DailySalesByDate(Carbon $startDate, Carbon $endDate): array
    {
        $out = [];
        foreach (self::getTemu2OrdersTableRows($startDate, $endDate) as $r) {
            $qty = (int) ($r['quantity_purchased'] ?? 0);
            $base = (float) ($r['base_price_total'] ?? 0);
            if ($qty <= 0 || $base <= 0) {
                continue;
            }
            $d = substr((string) ($r['created_at'] ?? ''), 0, 10);
            if ($d === '') {
                continue;
            }
            if (! isset($out[$d])) {
                $out[$d] = ['sales' => 0.0, 'base_sales' => 0.0, 'qty' => 0, 'oids' => []];
            }
            $lineSales = (float) ($r['line_sales'] ?? 0);
            $rawUnit = ($lineSales > 0 && $qty > 0) ? ($lineSales / $qty) : $base;
            $calc = self::temuPriceSalesAndProfit($rawUnit, $qty, self::temuMarginDecimal(), 0.0, 0.0, false);
            $out[$d]['sales'] += $calc['sales'];
            $out[$d]['base_sales'] += $calc['base'] * $qty;
            $out[$d]['qty'] += $qty;
            $oid = trim((string) ($r['order_id'] ?? ''));
            if ($oid !== '') {
                $out[$d]['oids'][$oid] = true;
            }
        }

        $flat = [];
        foreach ($out as $d => $row) {
            $flat[$d] = [
                'sales' => round((float) $row['sales'], 2),
                'base_sales' => round((float) $row['base_sales'], 2),
                'qty' => (int) $row['qty'],
                'orders' => count($row['oids']),
            ];
        }
        ksort($flat);

        return $flat;
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

        // Y Sales = yesterday's Temu amount-API sales (base + freight), matching
        // Seller Central's daily sales bar (e.g. Sep 7 PT = $1,999.61).
        return (float) self::computeMetricsFromOrders($start, $end)['base_sales'];
    }

    /** Y Sales from temu2_orders: base-price revenue on yesterday (wall-clock Pacific). */
    public static function computeYSalesFromTemu2Orders(): ?float
    {
        if (! Schema::hasTable('temu2_orders') || ! Temu2Order::whereNotNull('parent_order_time')->exists()) {
            return null;
        }

        $yesterday = Carbon::now(self::PST)->subDay();

        return (float) self::computeMetricsFromOrders(
            $yesterday->copy()->startOfDay(),
            $yesterday->copy()->endOfDay(),
            true
        )['sales'];
    }

    /** L7 Sales from temu2_orders: seven wall-clock Pacific days ending yesterday. */
    public static function computeL7SalesFromTemu2Orders(): ?float
    {
        if (! Schema::hasTable('temu2_orders') || ! Temu2Order::whereNotNull('parent_order_time')->exists()) {
            return null;
        }

        [$start, $end] = self::channelMasterL7Window();

        return (float) self::computeMetricsFromOrders($start, $end, true)['sales'];
    }

    /**
     * Temu 3 sheet metrics — same Full Temu Price Sales / GPFT / GROI as /temu3-decrease.
     *
     * @return array{sales: float, base_sales: float, full_sales: float, orders: int, qty: int, pft: float, gpft: float, cogs: float}
     */
    public static function computeMetricsFromTemu3Orders(Carbon $startDate, Carbon $endDate): array
    {
        $empty = [
            'sales' => 0.0,
            'base_sales' => 0.0,
            'full_sales' => 0.0,
            'orders' => 0,
            'qty' => 0,
            'pft' => 0.0,
            'gpft' => 0.0,
            'cogs' => 0.0,
        ];
        if (! Schema::hasTable('temu3_orders')) {
            return $empty;
        }

        $rows = self::getTemu3OrdersTableRows($startDate, $endDate);
        if ($rows === []) {
            return $empty;
        }

        [$pmSet, $noSpaceToNormalized] = self::temu3ProductMasterSkuSets();

        $margin = self::temuMarginDecimal();
        $totalFull = 0.0;
        $totalBase = 0.0;
        $totalQty = 0;
        $totalGpft = 0.0;
        $totalGroiPft = 0.0;
        $totalCogs = 0.0;
        $orderSet = [];

        foreach ($rows as $r) {
            $sku = trim((string) ($r['contribution_sku'] ?? ''));
            $orderId = trim((string) ($r['order_id'] ?? ''));
            if ($sku === '' || $orderId === '') {
                continue;
            }
            $n = self::normalizeTemu3Sku($sku);
            $nNoSpace = str_replace(' ', '', $n);
            if (! isset($pmSet[$n]) && ! isset($noSpaceToNormalized[$nNoSpace])) {
                continue;
            }

            $qty = (int) ($r['quantity_purchased'] ?? 0);
            $base = (float) ($r['base_price_total'] ?? 0);
            if ($qty <= 0 || $base <= 0) {
                continue;
            }

            $lp = (float) ($r['lp'] ?? 0);
            $ship = (float) ($r['temu_ship'] ?? 0);
            $calc = self::temuPriceSalesAndProfit($base, $qty, $margin, $lp, $ship);

            $totalFull += $calc['sales'];
            $totalBase += $calc['base'] * $qty;
            $totalQty += $qty;
            $totalGpft += $calc['profit'];
            $totalGroiPft += $calc['profit'];
            $totalCogs += $lp * $qty;
            $orderSet[$orderId] = true;
        }

        return [
            'sales' => round($totalFull, 2),
            'base_sales' => round($totalBase, 2),
            'full_sales' => round($totalFull, 2),
            'orders' => count($orderSet),
            'qty' => $totalQty,
            'pft' => round($totalGroiPft, 2),
            'gpft' => round($totalGpft, 2),
            'cogs' => round($totalCogs, 2),
        ];
    }

    /**
     * Temu 3 Sales Data page metrics from temu3_daily_data / temu3_daily_data_l60.
     * Same Full Price / GPFT / GROI math as temu3_orders. No date filter = all uploaded rows
     * (matches /temu3-tabulator badges, same as Temu 2 tabulator → Active Channel).
     *
     * @return array{sales: float, base_sales: float, full_sales: float, orders: int, qty: int, pft: float, gpft: float, cogs: float}
     */
    public static function computeMetricsFromTemu3DailyData(string $table = 'temu3_daily_data', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $empty = [
            'sales' => 0.0,
            'base_sales' => 0.0,
            'full_sales' => 0.0,
            'orders' => 0,
            'qty' => 0,
            'pft' => 0.0,
            'gpft' => 0.0,
            'cogs' => 0.0,
        ];
        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $modelClass = $table === 'temu3_daily_data_l60' ? Temu3DailyDataL60::class : Temu3DailyData::class;
        $query = $modelClass::query();
        if ($startDate && $endDate) {
            $query->where('purchase_date', '>=', $startDate->copy()->startOfDay())
                ->where('purchase_date', '<=', $endDate->copy()->endOfDay());
        }
        $items = $query->get();
        if ($items->isEmpty()) {
            return $empty;
        }

        [$pmSet, $noSpaceToNormalized] = self::temu3ProductMasterSkuSets();
        $productMasters = self::productMastersForSkus($items->pluck('contribution_sku'));
        $margin = self::temuMarginDecimal();

        $totalFull = 0.0;
        $totalBase = 0.0;
        $totalQty = 0;
        $totalGpft = 0.0;
        $totalGroiPft = 0.0;
        $totalCogs = 0.0;
        $orderSet = [];

        foreach ($items as $item) {
            $sku = trim((string) ($item->contribution_sku ?? ''));
            $orderId = trim((string) ($item->order_id ?? ''));
            if ($sku === '' || $orderId === '') {
                continue;
            }
            $n = self::normalizeTemu3Sku($sku);
            $nNoSpace = str_replace(' ', '', $n);
            if (! isset($pmSet[$n]) && ! isset($noSpaceToNormalized[$nNoSpace])) {
                continue;
            }
            $qty = (int) ($item->quantity_purchased ?? 0);
            $base = (float) ($item->base_price_total ?? 0);
            if ($qty <= 0 || $base <= 0) {
                continue;
            }

            [$lp, $ship] = self::lpAndTemuShip($productMasters, $sku);
            $calc = self::temuPriceSalesAndProfit($base, $qty, $margin, $lp, $ship);

            $totalFull += $calc['sales'];
            $totalBase += $calc['base'] * $qty;
            $totalQty += $qty;
            $totalGpft += $calc['profit'];
            $totalGroiPft += $calc['profit'];
            $totalCogs += $lp * $qty;
            $orderSet[$orderId] = true;
        }

        return [
            'sales' => round($totalFull, 2),
            'base_sales' => round($totalBase, 2),
            'full_sales' => round($totalFull, 2),
            'orders' => count($orderSet),
            'qty' => $totalQty,
            'pft' => round($totalGroiPft, 2),
            'gpft' => round($totalGpft, 2),
            'cogs' => round($totalCogs, 2),
        ];
    }

    /**
     * Same L30 window and Full Temu Price sales as /temu3-decrease (`temu3_orders`).
     *
     * @return array{sales: float, base_sales: float, full_sales: float, orders: int, qty: int, pft: float, gpft: float, cogs: float}
     */
    public static function computeTemu3ActiveChannelL30(): array
    {
        [$start, $end] = self::temu3SheetL30Window();

        return self::computeMetricsFromTemu3Orders($start, $end);
    }

    /**
     * Same L60 window as /temu3-decrease (`temu3_orders`).
     *
     * @return array{sales: float, base_sales: float, full_sales: float, orders: int, qty: int, pft: float, gpft: float, cogs: float}
     */
    public static function computeTemu3ActiveChannelL60(): array
    {
        [$start, $end] = self::temu3SheetL60Window();

        return self::computeMetricsFromTemu3Orders($start, $end);
    }

    public static function computeYSalesFromTemu3DailyData(): ?float
    {
        if (! Schema::hasTable('temu3_daily_data')) {
            return null;
        }
        $latest = Temu3DailyData::whereNotNull('purchase_date')->max('purchase_date');
        if (! $latest) {
            return null;
        }
        $yesterday = Carbon::parse($latest)->subDay();
        $m = self::computeMetricsFromTemu3DailyData(
            'temu3_daily_data',
            $yesterday->copy()->startOfDay(),
            $yesterday->copy()->endOfDay()
        );

        return (float) ($m['base_sales'] ?? 0) > 0 ? (float) $m['base_sales'] : null;
    }

    public static function normalizeTemu3Sku(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
        $sku = preg_replace('/\s+/', ' ', $sku);

        return (string) $sku;
    }

    /**
     * Per Pacific-day Full Temu Price sales from temu3_orders (same SKU filter as L30).
     *
     * @return array<string, array{sales: float, base_sales: float, qty: int, orders: int}>
     */
    public static function temu3DailySalesByDate(Carbon $startDate, Carbon $endDate): array
    {
        $out = [];
        if (! Schema::hasTable('temu3_orders')) {
            return $out;
        }

        [$pmSet, $noSpaceToNormalized] = self::temu3ProductMasterSkuSets();
        foreach (self::getTemu3OrdersTableRows($startDate, $endDate) as $r) {
            $sku = trim((string) ($r['contribution_sku'] ?? ''));
            $qty = (int) ($r['quantity_purchased'] ?? 0);
            $base = (float) ($r['base_price_total'] ?? 0);
            if ($sku === '' || $qty <= 0 || $base <= 0) {
                continue;
            }
            $n = self::normalizeTemu3Sku($sku);
            $nNoSpace = str_replace(' ', '', $n);
            if (! isset($pmSet[$n]) && ! isset($noSpaceToNormalized[$nNoSpace])) {
                continue;
            }
            $d = substr((string) ($r['created_at'] ?? ''), 0, 10);
            if ($d === '') {
                continue;
            }
            if (! isset($out[$d])) {
                $out[$d] = ['sales' => 0.0, 'base_sales' => 0.0, 'qty' => 0, 'oids' => []];
            }
            $out[$d]['sales'] += self::computeFullTemuPrice($base) * $qty;
            $out[$d]['base_sales'] += $base * $qty;
            $out[$d]['qty'] += $qty;
            $oid = trim((string) ($r['order_id'] ?? ''));
            if ($oid !== '') {
                $out[$d]['oids'][$oid] = true;
            }
        }

        $flat = [];
        foreach ($out as $d => $row) {
            $flat[$d] = [
                'sales' => round((float) $row['sales'], 2),
                'base_sales' => round((float) $row['base_sales'], 2),
                'qty' => (int) $row['qty'],
                'orders' => count($row['oids']),
            ];
        }
        ksort($flat);

        return $flat;
    }

    /**
     * Rolling window series, one point per calendar day from $chartStart through $chartEnd.
     *
     * @param  array<string, array{sales?: float}|float>  $byDay
     * @return list<array{date: string, value: float}>
     */
    public static function rollingSalesSeries(array $byDay, Carbon $chartStart, Carbon $chartEnd, int $windowDays): array
    {
        $out = [];
        $cursor = $chartStart->copy()->startOfDay();
        $end = $chartEnd->copy()->startOfDay();
        $windowDays = max(1, $windowDays);
        while ($cursor->lte($end)) {
            $sum = 0.0;
            for ($i = 0; $i < $windowDays; $i++) {
                $d = $cursor->copy()->subDays($i)->toDateString();
                $cell = $byDay[$d] ?? 0;
                $sum += is_array($cell) ? (float) ($cell['sales'] ?? 0) : (float) $cell;
            }
            $out[] = [
                'date' => $cursor->format('M d'),
                'value' => round($sum, 2),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @return array{0: array<string, true>, 1: array<string, string>}
     */
    private static function temu3ProductMasterSkuSets(): array
    {
        $pmSet = [];
        $noSpaceToNormalized = [];
        foreach (ProductMaster::query()->whereNotNull('sku')->where('sku', '!=', '')->pluck('sku') as $sku) {
            if (stripos((string) $sku, 'PARENT') !== false) {
                continue;
            }
            $n = self::normalizeTemu3Sku((string) $sku);
            if ($n === '') {
                continue;
            }
            $pmSet[$n] = true;
            $ns = str_replace(' ', '', $n);
            if ($ns !== '') {
                $noSpaceToNormalized[$ns] = $n;
            }
        }

        return [$pmSet, $noSpaceToNormalized];
    }

    public static function computeYSalesFromTemu3Orders(): ?float
    {
        if (! Schema::hasTable('temu3_orders')) {
            return null;
        }

        $yesterday = Carbon::now(self::PST)->subDay();

        return (float) self::computeMetricsFromTemu3Orders(
            $yesterday->copy()->startOfDay(),
            $yesterday->copy()->endOfDay()
        )['sales'];
    }

    public static function computeL7SalesFromTemu3Orders(): ?float
    {
        if (! Schema::hasTable('temu3_orders')) {
            return null;
        }

        $latestPacific = Carbon::now(self::PST);
        $end = $latestPacific->copy()->subDay()->endOfDay();
        $start = $latestPacific->copy()->subDay()->subDays(6)->startOfDay();

        return (float) self::computeMetricsFromTemu3Orders($start, $end)['sales'];
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
        return self::getChannelOrdersTableRows($startDate, $endDate, false);
    }

    /** Same shape as getOrdersTableRows, from temu2_orders + temu2_metrics (not temu2_daily_data). */
    public static function getTemu2OrdersTableRows(Carbon $startDate, Carbon $endDate): array
    {
        return self::getChannelOrdersTableRows($startDate, $endDate, true);
    }

    /**
     * Same shape as getOrdersTableRows, from the Temu 3 Seller Center order sheet
     * (`temu3_orders`). SKU = contribution sku, unit price = goods base price.
     */
    public static function getTemu3OrdersTableRows(Carbon $startDate, Carbon $endDate): array
    {
        if (! Schema::hasTable('temu3_orders')) {
            return [];
        }

        $appTz = config('app.timezone');
        $start = $startDate->copy()->setTimezone($appTz);
        $end = $endDate->copy()->setTimezone($appTz);
        // Compare as wall-clock strings so timestamp TZ conversion cannot drop today's sheet rows.
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $orders = Temu3Order::whereBetween('purchase_date', [$startStr, $endStr])
            ->where(function ($q) {
                $q->whereNull('order_status')
                    ->orWhereRaw('UPPER(order_status) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
            })
            ->where(function ($q) {
                $q->whereNull('order_item_status')
                    ->orWhereRaw('UPPER(order_item_status) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
            })
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $margin = self::temuMarginDecimal();
        $skus = $orders->map(fn ($o) => trim((string) ($o->contribution_sku ?? '')));
        $productMasters = self::productMastersForSkus($skus);

        $skuList = $skus->filter()->unique()->values()->toArray();
        $priceBySku = collect();
        if (! empty($skuList) && Schema::hasTable('temu3_pricing')) {
            $pricingBySku = Temu3Pricing::whereIn('sku', $skuList)->pluck('base_price', 'sku');
            foreach ($pricingBySku as $skuKey => $unit) {
                if ((float) $unit > 0) {
                    $priceBySku[$skuKey] = $unit;
                }
            }
        }

        $result = [];

        foreach ($orders as $o) {
            $row = $o->toArray();
            if (Temu3OrderSheet::shouldExcludeFromSales($row)) {
                continue;
            }

            $sku = trim((string) ($o->contribution_sku ?? ''));
            $pm = ($sku !== '' && isset($productMasters[$sku])) ? $productMasters[$sku] : null;
            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $sku);
            $parent = $pm ? ($pm->parent ?? '') : '';

            $quantity = (int) ($o->quantity_purchased ?? 0);
            $price = (float) ($o->base_price_total ?? 0);
            if ($price <= 0) {
                $price = (float) ($priceBySku[$sku] ?? 0);
            }

            $fbPrice = self::computeFbPrice($price, $quantity);
            $pftDecimal = $fbPrice > 0 ? (($fbPrice * $margin) - $lp - $temuShip) / $fbPrice : 0;
            $pft = $pftDecimal * $fbPrice * $quantity;

            $result[] = [
                'Parent' => $parent,
                'contribution_sku' => $sku,
                'order_id' => $o->order_id ?? '',
                'product_name_by_customer_order' => $o->product_name_by_customer_order ?? ($o->product_name ?? ''),
                'variation' => $o->variation ?? '',
                'quantity_purchased' => $quantity,
                'quantity_shipped' => (int) ($o->quantity_shipped ?? 0),
                'quantity_to_ship' => (int) ($o->quantity_to_ship ?? 0),
                'base_price_total' => round($price, 2),
                'fb_price' => round($fbPrice, 2),
                'lp' => $lp,
                'temu_ship' => $temuShip,
                'pft' => round($pft, 2),
                'order_status' => $o->order_status ?? '',
                'fulfillment_mode' => $o->fulfillment_mode ?? '',
                'tracking_number' => $o->tracking_number ?? '',
                'carrier' => $o->carrier ?? '',
                'created_at' => $o->purchase_date
                    ? $o->purchase_date->format('Y-m-d H:i:s')
                    : null,
            ];
        }

        return $result;
    }

    private static function getChannelOrdersTableRows(Carbon $startDate, Carbon $endDate, bool $isTemu2): array
    {
        // FetchTemuOrders / FetchTemu2Orders store parent_order_time in Pacific (Temu's
        // reporting tz) and the app timezone is America/Los_Angeles, so Pacific windows
        // already match the stored wall-clock. Align boundaries to the app tz anyway so
        // this stays correct even if the app timezone changes or older rows were written
        // under a different tz.
        $appTz = config('app.timezone');
        $start = $startDate->copy()->setTimezone($appTz);
        $end = $endDate->copy()->setTimezone($appTz);

        if ($isTemu2 && ! Schema::hasTable('temu2_orders')) {
            return [];
        }

        $orderModel = $isTemu2 ? Temu2Order::class : TemuOrder::class;
        $orders = $orderModel::whereBetween('parent_order_time', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('order_status_text')
                    ->orWhereRaw('UPPER(order_status_text) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
            })
            ->orderBy('parent_order_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        // Same marketplace_percentages.Temu take-home as /temu-decrease and /temu2-decrease.
        $margin = self::temuMarginDecimal();
        $skus = $orders->map(function ($o) {
            $sku = trim((string) ($o->ext_code ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($o->display_sku ?? ''));
            }

            return $sku;
        });
        $productMasters = self::productMastersForSkus($skus);

        $skuList = $skus->filter()->unique()->values()->toArray();
        $metricsTable = $isTemu2 ? 'temu2_metrics' : 'temu_metrics';
        $metricsModel = $isTemu2 ? Temu2Metric::class : TemuMetric::class;
        $priceBySku = collect();
        if (! empty($skuList) && Schema::hasTable($metricsTable)) {
            $metricRows = $metricsModel::whereIn('sku', $skuList)->get();
            foreach ($metricRows as $metricRow) {
                $priceBySku[$metricRow->sku] = self::resolveListingBasePrice(
                    $metricRow->base_price ?? null,
                    $metricRow->recommended_base_price ?? null
                );
            }
        }
        if ($isTemu2 && ! empty($skuList) && Schema::hasTable('temu2_pricing')) {
            $pricingBySku = Temu2Pricing::whereIn('sku', $skuList)->pluck('base_price', 'sku');
            foreach ($pricingBySku as $skuKey => $unit) {
                if ((float) ($priceBySku[$skuKey] ?? 0) <= 0 && (float) $unit > 0) {
                    $priceBySku[$skuKey] = $unit;
                }
            }
        }

        $result = [];

        foreach ($orders as $o) {
            $sku = trim((string) ($o->ext_code ?? ''));
            if ($sku === '') {
                $sku = trim((string) ($o->display_sku ?? ''));
            }
            $pm = ($sku !== '' && isset($productMasters[$sku])) ? $productMasters[$sku] : null;
            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $sku);
            $parent = $pm ? ($pm->parent ?? '') : '';
            $pmValues = [];
            if ($pm) {
                $pmValues = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
            }

            $quantity = (int) ($o->quantity ?? 0);

            // Official line sales from bg.order.amount.query: basePrice + shipAmountTotal
            // (same total as parent estimatedRevenue). Fall back to stored base, then catalog.
            $price = (float) ($priceBySku[$sku] ?? 0);
            $lineSales = TemuOrderAmountParser::lineSalesAmount($o);
            $orderAmount = $lineSales ?? ((float) ($o->order_base_amount ?? 0) ?: 0.0);
            if ($orderAmount > 0 && $quantity > 0) {
                $price = $orderAmount / $quantity;
            }

            $hasApiSales = $lineSales !== null && $lineSales > 0;
            $officialUnit = $hasApiSales && $quantity > 0 ? $lineSales / $quantity : $price;
            $fbPrice = $hasApiSales ? $officialUnit : self::computeFbPrice($price, $quantity);
            $pftDecimal = $fbPrice > 0 ? (($fbPrice * $margin) - $lp - $temuShip) / $fbPrice : 0;
            $pft = $pftDecimal * $fbPrice * $quantity;

            $mapped = [
                'Parent' => $parent,
                'contribution_sku' => $sku,
                'order_id' => $o->parent_order_sn ?: ($o->order_sn ?? ''),
                'product_name_by_customer_order' => $o->goods_name ?? '',
                'variation' => $o->spec ?? '',
                'quantity_purchased' => $quantity,
                'quantity_shipped' => 0,
                'quantity_to_ship' => 0,
                'base_price_total' => round($officialUnit > 0 ? $officialUnit : $price, 2),
                'listing_base_price' => round((float) ($priceBySku[$sku] ?? 0), 2),
                'line_sales' => $hasApiSales ? round($lineSales, 2) : round(self::lineSales($price, $quantity), 2),
                'fb_price' => round($fbPrice, 2),
                'lp' => $lp,
                'temu_ship' => $temuShip,
                'handling_charge' => $pmValues['handling_charge'] ?? null,
                'o_size_charge' => $pmValues['o_size_charge'] ?? null,
                'pft' => round($pft, 2),
                'order_status' => $o->order_status_text ?? '',
                'fulfillment_mode' => $o->fulfillment_type ?? '',
                'tracking_number' => $o->tracking_number ?? '',
                'carrier' => $o->carrier ?? '',
                'created_at' => $o->parent_order_time
                    ? $o->parent_order_time->format('Y-m-d H:i:s')
                    : null,
            ];

            $result[] = $isTemu2
                ? array_merge(self::temu2RawOrderColumns($o), $mapped)
                : $mapped;
        }

        return $result;
    }

    /**
     * Scalar temu2_orders columns for the tabulator (JSON blobs omitted).
     * order_status is renamed so the mapped text status can keep that key.
     *
     * @return array<string, mixed>
     */
    private static function temu2RawOrderColumns(object $o): array
    {
        $skip = ['id', 'raw_json', 'amount_raw_json'];
        $out = [];
        foreach ($o->getAttributes() as $key => $unused) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $value = $o->{$key};
            if ($value instanceof \DateTimeInterface) {
                $out[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value) || is_object($value)) {
                continue;
            } else {
                $out[$key] = $value;
            }
        }
        if (array_key_exists('order_status', $out)) {
            $out['order_status_code'] = $out['order_status'];
            unset($out['order_status']);
        }

        return $out;
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
            $temuShip = ProductMasterTemuShip::forPricing($values, $pm);
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        return [$lp, $temuShip];
    }
}
