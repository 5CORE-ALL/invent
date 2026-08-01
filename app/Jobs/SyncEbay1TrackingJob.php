<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Ebay1TrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to eBay 1 for linked orders.
 */
class SyncEbay1TrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('ebay1'));
    }

    public function uniqueId(): string
    {
        return 'mm-ebay1-tracking-sync';
    }

    public function handle(Ebay1TrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! Ebay1TrackingSyncService::canAutoPush()) {
            Log::info('SyncEbay1TrackingJob: skipped (push_tracking_to_ebay1 Off)');

            return;
        }

        $result = $sync->syncPendingFromShopify($this->limit);

        Log::info('SyncEbay1TrackingJob: completed', $result);
    }
}
