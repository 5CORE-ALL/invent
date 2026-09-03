<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Shared From→To slab rule for Google Shopping BGT part columns.
 * Same evaluation as /amazon-ads/all (top to bottom, first hit wins),
 * stored in Google-only tables — not shared with Amazon.
 */
abstract class GoogleShoppingBgtSlabRule
{
    abstract public static function cacheKey(): string;

    abstract public static function tableName(): string;

    abstract public static function modelClass(): string;

    abstract public static function fromKey(): string;

    abstract public static function toKey(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract public static function defaultBands(): array;

    public static function slabNoun(): string
    {
        return 'slab';
    }

    public static function minBgt(): int
    {
        return 0;
    }

    public static function treatMissingInputAsZero(): bool
    {
        return true;
    }

    /** Reviews-style: a value between this To and the next From still counts. */
    public static function fillGapsBetweenBands(): bool
    {
        return false;
    }

    /**
     * @return array{bands: array<int, array<string, mixed>>}
     */
    public static function defaults(): array
    {
        return ['bands' => static::defaultBands()];
    }

    /**
     * @return array{bands: array<int, array<string, mixed>>}
     */
    public static function resolvedRule(): array
    {
        try {
            return Cache::remember(static::cacheKey(), 86400, static fn (): array => static::loadResolvedRule());
        } catch (\Throwable) {
            return static::loadResolvedRule();
        }
    }

    /**
     * @return array{bands: array<int, array<string, mixed>>}
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable(static::tableName())) {
            return static::defaults();
        }
        $model = static::modelClass();
        $row = $model::query()->orderBy('id')->first();
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return static::defaults();
        }

        return static::normalizeRule($row->rule);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(static::cacheKey());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{bands: array<int, array<string, mixed>>}
     */
    public static function normalizeRule(array $input): array
    {
        $fromKey = static::fromKey();
        $toKey = static::toKey();
        $bandsIn = [];
        if (isset($input['bands']) && is_array($input['bands'])) {
            $bandsIn = array_values($input['bands']);
        }

        $bands = [];
        foreach ($bandsIn as $band) {
            if (! is_array($band)) {
                continue;
            }
            $minBgt = static::minBgt();
            $bgt = (int) round((float) ($band['bgt'] ?? $minBgt));
            if ($minBgt >= 1) {
                $bgt = (int) max($minBgt, $bgt);
            }
            $bands[] = [
                $fromKey => (float) ($band[$fromKey] ?? 0),
                $toKey => (float) ($band[$toKey] ?? 9999),
                'bgt' => $bgt,
                'label' => (string) ($band['label'] ?? ''),
                'color' => (string) ($band['color'] ?? '#6c757d'),
            ];
        }
        if ($bands === []) {
            $bands = static::defaultBands();
        } else {
            $bands = static::maybeFlipLegacyRedFirst($bands);
            static::validateBands($bands);
        }

        return ['bands' => $bands];
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     * @return array<int, array<string, mixed>>
     */
    public static function maybeFlipLegacyRedFirst(array $bands): array
    {
        return $bands;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    public static function validateBands(array $bands): void
    {
        if ($bands === []) {
            throw new \InvalidArgumentException('Add at least one '.static::slabNoun().' slab.');
        }
        $fromKey = static::fromKey();
        $toKey = static::toKey();
        $minBgt = static::minBgt();
        foreach ($bands as $i => $band) {
            $from = (float) ($band[$fromKey] ?? NAN);
            $to = (float) ($band[$toKey] ?? NAN);
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
            if ($bgt < $minBgt || $bgt > 100_000) {
                throw new \InvalidArgumentException('Slab '.($i + 1).': Bgt must be between '.$minBgt.' and 100000.');
            }
        }
    }

    /**
     * @param  array{bands?: array<int, array<string, mixed>>}  $rule
     */
    public static function persistRule(array $rule): void
    {
        static::ensureSettingsTable();
        $normalized = static::normalizeRule($rule);
        $model = static::modelClass();
        $row = $model::query()->orderBy('id')->first();
        if ($row === null) {
            $model::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        static::forgetResolvedCache();
    }

    /**
     * @return array{bgt: int|null, color: string, label: string}
     */
    public static function apply(?float $value, ?array $rule = null): array
    {
        $empty = ['bgt' => null, 'color' => '#6c757d', 'label' => ''];
        if ($value === null || ! is_finite($value)) {
            if (! static::treatMissingInputAsZero()) {
                return $empty;
            }
            $value = 0.0;
        }
        $r = $rule ?? static::resolvedRule();
        $fromKey = static::fromKey();
        $toKey = static::toKey();
        $bands = array_values(array_filter($r['bands'] ?? [], 'is_array'));
        $n = count($bands);
        foreach ($bands as $i => $band) {
            $from = (float) ($band[$fromKey] ?? 0);
            $to = (float) ($band[$toKey] ?? 9999);
            $hit = ($value >= $from && $value <= $to);
            if (! $hit && static::fillGapsBetweenBands()) {
                $nextFrom = ($i < $n - 1)
                    ? (float) ($bands[$i + 1][$fromKey] ?? ($to + 0.01))
                    : null;
                $hit = ($nextFrom !== null && $value > $to && $value < $nextFrom);
            }
            if (! $hit) {
                continue;
            }
            $bgt = (int) ($band['bgt'] ?? 0);
            if ($bgt < static::minBgt()) {
                return $empty;
            }

            return [
                'bgt' => $bgt,
                'color' => (string) ($band['color'] ?? '#6c757d'),
                'label' => (string) ($band['label'] ?? ''),
            ];
        }

        return $empty;
    }

    private static function ensureSettingsTable(): void
    {
        $table = static::tableName();
        if (Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->longText('rule');
                $blueprint->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error($table.' create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create '.$table.': '.$e->getMessage(), 0, $e);
        }
    }
}
