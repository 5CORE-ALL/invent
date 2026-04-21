<?php

namespace App\Support;

/**
 * L30 ACOS (%) → suggested SB daily budget tier (SBGT).
 * Tiers: pink 12, green 8, blue 4, yellow 2, red 1 — same breakpoints as
 * Tabulator SBGT mutators and the old KW utilized tooling.
 */
final class AmazonAcosSbgtRule
{
    public static function sbgtFromAcosL30(float $acos): int
    {
        if ($acos >= 40) {
            return 1;
        }
        if ($acos > 30) {
            return 2;
        }
        if ($acos > 20) {
            return 4;
        }
        if ($acos > 10) {
            return 8;
        }

        return 12;
    }
}
