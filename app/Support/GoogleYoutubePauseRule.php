<?php

namespace App\Support;

use App\Models\GoogleYoutubePauseRuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube grid pause rule: Spend LT + ACOS LT slabs.
 * First matching slab marks the campaign PAUSED in the app Sts column.
 */
final class GoogleYoutubePauseRule
{
    public const CACHE_KEY = 'google_youtube_pause_rule_resolved_v1';

    /**
     * @return array{enabled: bool, slabs: list<array{spend_gt: float, acos_gt: float}>}
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'slabs' => [
                ['spend_gt' => 30.0, 'acos_gt' => 50.0],
            ],
        ];
    }

    /**
     * @return array{enabled: bool, slabs: list<array{spend_gt: float, acos_gt: float}>}
     */
    public static function resolved(): array
    {
        if (! Schema::hasTable('google_youtube_pause_rule_settings')) {
            return self::defaults();
        }

        $row = GoogleYoutubePauseRuleSetting::query()->orderBy('id')->first(['id', 'rule', 'updated_at']);
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        $version = $row->updated_at !== null ? $row->updated_at->getTimestamp() : 0;
        $cacheKey = self::CACHE_KEY.':'.$row->id.':'.$version;
        $rule = $row->rule;

        return Cache::remember($cacheKey, 86400, static fn () => self::normalize($rule));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{enabled: bool, slabs: list<array{spend_gt: float, acos_gt: float}>}
     */
    public static function normalize(array $input): array
    {
        $enabled = array_key_exists('enabled', $input)
            ? filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $raw = $input['slabs'] ?? null;
        if (! is_array($raw) || $raw === []) {
            return ['enabled' => $enabled, 'slabs' => self::defaults()['slabs']];
        }

        $slabs = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $spend = (float) ($row['spend_gt'] ?? $row['spend'] ?? 0);
            $acos = (float) ($row['acos_gt'] ?? $row['acos'] ?? 0);
            if (! is_finite($spend) || ! is_finite($acos)) {
                continue;
            }
            $slabs[] = [
                'spend_gt' => max(0.0, $spend),
                'acos_gt' => max(0.0, min(10000.0, $acos)),
            ];
        }

        if ($slabs === []) {
            $slabs = self::defaults()['slabs'];
        }

        return ['enabled' => $enabled, 'slabs' => $slabs];
    }

    /**
     * @param  array{enabled: bool, slabs: list<array{spend_gt: float, acos_gt: float}>}  $rule
     */
    public static function persist(array $rule): void
    {
        if (! Schema::hasTable('google_youtube_pause_rule_settings')) {
            throw new \RuntimeException('Table google_youtube_pause_rule_settings does not exist. Run migrations.');
        }

        $normalized = self::normalize($rule);
        $row = GoogleYoutubePauseRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            GoogleYoutubePauseRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetCache();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        if (! Schema::hasTable('google_youtube_pause_rule_settings')) {
            return;
        }
        $row = GoogleYoutubePauseRuleSetting::query()->orderBy('id')->first(['id', 'updated_at']);
        if ($row !== null) {
            $version = $row->updated_at !== null ? $row->updated_at->getTimestamp() : 0;
            Cache::forget(self::CACHE_KEY.':'.$row->id.':'.$version);
        }
    }

    /**
     * @param  array{enabled?: bool, slabs?: list<array{spend_gt: float, acos_gt: float}>}|null  $rule
     */
    public static function shouldPause(float $spendLt, float $acosLt, ?array $rule = null): bool
    {
        $r = $rule ?? self::resolved();
        if (empty($r['enabled'])) {
            return false;
        }

        foreach ($r['slabs'] ?? [] as $slab) {
            $spendGt = (float) ($slab['spend_gt'] ?? 0);
            $acosGt = (float) ($slab['acos_gt'] ?? 0);
            if ($spendLt > $spendGt && $acosLt > $acosGt) {
                return true;
            }
        }

        return false;
    }
}
