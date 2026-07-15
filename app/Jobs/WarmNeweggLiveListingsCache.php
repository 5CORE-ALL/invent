<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\NeweggLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Newegg listing cache (do not run on page request).
 */
class WarmNeweggLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('newegg'));
    }

    public function handle(NeweggLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmNeweggLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
