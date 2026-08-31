<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\DobaTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDobaTrackingJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor('doba'));
    }

    public function uniqueId(): string
    {
        return 'mm-doba-tracking-sync';
    }

    public function handle(DobaTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! DobaTrackingSyncService::canPushTracking()) {
            Log::info('SyncDobaTrackingJob: skipped (push_tracking_to_doba Off)');

            return;
        }

        $this->fulfillShopifyCopiesFirst('doba', $this->limit);
        $this->runTrackingSafely(fn () => $sync->syncPending($this->limit));
    }
}
