<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Shopify import dispatch helpers for Marketplace Manager channels.
 */
class MarketplaceShopifyImportQueue
{
    /**
     * Unique locks / crashed workers can leave import_status=queued with no jobs
     * row and no shopify_order_id. Reset those rows when this order's import job
     * is not actually sitting on the dedicated mm-{slug} queue.
     *
     * Do not require the whole queue to be empty — Amazon order-sync / tracking
     * jobs share mm-amazon and would otherwise freeze Shopify imports forever.
     *
     * @param  class-string  $modelClass
     * @param  callable(Builder):void|null  $constrain
     */
    public static function releaseStuckQueued(string $modelClass, string $queue, ?callable $constrain = null): int
    {
        $table = (new $modelClass)->getTable();
        if (! Schema::hasColumn($table, 'import_status')) {
            return 0;
        }

        $query = $modelClass::query()
            ->where('import_status', 'queued')
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            });

        if ($constrain) {
            $constrain($query);
        }

        $ids = $query->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        $blob = DB::table('jobs')->where('queue', $queue)->pluck('payload')->implode("\n");

        $stuck = [];
        foreach ($ids as $id) {
            if (self::payloadReferencesId($blob, $id)) {
                continue;
            }
            $stuck[] = $id;
        }

        if ($stuck === []) {
            return 0;
        }

        return (int) $modelClass::query()
            ->whereIn('id', $stuck)
            ->where('import_status', 'queued')
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->update(['import_status' => 'ready']);
    }

    public static function push(object $job, string $queue): void
    {
        try {
            (new UniqueLock(app('cache.store')))->release($job);
        } catch (\Throwable $e) {
            Log::debug('MarketplaceShopifyImportQueue: unique lock release skipped', [
                'error' => $e->getMessage(),
            ]);
        }

        Queue::connection('database')->pushOn($queue, $job);
    }

    /**
     * True when a database-queue payload still holds this model/job id
     * (PHP-serialized constructor ints, JSON ids, uniqueId suffixes).
     */
    protected static function payloadReferencesId(string $blob, int $id): bool
    {
        if ($blob === '' || $id <= 0) {
            return false;
        }

        $idStr = (string) $id;

        return str_contains($blob, ';i:'.$idStr.';')
            || str_contains($blob, ';s:'.strlen($idStr).':"'.$idStr.'";')
            || (bool) preg_match('/import[-:]'.$idStr.'(?!\d)/', $blob);
    }
}
