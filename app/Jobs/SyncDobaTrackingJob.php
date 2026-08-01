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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

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

        $result = $sync->syncPending($this->limit);

        Log::info('SyncDobaTrackingJob: completed', $result);
    }
}
