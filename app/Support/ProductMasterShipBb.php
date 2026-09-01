<?php

namespace App\Support;

/**
 * Ship BB for BestBuy pricing and other ship_bb consumers.
 * Prefers ship_bb_base + handling + o-size (what Shipping Master saves).
 * If only a legacy stored ship_bb exists, strips the old full O-Size add-on
 * (when Type is O-Size) then adds handling + o-size. Never writes back.
 */
class ProductMasterShipBb
{
    public static function stored(?array $values, $pm = null): ?float
    {
        $values = self::normalizeValues($values, $pm);

        foreach ($values as $key => $value) {
            if (strtolower((string) $key) !== 'ship_bb') {
                continue;
            }
            if ($value === null || $value === '') {
                return null;
            }

            return is_numeric($value) ? (float) $value : null;
        }

        if ($pm !== null && isset($pm->ship_bb) && $pm->ship_bb !== null && $pm->ship_bb !== '' && is_numeric($pm->ship_bb)) {
            return (float) $pm->ship_bb;
        }

        return null;
    }

    public static function forPricing(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);
        $handling = self::handlingCharge($values);
        $oSize = self::oSizeCharge($values);

        $base = self::numericKey($values, 'ship_bb_base');
        if ($base !== null) {
            return round($base + $handling + $oSize, 2);
        }

        $stored = self::stored($values, $pm);
        if ($stored !== null) {
            return round($stored - self::legacyOSizeAddon($values) + $handling + $oSize, 2);
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

    private static function legacyOSizeAddon(array $values): float
    {
        $raw = $values['label_type'] ?? $values['Label Type'] ?? $values['Label_Type'] ?? '';
        $compact = strtolower(preg_replace('/[\s_-]+/', '', trim((string) $raw)));
        if ($compact !== 'osize') {
            return 0.0;
        }

        return self::oSizeCharge($values);
    }

    private static function numericKey(array $values, string $key): ?float
    {
        $raw = $values[$key] ?? null;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private static function chargeAmount(array $values, array $keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
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
