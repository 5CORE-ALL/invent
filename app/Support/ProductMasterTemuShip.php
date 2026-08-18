<?php

namespace App\Support;

/**
 * Temu ship for /temu-decrease and /temu2-decrease.
 * If Values.temu_ship already exists, use it and leave it (do not replace with regular ship).
 * If it is missing, fall back to regular ship; when Type is O-Size, add 50% of O-Size Charge.
 * Never writes back to Product Master.
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
     */
    public static function forPricing(?array $values, $pm = null): float
    {
        $values = self::normalizeValues($values, $pm);
        $stored = self::stored($values, $pm);
        if ($stored !== null) {
            return round($stored, 2);
        }

        $ship = self::regularShip($values, $pm);
        if (self::isOSize($values)) {
            $ship += self::oSizeCharge($values) * 0.5;
        }

        return round($ship, 2);
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

    private static function isOSize(array $values): bool
    {
        $raw = $values['label_type'] ?? $values['Label Type'] ?? $values['Label_Type'] ?? '';
        $compact = strtolower(preg_replace('/[\s_-]+/', '', trim((string) $raw)));

        return $compact === 'osize';
    }

    private static function oSizeCharge(array $values): float
    {
        $raw = $values['o_size_charge'] ?? $values['O-Size Charge'] ?? $values['o-size-charge'] ?? 0;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return 0.0;
        }

        return (float) $raw;
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
