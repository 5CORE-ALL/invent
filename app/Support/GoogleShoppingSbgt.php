<?php

namespace App\Support;

/**
 * Grid SBGT on /google/shopping/google-shopping:
 * Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC.
 * Same sum rules as /amazon-ads/all (no Dil, no Reviews). Independent of Amazon settings.
 */
final class GoogleShoppingSbgt
{
    /**
     * Grid SBGT. Null parts count as 0 in the sum. All-missing → null.
     * Explicit BGT ACOS of 0 zeros the total.
     */
    public static function sumFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null): ?int
    {
        if (self::isExplicitZero($bgtAcos)) {
            return 0;
        }

        $has = false;
        $sum = 0;
        foreach ([$bgtViews, $bgtCvr, $bgtAcos, $bgtPrc] as $part) {
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
}
