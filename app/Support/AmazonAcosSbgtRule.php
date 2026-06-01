<?php

namespace App\Support;

use App\Models\AmazonAcosSbgtRuleSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * L30 ACOS (%) → suggested daily budget tier (SBGT), with boundaries and tier $ amounts
 * persisted in {@see AmazonAcosSbgtRuleSetting} (Amazon Ads “BGT rule”).
 */
final class AmazonAcosSbgtRule
{
    public const CACHE_KEY = 'amazon_acos_sbgt_rule_resolved_v1';

    /**
     * Default rule: pink ≤E1 → sbgt_pink; green (E1,E2]; blue (E2,E3]; yellow (E3,E4); red ≥E4.
     *
     * @return array{e1: float, e2: float, e3: float, e4: float, sbgt_pink: int, sbgt_green: int, sbgt_blue: int, sbgt_yellow: int, sbgt_red: int}
     */
    public static function defaults(): array
    {
        return [
            'e1' => 10.0,
            'e2' => 20.0,
            'e3' => 30.0,
            'e4' => 40.0,
            'sbgt_pink' => 12,
            'sbgt_green' => 8,
            'sbgt_blue' => 4,
            'sbgt_yellow' => 2,
            'sbgt_red' => 1,
        ];
    }

    /**
     * Active rule (cached). Falls back to {@see defaults} when the table is missing or empty.
     *
     * @return array{e1: float, e2: float, e3: float, e4: float, sbgt_pink: int, sbgt_green: int, sbgt_blue: int, sbgt_yellow: int, sbgt_red: int}
     */
    public static function resolvedRule(): array
    {
        return Cache::remember(self::CACHE_KEY, 86400, static function (): array {
            if (! Schema::hasTable('amazon_acos_sbgt_rule_settings')) {
                return self::defaults();
            }
            $row = AmazonAcosSbgtRuleSetting::query()->orderBy('id')->first();
            if ($row === null || ! is_array($row->rule) || $row->rule === []) {
                return self::defaults();
            }

            return self::normalizeRule(array_merge(self::defaults(), $row->rule));
        });
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{e1: float, e2: float, e3: float, e4: float, sbgt_pink: int, sbgt_green: int, sbgt_blue: int, sbgt_yellow: int, sbgt_red: int}
     */
    public static function normalizeRule(array $input): array
    {
        $d = self::defaults();
        $e1 = (float) ($input['e1'] ?? $d['e1']);
        $e2 = (float) ($input['e2'] ?? $d['e2']);
        $e3 = (float) ($input['e3'] ?? $d['e3']);
        $e4 = (float) ($input['e4'] ?? $d['e4']);
        foreach ([$e1, $e2, $e3, $e4] as $e) {
            if (! is_finite($e)) {
                throw new \InvalidArgumentException('ACOS boundaries must be finite numbers.');
            }
        }
        if (! ($e1 < $e2 && $e2 < $e3 && $e3 < $e4)) {
            throw new \InvalidArgumentException('ACOS boundaries must satisfy E1 < E2 < E3 < E4.');
        }
        if ($e4 > 500) {
            throw new \InvalidArgumentException('E4 (red threshold) must be ≤ 500.');
        }
        if ($e1 <= 0) {
            throw new \InvalidArgumentException('E1 must be positive.');
        }

        $tiers = [];
        foreach (['sbgt_pink', 'sbgt_green', 'sbgt_blue', 'sbgt_yellow', 'sbgt_red'] as $k) {
            $v = (int) round((float) ($input[$k] ?? $d[$k]));
            if ($v < 1 || $v > 100_000) {
                throw new \InvalidArgumentException('Each SBGT tier must be between 1 and 100000.');
            }
            $tiers[$k] = $v;
        }

        return [
            'e1' => $e1,
            'e2' => $e2,
            'e3' => $e3,
            'e4' => $e4,
            'sbgt_pink' => $tiers['sbgt_pink'],
            'sbgt_green' => $tiers['sbgt_green'],
            'sbgt_blue' => $tiers['sbgt_blue'],
            'sbgt_yellow' => $tiers['sbgt_yellow'],
            'sbgt_red' => $tiers['sbgt_red'],
        ];
    }

    /**
     * @param  array{e1: float, e2: float, e3: float, e4: float, sbgt_pink: int, sbgt_green: int, sbgt_blue: int, sbgt_yellow: int, sbgt_red: int}  $rule
     */
    public static function persistRule(array $rule): void
    {
        if (! Schema::hasTable('amazon_acos_sbgt_rule_settings')) {
            throw new \RuntimeException('Table amazon_acos_sbgt_rule_settings does not exist. Run migrations.');
        }
        $row = AmazonAcosSbgtRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAcosSbgtRuleSetting::query()->create(['rule' => $rule]);
        } else {
            $row->update(['rule' => $rule]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function reportRowArray(object|array $row): array
    {
        if (is_array($row)) {
            return $row;
        }
        if ($row instanceof Model) {
            return $row->toArray();
        }

        return (array) $row;
    }

    /**
     * L30 spend for ACOS: prefer `cost`, else `spend` (same order as
     * {@see \App\Http\Controllers\AmazonAdsController} / `l30DisplaySpendFromRowArray`).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function l30DisplaySpendForAcos(object|array $row): ?float
    {
        $r = self::reportRowArray($row);
        foreach (['cost', 'spend'] as $k) {
            if (! array_key_exists($k, $r)) {
                continue;
            }
            $v = $r[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * L30 sales for ACOS: prefer `sales30d`, else `sales` (Amazon Ads All / SP & SB L30 rows).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function l30SalesForAcos(object|array $row): ?float
    {
        $r = self::reportRowArray($row);
        foreach (['sales30d', 'sales'] as $k) {
            if (! array_key_exists($k, $r)) {
                continue;
            }
            $v = $r[$k] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $n = (float) $v;
            if (is_finite($n)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * ACOS % from one L30 summary report row, aligned with {@see \App\Http\Controllers\AmazonAdsController::computedAcosPercentFromReportRow}
     * (whole-number percent like the All page ACOS column; spend prefers `cost` then `spend`).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function acosPercentForSbgtFromReportRow(object|array $row): ?float
    {
        $c = self::l30DisplaySpendForAcos($row);
        if ($c === null) {
            return null;
        }
        $sales = self::l30SalesForAcos($row);
        if ($sales === null) {
            return null;
        }
        if ($sales > 0) {
            $v = ($c / $sales) * 100;

            return is_finite($v) ? (float) round($v, 0) : null;
        }
        if ($c > 0) {
            return 100.0;
        }

        return 0.0;
    }

    /**
     * Suggested SBGT tier ($) from one L30 report row (same inputs as Amazon Ads All SBGT column).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function sbgtFromL30ReportRow(object|array $row): ?int
    {
        $acos = self::acosPercentForSbgtFromReportRow($row);
        if ($acos === null) {
            return null;
        }

        return self::sbgtFromAcosL30($acos);
    }

    public static function sbgtFromAcosL30(float $acos): int
    {
        $r = self::resolvedRule();
        if ($acos >= $r['e4']) {
            return (int) $r['sbgt_red'];
        }
        if ($acos > $r['e3']) {
            return (int) $r['sbgt_yellow'];
        }
        if ($acos > $r['e2']) {
            return (int) $r['sbgt_blue'];
        }
        if ($acos > $r['e1']) {
            return (int) $r['sbgt_green'];
        }

        return (int) $r['sbgt_pink'];
    }

    /**
     * Distinct tier values allowed for SBGT push / validation.
     *
     * @return list<int>
     */
    public static function allowedSbgtTierValues(): array
    {
        $r = self::resolvedRule();
        $vals = [
            (int) $r['sbgt_pink'],
            (int) $r['sbgt_green'],
            (int) $r['sbgt_blue'],
            (int) $r['sbgt_yellow'],
            (int) $r['sbgt_red'],
        ];
        $vals = array_values(array_unique($vals));
        sort($vals, SORT_NUMERIC);

        return $vals;
    }

    /**
     * SQL CASE expression (numeric) for ORDER BY SBGT column, from an ACOS % scalar SQL fragment.
     */
    public static function sqlSortCaseExpression(string $acosExpr): string
    {
        $r = self::resolvedRule();
        $e1 = self::sqlNumberLiteral($r['e1']);
        $e2 = self::sqlNumberLiteral($r['e2']);
        $e3 = self::sqlNumberLiteral($r['e3']);
        $e4 = self::sqlNumberLiteral($r['e4']);
        $sp = (int) $r['sbgt_pink'];
        $sg = (int) $r['sbgt_green'];
        $sb = (int) $r['sbgt_blue'];
        $sy = (int) $r['sbgt_yellow'];
        $sr = (int) $r['sbgt_red'];

        return 'CASE WHEN ('.$acosExpr.') >= '.$e4.' THEN '.$sr
            .' WHEN ('.$acosExpr.') > '.$e3.' THEN '.$sy
            .' WHEN ('.$acosExpr.') > '.$e2.' THEN '.$sb
            .' WHEN ('.$acosExpr.') > '.$e1.' THEN '.$sg
            .' ELSE '.$sp.' END';
    }

    private static function sqlNumberLiteral(float $n): string
    {
        if (! is_finite($n)) {
            return '0';
        }
        $s = rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }
}
