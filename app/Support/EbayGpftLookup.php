<?php

namespace App\Support;

use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Log;

/**
 * GPFT % per SKU, the same number the eBay 1 grid shows in its GPFT% column:
 *   ((eBay price × take-home − ship − LP) / eBay price) × 100
 * Price is the listing price (ebay_metrics.ebay_price). LP and ship come from
 * product_master.Values. A SKU with no price has no GPFT %.
 */
final class EbayGpftLookup
{
    /**
     * @param  array<string, float|int|string|null>  $priceBySku  Keyed by SKU (any case / spacing).
     * @return array<string, ?float> Keyed by EbayStdNpftLookup::key(sku). Null = no price.
     */
    public static function forPrices(array $priceBySku): array
    {
        $prices = [];
        $display = [];
        foreach ($priceBySku as $sku => $price) {
            $key = EbayStdNpftLookup::key((string) $sku);
            if ($key === '' || str_starts_with($key, 'PARENT')) {
                continue;
            }
            $prices[$key] = is_numeric($price) ? (float) $price : null;
            $display[$key] = trim((string) $sku);
        }
        if ($prices === []) {
            return [];
        }

        try {
            $takeHome = MarketplacePercentage::takeHomeDecimal('Ebay');
        } catch (\Throwable $e) {
            Log::warning('Dil vs SBid: eBay take-home % failed', ['error' => $e->getMessage()]);
            $takeHome = 1.0;
        }
        $cost = EbayStdNpftLookup::costs(array_values($display));

        $out = [];
        foreach ($prices as $key => $price) {
            $c = $cost[$key] ?? ['lp' => null, 'ship' => 0.0];
            $out[$key] = DilVsSbidRule::gpftPercent($price, $c['lp'], $c['ship'], $takeHome);
        }

        return $out;
    }
}
