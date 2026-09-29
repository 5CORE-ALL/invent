<?php

namespace App\Support;

/**
 * View VS SBID slabs (ebay1_sbid_slabs): first matching For L7 Views range wins.
 * When E L30 sold (el30) is 0, always apply the maximum S Bid % among slabs that are not paused.
 * A paused slab pauses the promoted listing.
 */
final class SbidSlabRule
{
    /**
     * @return array{bid:float,pause:bool}
     */
    public static function match(float $esold, float $l7Views, array $slabs): array
    {
        if ($esold <= 0) {
            return ['bid' => self::maxSbid($slabs), 'pause' => false];
        }

        foreach ($slabs as $s) {
            if (! is_array($s)) {
                continue;
            }
            if (self::inRange($l7Views, $s['l7_views_min'] ?? null, $s['l7_views_max'] ?? null)) {
                if (self::isPaused($s)) {
                    return ['bid' => 0.0, 'pause' => true];
                }

                return ['bid' => (float) ($s['sbid'] ?? 0), 'pause' => false];
            }
        }

        return ['bid' => 0.0, 'pause' => false];
    }

    public static function resolve(float $esold, float $l7Views, array $slabs): float
    {
        $decision = self::match($esold, $l7Views, $slabs);

        return $decision['pause'] ? 0.0 : $decision['bid'];
    }

    public static function isPaused(array $slab): bool
    {
        $flag = $slab['paused'] ?? false;

        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    public static function maxSbid(array $slabs): float
    {
        $max = 0.0;
        foreach ($slabs as $s) {
            if (! is_array($s) || self::isPaused($s)) {
                continue;
            }
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
