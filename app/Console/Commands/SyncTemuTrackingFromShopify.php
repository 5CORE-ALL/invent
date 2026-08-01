<?php

namespace App\Console\Commands;

use App\Models\TemuOrder;
use App\Services\MarketplaceManager\TemuTrackingSyncService;
use Illuminate\Console\Command;

class SyncTemuTrackingFromShopify extends Command
{
    protected $signature = 'temu:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Temu parent_order_sn}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Temu (stub).';

    public function handle(TemuTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = TemuOrder::query()
                ->where('parent_order_sn', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Temu order {$orderId} not found in temu_orders.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! TemuTrackingSyncService::canPushTracking()) {
            $this->info('Skipped: Push Shopify tracking to Temu is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPending(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
