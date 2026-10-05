<?php

namespace App\Console\Commands;

use App\Models\Temu3ApiOrder;
use App\Services\MarketplaceManager\Temu3TrackingSyncService;
use Illuminate\Console\Command;

class SyncTemu3TrackingFromShopify extends Command
{
    protected $signature = 'temu3:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Temu 3 parent_order_sn}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Temu 3 (self-fulfilled shipment confirm).';

    public function handle(Temu3TrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = Temu3ApiOrder::query()
                ->where('parent_order_sn', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Temu order {$orderId} not found in temu3_api_orders.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! Temu3TrackingSyncService::canPushTracking()) {
            $this->info('Skipped: Push Shopify tracking to Temu 3 is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPending(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
