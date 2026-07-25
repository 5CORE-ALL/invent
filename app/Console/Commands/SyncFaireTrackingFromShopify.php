<?php

namespace App\Console\Commands;

use App\Models\FaireOrderMetric;
use App\Services\MarketplaceManager\FaireTrackingSyncService;
use Illuminate\Console\Command;

class SyncFaireTrackingFromShopify extends Command
{
    protected $signature = 'faire:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Faire order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Faire (Ship Order).';

    public function handle(FaireTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = FaireOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Faire order {$orderId} not found in faire_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! FaireTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Faire is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
