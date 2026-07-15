<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncInventoryToReverbManager implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // Reverb's live listing crawl alone can take 5-15+ minutes (see RunMarketplaceInventorySyncJob);
    // 600s was too short and made this job fail on every scheduled run. Stay under the
    // marketplace-manager-worker's --timeout=1800 so the job reports a clean failure instead
    // of the worker force-killing it right at the ceiling.
    public int $timeout = 1700;

    public function __construct()
    {
        $this->onQueue(\App\Services\MarketplaceManager\MarketplaceManagerRegistry::QUEUE);
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToReverb: starting');
        Artisan::call('reverb:manager-sync-inventory');
        Log::info('SyncInventoryToReverb: completed', ['output' => trim(Artisan::output())]);
    }
}
