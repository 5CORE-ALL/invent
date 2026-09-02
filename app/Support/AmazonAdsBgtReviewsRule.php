<?php

namespace App\Support;

use App\Models\AmazonAdsBgtReviewsRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Reviews (star rating) → suggested daily budget (Bgt Reviews).
 * Four fixed slabs: 2.99–3.5, 3.51–4, 4.01–4.5, >4.5. Rating below 2.99 has no Bgt.
 */
final class AmazonAdsBgtReviewsRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_reviews_rule_resolved_v1';

    public const SLAB_COUNT = 4;

    /**
     * @return array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['rev_from' => 2.99, 'rev_to' => 3.5, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
            ['rev_from' => 3.51, 'rev_to' => 4.0, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['rev_from' => 4.01, 'rev_to' => 4.5, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['rev_from' => 4.51, 'rev_to' => 5.0, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
        ];
    }

    /**
     * @return array{bands: array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * Keep the four review ranges; overlay saved Bgt / label / color.
     *
     * @param  array<int, array<string, mixed>>|null  $existing
     * @return array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>
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
                'rev_from' => (float) $def['rev_from'],
                'rev_to' => (float) $def['rev_to'],
                'bgt' => $bgt,
                'label' => (string) ($prev['label'] ?? $def['label']),
                'color' => (string) ($prev['color'] ?? $def['color']),
            ];
        }

        return $out;
    }

    /**
     * @return array{bands: array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>}
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
     * @return array{bands: array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_reviews_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtReviewsRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>}
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
        $row = AmazonAdsBgtReviewsRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtReviewsRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $rating, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($rating === null || ! is_finite($rating) || $rating < 2.99) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        $bands = array_values(array_filter($r['bands'] ?? [], 'is_array'));
        if ($bands === []) {
            return $empty;
        }
        $last = $bands[count($bands) - 1];
        if ($rating > 4.5) {
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
            $from = (float) ($band['rev_from'] ?? 0);
            $to = (float) ($band['rev_to'] ?? 5);
            $nextFrom = (float) ($bands[$i + 1]['rev_from'] ?? ($to + 0.01));
            $hit = ($rating >= $from && $rating <= $to)
                || ($rating > $to && $rating < $nextFrom);
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
        if (Schema::hasTable('amazon_ads_bgt_reviews_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_reviews_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_reviews_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_reviews_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
