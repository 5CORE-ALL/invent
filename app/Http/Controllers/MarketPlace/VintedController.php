<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\VintedPricing;
use App\Models\VintedSalesData;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Vinted helpers (margin, L30 sales sync). The Vinted Analytics page lives in
 * VintedAnalyticsController (/vinted/analytics).
 */
class VintedController extends Controller
{
    private const DEFAULT_MARGIN_PCT = 95;

    /**
     * Rolling 30 days ending Pacific yesterday. This is the window Active Channel Master uses
     * for Vinted, so Sales and V L30 on the Analytics page match it. (It used to end on the
     * latest sale date in the sheet, which gave a different 30 days whenever the last sale
     * was not yesterday.)
     *
     * @return array{0: string, 1: string}|null  [start, end], or null when there is no sales data
     */
    public static function l30Window(): ?array
    {
        if (! Schema::hasTable('vinted_sales_data')) {
            return null;
        }
        if (! VintedSalesData::whereNotNull('sale_date')->exists()) {
            return null;
        }

        $end = \Carbon\Carbon::yesterday('America/Los_Angeles');

        return [$end->copy()->subDays(29)->toDateString(), $end->toDateString()];
    }

    /**
     * @return array<string, int>
     */
    public static function salesL30BySku(): array
    {
        $window = self::l30Window();
        if ($window === null) {
            return [];
        }
        [$l30Start, $l30End] = $window;

        $raw = VintedSalesData::query()
            ->whereNotNull('sku_code')
            ->where('sku_code', '!=', '')
            ->whereBetween('sale_date', [$l30Start, $l30End])
            ->selectRaw('UPPER(TRIM(sku_code)) as sku_upper, SUM(GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)) as qty')
            ->groupBy('sku_upper')
            ->pluck('qty', 'sku_upper');

        $out = [];
        foreach ($raw as $sku => $qty) {
            $key = strtoupper(trim((string) $sku));
            if ($key === '') {
                continue;
            }
            $out[$key] = (int) $qty;
        }

        return $out;
    }

    public static function syncL30FromSales(): int
    {
        $salesL30 = self::salesL30BySku();
        if (empty($salesL30)) {
            return 0;
        }

        $pmSkuByUpper = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->pluck('sku')
            ->filter()
            ->mapWithKeys(fn ($s) => [strtoupper(trim((string) $s)) => $s])
            ->all();

        $pricingSkuByUpper = VintedPricing::query()
            ->pluck('sku')
            ->filter()
            ->mapWithKeys(fn ($s) => [strtoupper(trim((string) $s)) => $s])
            ->all();

        $updated = 0;
        foreach ($salesL30 as $skuUpper => $qty) {
            if ($skuUpper === '' || stripos($skuUpper, 'PARENT') === 0) {
                continue;
            }
            $sku = $pmSkuByUpper[$skuUpper]
                ?? $pricingSkuByUpper[$skuUpper]
                ?? $skuUpper;

            VintedPricing::updateOrCreate(
                ['sku' => $sku],
                ['l30' => $qty]
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * Take-home margin % from marketplace_percentages where marketplace = Vinted.
     */
    public static function marginPercent(): float
    {
        $raw = MarketplacePercentage::query()
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['vinted'])
            ->value('percentage');

        if ($raw !== null && is_numeric($raw) && (float) $raw > 0) {
            return (float) $raw;
        }

        return (float) self::DEFAULT_MARGIN_PCT;
    }

    /**
     * Margin factor (0–1) from marketplace_percentages Vinted.
     */
    public static function marginFactor(): float
    {
        return self::marginPercent() / 100;
    }
}
