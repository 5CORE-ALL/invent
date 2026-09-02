<?php

namespace App\Support;

use App\Models\AmazonAdsBgtCvrRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amz page CVR L30 (parent A L30 ÷ Sess30 × 100) → suggested daily budget (Bgt Cvr).
 * Six default slabs: 0–4 plus five subsequent bands; first-row From/To/Bgt autofills the rest.
 */
final class AmazonAdsBgtCvrRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_cvr_rule_resolved_v1';

    public const SLAB_COUNT = 6;

    /**
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return self::autofillFromFirst([
            'cvr_from' => 0,
            'cvr_to' => 4,
            'bgt' => 1,
            'label' => 'Red',
            'color' => '#a00211',
        ]);
    }

    /**
     * Build 6 slabs from the first 0–4-style band. Subsequent From/To step by the first width;
     * last To is 9999. Bgt Cvr increments by 1 from the first value.
     *
     * @param  array<string, mixed>  $first
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
     */
    public static function autofillFromFirst(array $first, ?array $existing = null): array
    {
        $from = is_numeric($first['cvr_from'] ?? null) ? (float) $first['cvr_from'] : 0.0;
        $to = is_numeric($first['cvr_to'] ?? null) ? (float) $first['cvr_to'] : 4.0;
        if ($to < $from) {
            $to = $from;
        }
        $width = $to - $from;
        if ($width <= 0) {
            $width = 4.0;
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
                // Contiguous with the previous To so decimal CVR (e.g. 4.2) is not dropped in a gap.
                $bandFrom = $cursorTo;
                $bandTo = ($i === self::SLAB_COUNT - 1) ? 9999.0 : ($cursorTo + $width);
            }
            $out[] = [
                'cvr_from' => $bandFrom,
                'cvr_to' => $bandTo,
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
        try {
            return Cache::remember(self::CACHE_KEY, 86400, static fn (): array => self::loadResolvedRule());
        } catch (\Throwable) {
            return self::loadResolvedRule();
        }
    }

    /**
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_cvr_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtCvrRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>}
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
                'cvr_from' => (float) ($band['cvr_from'] ?? 0),
                'cvr_to' => (float) ($band['cvr_to'] ?? 9999),
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
        $row = AmazonAdsBgtCvrRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtCvrRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
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
        if (Schema::hasTable('amazon_ads_bgt_cvr_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_cvr_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_cvr_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_cvr_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
