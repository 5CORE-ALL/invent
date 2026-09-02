<?php

namespace App\Support;

use App\Models\AmazonAdsBgtPrcRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Price (Amz list / LMP) → suggested daily budget (BGT PRC).
 * Five fixed slabs: 20–40, 41–60, 61–100, 101–150, >150. Price below 20 has no Bgt.
 */
final class AmazonAdsBgtPrcRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_prc_rule_resolved_v1';

    public const SLAB_COUNT = 5;

    /**
     * @return array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['prc_from' => 20, 'prc_to' => 40, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
            ['prc_from' => 41, 'prc_to' => 60, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['prc_from' => 61, 'prc_to' => 100, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['prc_from' => 101, 'prc_to' => 150, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['prc_from' => 151, 'prc_to' => 9999, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
        ];
    }

    /**
     * @return array{bands: array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * Keep the five price ranges; overlay saved Bgt / label / color.
     *
     * @param  array<int, array<string, mixed>>|null  $existing
     * @return array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>
     */
    public static function lockedSlabs(?array $existing = null): array
    {
        $defaults = self::defaultBands();
        $out = [];
        foreach ($defaults as $i => $def) {
            $prev = is_array($existing[$i] ?? null) ? $existing[$i] : [];
            $bgt = is_numeric($prev['bgt'] ?? null) ? (int) $prev['bgt'] : (int) $def['bgt'];
            if ($bgt < 1) {
                $bgt = (int) $def['bgt'];
            }
            $out[] = [
                'prc_from' => (float) $def['prc_from'],
                'prc_to' => (float) $def['prc_to'],
                'bgt' => $bgt,
                'label' => (string) ($prev['label'] ?? $def['label']),
                'color' => (string) ($prev['color'] ?? $def['color']),
            ];
        }

        return $out;
    }

    /**
     * @return array{bands: array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>}
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
     * @return array{bands: array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_prc_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtPrcRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>}
     */
    public static function normalizeRule(array $input): array
    {
        $bandsIn = [];
        if (isset($input['bands']) && is_array($input['bands'])) {
            $bandsIn = array_values($input['bands']);
        }

        return ['bands' => self::lockedSlabs($bandsIn)];
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        self::ensureSettingsTable();
        $normalized = self::normalizeRule($rule);
        $row = AmazonAdsBgtPrcRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtPrcRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $price, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($price === null || ! is_finite($price) || $price < 20) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        $bands = array_values(array_filter($r['bands'] ?? [], 'is_array'));
        if ($bands === []) {
            return $empty;
        }
        $last = $bands[count($bands) - 1];
        if ($price > 150) {
            $bgt = (int) ($last['bgt'] ?? 0);

            return [
                'bgt' => $bgt > 0 ? $bgt : null,
                'color' => (string) ($last['color'] ?? '#6c757d'),
                'label' => (string) ($last['label'] ?? ''),
            ];
        }
        foreach ($bands as $i => $band) {
            if ($i === count($bands) - 1) {
                continue;
            }
            $from = (float) ($band['prc_from'] ?? 0);
            $to = (float) ($band['prc_to'] ?? 9999);
            $nextFrom = (float) ($bands[$i + 1]['prc_from'] ?? ($to + 1));
            $hit = ($price >= $from && $price <= $to)
                || ($price > $to && $price < $nextFrom);
            if (! $hit) {
                continue;
            }
            $bgt = (int) ($band['bgt'] ?? 0);

            return [
                'bgt' => $bgt > 0 ? $bgt : null,
                'color' => (string) ($band['color'] ?? '#6c757d'),
                'label' => (string) ($band['label'] ?? ''),
            ];
        }

        return $empty;
    }

    private static function ensureSettingsTable(): void
    {
        if (Schema::hasTable('amazon_ads_bgt_prc_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_prc_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_prc_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_prc_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
