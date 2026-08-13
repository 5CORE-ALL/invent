<?php

namespace App\Console\Commands;

use App\Models\AmazonOrder;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use Illuminate\Console\Command;

class SyncAmazonTrackingFromShopify extends Command
{
    protected $signature = 'amazon:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Amazon order id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Amazon confirmShipment.';

    public function handle(AmazonTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $order = AmazonOrder::query()
                ->where('amazon_order_id', $orderId)
                ->orderByDesc('id')
                ->first();

            if (! $order) {
                $this->error("Amazon order {$orderId} not found in amazon_orders.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($order);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! AmazonTrackingSyncService::canPushTracking()) {
            $this->info('Skipped: Push Shopify tracking to Amazon is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
