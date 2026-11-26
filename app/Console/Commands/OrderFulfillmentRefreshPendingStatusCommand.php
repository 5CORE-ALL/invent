<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Services\MarketplaceManager\AliexpressOrderSyncService;
use App\Services\MarketplaceManager\ChannelOrderDeliveryStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daily: ask each marketplace for the current status of every order that /order-fulfillment/pending still
 * shows, so orders that were cancelled / closed / shipped elsewhere stop showing as Pending.
 *
 * The normal order fetch only brings back orders that are still open, so a cancelled order keeps its old
 * "waiting to ship" status forever. This command re-reads the pending orders one by one:
 *  - AliExpress: re-reads each order and fixes the stored status (CANCELLED / FINISH / ...).
 *  - Other marketplaces: runs that marketplace's order-detail refresh for the order.
 * The page reads the database live, so it is updated as soon as this finishes.
 */
class OrderFulfillmentRefreshPendingStatusCommand extends Command
{
    public const LAST_RUN_CACHE_KEY = 'order_fulfillment.refresh_pending_status.last_run';

    protected $signature = 'order-fulfillment:refresh-pending-status
        {--limit=150 : Max pending orders to re-check per marketplace}
        {--max-minutes=40 : Stop re-checking after this many minutes}
        {--slug= : Only this marketplace (e.g. aliexpress)}';

    protected $description = 'Re-check the marketplace status of every pending order and update /order-fulfillment/pending.';

    public function handle(OrderFulfillmentController $page, AliexpressOrderSyncService $aliexpress): int
    {
        @ini_set('memory_limit', '1G');
        @set_time_limit(0);

        $limit = max(1, (int) $this->option('limit'));
        $deadline = now()->addMinutes(max(1, (int) $this->option('max-minutes')));
        $only = strtolower(trim((string) $this->option('slug')));

        $before = $page->pendingOrderIdsBySlug();
        $report = [];

        foreach ($before as $slug => $info) {
            if ($only !== '' && $slug !== $only) {
                continue;
            }
            if (now()->greaterThan($deadline)) {
                $this->warn('Time limit reached: the remaining marketplaces will be checked tomorrow.');
                break;
            }

            $checked = 0;
            $failed = 0;
            try {
                if ($slug === 'aliexpress') {
                    $r = $aliexpress->reconcilePendingOrders($limit);
                    $checked = $r['checked'];
                    $failed = $r['failed'];
                } else {
                    $class = ChannelOrderDeliveryStatus::detailServiceClass($slug);
                    if ($class === null || ! class_exists($class)) {
                        $report[$slug] = [$info['label'], count($info['orders']), 'no refresh available', count($info['orders'])];

                        continue;
                    }
                    $service = app($class);
                    if (! method_exists($service, 'fetchAndPersistOrderDetail')) {
                        $report[$slug] = [$info['label'], count($info['orders']), 'no refresh available', count($info['orders'])];

                        continue;
                    }
                    $acceptsRefresh = (new \ReflectionMethod($service, 'fetchAndPersistOrderDetail'))->getNumberOfParameters() > 1;
                    foreach (array_slice($info['orders'], 0, $limit) as $orderId) {
                        if (now()->greaterThan($deadline)) {
                            break;
                        }
                        $checked++;
                        try {
                            $res = $acceptsRefresh
                                ? $service->fetchAndPersistOrderDetail($orderId, true)
                                : $service->fetchAndPersistOrderDetail($orderId);
                            if (is_array($res) && empty($res['success'])) {
                                $failed++;
                            }
                        } catch (\Throwable $e) {
                            $failed++;
                            Log::info('order-fulfillment:refresh-pending-status order failed', [
                                'slug' => $slug, 'order' => $orderId, 'error' => $e->getMessage(),
                            ]);
                        }
                        usleep(200000);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('order-fulfillment:refresh-pending-status channel failed', ['slug' => $slug, 'error' => $e->getMessage()]);
                $this->warn($info['label'].': '.$e->getMessage());
            }

            $report[$slug] = [$info['label'], count($info['orders']), $checked.' re-checked'.($failed ? ", {$failed} failed" : ''), null];
        }

        // Re-read the page data to see what dropped out.
        $after = $page->pendingOrderIdsBySlug();
        $rows = [];
        $removed = 0;
        foreach ($report as $slug => [$label, $pendingBefore, $note, $fixedAfter]) {
            $pendingAfter = $fixedAfter ?? count($after[$slug]['orders'] ?? []);
            $gone = max(0, $pendingBefore - $pendingAfter);
            $removed += $gone;
            $rows[] = [$label, $pendingBefore, $note, $pendingAfter, $gone];
        }
        $this->table(['Marketplace', 'Pending before', 'Check', 'Pending after', 'No longer pending'], $rows);
        $this->info("Orders that dropped off Pending: {$removed}");

        Cache::put(self::LAST_RUN_CACHE_KEY, [
            'finished_at' => now()->toIso8601String(),
            'removed' => $removed,
        ], now()->addDays(7));
        Log::info('order-fulfillment:refresh-pending-status done', ['removed' => $removed]);

        return self::SUCCESS;
    }
}
