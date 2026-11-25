<?php

namespace App\Support;

use App\Models\AmazonAdsSbidRuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * U2%/U1% utilization thresholds and CPC multipliers for suggested SBID (Amazon Ads All + bid jobs).
 *
 * Utilization bands (U7% and U1% are compared together):
 *  - "< very low" (default < 30):  both below util_vlow          -> vlow_* rule
 *  - "very low to low" (30 to 66): both below util_low, but not both below util_vlow -> both_low_* rule
 *  - between low and high:         no SBID
 *  - "> high" (default > 99):      both above util_high          -> both_high_mult_l1
 */
final class AmazonAdsSbidRule
{
    public const CACHE_KEY = 'amazon_ads_sbid_rule_resolved_v2';

    public const TIER_VLOW = 'vlow';

    public const TIER_LOW = 'low';

    /** Keys that belong to the "< very low" (default < 30) rule box. */
    public const VLOW_KEYS = [
        'vlow_mult_l1',
        'vlow_mult_l2',
        'vlow_mult_l7',
        'vlow_avg_cpc_add',
        'vlow_fallback',
    ];

    /**
     * Same behavior as the original hard-coded {@see AmazonBidUtilizationService::sbidFromUb2Ub1Cpc} defaults.
     * The new "< 30" box starts with the same multipliers as the 30-66 box, so nothing changes until it is edited.
     *
     * @return array{
     *     util_vlow: float,
     *     util_low: float,
     *     util_high: float,
     *     vlow_mult_l1: float,
     *     vlow_mult_l2: float,
     *     vlow_mult_l7: float,
     *     vlow_fallback: float,
     *     vlow_avg_cpc_add: float,
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
            'util_vlow' => 30.0,
            'util_low' => 66.0,
            'util_high' => 99.0,
            'vlow_mult_l1' => 1.1,
            'vlow_mult_l2' => 1.1,
            'vlow_mult_l7' => 1.1,
            'vlow_fallback' => 0.75,
            'vlow_avg_cpc_add' => 0.10,
            'both_low_mult_l1' => 1.1,
            'both_low_mult_l2' => 1.1,
            'both_low_mult_l7' => 1.1,
            'both_low_fallback' => 0.75,
            'avg_cpc_add' => 0.10,
            'both_high_mult_l1' => 0.9,
        ];
    }

    /**
     * @return array<string, float>
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

        $stored = $row->rule;
        $merged = array_merge(self::defaults(), $stored);
        // A rule saved before the "< 30" box existed: let normalizeRule() pick a threshold that fits its Low %.
        if (! array_key_exists('util_vlow', $stored)) {
            unset($merged['util_vlow']);
        }

        return self::normalizeRule($merged);
    }

    public static function forgetResolvedCache(): void
    {
        self::$resolvedMemo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * True when both U2% and U1% are strictly below the configured low utilization threshold
     * (this covers the "< very low" and the "very low to low" boxes together).
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
     * True when both U2% and U1% are strictly below the "very low" threshold (default 30).
     *
     * @param  array{util_vlow?: float}|null  $rule
     */
    public static function isBothBelowUtilVeryLow(float $ub2, float $ub1, ?array $rule = null): bool
    {
        $r = $rule ?? self::resolvedRule();
        $vlow = (float) ($r['util_vlow'] ?? self::defaults()['util_vlow']);

        return $ub2 < $vlow && $ub1 < $vlow;
    }

    /**
     * Which "under" box a campaign falls in: 'vlow' (both < 30), 'low' (both < 66 but not both < 30), or null.
     *
     * @param  array<string, float>|null  $rule
     */
    public static function underTier(float $ub2, float $ub1, ?array $rule = null): ?string
    {
        $r = $rule ?? self::resolvedRule();
        if (! self::isBothBelowUtilLow($ub2, $ub1, $r)) {
            return null;
        }

        return self::isBothBelowUtilVeryLow($ub2, $ub1, $r) ? self::TIER_VLOW : self::TIER_LOW;
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
     * @return array<string, float>
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

        if (array_key_exists('util_vlow', $input) && $input['util_vlow'] !== null && $input['util_vlow'] !== '') {
            $utilVlow = (float) $input['util_vlow'];
        } else {
            // Not given: the default 30, or half of Low % when Low % is 30 or less.
            $utilVlow = $d['util_vlow'] < $utilLow ? $d['util_vlow'] : $utilLow / 2;
        }
        if (! is_finite($utilVlow)) {
            throw new \InvalidArgumentException('Utilization thresholds must be finite numbers.');
        }
        if ($utilVlow <= 0 || $utilVlow >= $utilLow) {
            throw new \InvalidArgumentException('"< Utilized" threshold must be positive and below the Low % threshold.');
        }

        $mults = [];
        foreach (['both_low_mult_l1', 'both_low_mult_l2', 'both_low_mult_l7', 'both_high_mult_l1', 'vlow_mult_l1', 'vlow_mult_l2', 'vlow_mult_l7'] as $k) {
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
        $vfb = (float) ($input['vlow_fallback'] ?? $d['vlow_fallback']);
        if (! is_finite($vfb) || $vfb < 0.01 || $vfb > 25.0) {
            throw new \InvalidArgumentException('Fallback SBID (no CPC) must be between 0.01 and 25.');
        }

        $avgAdd = (float) ($input['avg_cpc_add'] ?? $d['avg_cpc_add']);
        if (! is_finite($avgAdd) || $avgAdd < 0 || $avgAdd > 5.0) {
            throw new \InvalidArgumentException('Avg CPC add-on must be between 0 and 5.');
        }
        $vAvgAdd = (float) ($input['vlow_avg_cpc_add'] ?? $d['vlow_avg_cpc_add']);
        if (! is_finite($vAvgAdd) || $vAvgAdd < 0 || $vAvgAdd > 5.0) {
            throw new \InvalidArgumentException('Avg CPC add-on must be between 0 and 5.');
        }

        return [
            'util_vlow' => $utilVlow,
            'util_low' => $utilLow,
            'util_high' => $utilHigh,
            'vlow_mult_l1' => $mults['vlow_mult_l1'],
            'vlow_mult_l2' => $mults['vlow_mult_l2'],
            'vlow_mult_l7' => $mults['vlow_mult_l7'],
            'vlow_fallback' => $vfb,
            'vlow_avg_cpc_add' => $vAvgAdd,
            'both_low_mult_l1' => $mults['both_low_mult_l1'],
            'both_low_mult_l2' => $mults['both_low_mult_l2'],
            'both_low_mult_l7' => $mults['both_low_mult_l7'],
            'both_low_fallback' => $fb,
            'avg_cpc_add' => $avgAdd,
            'both_high_mult_l1' => $mults['both_high_mult_l1'],
        ];
    }

    /**
     * @param  array<string, float>  $rule  Result of {@see normalizeRule()}
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
