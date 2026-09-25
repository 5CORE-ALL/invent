<?php

namespace App\Support;

/**
 * /reverb-pricing Views + CVR: bump impressions ÷ 1000, then CVR from that scaled number.
 */
final class ReverbPricingViews
{
    public const DIVISOR = 1000;

    public static function scale(float|int $rawViews): float
    {
        $raw = (float) $rawViews;

        return $raw > 0 ? $raw / self::DIVISOR : 0.0;
    }

    public static function toRawImpressions(float|int $scaledViews): int
    {
        $scaled = (float) $scaledViews;

        return $scaled > 0 ? (int) round($scaled * self::DIVISOR) : 0;
    }

    public static function cvrPercent(float|int $rvL30, float|int $rawViews, int $decimals = 0): float
    {
        $views = self::scale($rawViews);
        if ($views <= 0) {
            return 0.0;
        }

        return round(((float) $rvL30 / $views) * 100, $decimals);
    }
}
