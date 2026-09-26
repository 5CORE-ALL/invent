<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Manual "Fetch tracking now" catch-up. Not unique — a click always runs.
 * Fulfills unfulfilled Shopify copies, then pushes tracking to every marketplace.
 */
class FetchMarketplaceShopifyTrackingNowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = false;

    public function __construct(
        public int $limit = 2500,
        public int $trackingLimit = 150,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function handle(VeeqoShopifyFulfillmentService $sync): void
    {
        try {
            $result = $sync->syncPendingUnfulfilled($this->limit, true, true);
            Log::info('FetchMarketplaceShopifyTrackingNowJob: shopify fulfill', is_array($result) ? $result : []);
        } catch (\Throwable $e) {
            Log::error('FetchMarketplaceShopifyTrackingNowJob: shopify fulfill failed', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Artisan::call('mm:push-orders-tracking', [
                '--days' => 14,
                '--skip-fetch' => true,
                '--skip-inventory' => true,
                '--tracking-limit' => $this->trackingLimit,
            ]);
            Log::info('FetchMarketplaceShopifyTrackingNowJob: marketplace push', [
                'output' => mb_substr(trim(Artisan::output()), 0, 2000),
            ]);
        } catch (\Throwable $e) {
            Log::error('FetchMarketplaceShopifyTrackingNowJob: marketplace push failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
