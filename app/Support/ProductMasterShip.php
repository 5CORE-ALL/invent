<?php

namespace App\Support;

/**
 * Normal Ship for pricing pages (Shipping Master "Ship" column).
 * Prefers ship_base + handling + o-size + PR (what Shipping Master saves).
 * Falls back to stored Values['ship'] (never ship_bb / TT Ship). Never writes back.
 */
class ProductMasterShip
{
    public static function stored(?array $values, $pm = null): ?float
    {
        $values = self::normalizeValues($values, $pm);

        foreach ($values as $key => $value) {
            if (strtolower((string) $key) !== 'ship') {
                continue;
            }
            if ($value === null || $value === '') {
                return null;
            }

            return is_numeric($value) ? (float) $value : null;
        }

        if ($pm !== null && isset($pm->ship) && $pm->ship !== null && $pm->ship !== '' && is_numeric($pm->ship)) {
            return (float) $pm->ship;
        }

        return null;
    }

    public static function forPricing(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);
        $handling = self::handlingCharge($values);
        $oSize = self::oSizeCharge($values);
        $pr = self::prCharge($values);

        $base = self::numericKey($values, 'ship_base');
        if ($base !== null) {
            return round($base + $handling + $oSize + $pr, 2);
        }

        $stored = self::stored($values, $pm);
        if ($stored !== null) {
            return round($stored, 2);
        }

        return 0.0;
    }

    public static function handlingCharge(?array $values, $pm = null): float
    {
        return self::chargeAmount(self::normalizeValues($values, $pm), ['handling_charge', 'Handling Charge', 'handling-charge']);
    }

    public static function oSizeCharge(?array $values, $pm = null): float
    {
        return self::chargeAmount(self::normalizeValues($values, $pm), ['o_size_charge', 'O-Size Charge', 'o-size-charge']);
    }

    public static function prCharge(?array $values, $pm = null): float
    {
        return self::chargeAmount(self::normalizeValues($values, $pm), ['pr_charge', 'PR Charge', 'pr-charge']);
    }

    private static function numericKey(array $values, string $key): ?float
    {
        $want = strtolower($key);
        foreach ($values as $k => $raw) {
            if (strtolower((string) $k) !== $want) {
                continue;
            }
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                return null;
            }

            return (float) $raw;
        }

        return null;
    }

    private static function chargeAmount(array $values, array $keys): float
    {
        $want = [];
        foreach ($keys as $key) {
            $want[strtolower((string) $key)] = true;
        }

        foreach ($values as $key => $raw) {
            if (! isset($want[strtolower((string) $key)])) {
                continue;
            }
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                return 0.0;
            }

            return (float) $raw;
        }

        return 0.0;
    }

    private static function normalizeValues(?array $values, $pm = null): array
    {
        if (! is_array($values) || $values === []) {
            if ($pm === null) {
                return [];
            }
            $raw = $pm->Values ?? [];
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            $values = is_array($raw) ? $raw : [];
        }

        return $values;
    }
}
