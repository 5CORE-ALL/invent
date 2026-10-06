<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * After a completed marketplace catalog sync, drop saved link rows whose
 * seller SKU was not in that catalog. Runs on every full sync, not once.
 * A short or partial catalog is left untouched.
 */
final class MarketplaceLinkMapPruner
{
    /**
     * @param  list<string>  $skus
     */
    public static function remember(string $channel, array $skus, bool $reset): void
    {
        $seen = $reset ? [] : Cache::get(self::key($channel), []);
        if (! is_array($seen)) {
            $seen = [];
        }
        foreach ($skus as $sku) {
            $sku = strtoupper(trim((string) $sku));
            if ($sku !== '') {
                $seen[$sku] = true;
            }
        }
        Cache::put(self::key($channel), $seen, now()->addHours(6));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function prune(string $modelClass, string $channel, string $skuColumn = 'sku'): int
    {
        $seen = Cache::get(self::key($channel), []);
        if (! is_array($seen) || count($seen) < 20) {
            Log::warning('Link map prune skipped: catalog was too small', [
                'channel' => $channel,
                'seen' => is_array($seen) ? count($seen) : 0,
            ]);

            return -1;
        }

        $existing = (int) $modelClass::query()->count();
        if ($existing > 0 && count($seen) < (int) floor($existing * 0.25)) {
            Log::warning('Link map prune skipped: seen SKUs are far below the saved map', [
                'channel' => $channel,
                'seen' => count($seen),
                'saved' => $existing,
            ]);

            return -1;
        }

        $dropIds = [];
        $modelClass::query()
            ->select(['id', $skuColumn])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($seen, $skuColumn, &$dropIds) {
                foreach ($rows as $row) {
                    $sku = strtoupper(trim((string) $row->{$skuColumn}));
                    if ($sku === '' || isset($seen[$sku])) {
                        continue;
                    }
                    $dropIds[] = $row->id;
                }
            });

        $removed = 0;
        foreach (array_chunk($dropIds, 500) as $ids) {
            $removed += $modelClass::query()->whereIn('id', $ids)->delete();
        }
        Cache::forget(self::key($channel));
        if ($removed > 0) {
            Log::info('Link map removed SKUs no longer on the seller catalog', [
                'channel' => $channel,
                'removed' => $removed,
            ]);
        }

        return $removed;
    }

    private static function key(string $channel): string
    {
        return 'mm_link_map_seen_skus_'.strtolower(trim($channel));
    }
}
