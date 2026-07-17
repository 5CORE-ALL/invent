<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\ShopifyCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Shared Shopify live master warm (once for all marketplaces).
 * Syncs active catalog + write-through qty into shopify_skus.
 * Do not run on every marketplace listings page — use Marketplace Manager "Refresh Shopify".
 */
class WarmShopifyLiveCatalogCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STATUS_CACHE_KEY = 'mm.shopify.live_master.refresh_status';

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        // Shared job — use reverb queue worker which is always up for MM.
        $this->onQueue(MarketplaceManagerRegistry::queueFor('reverb'));
    }

    public function handle(ShopifyCatalogSyncService $sync): void
    {
        Cache::put(self::STATUS_CACHE_KEY, [
            'status' => 'running',
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        try {
            $result = $sync->syncCatalog('main');
            Cache::put(self::STATUS_CACHE_KEY, [
                'status' => ! empty($result['completed']) ? 'done' : 'partial',
                'finished_at' => now()->toDateTimeString(),
                'result' => $result,
            ], 3600);
            Log::info('WarmShopifyLiveCatalogCache: warmed shared Shopify live master', $result);
        } catch (\Throwable $e) {
            Cache::put(self::STATUS_CACHE_KEY, [
                'status' => 'failed',
                'finished_at' => now()->toDateTimeString(),
                'error' => $e->getMessage(),
            ], 3600);
            throw $e;
        }
    }
}
