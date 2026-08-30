<?php

namespace App\Support;

use App\Models\AmazonAdsSbgtDoubleRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Overlay on the ACOS → SBGT (BGT) rule for /amazon-ads/all.
 *
 * Bands are the same Dil colors as the grid Dil column (fmtSkuDil):
 * gray (INV=0), red (&lt;16.66), yellow (&lt;25), green (&lt;50), pink (≥50).
 * Each color has its own on/off and multiplier. Amazon L30 must also be
 * at or below the shared threshold (default 0).
 */
final class AmazonAdsSbgtDoubleRule
{
    public const CACHE_KEY = 'amazon_ads_sbgt_double_rule_resolved_v3';

    /** Same cutoffs as resources/views/amazon_ads/all.blade.php fmtSkuDil. */
    public const DIL_RED_LT = 16.66;

    public const DIL_YELLOW_LT = 25.0;

    public const DIL_GREEN_LT = 50.0;

    public const COLOR_RED = '#a00211';

    public const COLOR_YELLOW = '#ffc107';

    public const COLOR_GREEN = '#28a745';

    public const COLOR_PINK = '#e83e8c';

    public const COLOR_GRAY = '#6c757d';

    /**
     * Fixed Dil-column colors. Only enabled + multiplier are persisted per key.
     *
     * @return list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     */
    public static function colorCatalog(): array
    {
        return [
            ['key' => 'red', 'label' => 'Dil red', 'color' => self::COLOR_RED, 'enabled' => true, 'multiplier' => 2.0],
            ['key' => 'yellow', 'label' => 'Dil yellow', 'color' => self::COLOR_YELLOW, 'enabled' => false, 'multiplier' => 2.0],
            ['key' => 'green', 'label' => 'Dil green', 'color' => self::COLOR_GREEN, 'enabled' => true, 'multiplier' => 2.0],
            ['key' => 'pink', 'label' => 'Dil pink', 'color' => self::COLOR_PINK, 'enabled' => false, 'multiplier' => 2.0],
            ['key' => 'gray', 'label' => 'Dil gray', 'color' => self::COLOR_GRAY, 'enabled' => false, 'multiplier' => 2.0],
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     amz_l30_max: float,
     *     bands: list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     * }
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'amz_l30_max' => 0.0,
            'bands' => self::colorCatalog(),
        ];
    }

    /**
     * Dil text color on /amazon-ads/all (same rules as fmtSkuDil).
     */
    public static function dilColorKey(?float $dil, ?float $inv): ?string
    {
        if ($inv !== null && is_finite($inv) && $inv <= 0) {
            return 'gray';
        }
        if ($dil === null || ! is_finite($dil)) {
            return null;
        }
        if ($dil < self::DIL_RED_LT) {
            return 'red';
        }
        if ($dil < self::DIL_YELLOW_LT) {
            return 'yellow';
        }
        if ($dil < self::DIL_GREEN_LT) {
            return 'green';
        }

        return 'pink';
    }

    /**
     * @return array{
     *     enabled: bool,
     *     amz_l30_max: float,
     *     bands: list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     * }
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
     * @return array{
     *     enabled: bool,
     *     amz_l30_max: float,
     *     bands: list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     * }
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_sbgt_double_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsSbgtDoubleRuleSetting::query()->orderBy('id')->first();
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        return self::normalizeRule($row->rule);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('amazon_ads_sbgt_double_rule_resolved_v2');
        Cache::forget('amazon_ads_sbgt_double_rule_resolved_v1');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     enabled: bool,
     *     amz_l30_max: float,
     *     bands: list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     * }
     */
    public static function normalizeRule(array $input): array
    {
        $d = self::defaults();
        $amzMax = (float) ($input['amz_l30_max'] ?? $d['amz_l30_max']);
        if (! is_finite($amzMax) || $amzMax < 0 || $amzMax > 1_000_000) {
            throw new \InvalidArgumentException('Amazon L30 threshold must be between 0 and 1000000.');
        }

        $savedByKey = self::indexSavedBands($input);

        $bands = [];
        foreach (self::colorCatalog() as $def) {
            $saved = $savedByKey[$def['key']] ?? null;
            $mult = $saved !== null
                ? (float) ($saved['multiplier'] ?? $def['multiplier'])
                : (float) $def['multiplier'];
            if (! is_finite($mult) || $mult < 1.0 || $mult > 20.0) {
                throw new \InvalidArgumentException($def['label'].': multiplier must be between 1 and 20.');
            }
            $bands[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'color' => $def['color'],
                'enabled' => $saved !== null
                    ? self::boolish($saved['enabled'] ?? true)
                    : (bool) $def['enabled'],
                'multiplier' => $mult,
            ];
        }

        return [
            'enabled' => self::boolish($input['enabled'] ?? $d['enabled']),
            'amz_l30_max' => $amzMax,
            'bands' => $bands,
        ];
    }

    /**
     * @param  array{
     *     enabled: bool,
     *     amz_l30_max: float,
     *     bands: list<array{key: string, label: string, color: string, enabled: bool, multiplier: float}>
     * }  $rule
     */
    public static function persistRule(array $rule): void
    {
        self::ensureSettingsTable();
        $normalized = self::normalizeRule($rule);
        $row = AmazonAdsSbgtDoubleRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsSbgtDoubleRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @param  array<string, mixed>|null  $skuMetrics
     * @return array{sbgt: int|null, doubled: bool, reason: string, multiplier: float|null}
     */
    public static function apply(?int $baseSbgt, ?array $skuMetrics, ?array $rule = null): array
    {
        $dil = isset($skuMetrics['dil']) && is_numeric($skuMetrics['dil'])
            ? (float) $skuMetrics['dil']
            : null;
        $amzL30 = isset($skuMetrics['l30']) && is_numeric($skuMetrics['l30'])
            ? (float) $skuMetrics['l30']
            : null;
        $inv = null;
        foreach (['inv', 'Inv'] as $k) {
            if (isset($skuMetrics[$k]) && is_numeric($skuMetrics[$k])) {
                $inv = (float) $skuMetrics[$k];
                break;
            }
        }

        return self::applyFromMetrics($baseSbgt, $dil, $amzL30, $inv, $rule);
    }

    /**
     * @return array{sbgt: int|null, doubled: bool, reason: string, multiplier: float|null}
     */
    public static function applyFromMetrics(?int $baseSbgt, ?float $dil, ?float $amzL30, ?float $inv = null, ?array $rule = null): array
    {
        $empty = ['sbgt' => $baseSbgt, 'doubled' => false, 'reason' => '', 'multiplier' => null];
        if ($baseSbgt === null || $baseSbgt < 1) {
            return $empty;
        }

        $r = $rule ?? self::resolvedRule();
        $hit = self::matchingColorBand($dil, $inv, $amzL30, $r);
        if ($hit === null) {
            return $empty;
        }

        $mult = (float) ($hit['multiplier'] ?? 2);
        $next = (int) max(1, (int) round($baseSbgt * $mult));
        $label = (string) ($hit['label'] ?? 'Dil');

        return [
            'sbgt' => $next,
            'doubled' => $next !== $baseSbgt,
            'multiplier' => $mult,
            'reason' => $label.' + Amz L30 '.self::formatNumber($amzL30 ?? 0)
                .' → ×'.self::formatNumber($mult).' ('.$baseSbgt.' → '.$next.')',
        ];
    }

    /**
     * @return list<int>
     */
    public static function allowedPushTierValues(): array
    {
        $base = AmazonAcosSbgtRule::allowedSbgtTierValues();
        $r = self::resolvedRule();
        if (empty($r['enabled'])) {
            return $base;
        }
        $out = $base;
        foreach (self::enabledMultipliers($r) as $mult) {
            foreach ($base as $tier) {
                $doubled = (int) max(1, (int) round($tier * $mult));
                if ($doubled > 0) {
                    $out[] = $doubled;
                }
            }
        }
        $out = array_values(array_unique(array_filter($out, static fn ($v) => $v > 0)));
        sort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<float>
     */
    public static function enabledMultipliers(array $rule): array
    {
        $out = [];
        foreach ($rule['bands'] ?? [] as $band) {
            if (! is_array($band) || empty($band['enabled'])) {
                continue;
            }
            $mult = (float) ($band['multiplier'] ?? 0);
            if (is_finite($mult) && $mult > 0 && ! in_array($mult, $out, true)) {
                $out[] = $mult;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{key: string, label: string, color: string, enabled: bool, multiplier: float}|null
     */
    private static function matchingColorBand(?float $dil, ?float $inv, ?float $amzL30, array $rule): ?array
    {
        if (empty($rule['enabled'])) {
            return null;
        }
        if ($amzL30 === null || ! is_finite($amzL30)) {
            return null;
        }
        $amzMax = (float) ($rule['amz_l30_max'] ?? 0);
        if ($amzL30 > $amzMax) {
            return null;
        }

        $key = self::dilColorKey($dil, $inv);
        if ($key === null) {
            return null;
        }

        foreach ($rule['bands'] ?? [] as $band) {
            if (! is_array($band) || empty($band['enabled'])) {
                continue;
            }
            if (($band['key'] ?? '') === $key) {
                return $band;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private static function indexSavedBands(array $input): array
    {
        $raw = [];
        if (isset($input['bands']) && is_array($input['bands'])) {
            $raw = $input['bands'];
        } else {
            $raw = self::legacyFlatBands($input);
        }

        $byKey = [];
        foreach ($raw as $band) {
            if (! is_array($band)) {
                continue;
            }
            $key = self::bandKeyFromSaved($band);
            if ($key === null || isset($byKey[$key])) {
                continue;
            }
            $byKey[$key] = $band;
        }

        return $byKey;
    }

    /**
     * @param  array<string, mixed>  $band
     */
    private static function bandKeyFromSaved(array $band): ?string
    {
        $key = strtolower(trim((string) ($band['key'] ?? '')));
        $allowed = ['red', 'yellow', 'green', 'pink', 'gray'];
        if (in_array($key, $allowed, true)) {
            return $key;
        }

        $label = strtolower(trim((string) ($band['label'] ?? '')));
        foreach ($allowed as $k) {
            if ($label !== '' && str_contains($label, $k)) {
                return $k;
            }
        }

        $color = strtolower(trim((string) ($band['color'] ?? '')));
        $map = [
            self::COLOR_RED => 'red',
            self::COLOR_YELLOW => 'yellow',
            self::COLOR_GREEN => 'green',
            self::COLOR_PINK => 'pink',
            self::COLOR_GRAY => 'gray',
        ];

        return $map[$color] ?? null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private static function legacyFlatBands(array $input): array
    {
        if (! array_key_exists('red_from', $input) && ! array_key_exists('green_from', $input)) {
            return [];
        }
        $mult = (float) ($input['multiplier'] ?? 2);
        if (! is_finite($mult) || $mult < 1) {
            $mult = 2.0;
        }

        return [
            ['key' => 'red', 'enabled' => self::boolish($input['red_enabled'] ?? true), 'multiplier' => $mult],
            ['key' => 'green', 'enabled' => self::boolish($input['green_enabled'] ?? true), 'multiplier' => $mult],
        ];
    }

    private static function formatNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    private static function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            $s = strtolower(trim($v));
            if (in_array($s, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($s, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $v;
    }

    private static function ensureSettingsTable(): void
    {
        if (Schema::hasTable('amazon_ads_sbgt_double_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_sbgt_double_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_sbgt_double_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_sbgt_double_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
