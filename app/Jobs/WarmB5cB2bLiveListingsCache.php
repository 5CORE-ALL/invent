<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\B5cB2bLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of live Business 5 Core B2B listing cache (do not run on page request).
 */
class WarmB5cB2bLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('b5cb2b'));
    }

    public function handle(B5cB2bLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmB5cB2bLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
