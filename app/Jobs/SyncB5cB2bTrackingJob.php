<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\B5cB2bTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncB5cB2bTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, FulfillsShopifyBeforeChannelTracking;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public array $backoff = [20, 60, 120];

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function uniqueId(): string
    {
        return 'mm-b5cb2b-tracking-sync';
    }

    public function handle(B5cB2bTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! B5cB2bTrackingSyncService::canAutoPush()) {
            Log::info('SyncB5cB2bTrackingJob: skipped (push_tracking_to_b5cb2b Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('b5cb2b', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncFromShopify($this->limit));
    }
}
