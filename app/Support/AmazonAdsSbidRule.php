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
     *     avg_cpc_add: float,
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
            'avg_cpc_add' => 0.10,
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
     *     avg_cpc_add: float,
     *     both_high_mult_l1: float
     * }
     */
    public static function resolvedRule(): array
    {
        // Called for every grid row. Keep it to one lookup per few seconds, not one per row.
        $memo = ! app()->runningUnitTests();
        $now = microtime(true);
        if ($memo && self::$resolvedMemo !== null && self::$resolvedMemo[0] > $now) {
            return self::$resolvedMemo[1];
        }

        $rule = self::loadResolvedRule();
        if ($memo) {
            self::$resolvedMemo = [$now + 5, $rule];
        }

        return $rule;
    }

    /** @var array{0: float, 1: array<string, float>}|null */
    private static ?array $resolvedMemo = null;

    private static ?bool $settingsTableKnown = null;

    private static function settingsTableExists(): bool
    {
        if (self::$settingsTableKnown === true && ! app()->runningUnitTests()) {
            return true;
        }
        self::$settingsTableKnown = Schema::hasTable('amazon_ads_sbid_rule_settings');

        return self::$settingsTableKnown;
    }

    private static function loadResolvedRule(): array
    {
        if (! self::settingsTableExists()) {
            return self::defaults();
        }
        $row = AmazonAdsSbidRuleSetting::query()->orderBy('id')->first();
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        return self::normalizeRule(array_merge(self::defaults(), $row->rule));
    }

    public static function forgetResolvedCache(): void
    {
        self::$resolvedMemo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * True when both U2% and U1% are strictly below the configured low utilization threshold.
     *
     * @param  array{util_low?: float, util_high?: float}|null  $rule  Result of {@see resolvedRule()}, or null to resolve once.
     */
    public static function isBothBelowUtilLow(float $ub2, float $ub1, ?array $rule = null): bool
    {
        $r = $rule ?? self::resolvedRule();
        $low = (float) ($r['util_low'] ?? self::defaults()['util_low']);

        return $ub2 < $low && $ub1 < $low;
    }

    /**
     * True when both U2% and U1% are strictly above the configured high utilization threshold.
     *
     * @param  array{util_low?: float, util_high?: float}|null  $rule  Result of {@see resolvedRule()}, or null to resolve once.
     */
    public static function isBothAboveUtilHigh(float $ub2, float $ub1, ?array $rule = null): bool
    {
        $r = $rule ?? self::resolvedRule();
        $high = (float) ($r['util_high'] ?? self::defaults()['util_high']);

        return $ub2 > $high && $ub1 > $high;
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
     *     avg_cpc_add: float,
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

        $avgAdd = (float) ($input['avg_cpc_add'] ?? $d['avg_cpc_add']);
        if (! is_finite($avgAdd) || $avgAdd < 0 || $avgAdd > 5.0) {
            throw new \InvalidArgumentException('Avg CPC add-on must be between 0 and 5.');
        }

        return [
            'util_low' => $utilLow,
            'util_high' => $utilHigh,
            'both_low_mult_l1' => $mults['both_low_mult_l1'],
            'both_low_mult_l2' => $mults['both_low_mult_l2'],
            'both_low_mult_l7' => $mults['both_low_mult_l7'],
            'both_low_fallback' => $fb,
            'avg_cpc_add' => $avgAdd,
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
     *     avg_cpc_add: float,
     *     both_high_mult_l1: float
     * }  $rule
     */
    public static function persistRule(array $rule): void
    {
        if (! self::settingsTableExists()) {
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
