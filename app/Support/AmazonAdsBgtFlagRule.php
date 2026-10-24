<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * BGT Vs FLAG: applies to GPFT-flagged campaigns only (green flag on /amazon-ads/all: the campaign has L30 spend
 * and its L30 ACOS% is below its GPFT%). The slab input is the gap = GPFT% − ACOS% in percentage points
 * (always above 0 for a flagged campaign). First matching slab wins, top to bottom, inclusive. The slab Bgt is
 * added to SBGT as one more part; campaigns without a flag get no part from this rule.
 * Default budgets are 0 so SBGT does not change until the slabs are set.
 */
final class AmazonAdsBgtFlagRule
{
    use StoresAmazonAdsRuleJson;

    public const CACHE_KEY = 'amazon_ads_bgt_flag_rule_resolved_v1';

    /**
     * @return array<int, array{flag_from: float, flag_to: float, bgt: int|float, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['flag_from' => 25, 'flag_to' => 9999, 'bgt' => 0, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['flag_from' => 10, 'flag_to' => 25, 'bgt' => 0, 'label' => 'Green', 'color' => '#28a745'],
            ['flag_from' => 0, 'flag_to' => 10, 'bgt' => 0, 'label' => 'Blue', 'color' => '#2563eb'],
        ];
    }

    /**
     * @return array{bands: array<int, array{flag_from: float, flag_to: float, bgt: int|float, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{flag_from: float, flag_to: float, bgt: int|float, label: string, color: string}>}
     */
    public static function resolvedRule(): array
    {
        $decoded = self::readStoredRule('flag');
        if ($decoded === null || $decoded === []) {
            return self::defaults();
        }

        return self::normalizeRule($decoded);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array{flag_from: float, flag_to: float, bgt: int|float, label: string, color: string}>}
     */
    public static function normalizeRule(array $input): array
    {
        $bandsIn = [];
        if (isset($input['bands']) && is_array($input['bands'])) {
            $bandsIn = array_values($input['bands']);
        }

        $bands = [];
        foreach ($bandsIn as $band) {
            if (! is_array($band)) {
                continue;
            }
            $bands[] = [
                'flag_from' => (float) ($band['flag_from'] ?? 0),
                'flag_to' => (float) ($band['flag_to'] ?? 9999),
                'bgt' => AmazonAdsSbgt::normalizeBgtValue($band['bgt'] ?? 0),
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }
        if ($bands === []) {
            $bands = self::defaultBands();
        } else {
            self::validateBands($bands);
        }

        return ['bands' => $bands];
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    public static function validateBands(array $bands): void
    {
        if ($bands === []) {
            throw new \InvalidArgumentException('Add at least one FLAG slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['flag_from'] ?? NAN);
            $to = (float) ($band['flag_to'] ?? NAN);
            $bgt = (float) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($bgt < -9_999_999 || $bgt > 9_999_999) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Flag must be between -9999999 and 9999999.');
            }
        }
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        $normalized = self::normalizeRule($rule);
        self::storeRuleJson('flag', $normalized);
        self::forgetResolvedCache();
    }

    /**
     * Gap in percentage points (GPFT% − ACOS%) for a GPFT-flagged campaign, otherwise null.
     * Flagged = L30 spend above 0 and L30 ACOS% below GPFT%, the same test as the Flag column.
     */
    public static function flagGap(?float $acosPct, ?float $gpft, ?float $spend): ?float
    {
        if ($acosPct === null || $gpft === null || $spend === null
            || ! is_finite($acosPct) || ! is_finite($gpft) || ! is_finite($spend)
            || ! ($spend > 0) || ! ($acosPct < $gpft)) {
            return null;
        }

        return round($gpft - $acosPct, 2);
    }

    /**
     * True when any slab has a non-zero budget. Lets bulk callers skip the L30 ACOS lookup while the rule is all zeros.
     *
     * @param  array{bands?: array<int, array<string, mixed>>}|null  $rule
     */
    public static function hasBudgets(?array $rule = null): bool
    {
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (is_array($band) && (float) ($band['bgt'] ?? 0) !== 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{bgt: int|float|null, color: string, label: string}
     */
    public static function apply(?float $gap, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($gap === null || ! is_finite($gap)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['flag_from'] ?? 0);
            $to = (float) ($band['flag_to'] ?? 9999);
            if ($gap >= $from && $gap <= $to) {
                return [
                    'bgt' => AmazonAdsSbgt::normalizeBgtValue($band['bgt'] ?? 0),
                    'color' => (string) ($band['color'] ?? '#6c757d'),
                    'label' => (string) ($band['label'] ?? ''),
                ];
            }
        }

        return $empty;
    }
}
