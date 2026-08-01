<?php

namespace App\Console\Commands;

use App\Models\Ebay1OrderMetric;
use App\Services\MarketplaceManager\Ebay1TrackingSyncService;
use Illuminate\Console\Command;

class SyncEbay1TrackingFromShopify extends Command
{
    protected $signature = 'ebay1:sync-tracking|ebay1:sync-tracking-from-shopify
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single eBay 1 order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to eBay 1 (Ship Order).';

    public function handle(Ebay1TrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = Ebay1OrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("eBay 1 order {$orderId} not found in ebay1_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! Ebay1TrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to eBay 1 is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
