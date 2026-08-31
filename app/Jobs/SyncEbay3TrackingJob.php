<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Ebay3TrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to eBay 3 for linked orders.
 */
class SyncEbay3TrackingJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor('ebay3'));
    }

    public function uniqueId(): string
    {
        return 'mm-ebay3-tracking-sync';
    }

    public function handle(Ebay3TrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! Ebay3TrackingSyncService::canAutoPush()) {
            Log::info('SyncEbay3TrackingJob: skipped (push_tracking_to_ebay3 Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('ebay3', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPendingFromShopify($this->limit));
    }
}
