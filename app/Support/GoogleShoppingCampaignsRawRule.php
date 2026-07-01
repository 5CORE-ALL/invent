<?php

namespace App\Support;

use App\Models\GoogleShoppingCampaignsRawRuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted ACOS → SBGT bands and 7UB/1UB → SBID multipliers for G-Shopping DB (raw) Tabulator.
 */
final class GoogleShoppingCampaignsRawRule
{
    public const CACHE_KEY = 'google_shopping_campaigns_raw_rule_resolved_v3';

    /**
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    public static function defaultSbgtBands(): array
    {
        return [
            ['acos_from' => 99.01, 'acos_to' => 9999, 'sbgt' => 1, 'label' => 'Critical', 'color' => '#dc2626'],
            ['acos_from' => 50, 'acos_to' => 99, 'sbgt' => 2, 'label' => 'Bad', 'color' => '#ef4444'],
            ['acos_from' => 40, 'acos_to' => 50, 'sbgt' => 3, 'label' => 'Poor', 'color' => '#f97316'],
            ['acos_from' => 30, 'acos_to' => 40, 'sbgt' => 5, 'label' => 'Fair', 'color' => '#ca8a04'],
            ['acos_from' => 20, 'acos_to' => 30, 'sbgt' => 10, 'label' => 'Good', 'color' => '#22c55e'],
            ['acos_from' => 0.01, 'acos_to' => 20, 'sbgt' => 20, 'label' => 'Excellent', 'color' => '#16a34a'],
            ['acos_from' => 0, 'acos_to' => 0, 'sbgt' => 3, 'label' => 'Zero ACOS', 'color' => '#6c757d'],
        ];
    }

    /**
     * @return array{sbgt: array{bands: array<int, array<string, mixed>>}, sbid: array<string, float>}
     */
    public static function defaults(): array
    {
        return [
            'sbgt' => [
                'bands' => self::defaultSbgtBands(),
            ],
            'sbid' => [
                'util_low' => 66.0,
                'util_high' => 99.0,
                'over_mult_l1' => 0.9,
                'under_mult_l1' => 1.1,
                'under_mult_l7' => 1.1,
                'under_fallback' => 0.75,
            ],
        ];
    }

    /**
     * @return array{sbgt: array{bands: array<int, array<string, mixed>>}, sbid: array<string, float>}
     */
    public static function resolvedRule(): array
    {
        if (! Schema::hasTable('google_shopping_campaigns_raw_rule_settings')) {
            return self::defaults();
        }

        $row = GoogleShoppingCampaignsRawRuleSetting::query()
            ->orderBy('id')
            ->first(['id', 'rule', 'updated_at']);

        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        $version = $row->updated_at !== null
            ? $row->updated_at->getTimestamp()
            : 0;
        $cacheKey = self::CACHE_KEY.':'.$row->id.':'.$version;

        $rule = $row->rule;

        return Cache::remember($cacheKey, 86400, static function () use ($rule): array {
            return self::normalizeRule(array_replace_recursive(self::defaults(), $rule));
        });
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);

        if (Schema::hasTable('google_shopping_campaigns_raw_rule_settings')) {
            $row = GoogleShoppingCampaignsRawRuleSetting::query()
                ->orderBy('id')
                ->first(['id', 'updated_at']);
            if ($row !== null) {
                $version = $row->updated_at !== null ? $row->updated_at->getTimestamp() : 0;
                Cache::forget(self::CACHE_KEY.':'.$row->id.':'.$version);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{sbgt: array{bands: array<int, array<string, mixed>>}, sbid: array<string, float>}
     */
    public static function normalizeRule(array $input): array
    {
        $d = self::defaults();
        $sbIn = is_array($input['sbgt'] ?? null) ? $input['sbgt'] : [];
        $sbidIn = is_array($input['sbid'] ?? null) ? $input['sbid'] : [];

        $bands = [];
        if (isset($sbIn['bands']) && is_array($sbIn['bands'])) {
            $bands = self::normalizeSbgtBands($sbIn['bands']);
        } elseif (self::looksLikeLegacySbgt($sbIn)) {
            $bands = self::legacySbgtToBands(self::normalizeLegacySbgtInput($sbIn));
        } else {
            $bands = self::defaultSbgtBands();
        }

        self::validateSbgtBands($bands);

        $sbid = [];
        foreach ($d['sbid'] as $k => $def) {
            $sbid[$k] = (float) ($sbidIn[$k] ?? $def);
        }
        $low = $sbid['util_low'];
        $high = $sbid['util_high'];
        if (! is_finite($low) || ! is_finite($high)) {
            throw new \InvalidArgumentException('SBID utilization thresholds must be finite.');
        }
        if ($low <= 0 || $low > 200) {
            throw new \InvalidArgumentException('SBID low threshold must be in (0, 200].');
        }
        if ($high <= $low || $high > 500) {
            throw new \InvalidArgumentException('SBID high threshold must be greater than low and at most 500.');
        }
        foreach (['over_mult_l1', 'under_mult_l1', 'under_mult_l7'] as $mk) {
            $m = $sbid[$mk];
            if (! is_finite($m) || $m < 0.01 || $m > 5.0) {
                throw new \InvalidArgumentException('SBID CPC multipliers must be between 0.01 and 5.');
            }
        }
        $fb = $sbid['under_fallback'];
        if (! is_finite($fb) || $fb < 0.01 || $fb > 25.0) {
            throw new \InvalidArgumentException('SBID fallback must be between 0.01 and 25.');
        }

        return ['sbgt' => ['bands' => $bands], 'sbid' => $sbid];
    }

    /**
     * @param  array{sbgt: array<string, mixed>, sbid: array<string, float>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        if (! Schema::hasTable('google_shopping_campaigns_raw_rule_settings')) {
            throw new \RuntimeException('Table google_shopping_campaigns_raw_rule_settings does not exist. Run migrations.');
        }
        $row = GoogleShoppingCampaignsRawRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            GoogleShoppingCampaignsRawRuleSetting::query()->create(['rule' => $rule]);
        } else {
            $row->update(['rule' => $rule]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @param  array{sbgt?: array<string, mixed>, sbid?: array<string, mixed>}|null  $rule  Full rule or null to resolve.
     */
    public static function sbgtFromAcos(float $acos, ?array $rule = null): int
    {
        $r = $rule ?? self::resolvedRule();
        $bands = self::normalizeSbgtBands($r['sbgt']['bands'] ?? []);

        if (! is_finite($acos) || $acos < 0) {
            $fallback = self::fallbackSbgtFromBands($bands);

            return $fallback ?? 20;
        }

        foreach ($bands as $band) {
            $from = (float) ($band['acos_from'] ?? 0);
            $to = (float) ($band['acos_to'] ?? 9999);
            if ($acos >= $from && $acos <= $to) {
                return (int) ($band['sbgt'] ?? 0);
            }
        }

        return self::fallbackSbgtFromBands($bands) ?? 1;
    }

    /**
     * @param  array{sbgt?: array<string, mixed>, sbid?: array<string, mixed>}|null  $rule
     */
    public static function sbidFromUb7Ub1Cpc(float $ub7, float $ub1, float $cpcL1, float $cpcL7, ?array $rule = null): ?float
    {
        $r = $rule ?? self::resolvedRule();
        $s = $r['sbid'];
        $low = (float) $s['util_low'];
        $high = (float) $s['util_high'];

        if ($ub7 > $high && $ub1 > $high) {
            $m = (float) $s['over_mult_l1'];

            return floor($cpcL1 * $m * 100.0) / 100.0;
        }
        if ($ub7 < $low && $ub1 < $low) {
            $fb = (float) $s['under_fallback'];
            $m1 = (float) $s['under_mult_l1'];
            $m7 = (float) $s['under_mult_l7'];
            if ($cpcL1 <= 0.0 && $cpcL7 <= 0.0) {
                return $fb;
            }
            if ($cpcL1 > 0.0) {
                return floor($cpcL1 * $m1 * 100.0) / 100.0;
            }

            return floor($cpcL7 * $m7 * 100.0) / 100.0;
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    public static function normalizeSbgtBands(array $bands): array
    {
        if ($bands === []) {
            return [];
        }

        $hasFromTo = false;
        foreach ($bands as $band) {
            if (array_key_exists('acos_from', $band) || array_key_exists('acos_to', $band)) {
                $hasFromTo = true;
                break;
            }
        }

        if (! $hasFromTo) {
            usort($bands, fn ($a, $b) => ((float) ($a['acos_max'] ?? 0)) <=> ((float) ($b['acos_max'] ?? 0)));
            $prevTo = 0.0;
            $converted = [];
            foreach ($bands as $band) {
                $to = (float) ($band['acos_max'] ?? 9999);
                $converted[] = [
                    'acos_from' => $prevTo,
                    'acos_to' => $to,
                    'sbgt' => (int) ($band['sbgt'] ?? 0),
                    'label' => (string) ($band['label'] ?? ''),
                    'color' => (string) ($band['color'] ?? '#6c757d'),
                ];
                $prevTo = $to;
            }
            $bands = $converted;
        }

        $out = [];
        foreach ($bands as $band) {
            $out[] = [
                'acos_from' => (float) ($band['acos_from'] ?? 0),
                'acos_to' => (float) ($band['acos_to'] ?? 9999),
                'sbgt' => (int) ($band['sbgt'] ?? 0),
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }

        usort($out, fn ($a, $b) => $b['acos_from'] <=> $a['acos_from']);

        return array_values(array_filter($out, static function (array $band): bool {
            return stripos((string) ($band['label'] ?? ''), 'below min') === false;
        }));
    }

    /**
     * @param  array<string, mixed>  $sbIn
     */
    private static function looksLikeLegacySbgt(array $sbIn): bool
    {
        return isset($sbIn['gt']) || isset($sbIn['ge_50']) || isset($sbIn['ge_low']);
    }

    /**
     * @param  array<string, mixed>  $sbIn
     * @return array<string, float|int>
     */
    private static function normalizeLegacySbgtInput(array $sbIn): array
    {
        $legacyDefaults = [
            'gt' => 99.0,
            'val_gt' => 1,
            'ge_50' => 50.0,
            'val_50_99' => 2,
            'ge_40' => 40.0,
            'val_40_50' => 3,
            'ge_30' => 30.0,
            'val_30_40' => 5,
            'ge_20' => 20.0,
            'val_20_30' => 10,
            'ge_low' => 0.01,
            'val_low' => 20,
            'val_else' => 20,
            'le_zero' => 0.0,
            'val_eq_zero' => 3,
        ];

        $sbgt = [];
        foreach ($legacyDefaults as $k => $def) {
            $sbgt[$k] = is_int($def)
                ? (int) round((float) ($sbIn[$k] ?? $def))
                : (float) ($sbIn[$k] ?? $def);
        }

        foreach (array_keys($sbgt) as $k) {
            $v = $sbgt[$k];
            if (! is_finite((float) $v)) {
                throw new \InvalidArgumentException('SBGT rule: '.$k.' must be a finite number.');
            }
        }

        $leZero = (float) $sbgt['le_zero'];
        $geLow = (float) $sbgt['ge_low'];
        $ge20 = (float) $sbgt['ge_20'];
        $ge30 = (float) $sbgt['ge_30'];
        $ge40 = (float) $sbgt['ge_40'];
        $ge50 = (float) $sbgt['ge_50'];
        $gt = (float) $sbgt['gt'];
        if (! ($geLow < $ge20 && $ge20 < $ge30 && $ge30 < $ge40 && $ge40 < $ge50 && $ge50 < $gt)) {
            throw new \InvalidArgumentException('SBGT thresholds must satisfy ge_low < ge_20 < ge_30 < ge_40 < ge_50 < gt.');
        }
        if ($leZero < 0 || $leZero >= $geLow) {
            throw new \InvalidArgumentException('SBGT le_zero threshold must satisfy 0 <= le_zero < ge_low.');
        }
        if ($geLow < 0 || $gt > 500) {
            throw new \InvalidArgumentException('SBGT band limits out of range.');
        }

        foreach (['val_gt', 'val_50_99', 'val_40_50', 'val_30_40', 'val_20_30', 'val_low', 'val_else', 'val_eq_zero'] as $vk) {
            $iv = (int) $sbgt[$vk];
            if ($iv < 1 || $iv > 100_000) {
                throw new \InvalidArgumentException('Each SBGT tier value must be between 1 and 100000.');
            }
            $sbgt[$vk] = $iv;
        }

        return $sbgt;
    }

    /**
     * Convert the old ge_* / gt threshold form into From–To bands (high ACOS first).
     *
     * @param  array<string, float|int>  $sbgt
     * @return array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>
     */
    private static function legacySbgtToBands(array $sbgt): array
    {
        $gt = (float) $sbgt['gt'];
        $geLow = (float) $sbgt['ge_low'];
        $leZero = (float) $sbgt['le_zero'];

        return [
            [
                'acos_from' => round($gt + 0.01, 3),
                'acos_to' => 9999,
                'sbgt' => (int) $sbgt['val_gt'],
                'label' => 'Above threshold',
                'color' => '#dc2626',
            ],
            [
                'acos_from' => (float) $sbgt['ge_50'],
                'acos_to' => $gt,
                'sbgt' => (int) $sbgt['val_50_99'],
                'label' => '50–'.$gt.'%',
                'color' => '#ef4444',
            ],
            [
                'acos_from' => (float) $sbgt['ge_40'],
                'acos_to' => (float) $sbgt['ge_50'],
                'sbgt' => (int) $sbgt['val_40_50'],
                'label' => '40–50%',
                'color' => '#f97316',
            ],
            [
                'acos_from' => (float) $sbgt['ge_30'],
                'acos_to' => (float) $sbgt['ge_40'],
                'sbgt' => (int) $sbgt['val_30_40'],
                'label' => '30–40%',
                'color' => '#ca8a04',
            ],
            [
                'acos_from' => (float) $sbgt['ge_20'],
                'acos_to' => (float) $sbgt['ge_30'],
                'sbgt' => (int) $sbgt['val_20_30'],
                'label' => '20–30%',
                'color' => '#22c55e',
            ],
            [
                'acos_from' => $geLow,
                'acos_to' => (float) $sbgt['ge_20'],
                'sbgt' => (int) $sbgt['val_low'],
                'label' => 'Excellent',
                'color' => '#16a34a',
            ],
            [
                'acos_from' => $leZero,
                'acos_to' => $leZero,
                'sbgt' => (int) $sbgt['val_eq_zero'],
                'label' => 'Zero ACOS',
                'color' => '#6c757d',
            ],
        ];
    }

    /**
     * @param  array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>  $bands
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
            if ($sbgt < 1 || $sbgt > 100_000) {
                throw new \InvalidArgumentException('SBGT band '.($i + 1).': SBGT must be between 1 and 100000.');
            }
        }
    }

    /**
     * @param  array<int, array{acos_from: float, acos_to: float, sbgt: int, label: string, color: string}>  $bands
     */
    private static function fallbackSbgtFromBands(array $bands): ?int
    {
        if ($bands === []) {
            return null;
        }

        $bestLowBand = null;
        foreach ($bands as $band) {
            $from = (float) ($band['acos_from'] ?? 0);
            $to = (float) ($band['acos_to'] ?? 0);
            if ($from === $to && $from === 0.0) {
                continue;
            }
            if ($bestLowBand === null || $from < (float) ($bestLowBand['acos_from'] ?? 0)) {
                $bestLowBand = $band;
            }
        }

        if ($bestLowBand !== null) {
            return (int) ($bestLowBand['sbgt'] ?? 0);
        }

        $last = end($bands);

        return $last ? (int) ($last['sbgt'] ?? 0) : null;
    }
}
