<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Cache;

/**
 * Rotate Inv SKU Mismatch pushes so one long catalog / one failing SKU
 * cannot block the rest of the tab.
 */
final class MarketplaceMismatchBatch
{
    public const DEFAULT_LIMIT = 16;

    public static function cacheKey(string $channel): string
    {
        return 'mm.'.strtolower(trim($channel)).'.mismatch.tried';
    }

    /**
     * @param  list<string>  $skus
     * @return array{batch: list<string>, remaining: int}
     */
    public static function take(string $channel, array $skus, ?int $limit = null): array
    {
        $channel = strtolower(trim($channel));
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '')));
        $limit = max(1, min(40, $limit ?? self::DEFAULT_LIMIT));
        $key = self::cacheKey($channel);

        if ($skus === []) {
            try {
                Cache::forget($key);
            } catch (\Throwable $e) {
                // ignore
            }

            return ['batch' => [], 'remaining' => 0];
        }

        $tried = [];
        try {
            $cached = Cache::get($key);
            $tried = is_array($cached) ? $cached : [];
        } catch (\Throwable $e) {
            $tried = [];
        }

        $fresh = [];
        foreach ($skus as $sku) {
            if (! isset($tried[strtoupper($sku)])) {
                $fresh[] = $sku;
            }
        }
        if ($fresh === []) {
            $fresh = $skus;
            $tried = [];
        }

        $batch = array_slice($fresh, 0, $limit);
        foreach ($batch as $sku) {
            $tried[strtoupper($sku)] = true;
        }
        try {
            Cache::put($key, $tried, now()->addHours(6));
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'batch' => $batch,
            'remaining' => max(0, count($skus) - count($batch)),
        ];
    }
}
