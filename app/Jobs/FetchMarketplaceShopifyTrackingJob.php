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
 * Fetch Veeqo / GOFO (4Seller labels) / marketplace tracking onto unfulfilled Shopify copies.
 */
class FetchMarketplaceShopifyTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 580;

    public function __construct(
        public int $limit = 120,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE);
    }

    public function uniqueId(): string
    {
        return 'mm-fetch-shopify-tracking';
    }

    public function handle(VeeqoShopifyFulfillmentService $sync): void
    {
        $result = $sync->syncPendingUnfulfilled($this->limit);
        Log::info('FetchMarketplaceShopifyTrackingJob: completed', $result);
    }
}
