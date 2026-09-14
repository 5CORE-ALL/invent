<?php

namespace App\Support;

/**
 * View VS SBID slabs (ebay1_sbid_slabs): first matching For L7 Views range wins.
 * When E L30 sold (el30) is 0, always apply the maximum S Bid % among slabs.
 */
final class SbidSlabRule
{
    public static function resolve(float $esold, float $l7Views, array $slabs): float
    {
        if ($esold <= 0) {
            return self::maxSbid($slabs);
        }

        foreach ($slabs as $s) {
            if (self::inRange($l7Views, $s['l7_views_min'] ?? null, $s['l7_views_max'] ?? null)) {
                return (float) ($s['sbid'] ?? 0);
            }
        }

        return 0.0;
    }

    public static function maxSbid(array $slabs): float
    {
        $max = 0.0;
        foreach ($slabs as $s) {
            $bid = (float) ($s['sbid'] ?? 0);
            if ($bid > $max) {
                $max = $bid;
            }
        }

        return $max;
    }

    public static function inRange(float $val, $min, $max): bool
    {
        if ($min !== null && $min !== '' && $val < (float) $min) {
            return false;
        }
        if ($max !== null && $max !== '' && $val > (float) $max) {
            return false;
        }

        return true;
    }
}
