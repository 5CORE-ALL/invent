<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Wayfair for linked orders.
 */
class SyncWayfairTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('wayfair'));
    }

    public function uniqueId(): string
    {
        return 'mm-wayfair-tracking-sync';
    }

    public function handle(WayfairTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! WayfairTrackingSyncService::canPushTracking()) {
            Log::info('SyncWayfairTrackingJob: skipped (push_tracking_to_wayfair Off)');

            return;
        }

        $result = $sync->syncPending($this->limit);

        Log::info('SyncWayfairTrackingJob: completed', $result);
    }
}
