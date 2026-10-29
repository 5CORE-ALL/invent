<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\ListingChannelRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** Runs the "Refresh listings" button for one marketplace listing page. */
class RefreshListingChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3300;

    /** Dedicated queue (worker kept alive by queue:watchdog) so a click never waits behind long price-rule jobs. */
    public const QUEUE = 'listing-refresh';

    public function __construct(public string $slug, public bool $manual = true)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(ListingChannelRefresher $refresher): void
    {
        $refresher->run($this->slug, $this->manual);
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put(ListingChannelRefresher::statusKey($this->slug), [
            'state' => 'failed',
            'message' => $e?->getMessage() ?: 'Refresh failed.',
            'started_at' => now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'steps' => [],
        ], now()->addDays(2));
    }
}
