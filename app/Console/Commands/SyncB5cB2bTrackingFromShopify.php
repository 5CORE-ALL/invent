<?php

namespace App\Console\Commands;

use App\Models\B5cB2bOrder;
use App\Services\MarketplaceManager\B5cB2bTrackingSyncService;
use Illuminate\Console\Command;

class SyncB5cB2bTrackingFromShopify extends Command
{
    protected $signature = 'b5cb2b:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single B2B store_order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Business 5 Core (B2B).';

    public function handle(B5cB2bTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = B5cB2bOrder::query()
                ->where('store_order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Business 5 Core B2B order {$orderId} not found.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! B5cB2bTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Business 5 Core (B2B) is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
