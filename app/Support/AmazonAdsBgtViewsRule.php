<?php

namespace App\Support;

use App\Models\AmazonAdsBgtViewsRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amz page View L30 (parent Sess30) → suggested daily budget (Bgt Views).
 * Dynamic slabs evaluated top to bottom. Defaults are Purple (high views) → Red (low views).
 */
final class AmazonAdsBgtViewsRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_views_rule_resolved_v2';

    /**
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['views_from' => 351, 'views_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ['views_from' => 281, 'views_to' => 350, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['views_from' => 211, 'views_to' => 280, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['views_from' => 141, 'views_to' => 210, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['views_from' => 71, 'views_to' => 140, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['views_from' => 0, 'views_to' => 70, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
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
        Cache::forget('amazon_ads_bgt_views_rule_resolved_v1');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>}
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
                'views_from' => (float) ($band['views_from'] ?? 0),
                'views_to' => (float) ($band['views_to'] ?? 9999),
                'bgt' => (int) round((float) ($band['bgt'] ?? 0)),
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
     * Old autofill listed Red (low views) at the top. Flip that 6-slab set so Purple is first.
     *
     * @param  array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>  $bands
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
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
            throw new \InvalidArgumentException('Add at least one Views slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['views_from'] ?? NAN);
            $to = (float) ($band['views_to'] ?? NAN);
            $bgt = (int) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From must be ≤ To.');
            }
            if ($from < 0) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From must be 0 or more.');
            }
            if ($bgt < 0 || $bgt > 100_000) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Views must be between 0 and 100000.');
            }
        }
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
                if ($bgt < 0) {
                    return $empty;
                }

                return [
                    'bgt' => $bgt,
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
