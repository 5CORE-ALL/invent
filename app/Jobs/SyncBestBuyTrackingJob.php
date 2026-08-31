<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\BestBuyTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to BestBuy for linked orders.
 */
class SyncBestBuyTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, FulfillsShopifyBeforeChannelTracking;

    public int $tries = 3;

    public int $timeout = 850;

    public int $uniqueFor = 900;

    public array $backoff = [20, 60, 120];

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('bestbuy'));
    }

    public function uniqueId(): string
    {
        return 'mm-bestbuy-tracking-sync';
    }

    public function handle(BestBuyTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! BestBuyTrackingSyncService::canPushTracking()) {
            Log::info('SyncBestBuyTrackingJob: skipped (push_tracking_to_bestbuy Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('bestbuy', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPending($this->limit));
    }
}
