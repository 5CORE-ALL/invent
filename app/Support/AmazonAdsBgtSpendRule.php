<?php

namespace App\Support;

use App\Models\AmazonAdsBgtSpendRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * L30 ad spend (grid cost) → suggested daily budget (Bgt Spend).
 * Dynamic slabs evaluated top to bottom.
 */
final class AmazonAdsBgtSpendRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_spend_rule_resolved_v1';

    /**
     * @return array<int, array{spend_from: float, spend_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['spend_from' => 50, 'spend_to' => 9999, 'bgt' => 3, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['spend_from' => 10, 'spend_to' => 50, 'bgt' => 2, 'label' => 'Green', 'color' => '#28a745'],
            ['spend_from' => 0, 'spend_to' => 10, 'bgt' => 1, 'label' => 'Blue', 'color' => '#2563eb'],
        ];
    }

    /**
     * @return array{bands: array<int, array{spend_from: float, spend_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{spend_from: float, spend_to: float, bgt: int, label: string, color: string}>}
     */
    public static function resolvedRule(): array
    {
        return self::loadResolvedRule();
    }

    /**
     * @return array{bands: array<int, array{spend_from: float, spend_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_spend_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtSpendRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{spend_from: float, spend_to: float, bgt: int, label: string, color: string}>}
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
                'spend_from' => (float) ($band['spend_from'] ?? 0),
                'spend_to' => (float) ($band['spend_to'] ?? 9999),
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
            throw new \InvalidArgumentException('Add at least one Spend slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['spend_from'] ?? NAN);
            $to = (float) ($band['spend_to'] ?? NAN);
            $bgt = (float) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From must be ≤ To.');
            }
            if ($bgt < -9_999_999 || $bgt > 9_999_999) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Spend must be between -9999999 and 9999999.');
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
        $row = AmazonAdsBgtSpendRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtSpendRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $spend, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($spend === null || ! is_finite($spend)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['spend_from'] ?? 0);
            $to = (float) ($band['spend_to'] ?? 9999);
            if ($spend >= $from && $spend <= $to) {
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

    private static function ensureSettingsTable(): void
    {
        if (Schema::hasTable('amazon_ads_bgt_spend_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_spend_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_spend_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_spend_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
