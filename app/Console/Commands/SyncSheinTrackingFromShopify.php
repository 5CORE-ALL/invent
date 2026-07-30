<?php

namespace App\Console\Commands;

use App\Models\SheinOrderMetric;
use App\Services\MarketplaceManager\SheinTrackingSyncService;
use Illuminate\Console\Command;

class SyncSheinTrackingFromShopify extends Command
{
    protected $signature = 'shein:sync-tracking-from-shopify
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Shein order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Shein (Ship Order).';

    public function handle(SheinTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = SheinOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Shein order {$orderId} not found in shein_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! SheinTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Shein is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
