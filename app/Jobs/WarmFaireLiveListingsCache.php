<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\FaireLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Faire listing cache (do not run on page request).
 */
class WarmFaireLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('faire'));
    }

    public function handle(FaireLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmFaireLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
