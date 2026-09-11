<?php

namespace App\Support;

use App\Services\TemuShopifySalesService;

/**
 * Keep a pushed Sale / S PRC in the listing Price column when a catalog
 * report overwrites it with list / Your Price / retail.
 */
class PushedListingPrice
{
    public static function same(mixed $a, mixed $b): bool
    {
        $x = is_numeric($a) ? round((float) $a, 2) : 0.0;
        $y = is_numeric($b) ? round((float) $b, 2) : 0.0;

        return $x > 0 && $y > 0 && abs($x - $y) < 0.005;
    }

    /**
     * Last confirmed push from a data_view.value blob.
     *
     * @param  array<string, mixed>  $value
     */
    public static function fromValue(array $value): ?float
    {
        foreach (['SPRICE_PUSHED_VALUE', 'CHANNEL_PUSHED_PRICE', 'AMAZON_PUSHED_SALE'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key]) && (float) $value[$key] > 0) {
                return round((float) $value[$key], 2);
            }
        }

        return null;
    }

    /**
     * If the catalog number disagrees with the last pushed Sale, keep the Sale.
     */
    public static function prefer(?float $incoming, ?float $pushedSale): ?float
    {
        $incoming = ($incoming !== null && $incoming > 0) ? round($incoming, 2) : null;
        $pushed = ($pushedSale !== null && $pushedSale > 0) ? round($pushedSale, 2) : null;
        if ($pushed === null) {
            return $incoming;
        }
        if ($incoming === null) {
            return $pushed;
        }
        if (self::same($incoming, $pushed)) {
            return $incoming;
        }

        return $pushed;
    }

    /**
     * Temu / Temu 2 / Temu 3 store supplier base, not full S PRC.
     * Do not write the customer Sale into base_price.
     */
    public static function temuBaseToWrite(?float $incomingBase, ?float $pushedSprice): ?float
    {
        $incoming = ($incomingBase !== null && $incomingBase > 0) ? round($incomingBase, 2) : null;
        $pushed = ($pushedSprice !== null && $pushedSprice > 0) ? round($pushedSprice, 2) : null;
        if ($pushed === null) {
            return $incoming;
        }

        $expectedBase = TemuShopifySalesService::computePushBaseFromSprice($pushed);
        if ($incoming === null) {
            return $expectedBase;
        }
        if ($expectedBase !== null && self::same($incoming, $expectedBase)) {
            return $incoming;
        }
        $incomingFull = round(TemuShopifySalesService::computeFullTemuPrice($incoming), 2);
        if (self::same($incomingFull, $pushed)) {
            return $incoming;
        }
        if (self::same($incoming, $pushed)) {
            return $expectedBase;
        }

        return $expectedBase ?? $incoming;
    }
}
