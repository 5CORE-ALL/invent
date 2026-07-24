<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * CVR-based SPRICE multipliers for ebay 1/2/3 tabulator views.
 *
 *   CVR ≤ low_cvr  → SPRICE = base × down_mult  (default ≤7 → ×0.99)
 *   CVR > high_cvr → SPRICE = base × up_mult    (default >13 → ×1.01)
 *   otherwise      → skip (no change)
 *
 * Stored (shared across users & ebay accounts) in ebay_sbid_rules under key ebay_sprice_cvr.
 */
final class SpriceCvrMultRule
{
    public const KEY = 'ebay_sprice_cvr';

    public const DEFAULT_LOW_CVR = 7.0;

    public const DEFAULT_HIGH_CVR = 13.0;

    public const DEFAULT_DOWN_MULT = 0.99;

    public const DEFAULT_UP_MULT = 1.01;

    /** @return array{low_cvr: float, high_cvr: float, down_mult: float, up_mult: float} */
    public static function defaults(): array
    {
        return [
            'low_cvr' => self::DEFAULT_LOW_CVR,
            'high_cvr' => self::DEFAULT_HIGH_CVR,
            'down_mult' => self::DEFAULT_DOWN_MULT,
            'up_mult' => self::DEFAULT_UP_MULT,
        ];
    }

    /** @return array{low_cvr: float, high_cvr: float, down_mult: float, up_mult: float} */
    public static function settings(string $key = self::KEY): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $stored = $row ? json_decode($row->rule, true) : [];
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::sanitize(array_merge(self::defaults(), $stored));
    }

    /** @return array{low_cvr: float, high_cvr: float, down_mult: float, up_mult: float} */
    public static function sanitize(array $in): array
    {
        $low = isset($in['low_cvr']) && is_numeric($in['low_cvr'])
            ? (float) $in['low_cvr']
            : self::DEFAULT_LOW_CVR;
        $high = isset($in['high_cvr']) && is_numeric($in['high_cvr'])
            ? (float) $in['high_cvr']
            : self::DEFAULT_HIGH_CVR;
        $down = isset($in['down_mult']) && is_numeric($in['down_mult'])
            ? (float) $in['down_mult']
            : self::DEFAULT_DOWN_MULT;
        $up = isset($in['up_mult']) && is_numeric($in['up_mult'])
            ? (float) $in['up_mult']
            : self::DEFAULT_UP_MULT;

        if ($low < 0 || $low > 100) {
            $low = self::DEFAULT_LOW_CVR;
        }
        if ($high < 0 || $high > 100) {
            $high = self::DEFAULT_HIGH_CVR;
        }
        if ($low > $high) {
            $tmp = $low;
            $low = $high;
            $high = $tmp;
        }
        if ($down <= 0 || $down > 2) {
            $down = self::DEFAULT_DOWN_MULT;
        }
        if ($up <= 0 || $up > 2) {
            $up = self::DEFAULT_UP_MULT;
        }

        return [
            'low_cvr' => round($low, 2),
            'high_cvr' => round($high, 2),
            'down_mult' => round($down, 4),
            'up_mult' => round($up, 4),
        ];
    }
}
