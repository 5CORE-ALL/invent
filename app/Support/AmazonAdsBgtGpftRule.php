<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Avg GPFT% of the campaign's SKU(s) (grid GPFT column, same as /amazon-tabulator-view) → suggested daily budget (Bgt GPFT).
 * Dynamic slabs evaluated top to bottom. GPFT can be negative. Default budgets are 0 so SBGT
 * does not change until the slabs are set.
 */
final class AmazonAdsBgtGpftRule
{
    use StoresAmazonAdsRuleJson;

    public const CACHE_KEY = 'amazon_ads_bgt_gpft_rule_resolved_v1';

    /**
     * @return array<int, array{gpft_from: float, gpft_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['gpft_from' => 30, 'gpft_to' => 9999, 'bgt' => 0, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['gpft_from' => 15, 'gpft_to' => 30, 'bgt' => 0, 'label' => 'Green', 'color' => '#28a745'],
            ['gpft_from' => -9999, 'gpft_to' => 15, 'bgt' => 0, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @return array{bands: array<int, array{gpft_from: float, gpft_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{gpft_from: float, gpft_to: float, bgt: int, label: string, color: string}>}
     */
    public static function resolvedRule(): array
    {
        $decoded = self::readStoredRule('gpft');
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
     * @return array{bands: array<int, array{gpft_from: float, gpft_to: float, bgt: int, label: string, color: string}>}
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
                'gpft_from' => (float) ($band['gpft_from'] ?? 0),
                'gpft_to' => (float) ($band['gpft_to'] ?? 9999),
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
            throw new \InvalidArgumentException('Add at least one GPFT slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['gpft_from'] ?? NAN);
            $to = (float) ($band['gpft_to'] ?? NAN);
            $bgt = (float) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($bgt < -9_999_999 || $bgt > 9_999_999) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt GPFT must be between -9999999 and 9999999.');
            }
        }
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        $normalized = self::normalizeRule($rule);
        self::storeRuleJson('gpft', $normalized);
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|float|null, color: string, label: string}
     */
    public static function apply(?float $gpft, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($gpft === null || ! is_finite($gpft)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['gpft_from'] ?? 0);
            $to = (float) ($band['gpft_to'] ?? 9999);
            if ($gpft >= $from && $gpft <= $to) {
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
