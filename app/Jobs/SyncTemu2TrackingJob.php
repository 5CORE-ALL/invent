<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Temu2TrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Temu 2 for linked orders.
 */
class SyncTemu2TrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('temu2'));
    }

    public function uniqueId(): string
    {
        return 'mm-temu2-tracking-sync';
    }

    public function handle(Temu2TrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! Temu2TrackingSyncService::canPushTracking()) {
            Log::info('SyncTemu2TrackingJob: skipped (push_tracking_to_temu2 Off)');

            return;
        }

        $result = $sync->syncPending($this->limit);

        Log::info('SyncTemu2TrackingJob: completed', $result);
    }
}
