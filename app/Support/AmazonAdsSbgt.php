<?php

namespace App\Support;

/**
 * Grid SBGT on /amazon-ads/all: Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews.
 * Amazon daily budget cannot be $0, so an explicit 0 (BGT ACOS band or a 0 sum) is not
 * pushable — callers pause the campaign instead.
 */
final class AmazonAdsSbgt
{
    /**
     * Grid SBGT. Null parts count as 0 in the sum. All-missing → null.
     * Explicit BGT ACOS of 0 zeros the total (pause — $0 will not push).
     */
    public static function sumFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null, mixed $bgtReviews = null): ?int
    {
        if (self::isExplicitZero($bgtAcos)) {
            return 0;
        }

        $has = false;
        $sum = 0;
        foreach ([$bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtReviews] as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            if (! is_numeric($part)) {
                continue;
            }
            $has = true;
            $sum += (int) $part;
        }
        if (! $has) {
            return null;
        }

        return $sum < 1 ? 0 : $sum;
    }

    public static function isExplicitZero(mixed $raw): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }
        if (! is_numeric($raw)) {
            return false;
        }

        return (int) $raw === 0;
    }

    /**
     * Daily budget dollars Amazon will accept (whole dollars 1–9999). 0 is not pushable.
     */
    public static function parsePushableBudget(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (int) $raw;

        return ($n >= 1 && $n <= 9999) ? $n : null;
    }
}
