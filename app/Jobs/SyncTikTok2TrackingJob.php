<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTok2TrackingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTok2TrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('tiktok2'));
    }

    public function uniqueId(): string
    {
        return 'mm-tiktok2-tracking-sync';
    }

    public function handle(TikTok2TrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! TikTok2TrackingSyncService::canAutoPush()) {
            Log::info('SyncTikTok2TrackingJob: skipped (push_tracking_to_tiktok2 Off)');

            return;
        }

        $result = $sync->syncPendingFromShopify($this->limit);

        Log::info('SyncTikTok2TrackingJob: completed', $result);
    }
}
