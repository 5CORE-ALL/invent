<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AmazonLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmAmazonLiveListingsCache implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::queueFor('amazon'));
    }

    public function uniqueId(): string
    {
        return 'mm-warm-amazon-live-listings';
    }

    public function handle(AmazonLiveListingsService $live): void
    {
        Log::info('WarmAmazonLiveListingsCache: starting');
        $rows = $live->all(true);
        Log::info('WarmAmazonLiveListingsCache: completed', ['count' => count($rows)]);
    }
}
