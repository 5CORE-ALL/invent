<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * LMP × factor used on /ebay-tabulator-view:
 *   SPRICE = LMP × mult
 *   T Prc column = LMP × mult
 *
 * Stored (shared across users) in ebay_sbid_rules under key ebay1_lmp_mult.
 */
final class LmpMultRule
{
    public const KEY = 'ebay1_lmp_mult';

    public const DEFAULT_MULT = 0.98;

    /** @return array{mult: float} */
    public static function defaults(): array
    {
        return [
            'mult' => self::DEFAULT_MULT,
        ];
    }

    /** @return array{mult: float} */
    public static function settings(string $key = self::KEY): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $stored = $row ? json_decode($row->rule, true) : [];
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::sanitize(array_merge(self::defaults(), $stored));
    }

    /** @return array{mult: float} */
    public static function sanitize(array $in): array
    {
        $mult = isset($in['mult']) && is_numeric($in['mult'])
            ? (float) $in['mult']
            : self::DEFAULT_MULT;

        if ($mult <= 0 || $mult > 2) {
            $mult = self::DEFAULT_MULT;
        }

        return [
            'mult' => round($mult, 4),
        ];
    }
}
