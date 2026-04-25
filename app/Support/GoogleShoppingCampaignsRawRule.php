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
    public const CACHE_KEY = 'google_shopping_campaigns_raw_rule_resolved_v1';

    /**
     * @return array{sbgt: array<string, float|int>, sbid: array<string, float>}
     */
    public static function defaults(): array
    {
        return [
            'sbgt' => [
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
     * @return array{sbgt: array<string, float|int>, sbid: array<string, float>}
     */
    public static function resolvedRule(): array
    {
        return Cache::remember(self::CACHE_KEY, 86400, static function (): array {
            if (! Schema::hasTable('google_shopping_campaigns_raw_rule_settings')) {
                return self::defaults();
            }
            $row = GoogleShoppingCampaignsRawRuleSetting::query()->orderBy('id')->first();
            if ($row === null || ! is_array($row->rule) || $row->rule === []) {
                return self::defaults();
            }

            return self::normalizeRule(array_replace_recursive(self::defaults(), $row->rule));
        });
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{sbgt: array<string, float|int>, sbid: array<string, float>}
     */
    public static function normalizeRule(array $input): array
    {
        $d = self::defaults();
        $sbIn = is_array($input['sbgt'] ?? null) ? $input['sbgt'] : [];
        $sbidIn = is_array($input['sbid'] ?? null) ? $input['sbid'] : [];

        $sbgt = [];
        foreach ($d['sbgt'] as $k => $def) {
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

        $geLow = (float) $sbgt['ge_low'];
        $ge20 = (float) $sbgt['ge_20'];
        $ge30 = (float) $sbgt['ge_30'];
        $ge40 = (float) $sbgt['ge_40'];
        $ge50 = (float) $sbgt['ge_50'];
        $gt = (float) $sbgt['gt'];
        if (! ($geLow < $ge20 && $ge20 < $ge30 && $ge30 < $ge40 && $ge40 < $ge50 && $ge50 < $gt)) {
            throw new \InvalidArgumentException('SBGT thresholds must satisfy ge_low < ge_20 < ge_30 < ge_40 < ge_50 < gt.');
        }
        if ($geLow < 0 || $gt > 500) {
            throw new \InvalidArgumentException('SBGT band limits out of range.');
        }

        foreach (['val_gt', 'val_50_99', 'val_40_50', 'val_30_40', 'val_20_30', 'val_low', 'val_else'] as $vk) {
            $iv = (int) $sbgt[$vk];
            if ($iv < 1 || $iv > 100_000) {
                throw new \InvalidArgumentException('Each SBGT tier value must be between 1 and 100000.');
            }
            $sbgt[$vk] = $iv;
        }

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

        return ['sbgt' => $sbgt, 'sbid' => $sbid];
    }

    /**
     * @param  array{sbgt: array<string, float|int>, sbid: array<string, float>}  $rule
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
        $b = $r['sbgt'];

        if (! is_finite($acos) || $acos < 0) {
            return (int) $b['val_else'];
        }
        if ($acos > (float) $b['gt']) {
            return (int) $b['val_gt'];
        }
        if ($acos >= (float) $b['ge_50']) {
            return (int) $b['val_50_99'];
        }
        if ($acos >= (float) $b['ge_40']) {
            return (int) $b['val_40_50'];
        }
        if ($acos >= (float) $b['ge_30']) {
            return (int) $b['val_30_40'];
        }
        if ($acos >= (float) $b['ge_20']) {
            return (int) $b['val_20_30'];
        }
        if ($acos >= (float) $b['ge_low']) {
            return (int) $b['val_low'];
        }

        return (int) $b['val_else'];
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
}
