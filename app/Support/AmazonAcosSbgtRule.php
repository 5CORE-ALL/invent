<?php

namespace App\Support;

use App\Models\AmazonAcosSbgtRuleSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * L30 ACOS (%) → suggested daily budget tier (SBGT). Rule is a list of inclusive
 * ACOS % bands ({@see defaultSbgtBands}) persisted in {@see AmazonAcosSbgtRuleSetting}
 * (Amazon Ads “BGT rule”). Bands are evaluated top-to-bottom; the first band whose
 * From ≤ ACOS ≤ To wins. Use 9999 on To for the catch-all highest band.
 */
final class AmazonAcosSbgtRule
{
    public const CACHE_KEY = 'amazon_acos_sbgt_rule_resolved_v2';

    /**
     * Default From→To bands (high ACOS first).
     *
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    public static function defaultSbgtBands(): array
    {
        return [
            ['acos_from' => 40, 'acos_to' => 9999, 'sbgt' => 1, 'label' => 'Red', 'color' => '#dc2626'],
            ['acos_from' => 30, 'acos_to' => 40, 'sbgt' => 2, 'label' => 'Yellow', 'color' => '#ca8a04'],
            ['acos_from' => 20, 'acos_to' => 30, 'sbgt' => 4, 'label' => 'Blue', 'color' => '#2563eb'],
            ['acos_from' => 10, 'acos_to' => 20, 'sbgt' => 8, 'label' => 'Green', 'color' => '#16a34a'],
            ['acos_from' => 0, 'acos_to' => 10, 'sbgt' => 12, 'label' => 'Pink', 'color' => '#db2777'],
        ];
    }

    /**
     * @return array{bands: array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultSbgtBands()];
    }

    /**
     * Active rule (cached). Falls back to {@see defaults} when the table is missing or empty.
     * If file cache dirs are missing/unwritable, loads from DB without caching.
     *
     * @return array{bands: array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>}
     */
    public static function resolvedRule(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 86400, static fn (): array => self::loadResolvedRule());
        } catch (\Throwable) {
            return self::loadResolvedRule();
        }
    }

    /**
     * @return array{bands: array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_acos_sbgt_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAcosSbgtRuleSetting::query()->orderBy('id')->first();
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        return self::normalizeRule($row->rule);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Accepts either a band form ({bands: [...]}, or a bare list of bands) or the
     * legacy E1–E4 / sbgt_pink…sbgt_red form, and normalizes to {bands: [...]}.
     *
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>}
     */
    public static function normalizeRule(array $input): array
    {
        $bandsIn = null;
        if (isset($input['bands']) && is_array($input['bands'])) {
            $bandsIn = $input['bands'];
        } elseif (self::looksLikeBandList($input)) {
            $bandsIn = $input;
        } elseif (self::looksLikeLegacy($input)) {
            $bandsIn = self::legacyToBands($input);
        }

        $bands = ($bandsIn !== null && $bandsIn !== [])
            ? self::normalizeSbgtBands($bandsIn)
            : self::defaultSbgtBands();

        self::validateSbgtBands($bands);

        return ['bands' => $bands];
    }

    /**
     * @param  array{bands: array<int, array<string, mixed>>}  $rule
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
     * @param  array<int, array<string, mixed>>  $bands
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    public static function normalizeSbgtBands(array $bands): array
    {
        $out = [];
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $out[] = [
                'acos_from' => (float) ($band['acos_from'] ?? 0),
                'acos_to' => (float) ($band['acos_to'] ?? 9999),
                'sbgt' => (int) round((float) ($band['sbgt'] ?? 0)),
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private static function validateSbgtBands(array $bands): void
    {
        if ($bands === []) {
            throw new \InvalidArgumentException('Add at least one SBGT band.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['acos_from'] ?? NAN);
            $to = (float) ($band['acos_to'] ?? NAN);
            $sbgt = (int) ($band['sbgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('SBGT band '.($i + 1).': From and To must be finite numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('SBGT band '.($i + 1).': From must be ≤ To.');
            }
            if ($sbgt < 0 || $sbgt > 100_000) {
                throw new \InvalidArgumentException('SBGT band '.($i + 1).': SBGT must be between 0 and 100000 (0 pauses the campaign).');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function looksLikeBandList(array $input): bool
    {
        if ($input === [] || ! array_is_list($input)) {
            return false;
        }
        foreach ($input as $band) {
            if (is_array($band) && (array_key_exists('acos_from', $band) || array_key_exists('acos_to', $band) || array_key_exists('sbgt', $band))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function looksLikeLegacy(array $input): bool
    {
        return array_key_exists('e1', $input) || array_key_exists('sbgt_pink', $input);
    }

    /**
     * Convert the old E1–E4 / sbgt_* form into From→To bands (high ACOS first).
     *
     * @param  array<string, mixed>  $input
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    private static function legacyToBands(array $input): array
    {
        $e1 = (float) ($input['e1'] ?? 10);
        $e2 = (float) ($input['e2'] ?? 20);
        $e3 = (float) ($input['e3'] ?? 30);
        $e4 = (float) ($input['e4'] ?? 40);

        return [
            ['acos_from' => $e4, 'acos_to' => 9999, 'sbgt' => (int) ($input['sbgt_red'] ?? 1), 'label' => 'Red', 'color' => '#dc2626'],
            ['acos_from' => $e3, 'acos_to' => $e4, 'sbgt' => (int) ($input['sbgt_yellow'] ?? 2), 'label' => 'Yellow', 'color' => '#ca8a04'],
            ['acos_from' => $e2, 'acos_to' => $e3, 'sbgt' => (int) ($input['sbgt_blue'] ?? 4), 'label' => 'Blue', 'color' => '#2563eb'],
            ['acos_from' => $e1, 'acos_to' => $e2, 'sbgt' => (int) ($input['sbgt_green'] ?? 8), 'label' => 'Green', 'color' => '#16a34a'],
            ['acos_from' => 0, 'acos_to' => $e1, 'sbgt' => (int) ($input['sbgt_pink'] ?? 12), 'label' => 'Pink', 'color' => '#db2777'],
        ];
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
     * First finite numeric on the row for the given keys. When $preferPositive, a
     * stored 0 is skipped so a later key (e.g. `spend` after `cost`) can win.
     *
     * @param  array<string, mixed>  $r
     * @param  list<string>  $keys
     */
    private static function firstFiniteMetric(array $r, array $keys, bool $preferPositive = false): ?float
    {
        $zero = null;
        foreach ($keys as $k) {
            if (! array_key_exists($k, $r)) {
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
            if ($preferPositive && $n > 0) {
                return $n;
            }
            if ($preferPositive) {
                $zero = 0.0;

                continue;
            }

            return $n;
        }

        return $zero;
    }

    /**
     * L30 spend for ACOS: first positive of `cost` then `spend` (same order as
     * {@see \App\Http\Controllers\AmazonAdsController} / `l30DisplaySpendFromRowArray`).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function l30DisplaySpendForAcos(object|array $row): ?float
    {
        return self::firstFiniteMetric(self::reportRowArray($row), ['cost', 'spend'], true);
    }

    /**
     * Spend used only to decide the "spend exists, sold is 0 → ACOS 100%" sentinel.
     * L30 `cost`/`spend` first, then grid L7/L2/L1 spend overlays.
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function anySpendForAcosSentinel(object|array $row): ?float
    {
        $l30 = self::l30DisplaySpendForAcos($row);
        if ($l30 !== null && $l30 > 0) {
            return $l30;
        }
        $window = self::firstFiniteMetric(self::reportRowArray($row), ['L7spend', 'L2spend', 'L1spend'], true);
        if ($window !== null && $window > 0) {
            return $window;
        }

        return $l30;
    }

    /**
     * L30 sales for ACOS: prefer `sales30d`, else `sales` (Amazon Ads All / SP & SB L30 rows).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function l30SalesForAcos(object|array $row): ?float
    {
        return self::firstFiniteMetric(self::reportRowArray($row), ['sales30d', 'sales']);
    }

    /**
     * Ads Sold for ACOS: L30 purchases (`purchases30d`, SB `purchases`) or grid `Prchase`.
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function l30SoldForAcos(object|array $row): ?float
    {
        return self::firstFiniteMetric(self::reportRowArray($row), ['purchases30d', 'purchases', 'Prchase']);
    }

    /**
     * ACOS % from one L30 summary report row, aligned with {@see \App\Http\Controllers\AmazonAdsController::computedAcosPercentFromReportRow}
     * (whole-number percent like the All page ACOS column).
     *
     * Spend with Ads Sold = 0 is saved as 100% so BGT ACOS, SBGT, filters, pause rules,
     * and budget crons all use the same value (not 0% / pink).
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function acosPercentForSbgtFromReportRow(object|array $row): ?float
    {
        $c = self::l30DisplaySpendForAcos($row);
        $anySpend = self::anySpendForAcosSentinel($row);
        $sales = self::l30SalesForAcos($row);
        $sold = self::l30SoldForAcos($row);
        if ($c === null && $anySpend === null && $sales === null && $sold === null) {
            return null;
        }

        $spend = $c ?? 0.0;
        $sentinelSpend = $anySpend ?? $spend;

        // Spend exists and nothing sold → 100% ACOS (even if sales $ is missing or stale).
        if ($sentinelSpend > 0 && $sold !== null && $sold <= 0) {
            return 100.0;
        }
        if ($sales !== null && $sales > 0) {
            $base = $spend > 0 ? $spend : $sentinelSpend;
            $v = ($base / $sales) * 100;

            return is_finite($v) ? (float) round($v, 0) : null;
        }
        if ($sentinelSpend > 0) {
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
        $bands = self::resolvedRule()['bands'];

        if (is_finite($acos)) {
            foreach ($bands as $band) {
                $from = (float) ($band['acos_from'] ?? 0);
                $to = (float) ($band['acos_to'] ?? 9999);
                if ($acos >= $from && $acos <= $to) {
                    return (int) ($band['sbgt'] ?? 1);
                }
            }
        }

        return self::fallbackSbgtFromBands($bands);
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private static function fallbackSbgtFromBands(array $bands): int
    {
        $best = null;
        foreach ($bands as $band) {
            $from = (float) ($band['acos_from'] ?? 0);
            if ($best === null || $from < (float) ($best['acos_from'] ?? 0)) {
                $best = $band;
            }
        }

        return $best !== null ? (int) ($best['sbgt'] ?? 1) : 1;
    }

    /**
     * Distinct tier values allowed for SBGT push / validation.
     *
     * @return list<int>
     */
    public static function allowedSbgtTierValues(): array
    {
        $vals = [];
        foreach (self::resolvedRule()['bands'] as $band) {
            $vals[] = (int) ($band['sbgt'] ?? 0);
        }
        $vals = array_values(array_unique(array_filter($vals, static fn ($v) => $v > 0)));
        sort($vals, SORT_NUMERIC);

        return $vals;
    }

    /**
     * SQL CASE expression (numeric) for ORDER BY SBGT column, from an ACOS % scalar SQL fragment.
     */
    public static function sqlSortCaseExpression(string $acosExpr): string
    {
        $bands = self::resolvedRule()['bands'];
        $sql = 'CASE';
        foreach ($bands as $band) {
            $from = self::sqlNumberLiteral((float) ($band['acos_from'] ?? 0));
            $to = self::sqlNumberLiteral((float) ($band['acos_to'] ?? 9999));
            $sbgt = (int) ($band['sbgt'] ?? 1);
            $sql .= ' WHEN ('.$acosExpr.') >= '.$from.' AND ('.$acosExpr.') <= '.$to.' THEN '.$sbgt;
        }
        $sql .= ' ELSE '.self::fallbackSbgtFromBands($bands).' END';

        return $sql;
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
