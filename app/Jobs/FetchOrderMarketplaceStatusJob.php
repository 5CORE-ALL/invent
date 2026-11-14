<?php

namespace App\Jobs;

use App\Console\Commands\FetchOrderMarketplaceStatusCommand;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * "Fetch tracking status from marketplace" button on /order-fulfillment: same work as the twice-daily run.
 */
class FetchOrderMarketplaceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STATE_CACHE_KEY = 'order_fulfillment.marketplace_status.button_state';

    public int $tries = 1;

    public int $timeout = 3300;

    public bool $failOnTimeout = false;

    public function __construct(public int $days = 14)
    {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function handle(): void
    {
        Cache::put(self::STATE_CACHE_KEY, [
            'state' => 'running',
            'message' => 'Fetching orders from the marketplaces…',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        try {
            $exit = Artisan::call('order-fulfillment:fetch-marketplace-status', [
                '--days' => $this->days,
                '--max-minutes' => 40,
            ]);
            $last = Cache::get(FetchOrderMarketplaceStatusCommand::LAST_RUN_CACHE_KEY);
            Cache::put(self::STATE_CACHE_KEY, [
                'state' => $exit === 0 ? 'done' : 'failed',
                'message' => $exit === 0
                    ? 'Saved '.(int) ($last['orders_saved'] ?? 0).' order statuses; tracking status set for '.(int) ($last['tracking_status_set'] ?? 0).'.'
                    : mb_substr(trim(Artisan::output()), 0, 300),
                'finished_at' => now()->toIso8601String(),
            ], now()->addHours(2));
        } catch (\Throwable $e) {
            $this->failed($e);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put(self::STATE_CACHE_KEY, [
            'state' => 'failed',
            'message' => $e?->getMessage() ?: 'Fetch failed.',
            'finished_at' => now()->toIso8601String(),
        ], now()->addHours(2));
    }
}
