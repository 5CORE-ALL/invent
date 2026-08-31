<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\ReverbTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Reverb for linked orders.
 */
class SyncReverbTrackingJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor('reverb'));
    }

    public function uniqueId(): string
    {
        return 'mm-reverb-tracking-sync';
    }

    public function handle(ReverbTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! ReverbTrackingSyncService::canAutoPush()) {
            Log::info('SyncReverbTrackingJob: skipped (push_tracking_to_reverb Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('reverb', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPendingFromShopify($this->limit));
    }
}
