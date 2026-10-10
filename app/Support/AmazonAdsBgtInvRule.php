<?php

namespace App\Support;

use App\Models\AmazonAdsBgtInvRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * On-hand inventory (grid Inv) → suggested daily budget (Bgt Inv).
 * Dynamic slabs evaluated top to bottom. Default budgets are 0 so SBGT
 * does not change until the slabs are set.
 */
final class AmazonAdsBgtInvRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_inv_rule_resolved_v1';

    /**
     * @return array<int, array{inv_from: float, inv_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['inv_from' => 50, 'inv_to' => 9999, 'bgt' => 0, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['inv_from' => 10, 'inv_to' => 50, 'bgt' => 0, 'label' => 'Green', 'color' => '#28a745'],
            ['inv_from' => 0, 'inv_to' => 10, 'bgt' => 0, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @return array{bands: array<int, array{inv_from: float, inv_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{inv_from: float, inv_to: float, bgt: int, label: string, color: string}>}
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
     * @return array{bands: array<int, array{inv_from: float, inv_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_inv_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtInvRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{inv_from: float, inv_to: float, bgt: int, label: string, color: string}>}
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
                'inv_from' => (float) ($band['inv_from'] ?? 0),
                'inv_to' => (float) ($band['inv_to'] ?? 9999),
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
            throw new \InvalidArgumentException('Add at least one Inv slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['inv_from'] ?? NAN);
            $to = (float) ($band['inv_to'] ?? NAN);
            $bgt = (float) ($band['bgt'] ?? 0);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From and To must be numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': From must be ≤ To.');
            }
            if ($bgt < -100_000 || $bgt > 100_000) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Inv must be between -100000 and 100000.');
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
        $row = AmazonAdsBgtInvRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtInvRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|float|null, color: string, label: string}
     */
    public static function apply(?float $inv, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($inv === null || ! is_finite($inv)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['inv_from'] ?? 0);
            $to = (float) ($band['inv_to'] ?? 9999);
            if ($inv >= $from && $inv <= $to) {
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
        if (Schema::hasTable('amazon_ads_bgt_inv_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_inv_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_inv_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_inv_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
