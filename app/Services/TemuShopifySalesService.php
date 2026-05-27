<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Temu L30/L60 sales from apicentral.shopify_order_items — same identification as /shopify-orders.
 * Numeric source_name rows are tagged "Temu" (or "Temu Orders", etc.); direct source_name "TEMU" also matches.
 */
class TemuShopifySalesService
{
    public const PST = 'America/Los_Angeles';

    /** Apply Temu identification (mirrors ShopifyOrdersController tag / source mapping). */
    public static function applyIdentification(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereRaw('LOWER(source_name) LIKE ?', ['%temu%'])
                ->orWhere('tags', 'LIKE', '%Temu%');
        });
    }

    /** Same rolling window as /shopify-orders getData (last 30 days from PST). */
    public static function shopifyOrdersL30Start(): Carbon
    {
        return Carbon::now(self::PST)->subDays(30)->startOfDay();
    }

    /** L30 window for tabulator + all-marketplace-master Temu row (matches /shopify-orders). */
    public static function tabulatorL30Window(): array
    {
        return [
            self::shopifyOrdersL30Start(),
            Carbon::now(self::PST)->endOfDay(),
        ];
    }

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

    /** FB Prc: +$2.99 per unit when line total (base × qty) is under $27. */
    public static function computeFbPrice(float $basePrice, int $quantity): float
    {
        if ($quantity <= 0 || $basePrice <= 0) {
            return 0.0;
        }

        return ($basePrice * $quantity) < 27 ? $basePrice + 2.99 : $basePrice;
    }

    /** Line revenue using FB Prc. */
    public static function lineSales(float $basePrice, int $quantity): float
    {
        $fbPrice = self::computeFbPrice($basePrice, $quantity);

        return $fbPrice > 0 ? $fbPrice * $quantity : 0.0;
    }

    /**
     * @return array{sales: float, orders: int, qty: int, pft: float, cogs: float}
     */
    public static function computeMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $rows = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereBetween('order_date', [$startDate, $endDate])
            ->get(['order_number', 'sku', 'quantity', 'price']);

        if ($rows->isEmpty()) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $margin = self::temuMarginDecimal();
        $productMasters = self::productMastersForSkus($rows->pluck('sku'));

        $totalSales = 0.0;
        $totalQty = 0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $orderSet = [];

        foreach ($rows as $r) {
            $price = (float) ($r->price ?? 0);
            $quantity = (int) ($r->quantity ?? 0);
            if ($quantity <= 0 || $price <= 0) {
                continue;
            }

            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $r->sku);

            $fbPrice = self::computeFbPrice($price, $quantity);
            $lineSales = self::lineSales($price, $quantity);
            $totalSales += $lineSales;
            $totalQty += $quantity;
            $totalCogs += $lp * $quantity;
            $pftDecimal = $fbPrice > 0 ? (($fbPrice * $margin) - $lp - $temuShip) / $fbPrice : 0;
            $totalPft += $pftDecimal * $fbPrice * $quantity;

            if (! empty($r->order_number)) {
                $orderSet[$r->order_number] = true;
            }
        }

        return [
            'sales' => round($totalSales, 2),
            'orders' => count($orderSet),
            'qty' => $totalQty,
            'pft' => round($totalPft, 2),
            'cogs' => round($totalCogs, 2),
        ];
    }

    /**
     * Row shape for /temu-tabulator (compatible with existing column fields).
     */
    public static function getDailyDataRows(Carbon $startDate, Carbon $endDate): array
    {
        $rows = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $margin = self::temuMarginDecimal();
        $productMasters = self::productMastersForSkus($rows->pluck('sku'));
        $result = [];

        foreach ($rows as $item) {
            $sku = $item->sku ?? '';
            $pm = ($sku !== '' && isset($productMasters[$sku])) ? $productMasters[$sku] : null;
            [$lp, $temuShip] = self::lpAndTemuShip($productMasters, $sku);
            $parent = $pm ? ($pm->parent ?? '') : '';

            $price = (float) ($item->price ?? 0);
            $quantity = (int) ($item->quantity ?? 0);
            $fbPrice = self::computeFbPrice($price, $quantity);
            $pftDecimal = $fbPrice > 0 ? (($fbPrice * $margin) - $lp - $temuShip) / $fbPrice : 0;
            $pft = $pftDecimal * $fbPrice * $quantity;

            $result[] = [
                'Parent' => $parent,
                'contribution_sku' => $sku,
                'order_id' => $item->order_number ?? '',
                'product_name_by_customer_order' => $item->product_title ?? '',
                'variation' => '',
                'quantity_purchased' => $quantity,
                'quantity_shipped' => 0,
                'quantity_to_ship' => 0,
                'base_price_total' => round($price, 2),
                'fb_price' => round($fbPrice, 2),
                'lp' => $lp,
                'temu_ship' => $temuShip,
                'pft' => round($pft, 2),
                'order_status' => $item->financial_status ?: ($item->fulfillment_status ?? ''),
                'fulfillment_mode' => '',
                'tracking_number' => $item->tracking_number ?? '',
                'carrier' => $item->tracking_company ?? '',
                'created_at' => $item->order_date
                    ? Carbon::parse($item->order_date)->format('Y-m-d H:i:s')
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Y Sales: revenue on the Pacific calendar day before the latest Temu shopify order_date.
     */
    public static function computeYSales(): ?float
    {
        $latestRaw = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereNotNull('order_date')
            ->max('order_date');

        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone(self::PST);
        $yStart = $latestPacific->copy()->subDay()->startOfDay();
        $yEnd = $latestPacific->copy()->subDay()->endOfDay();

        $rows = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereBetween('order_date', [$yStart, $yEnd])
            ->where('quantity', '>', 0)
            ->get(['price', 'quantity']);

        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += self::lineSales((float) ($r->price ?? 0), (int) ($r->quantity ?? 0));
        }

        return round($sum, 2);
    }

    /**
     * L7 Sales: seven Pacific days ending on Y-Sales "yesterday".
     */
    public static function computeL7Sales(): ?float
    {
        $latestRaw = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereNotNull('order_date')
            ->max('order_date');

        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone(self::PST);
        $end = $latestPacific->copy()->subDay()->endOfDay();
        $start = $latestPacific->copy()->subDay()->subDays(6)->startOfDay();

        $rows = self::applyIdentification(
            DB::connection('apicentral')->table('shopify_order_items')
        )
            ->whereBetween('order_date', [$start, $end])
            ->where('quantity', '>', 0)
            ->get(['price', 'quantity']);

        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += self::lineSales((float) ($r->price ?? 0), (int) ($r->quantity ?? 0));
        }

        return round($sum, 2);
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
