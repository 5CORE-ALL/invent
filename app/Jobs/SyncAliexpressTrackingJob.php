<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AliexpressTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to AliExpress for linked orders.
 */
class SyncAliexpressTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('aliexpress'));
    }

    public function uniqueId(): string
    {
        return 'mm-aliexpress-tracking-sync';
    }

    public function handle(AliexpressTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! AliexpressTrackingSyncService::canAutoPush()) {
            Log::info('SyncAliexpressTrackingJob: skipped (push_tracking_to_aliexpress Off)');

            return;
        }

        $result = $sync->syncPendingFromShopify($this->limit);

        Log::info('SyncAliexpressTrackingJob: completed', $result);
    }
}
