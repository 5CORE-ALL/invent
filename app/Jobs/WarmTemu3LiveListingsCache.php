<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\Temu3LiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background warm of full live Temu 3 listing cache (do not run on page request).
 */
class WarmTemu3LiveListingsCache implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 400;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('temu3').'-listings');
    }

    public function uniqueId(): string
    {
        return 'temu3-live-listings';
    }

    public function handle(Temu3LiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmTemu3LiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
