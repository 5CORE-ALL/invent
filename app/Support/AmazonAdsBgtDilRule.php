<?php

namespace App\Support;

use App\Models\AmazonAdsBgtDilRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Dil% (OV L30 ÷ INV × 100) → suggested daily budget (Bgt Dil).
 * Default 3 slabs match /amazon-tabulator-view Dil colors: Pink 50%+, Green 25–50, Red &lt;25.
 * Dynamic slabs evaluated top to bottom.
 */
final class AmazonAdsBgtDilRule
{
    public const CACHE_KEY = 'amazon_ads_bgt_dil_rule_resolved_v1';

    /**
     * @return array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['dil_from' => 50, 'dil_to' => 9999, 'bgt' => 3, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['dil_from' => 25, 'dil_to' => 50, 'bgt' => 2, 'label' => 'Green', 'color' => '#28a745'],
            ['dil_from' => 0, 'dil_to' => 25, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @return array{bands: array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>}
     */
    public static function defaults(): array
    {
        return ['bands' => self::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>}
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
     * @return array{bands: array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_bgt_dil_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsBgtDilRuleSetting::query()->orderBy('id')->first();
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
     * @return array{bands: array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>}
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
                'dil_from' => (float) ($band['dil_from'] ?? 0),
                'dil_to' => (float) ($band['dil_to'] ?? 9999),
                'bgt' => (int) round((float) ($band['bgt'] ?? 0)),
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
            throw new \InvalidArgumentException('Add at least one Dil slab.');
        }
        foreach ($bands as $i => $band) {
            $from = (float) ($band['dil_from'] ?? NAN);
            $to = (float) ($band['dil_to'] ?? NAN);
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
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt Dil must be between 0 and 100000.');
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
        $row = AmazonAdsBgtDilRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsBgtDilRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $dil, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($dil === null || ! is_finite($dil)) {
            return $empty;
        }
        $r = $rule ?? self::resolvedRule();
        foreach ($r['bands'] ?? [] as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['dil_from'] ?? 0);
            $to = (float) ($band['dil_to'] ?? 9999);
            if ($dil >= $from && $dil <= $to) {
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
        if (Schema::hasTable('amazon_ads_bgt_dil_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_bgt_dil_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_bgt_dil_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_bgt_dil_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }
}
