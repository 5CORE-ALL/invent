<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Veeqo / GOFO (4Seller labels) / marketplace tracking onto unfulfilled
 * Shopify copies for every Marketplace Manager channel.
 */
class FetchMarketplaceShopifyTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1100;

    public int $uniqueFor = 1200;

    public array $backoff = [20, 60, 120];

    public function __construct(
        public int $limit = 250,
    ) {
        // Shared across all MM channels — do not sit behind Amazon order sync (1700s).
        $this->onQueue(MarketplaceManagerRegistry::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'mm-fetch-shopify-tracking';
    }

    public function handle(VeeqoShopifyFulfillmentService $sync): void
    {
        try {
            $result = $sync->syncPendingUnfulfilled($this->limit);
            Log::info('FetchMarketplaceShopifyTrackingJob: completed', $result);
        } catch (\Throwable $e) {
            Log::error('FetchMarketplaceShopifyTrackingJob: failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
