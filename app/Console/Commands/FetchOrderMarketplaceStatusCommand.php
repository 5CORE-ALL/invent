<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Services\MarketplaceManager\OrderMarketplaceStatusStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fills the "Order Status" column on /order-fulfillment.
 *
 *  1. Re-fetches the orders from every marketplace's own API (same fetch the hourly refresh uses).
 *  2. Saves the status each marketplace reports, with the time it was fetched.
 *  3. Where the marketplace itself says delivered / in transit, uses that as the Tracking Status
 *     when the carrier has not given a better one.
 *
 * Scheduled at 09:00 and 14:00 California time; the "Fetch tracking status from marketplace"
 * button on /order-fulfillment runs the same thing.
 */
class FetchOrderMarketplaceStatusCommand extends Command
{
    public const LAST_RUN_CACHE_KEY = 'order_fulfillment.marketplace_status.last_run';

    protected $signature = 'order-fulfillment:fetch-marketplace-status
        {--days=30 : How many days of orders to re-fetch from the marketplaces (1-30)}
        {--max-minutes=60 : Time limit for the marketplace fetch}
        {--skip-fetch : Do not call the marketplaces; only save the statuses already stored}';

    protected $description = 'Fetch order status from each marketplace and save it for the Order Status column on /order-fulfillment.';

    public function handle(OrderMarketplaceStatusStore $store): int
    {
        @ini_set('memory_limit', '1G');
        @set_time_limit(0);

        if (! OrderMarketplaceStatusStore::available()) {
            $this->error('Table order_fulfillment_marketplace_statuses is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        $started = now();
        $days = max(1, min(30, (int) $this->option('days')));
        $fetchNote = 'skipped';

        if (! $this->option('skip-fetch')) {
            $this->info('Fetching orders from the marketplaces ('.$days.' days)…');
            try {
                $exit = Artisan::call('mm:push-orders-tracking', [
                    '--days' => $days,
                    '--fetch-only' => true,
                    '--max-minutes' => max(5, min(240, (int) $this->option('max-minutes'))),
                ]);
                $fetchNote = $exit === 0 ? 'ok' : 'finished with errors';
                $output = trim(Artisan::output());
                if ($output !== '') {
                    $this->line(mb_substr($output, 0, 1500));
                }
            } catch (\Throwable $e) {
                // Still save what is stored: the previous hourly fetch is better than nothing.
                $fetchNote = 'failed: '.$e->getMessage();
                Log::warning('order-fulfillment:fetch-marketplace-status fetch failed', ['error' => $e->getMessage()]);
                $this->warn('Marketplace fetch failed: '.$e->getMessage());
            }
        }

        $this->info('Saving the marketplace order status of every order on the page…');
        try {
            $rows = app(OrderFulfillmentController::class)->rowsForMarketplaceStatusSync();
            $result = $store->snapshot($rows);
        } catch (\Throwable $e) {
            Log::error('order-fulfillment:fetch-marketplace-status snapshot failed', ['error' => $e->getMessage()]);
            $this->error('Could not save the statuses: '.$e->getMessage());

            return self::FAILURE;
        }

        Cache::put(self::LAST_RUN_CACHE_KEY, [
            'finished_at' => now()->toIso8601String(),
            'started_at' => $started->toIso8601String(),
            'orders_saved' => $result['saved'],
            'tracking_status_set' => $result['tracking_applied'],
            'marketplace_fetch' => $fetchNote,
        ], now()->addDays(7));

        $this->table(['Metric', 'Count'], [
            ['Marketplace fetch', $fetchNote],
            ['Order statuses saved', $result['saved']],
            ['Tracking status set from marketplace', $result['tracking_applied']],
        ]);

        return self::SUCCESS;
    }
}
