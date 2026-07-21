<?php

namespace App\Console\Commands;

use App\Models\ReverbOrderMetric;
use App\Services\MarketplaceManager\ReverbTrackingSyncService;
use Illuminate\Console\Command;

class SyncReverbTrackingFromShopify extends Command
{
    protected $signature = 'reverb:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Reverb order_number}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Reverb (mark shipped).';

    public function handle(ReverbTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = ReverbOrderMetric::query()
                ->where(function ($q) use ($orderId) {
                    $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
                })
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Reverb order {$orderId} not found in reverb_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! ReverbTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Reverb is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncPendingFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
