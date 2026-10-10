<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Amz page CVR L30 (parent A L30 ÷ Sess30 × 100) → suggested daily budget (Bgt Cvr).
 * Dynamic slabs evaluated top to bottom. Defaults are Purple (high CVR) → Red (low CVR).
 */
final class AmazonAdsBgtCvrRule
{
    use StoresAmazonAdsRuleJson;

    public const CACHE_KEY = 'amazon_ads_bgt_cvr_rule_resolved_v2';

    /**
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['cvr_from' => 20, 'cvr_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ['cvr_from' => 16, 'cvr_to' => 20, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['cvr_from' => 12, 'cvr_to' => 16, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['cvr_from' => 8, 'cvr_to' => 12, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['cvr_from' => 4, 'cvr_to' => 8, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['cvr_from' => 0, 'cvr_to' => 4, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
     */
    public static function resolvedRule(): array
    {
        return self::loadResolvedRule();
    }

    /**
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        $decoded = self::readStoredRule('cvr');
        if ($decoded === null || $decoded === []) {
            return self::defaults();
        }

        return self::normalizeRule($decoded);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('amazon_ads_bgt_cvr_rule_resolved_v1');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
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
                'cvr_from' => (float) ($band['cvr_from'] ?? 0),
                'cvr_to' => (float) ($band['cvr_to'] ?? 9999),
                'bgt' => AmazonAdsSbgt::normalizeBgtValue($band['bgt'] ?? 0),
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }
        if ($bands === []) {
            $bands = self::defaultBands();
        } else {
            $bands = self::maybeFlipLegacyRedFirst($bands);
            self::validateBands($bands);
        }

        return ['bands' => $bands];
    }

    /**
     * Old autofill listed Red (low CVR) at the top. Flip that 6-slab set so Purple is first.
     *
     * @param  array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>  $bands
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
     */
    public static function maybeFlipLegacyRedFirst(array $bands): array
    {
        if (count($bands) !== 6) {
            return $bands;
        }
        $labels = array_map(
            static fn (array $b): string => strtolower(trim((string) ($b['label'] ?? ''))),
            $bands
        );
        if ($labels !== ['red', 'yellow', 'blue', 'green', 'pink', 'purple']) {
            return $bands;
        }

        return array_values(array_reverse($bands));
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    public static function validateBands(array $bands): void
    {
        if ($bands === []) {
            throw new \InvalidArgumentException('Add at least one CVR slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['cvr_from'] ?? NAN);
            $to = (float) ($band['cvr_to'] ?? NAN);
            $bgt = (float) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($bgt < -9_999_999 || $bgt > 9_999_999) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Cvr must be between -9999999 and 9999999.');
            }
        }
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        $normalized = self::normalizeRule($rule);
        self::storeRuleJson('cvr', $normalized);
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $cvr, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        $v = ($cvr !== null && is_finite($cvr)) ? $cvr : 0.0;
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['cvr_from'] ?? 0);
            $to = (float) ($band['cvr_to'] ?? 9999);
            if ($v >= $from && $v <= $to) {
                $bgt = AmazonAdsSbgt::normalizeBgtValue($band['bgt'] ?? 0);

                return [
                    'bgt' => $bgt,
                    'color' => (string) ($band['color'] ?? '#6c757d'),
                    'label' => (string) ($band['label'] ?? ''),
                ];
            }
        }

        return $empty;
    }
}
