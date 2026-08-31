<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AlibabaTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push Shopify fulfillment tracking numbers to Alibaba for linked orders.
 */
class SyncAlibabaTrackingJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor('alibaba'));
    }

    public function uniqueId(): string
    {
        return 'mm-alibaba-tracking-sync';
    }

    public function handle(AlibabaTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! AlibabaTrackingSyncService::canAutoPush()) {
            Log::info('SyncAlibabaTrackingJob: skipped (push_tracking_to_alibaba Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('alibaba', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncFromShopify($this->limit));
    }
}
