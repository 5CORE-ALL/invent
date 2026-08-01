<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\TemuLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Temu listing cache (do not run on page request).
 */
class WarmTemuLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('temu'));
    }

    public function handle(TemuLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmTemuLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
