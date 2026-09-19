<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\InstagramPricing;
use App\Models\InstagramShopSheetdata;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Shop Analytics — Depop-style Tabulator grid (no ship) + CSV template upsert.
 * Overlay: instagram_pricing. L30/price fallback from instagram_shop_sheet_data.
 */
class InstagramAnalyticsController extends DepopStyleAnalyticsController
{
    protected static function pricingModelClass(): string
    {
        return InstagramPricing::class;
    }

    protected static function viewName(): string
    {
        return 'market-places.instagram_analytics';
    }

    protected static function csvPrefix(): string
    {
        return 'instagram_pricing';
    }

    protected static function marketplaceNames(): array
    {
        return ['instagram shop', 'instagramshop', 'instagram'];
    }

    protected static function defaultMarginPercent(): float
    {
        return 100.0;
    }

    protected static function channelLogName(): string
    {
        return 'Instagram analytics';
    }

    /**
     * Sheet L30 + price when no instagram_sales_data table exists.
     *
     * @return array<string, array{qty: int, sales: float, fallback_price: float}>
     */
    protected static function salesL30BySku(): array
    {
        if (! Schema::hasTable('instagram_shop_sheet_data')) {
            return [];
        }

        $rows = InstagramShopSheetdata::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'i_l30', 'price']);

        $out = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key === '') {
                continue;
            }
            $qty = (int) ($row->i_l30 ?? 0);
            $price = $row->price !== null ? (float) $row->price : 0.0;
            $out[$key] = [
                'qty' => $qty,
                'sales' => ($qty > 0 && $price > 0) ? $price * $qty : 0.0,
                'fallback_price' => $price,
            ];
        }

        return $out;
    }
}
