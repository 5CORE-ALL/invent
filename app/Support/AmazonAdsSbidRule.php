<?php

namespace App\Support;

use App\Models\AmazonAdsSbidRuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * U2%/U1% utilization thresholds and CPC multipliers for suggested SBID (Amazon Ads All + bid jobs).
 */
final class AmazonAdsSbidRule
{
    public const CACHE_KEY = 'amazon_ads_sbid_rule_resolved_v1';

    /**
     * Same behavior as the original hard-coded {@see AmazonBidUtilizationService::sbidFromUb2Ub1Cpc} defaults.
     *
     * @return array{
     *     util_low: float,
     *     util_high: float,
     *     both_low_mult_l1: float,
     *     both_low_mult_l2: float,
     *     both_low_mult_l7: float,
     *     both_low_fallback: float,
     *     both_high_mult_l1: float
     * }
     */
    public static function defaults(): array
    {
        return [
            'util_low' => 66.0,
            'util_high' => 99.0,
            'both_low_mult_l1' => 1.1,
            'both_low_mult_l2' => 1.1,
            'both_low_mult_l7' => 1.1,
            'both_low_fallback' => 0.75,
            'both_high_mult_l1' => 0.9,
        ];
    }

    /**
     * @return array{
     *     util_low: float,
     *     util_high: float,
     *     both_low_mult_l1: float,
     *     both_low_mult_l2: float,
     *     both_low_mult_l7: float,
     *     both_low_fallback: float,
     *     both_high_mult_l1: float
     * }
     */
    public static function resolvedRule(): array
    {
        return Cache::remember(self::CACHE_KEY, 86400, static function (): array {
            if (! Schema::hasTable('amazon_ads_sbid_rule_settings')) {
                return self::defaults();
            }
            $row = AmazonAdsSbidRuleSetting::query()->orderBy('id')->first();
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
     * @return array{
     *     util_low: float,
     *     util_high: float,
     *     both_low_mult_l1: float,
     *     both_low_mult_l2: float,
     *     both_low_mult_l7: float,
     *     both_low_fallback: float,
     *     both_high_mult_l1: float
     * }
     */
    public static function normalizeRule(array $input): array
    {
        $d = self::defaults();
        $utilLow = (float) ($input['util_low'] ?? $d['util_low']);
        $utilHigh = (float) ($input['util_high'] ?? $d['util_high']);
        if (! is_finite($utilLow) || ! is_finite($utilHigh)) {
            throw new \InvalidArgumentException('Utilization thresholds must be finite numbers.');
        }
        if ($utilLow <= 0 || $utilLow > 200) {
            throw new \InvalidArgumentException('Low utilization threshold must be positive and at most 200.');
        }
        if ($utilHigh <= $utilLow || $utilHigh > 500) {
            throw new \InvalidArgumentException('High threshold must be greater than low threshold and at most 500.');
        }

        $mults = [];
        foreach (['both_low_mult_l1', 'both_low_mult_l2', 'both_low_mult_l7', 'both_high_mult_l1'] as $k) {
            $v = (float) ($input[$k] ?? $d[$k]);
            if (! is_finite($v) || $v < 0.01 || $v > 5.0) {
                throw new \InvalidArgumentException('Each CPC multiplier must be between 0.01 and 5.');
            }
            $mults[$k] = $v;
        }

        $fb = (float) ($input['both_low_fallback'] ?? $d['both_low_fallback']);
        if (! is_finite($fb) || $fb < 0.01 || $fb > 25.0) {
            throw new \InvalidArgumentException('Fallback SBID (no CPC) must be between 0.01 and 25.');
        }

        return [
            'util_low' => $utilLow,
            'util_high' => $utilHigh,
            'both_low_mult_l1' => $mults['both_low_mult_l1'],
            'both_low_mult_l2' => $mults['both_low_mult_l2'],
            'both_low_mult_l7' => $mults['both_low_mult_l7'],
            'both_low_fallback' => $fb,
            'both_high_mult_l1' => $mults['both_high_mult_l1'],
        ];
    }

    /**
     * @param  array{
     *     util_low: float,
     *     util_high: float,
     *     both_low_mult_l1: float,
     *     both_low_mult_l2: float,
     *     both_low_mult_l7: float,
     *     both_low_fallback: float,
     *     both_high_mult_l1: float
     * }  $rule
     */
    public static function persistRule(array $rule): void
    {
        if (! Schema::hasTable('amazon_ads_sbid_rule_settings')) {
            throw new \RuntimeException('Table amazon_ads_sbid_rule_settings does not exist. Run migrations.');
        }
        $row = AmazonAdsSbidRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsSbidRuleSetting::query()->create(['rule' => $rule]);
        } else {
            $row->update(['rule' => $rule]);
        }
        self::forgetResolvedCache();
    }
}
