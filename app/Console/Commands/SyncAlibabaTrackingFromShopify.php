<?php

namespace App\Console\Commands;

use App\Models\AlibabaOrderMetric;
use App\Services\MarketplaceManager\AlibabaTrackingSyncService;
use Illuminate\Console\Command;

class SyncAlibabaTrackingFromShopify extends Command
{
    protected $signature = 'alibaba:sync-tracking
                            {--limit=40 : Max linked orders to check}
                            {--force : Run even if Push Shopify tracking setting is Off}
                            {--order= : Push tracking for a single Alibaba order_id}';

    protected $description = 'Push Shopify fulfillment tracking numbers to Alibaba (stub).';

    public function handle(AlibabaTrackingSyncService $sync): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = AlibabaOrderMetric::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();

            if (! $line) {
                $this->error("Alibaba order {$orderId} not found in alibaba_order_metrics.");

                return self::FAILURE;
            }

            $result = $sync->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! AlibabaTrackingSyncService::canAutoPush()) {
            $this->info('Skipped: Push Shopify tracking to Alibaba is Off in Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $result = $sync->syncFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
