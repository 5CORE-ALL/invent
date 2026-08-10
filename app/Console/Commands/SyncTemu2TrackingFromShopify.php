<?php

namespace App\Console\Commands;

use App\Models\Temu2Order;
use App\Services\MarketplaceManager\Temu2TrackingSyncService;
use Illuminate\Console\Command;

class SyncTemu2TrackingFromShopify extends Command
{
    protected $signature = 'temu2:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Temu 2 parent_order_sn}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Temu 2 (self-fulfilled shipment confirm).';

    public function handle(Temu2TrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = Temu2Order::query()
                ->where('parent_order_sn', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Temu order {$orderId} not found in temu2_orders.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! Temu2TrackingSyncService::canPushTracking()) {
            $this->info('Skipped: Push Shopify tracking to Temu 2 is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPending(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
