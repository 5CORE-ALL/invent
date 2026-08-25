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
     * row and no shopify_order_id. Only reset when that mm queue is empty so we
     * do not double-dispatch work that is still running.
     *
     * @param  class-string  $modelClass
     * @param  callable(Builder):void|null  $constrain
     */
    public static function releaseStuckQueued(string $modelClass, string $queue, ?callable $constrain = null): int
    {
        if (DB::table('jobs')->where('queue', $queue)->exists()) {
            return 0;
        }

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

        return (int) $query->update(['import_status' => 'ready']);
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
}
