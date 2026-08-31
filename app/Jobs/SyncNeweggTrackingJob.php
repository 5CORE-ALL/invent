<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\NeweggTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Newegg for linked orders.
 */
class SyncNeweggTrackingJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor('newegg'));
    }

    public function uniqueId(): string
    {
        return 'mm-newegg-tracking-sync';
    }

    public function handle(NeweggTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! NeweggTrackingSyncService::canAutoPush()) {
            Log::info('SyncNeweggTrackingJob: skipped (push_tracking_to_newegg Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('newegg', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPendingFromShopify($this->limit));
    }
}
