<?php

namespace App\Support;

use App\Models\AmazonAdsBgtReviewsRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Reviews (star rating) → suggested daily budget (Bgt Reviews).
 * Bands are a dynamic list of inclusive star ranges, evaluated top to bottom.
 */
final class AmazonAdsBgtReviewsRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_reviews_rule_resolved_v2';

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
        Cache::forget('amazon_ads_bgt_reviews_rule_resolved_v1');
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

        $bands = [];
        foreach ($bandsIn as $band) {
            if (! is_array($band)) {
                continue;
            }
            $bands[] = [
                'rev_from' => (float) ($band['rev_from'] ?? 0),
                'rev_to' => (float) ($band['rev_to'] ?? 5),
                'bgt' => (int) max(1, round((float) ($band['bgt'] ?? 1))),
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
            throw new \InvalidArgumentException('Add at least one Reviews slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['rev_from'] ?? NAN);
            $to = (float) ($band['rev_to'] ?? NAN);
            $bgt = (int) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From must be ≤ To.');
            }
            if ($bgt < 1 || $bgt > 100_000) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Reviews must be between 1 and 100000.');
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
        if ($rating === null || ! is_finite($rating)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        $bands = array_values(array_filter($r['bands'] ?? [], 'is_array'));
        $n = count($bands);
        foreach ($bands as $i => $band) {
            $from = (float) ($band['rev_from'] ?? 0);
            $to = (float) ($band['rev_to'] ?? 5);
            $nextFrom = ($i < $n - 1)
                ? (float) ($bands[$i + 1]['rev_from'] ?? ($to + 0.01))
                : null;
            $hit = ($rating >= $from && $rating <= $to)
                || ($nextFrom !== null && $rating > $to && $rating < $nextFrom);
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
