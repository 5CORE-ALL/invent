<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\DobaLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmDobaLiveListingsCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('doba'));
    }

    public function handle(DobaLiveListingsService $service): void
    {
        $rows = $service->all(true);
        Log::info('WarmDobaLiveListingsCache: warmed', ['count' => count($rows)]);
    }
}
