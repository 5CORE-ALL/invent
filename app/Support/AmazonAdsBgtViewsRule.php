<?php

namespace App\Support;

use App\Models\AmazonAdsBgtViewsRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amz page View L30 (parent Sess30) → suggested daily budget (Bgt Views).
 * Six default slabs: 0–70 plus five subsequent bands; first-row From/To/Bgt autofills the rest.
 */
final class AmazonAdsBgtViewsRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_views_rule_resolved_v1';

    public const SLAB_COUNT = 6;

    /**
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return self::autofillFromFirst([
            'views_from' => 0,
            'views_to' => 70,
            'bgt' => 1,
            'label' => 'Red',
            'color' => '#a00211',
        ]);
    }

    /**
     * Build 6 slabs from the first 0–70-style band. Subsequent From/To step by the first width;
     * last To is 9999. Bgt Views increments by 1 from the first value.
     *
     * @param  array<string, mixed>  $first
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
     */
    public static function autofillFromFirst(array $first, ?array $existing = null): array
    {
        $from = is_numeric($first['views_from'] ?? null) ? (float) $first['views_from'] : 0.0;
        $to = is_numeric($first['views_to'] ?? null) ? (float) $first['views_to'] : 70.0;
        if ($to < $from) {
            $to = $from;
        }
        $width = $to - $from;
        if ($width <= 0) {
            $width = 70.0;
            $to = $from + $width;
        }
        $bgt0 = is_numeric($first['bgt'] ?? null) ? (int) $first['bgt'] : 1;
        if ($bgt0 < 1) {
            $bgt0 = 1;
        }
        $labels = ['Red', 'Yellow', 'Blue', 'Green', 'Pink', 'Purple'];
        $colors = ['#a00211', '#ffc107', '#2563eb', '#28a745', '#e83e8c', '#7c3aed'];

        $out = [];
        $cursorTo = $to;
        for ($i = 0; $i < self::SLAB_COUNT; $i++) {
            $prev = is_array($existing[$i] ?? null) ? $existing[$i] : [];
            if ($i === 0) {
                $bandFrom = $from;
                $bandTo = $to;
            } else {
                $bandFrom = $cursorTo + 1;
                $bandTo = ($i === self::SLAB_COUNT - 1) ? 9999.0 : ($cursorTo + $width);
            }
            $out[] = [
                'views_from' => $bandFrom,
                'views_to' => $bandTo,
                'bgt' => $bgt0 + $i,
                'label' => (string) ($prev['label'] ?? $labels[$i] ?? ('Band '.($i + 1))),
                'color' => (string) ($prev['color'] ?? $colors[$i] ?? '#6c757d'),
            ];
            $cursorTo = $bandTo;
        }
        if ($existing !== null && isset($existing[0]) && is_array($existing[0])) {
            $out[0]['label'] = (string) ($first['label'] ?? $existing[0]['label'] ?? $out[0]['label']);
            $out[0]['color'] = (string) ($first['color'] ?? $existing[0]['color'] ?? $out[0]['color']);
        } else {
            $out[0]['label'] = (string) ($first['label'] ?? $out[0]['label']);
            $out[0]['color'] = (string) ($first['color'] ?? $out[0]['color']);
        }

        return $out;
    }

    /**
     * @return array{bands: array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>}
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
     * @return array{bands: array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_views_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtViewsRuleSetting::query()->orderBy('id')->first();
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
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>}
     */
    public static function normalizeRule(array $input): array
    {
        $bandsIn = [];
        if (isset($input['bands']) && is_array($input['bands'])) {
            $bandsIn = $input['bands'];
        }
        $bands = [];
        foreach ($bandsIn as $band) {
            if (! is_array($band)) {
                continue;
            }
            $bands[] = [
                'views_from' => (float) ($band['views_from'] ?? 0),
                'views_to' => (float) ($band['views_to'] ?? 9999),
                'bgt' => (int) max(1, round((float) ($band['bgt'] ?? 1))),
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }
        if ($bands === []) {
            $bands = self::defaultBands();
        }

        return ['bands' => $bands];
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        self::ensureSettingsTable();
        $normalized = self::normalizeRule($rule);
        $row = AmazonAdsBgtViewsRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtViewsRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $views, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        $v = ($views !== null && is_finite($views)) ? $views : 0.0;
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['views_from'] ?? 0);
            $to = (float) ($band['views_to'] ?? 9999);
            if ($v >= $from && $v <= $to) {
                $bgt = (int) ($band['bgt'] ?? 0);

                return [
                    'bgt' => $bgt > 0 ? $bgt : null,
                    'color' => (string) ($band['color'] ?? '#6c757d'),
                    'label' => (string) ($band['label'] ?? ''),
                ];
            }
        }

        return $empty;
    }

    private static function ensureSettingsTable(): void
    {
        if (Schema::hasTable('amazon_ads_bgt_views_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_views_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_views_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_views_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
