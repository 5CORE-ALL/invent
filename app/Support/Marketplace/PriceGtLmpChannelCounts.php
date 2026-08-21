<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Price > LMP (red triangle) counts for /price-gt-lmp.
 *
 * Rows are the same analytics pages as LMP Missing data.
 * Live page visits overwrite the count via storeReported().
 */
class PriceGtLmpChannelCounts
{
    public const TOTAL_CACHE_KEY = 'price_gt_lmp_total_v1';

    public const ROWS_CACHE_KEY = 'price_gt_lmp_rows_v1';

    public const REPORTED_CACHE_PREFIX = 'price_gt_lmp_reported_v1:';

    public static function resolveKey(string $channel): ?string
    {
        return LmpMissingChannelCounts::resolveKey($channel);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function masterRows(bool $useCache = true): array
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::ROWS_CACHE_KEY);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $rows = self::computeMasterRows();
        try {
            Cache::put(self::ROWS_CACHE_KEY, $rows, now()->addMinutes(10));
            Cache::put(self::TOTAL_CACHE_KEY, (int) collect($rows)->sum('price_gt_lmp'), now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    public static function totalCount(bool $useCache = true): int
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::TOTAL_CACHE_KEY);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return (int) collect(self::masterRows($useCache))->sum('price_gt_lmp');
    }

    public static function storeReported(string $channel, int $count): void
    {
        $key = self::resolveKey($channel);
        if ($key === null) {
            return;
        }
        $count = max(0, $count);
        try {
            Cache::put(self::REPORTED_CACHE_PREFIX.$key, $count, now()->addDay());
            Cache::forget(self::ROWS_CACHE_KEY);
            $total = self::totalCount(false);
            Cache::put(self::TOTAL_CACHE_KEY, $total, now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts storeReported failed: '.$e->getMessage());
        }
    }

    public static function cachedTotalOrZero(): int
    {
        try {
            $cached = Cache::get(self::TOTAL_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function computeMasterRows(): array
    {
        $masters = self::channelMasterByAlias();
        $rows = [];

        foreach (LmpMissingChannelCounts::analytics() as $key => $meta) {
            $master = self::matchMaster($masters, $meta['aliases'] ?? [], $meta['label'] ?? $key);
            $reported = self::reportedCount($key);

            $rows[] = [
                'id' => $master['id'] ?? $key,
                'key' => $key,
                'image' => $master['logo'] ?? null,
                'channel' => $meta['label'],
                'analytics_url' => url($meta['url']),
                'price_gt_lmp' => $reported !== null ? $reported : 0,
                'count_source' => $reported !== null ? 'page' : 'pending',
            ];
        }

        return $rows;
    }

    private static function reportedCount(string $key): ?int
    {
        try {
            $cached = Cache::get(self::REPORTED_CACHE_PREFIX.$key);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * @return array<string, array{id:mixed,logo:?string,channel:string}>
     */
    private static function channelMasterByAlias(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('channel_master')) {
            return [];
        }
        $hasLogo = \Illuminate\Support\Facades\Schema::hasColumn('channel_master', 'logo');
        $cols = ['id', 'channel'];
        if ($hasLogo) {
            $cols[] = 'logo';
        }

        $map = [];
        try {
            $rows = \App\Models\ChannelMaster::query()
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->get($cols);
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as $row) {
            $name = (string) $row->channel;
            $k = LmpMissingChannelCounts::normalize($name);
            $map[$k] = [
                'id' => $row->id,
                'logo' => $hasLogo ? ($row->logo ?? null) : null,
                'channel' => $name,
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{id:mixed,logo:?string,channel:string}>  $masters
     * @param  list<string>  $aliases
     * @return array{id:mixed,logo:?string,channel:string}|null
     */
    private static function matchMaster(array $masters, array $aliases, string $label): ?array
    {
        foreach (array_merge($aliases, [$label]) as $alias) {
            $k = LmpMissingChannelCounts::normalize((string) $alias);
            if ($k !== '' && isset($masters[$k])) {
                return $masters[$k];
            }
        }

        return null;
    }
}
