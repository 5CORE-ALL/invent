<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AliexpressLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live AliExpress listing cache (do not run on page request).
 */
class WarmAliexpressLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('aliexpress'));
    }

    public function handle(AliexpressLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmAliexpressLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
