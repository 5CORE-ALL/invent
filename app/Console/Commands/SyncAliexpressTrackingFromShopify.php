<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AliexpressTrackingSyncService;
use Illuminate\Console\Command;

class SyncAliexpressTrackingFromShopify extends Command
{
    protected $signature = 'aliexpress:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single AliExpress order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to AliExpress (declare/modify shipment).';

    public function handle(AliexpressTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = \App\Models\AliexpressOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("AliExpress order {$orderId} not found in aliexpress_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! AliexpressTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to AliExpress is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
