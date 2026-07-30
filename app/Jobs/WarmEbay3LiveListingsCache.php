<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\Ebay3LiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live eBay 3 listing cache (do not run on page request).
 */
class WarmEbay3LiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('ebay3'));
    }

    public function handle(Ebay3LiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmEbay3LiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
