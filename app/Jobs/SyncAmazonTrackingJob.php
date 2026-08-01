<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAmazonTrackingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 1000;

    public function __construct(
        public bool $respectSettings = true,
        public int $limit = 40,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function uniqueId(): string
    {
        return 'mm-amazon-tracking-sync';
    }

    public function handle(AmazonTrackingSyncService $sync): void
    {
        if ($this->respectSettings && ! AmazonTrackingSyncService::canPushTracking()) {
            Log::info('SyncAmazonTrackingJob: skipped (push_tracking_to_amazon Off)');

            return;
        }

        $result = $sync->syncFromShopify($this->limit);
        Log::info('SyncAmazonTrackingJob: completed', $result);
    }
}
