<?php

namespace App\Console\Commands;

use App\Models\NeweggOrderMetric;
use App\Services\MarketplaceManager\NeweggTrackingSyncService;
use Illuminate\Console\Command;

class SyncNeweggTrackingFromShopify extends Command
{
    protected $signature = 'newegg:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Newegg order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Newegg (Ship Order).';

    public function handle(NeweggTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = NeweggOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Newegg order {$orderId} not found in newegg_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! NeweggTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Newegg is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
