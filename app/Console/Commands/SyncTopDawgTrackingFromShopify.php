<?php

namespace App\Console\Commands;

use App\Models\TopDawgOrderMetric;
use App\Services\MarketplaceManager\TopDawgTrackingSyncService;
use Illuminate\Console\Command;

class SyncTopDawgTrackingFromShopify extends Command
{
    protected $signature = 'topdawg:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single TopDawg order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to TopDawg (Ship Order).';

    public function handle(TopDawgTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = TopDawgOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("TopDawg order {$orderId} not found in topdawg_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! TopDawgTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to TopDawg is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
