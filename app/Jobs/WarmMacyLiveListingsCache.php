<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\MacyLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Macy\'s listing cache (do not run on page request).
 */
class WarmMacyLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('macy'));
    }

    public function handle(MacyLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmMacyLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
