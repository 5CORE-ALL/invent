<?php

namespace App\Support;

/**
 * Ads spend / sales on one Amazon campaign-report row for L1, L7, or a daily date.
 */
class AmazonAdsLRangeMetrics
{
    /**
     * @return list<string>
     */
    public static function salesPreference(string $range): array
    {
        return match (strtoupper($range)) {
            'L1', 'DAILY' => ['sales1d', 'sales', 'sales30d', 'sales7d'],
            'L7' => ['sales7d', 'sales', 'sales30d', 'sales1d'],
            default => ['sales30d', 'sales', 'sales7d', 'sales1d'],
        };
    }

    /**
     * @param  array<int, string>  $dbColumns
     * @param  list<string>  $keys
     */
    public static function moneyFromKeys(array $r, array $dbColumns, array $keys): ?float
    {
        $zero = null;
        foreach ($keys as $k) {
            if (! array_key_exists($k, $r) && ! in_array($k, $dbColumns, true)) {
                continue;
            }
            $v = $r[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (! is_finite($n)) {
                continue;
            }
            if ($n > 0) {
                return round($n, 2);
            }
            $zero = 0.0;
        }

        return $zero;
    }

    /**
     * @param  array<int, string>  $dbColumns
     */
    public static function salesFromRow(array $r, array $dbColumns, string $range): ?float
    {
        return self::moneyFromKeys($r, $dbColumns, self::salesPreference($range));
    }

    /**
     * @param  array<int, string>  $dbColumns
     */
    public static function spendFromRow(array $r, array $dbColumns): ?float
    {
        return self::moneyFromKeys($r, $dbColumns, ['cost', 'spend']);
    }

    public static function preferAmount(?float $current, ?float $incoming): ?float
    {
        if ($incoming === null || ! is_finite($incoming)) {
            return $current;
        }
        $incoming = round($incoming, 2);
        if ($current === null) {
            return $incoming;
        }
        if ($current <= 0.0 && $incoming > 0.0) {
            return $incoming;
        }

        return $current;
    }
}
