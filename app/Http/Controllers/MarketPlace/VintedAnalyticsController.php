<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\VintedPricing;
use App\Models\VintedSalesData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Vinted Analytics — Depop-style Tabulator grid (no ship) + CSV template upsert.
 * Overlay: vinted_pricing. L30/sales from /vinted/sheet (vinted_sales_data).
 */
class VintedAnalyticsController extends DepopStyleAnalyticsController
{
    protected static function pricingModelClass(): string
    {
        return VintedPricing::class;
    }

    protected static function viewName(): string
    {
        return 'market-places.vinted_analytics';
    }

    protected static function csvPrefix(): string
    {
        return 'vinted_pricing';
    }

    protected static function marketplaceNames(): array
    {
        return ['vinted'];
    }

    protected static function defaultMarginPercent(): float
    {
        return 87.0;
    }

    protected static function channelLogName(): string
    {
        return 'Vinted analytics';
    }

    /**
     * @return array<string, array{qty: int, sales: float}>
     */
    protected static function salesL30BySku(): array
    {
        if (! Schema::hasTable('vinted_sales_data')) {
            return [];
        }

        $latestSaleDate = VintedSalesData::whereNotNull('sale_date')->max('sale_date');
        if (! $latestSaleDate) {
            return [];
        }

        $latestCarbon = Carbon::parse($latestSaleDate);
        $l30Start = $latestCarbon->copy()->subDays(29)->format('Y-m-d');
        $l30End = $latestCarbon->format('Y-m-d');

        $rows = VintedSalesData::query()
            ->whereNotNull('sku_code')
            ->where('sku_code', '!=', '')
            ->whereBetween('sale_date', [$l30Start, $l30End])
            ->get(['sku_code', 'quantity', 'item_price']);

        $out = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku_code));
            if ($key === '') {
                continue;
            }
            $qty = (int) ($row->quantity ?: 1);
            if ($qty < 1) {
                $qty = 1;
            }
            $sales = ((float) $row->item_price) * $qty;
            if (! isset($out[$key])) {
                $out[$key] = ['qty' => 0, 'sales' => 0.0];
            }
            $out[$key]['qty'] += $qty;
            $out[$key]['sales'] += $sales;
        }

        return $out;
    }
}
