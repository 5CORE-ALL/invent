<?php

namespace App\Services;

use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\SheinDailyData;
use App\Models\SheinDailyDataL60;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shein L30/L60 metrics from uploaded Seller Hub order exports
 * (shein_daily_data / shein_daily_data_l60) — same CSV format as sheinorders.csv.
 */
class SheinShopifySalesService
{
    public const PST = 'America/Los_Angeles';

    /** L30 window helper kept for callers; uploaded exports are typically already L30. */
    public static function shopifyOrdersL30Start(): Carbon
    {
        return Carbon::now(self::PST)->subDay()->startOfDay()->subDays(29);
    }

    public static function tabulatorL30Window(): array
    {
        $end = Carbon::now(self::PST)->subDay()->endOfDay();

        return [
            $end->copy()->startOfDay()->subDays(29),
            $end,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: bool} */
    public static function effectiveTabulatorWindow(): array
    {
        [$start, $end] = self::tabulatorL30Window();

        return [$start, $end, false];
    }

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
     * Row shape for /shein-tabulator from uploaded shein_daily_data.
     * Date args ignored — the upload file is the period source of truth.
     */
    public static function getDailyDataRows(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = SheinDailyData::query()->orderByDesc('order_processed_on')->orderByDesc('id');
        if ($startDate && $endDate) {
            $query->whereBetween('order_processed_on', [$startDate, $endDate]);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $productMasters = self::productMastersForSkus($rows->pluck('seller_sku'));
        $result = [];

        foreach ($rows as $item) {
            $sku = trim((string) ($item->seller_sku ?? ''));
            $orderStatus = (string) ($item->order_status ?? '');
            if (self::isExcludedStatus($orderStatus)) {
                continue;
            }

            [$lp, $ship] = self::lpAndShip($productMasters, $sku);
            $price = (float) ($item->product_price ?? 0);
            $quantity = max(1, (int) ($item->quantity ?? 0));
            $estRev = (float) ($item->estimated_merchandise_revenue ?? 0);
            // Sales = Product Price × qty (Seller Hub GMV)
            $lineRevenue = $price * $quantity;

            $result[] = [
                'order_type' => (string) ($item->order_type ?? ''),
                'order_number' => (string) ($item->order_number ?? ''),
                'exchange_order' => (string) ($item->exchange_order ?? ''),
                'order_status' => $orderStatus,
                'shipment_mode' => (string) ($item->shipment_mode ?? ''),
                'product_name' => (string) ($item->product_name ?? ''),
                'product_description' => (string) ($item->product_description ?? ''),
                'specification' => (string) ($item->specification ?? ''),
                'seller_sku' => $sku,
                'shein_sku' => (string) ($item->shein_sku ?? ''),
                'skc' => (string) ($item->skc ?? ''),
                'item_id' => (string) ($item->item_id ?? ''),
                'product_status' => (string) ($item->product_status ?? ''),
                'tracking_number' => (string) ($item->tracking_number ?? ''),
                'sellers_package' => (string) ($item->sellers_package ?? ''),
                'product_price' => round($price, 2),
                'coupon_discount' => round((float) ($item->coupon_discount ?? 0), 2),
                'store_campaign_discount' => round((float) ($item->store_campaign_discount ?? 0), 2),
                'commission' => round((float) ($item->commission ?? 0), 2),
                'estimated_merchandise_revenue' => round($estRev > 0 ? $estRev : $lineRevenue, 2),
                'fulfillment_service_fee' => round((float) ($item->fulfillment_service_fee ?? 0), 2),
                'storage_fee' => round((float) ($item->storage_fee ?? 0), 2),
                'consumption_tax' => round((float) ($item->consumption_tax ?? 0), 2),
                'province' => (string) ($item->province ?? ''),
                'city' => (string) ($item->city ?? ''),
                'quantity' => $quantity,
                'lp' => $lp,
                'ship' => $ship,
                'order_processed_on' => $item->order_processed_on
                    ? Carbon::parse($item->order_processed_on)->format('Y-m-d H:i:s')
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
     * @return array<string, array{al30: int, sales: float}>
     */
    public static function aggregateSalesBySku(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $agg = [];
        foreach (self::getDailyDataRows($startDate, $endDate) as $row) {
            $sku = trim((string) ($row['seller_sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (! isset($agg[$sku])) {
                $agg[$sku] = ['al30' => 0, 'sales' => 0.0];
            }
            $qty = max(0, (int) ($row['quantity'] ?? 0));
            $price = (float) ($row['product_price'] ?? 0);
            $est = (float) ($row['estimated_merchandise_revenue'] ?? 0);
            $agg[$sku]['al30'] += $qty > 0 ? $qty : 1;
            $agg[$sku]['sales'] += $price > 0 ? ($price * max(1, $qty)) : $est;
        }

        return $agg;
    }

    public static function computeL60Totals(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // L60 uses the dedicated upload table (ignore date window — file is the source of truth)
        $rows = SheinDailyDataL60::query()->get();
        $margin = self::sheinMarginDecimal();
        $summary = self::summarizeUploadedRows($rows, $margin);

        return [
            'total_sales' => round($summary['total_sales'], 2),
            'total_orders' => $summary['total_orders'],
            'total_quantity' => $summary['total_quantity'],
        ];
    }

    public static function computeChannelSummary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $rows = collect(self::getDailyDataRows($startDate, $endDate));
        $margin = self::sheinMarginDecimal();

        return self::summarizeUploadedRows($rows, $margin);
    }

    /**
     * @param  iterable<int, object|array>  $rows
     * @return array{total_orders: int, total_quantity: int, total_sales: float, total_cogs: float, total_pft: float, pft_percentage: float, roi_percentage: float, avg_price: float, total_commission: float}
     */
    private static function summarizeUploadedRows(iterable $rows, float $margin): array
    {
        $totalOrders = 0;
        $totalQuantity = 0;
        $totalSales = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;
        $totalCommission = 0.0;

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $orderStatus = (string) ($row['order_status'] ?? '');
            if (self::isExcludedStatus($orderStatus)) {
                continue;
            }

            $orderNum = trim((string) ($row['order_number'] ?? ''));
            $sellerSku = trim((string) ($row['seller_sku'] ?? ''));
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 0));
            $qty = $quantity;
            $productPrice = (float) ($row['product_price'] ?? 0);
            // Sales = Product Price × qty (Seller Hub GMV)
            $lineRevenue = $productPrice * $qty;
            $unitPriceForPft = $productPrice;

            $totalOrders++;
            $totalQuantity += $qty;
            $totalSales += $lineRevenue;
            $totalCommission += (float) ($row['commission'] ?? 0);

            if ($qty > 0 && $unitPriceForPft > 0) {
                $totalWeightedPrice += $unitPriceForPft * $qty;
                $totalQuantityForPrice += $qty;
            }

            $lp = (float) ($row['lp'] ?? 0);
            $ship = (float) ($row['ship'] ?? 0);
            if ($lp === 0.0 && $sellerSku !== '') {
                // LP may be missing on Eloquent L60 rows — leave 0
            }

            $totalCogs += $lp * $qty;
            $totalPft += ($unitPriceForPft * $margin - $lp - $ship) * $qty;
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

    /** @return array{0: float, 1: float} */
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
