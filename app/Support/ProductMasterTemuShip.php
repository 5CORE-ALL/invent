<?php

namespace App\Support;

/**
 * Temu ship for /temu-decrease, /temu2-decrease, and other Temu pricing.
 * Prefers temu_ship_base + handling + o-size (what Shipping Master saves).
 * If only a legacy stored temu_ship exists, strips the old 50% O-Size add-on
 * then adds handling + o-size. Never writes back to Product Master.
 */
class ProductMasterTemuShip
{
    /**
     * Stored per-SKU Temu ship, or null when the SKU has no temu_ship yet.
     */
    public static function stored(?array $values, $pm = null): ?float
    {
        $values = self::normalizeValues($values, $pm);

        foreach ($values as $key => $value) {
            if (strtolower((string) $key) !== 'temu_ship') {
                continue;
            }
            if ($value === null || $value === '') {
                return null;
            }

            return is_numeric($value) ? (float) $value : null;
        }

        if ($pm !== null) {
            $fromAccessor = $pm->temu_ship ?? null;
            if ($fromAccessor !== null && $fromAccessor !== '' && is_numeric($fromAccessor)) {
                return (float) $fromAccessor;
            }
        }

        return null;
    }

    /**
     * Rate used on Temu decrease pages and SPRICE / sales math.
     * Always includes Handling Charge + O-Size Charge when a slab base is known.
     */
    public static function forPricing(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);
        $handling = self::handlingCharge($values);
        $oSize = self::oSizeCharge($values);

        $temuBase = self::numericKey($values, 'temu_ship_base');
        if ($temuBase !== null) {
            return round($temuBase + $handling + $oSize, 2);
        }

        $stored = self::stored($values, $pm);
        if ($stored !== null) {
            $base = $stored - self::legacyOSizeHalf($values);

            return round($base + $handling + $oSize, 2);
        }

        $shipBase = self::numericKey($values, 'ship_base');
        if ($shipBase !== null) {
            return round($shipBase + $handling + $oSize, 2);
        }

        return round(self::regularShip($values, $pm), 2);
    }

    public static function handlingCharge(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);

        return self::chargeAmount($values, ['handling_charge', 'Handling Charge', 'handling-charge']);
    }

    public static function oSizeCharge(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);

        return self::chargeAmount($values, ['o_size_charge', 'O-Size Charge', 'o-size-charge']);
    }

    private static function legacyOSizeHalf(array $values): float
    {
        $raw = $values['label_type'] ?? $values['Label Type'] ?? $values['Label_Type'] ?? '';
        $compact = strtolower(preg_replace('/[\s_-]+/', '', trim((string) $raw)));
        if ($compact !== 'osize') {
            return 0.0;
        }

        return round(self::oSizeCharge($values) * 0.5, 2);
    }

    private static function regularShip(array $values, $pm = null): float
    {
        foreach ($values as $key => $value) {
            if (strtolower((string) $key) !== 'ship') {
                continue;
            }
            if ($value === null || $value === '' || ! is_numeric($value)) {
                break;
            }

            return (float) $value;
        }

        if ($pm !== null && isset($pm->ship) && $pm->ship !== null && $pm->ship !== '' && is_numeric($pm->ship)) {
            return (float) $pm->ship;
        }

        return 0.0;
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
