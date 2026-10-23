<?php

namespace App\Support;

/**
 * Grid SBGT on /google/shopping/google-shopping:
 * Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + BGT DIL (capped at a % of price, INV 0 → 0 when the rule is on).
 * Same sum rules as /amazon-ads/all (no Dil, no Reviews). Independent of Amazon settings.
 */
final class GoogleShoppingSbgt
{
    /**
     * Grid SBGT = the plain sum of the BGT parts (Views + Cvr + ACOS + PRC), as set by the rules.
     * Null parts count as 0. All-missing → null. A part that is 0 (e.g. the high-ACOS band) adds 0
     * to the sum; it does not zero the total. A total under 1 is 0 (cannot push a $0 budget).
     */
    public static function sumFromParts(mixed $bgtViews, mixed $bgtCvr, mixed $bgtAcos, mixed $bgtPrc = null, mixed $bgtDil = null): ?int
    {
        $has = false;
        $sum = 0;
        foreach ([$bgtViews, $bgtCvr, $bgtAcos, $bgtPrc, $bgtDil] as $part) {
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
}
