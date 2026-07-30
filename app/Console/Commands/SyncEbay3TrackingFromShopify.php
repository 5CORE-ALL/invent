<?php

namespace App\Console\Commands;

use App\Models\Ebay3OrderMetric;
use App\Services\MarketplaceManager\Ebay3TrackingSyncService;
use Illuminate\Console\Command;

class SyncEbay3TrackingFromShopify extends Command
{
    protected $signature = 'ebay3:sync-tracking-from-shopify
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single eBay 3 order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to eBay 3 (Ship Order).';

    public function handle(Ebay3TrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = Ebay3OrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("eBay 3 order {$orderId} not found in ebay3_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! Ebay3TrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to eBay 3 is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
