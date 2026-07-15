<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\ReverbLiveListingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Reverb listing cache (do not run on page request).
 */
class WarmReverbLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('reverb'));
    }

    public function handle(ReverbLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmReverbLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
