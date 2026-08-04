<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTokTrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('tiktok'));
    }

    public function uniqueId(): string
    {
        return 'mm-tiktok-tracking-sync';
    }

    public function handle(TikTokTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! TikTokTrackingSyncService::canAutoPush()) {
            Log::info('SyncTikTokTrackingJob: skipped (push_tracking_to_tiktok Off)');

            return;
        }

        $result = $sync->syncPendingFromShopify($this->limit);

        Log::info('SyncTikTokTrackingJob: completed', $result);
    }
}
