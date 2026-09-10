<?php

namespace App\Console\Commands;

use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\TikTokTrackingSyncService;
use Illuminate\Console\Command;

class TikTokSyncTracking extends Command
{
    protected $signature = 'tiktok:sync-tracking
        {--limit=40 : Max orders to check}
        {--force : Ignore push_tracking_to_tiktok setting}
        {--order= : Push tracking for a single TikTok order_id}';

    protected $description = 'Push Shopify fulfillment tracking to TikTok Shop for linked orders';

    public function handle(TikTokTrackingSyncService $service): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = TiktokOrder::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();
            if (! $line) {
                $this->error("TikTok order {$orderId} not found.");

                return self::FAILURE;
            }

            $result = $service->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        if (! $force && ! TikTokTrackingSyncService::canAutoPush()) {
            $this->warn('push_tracking_to_tiktok is OFF in settings. Use --force to override.');

            return 0;
        }

        $this->info("Syncing tracking from Shopify → TikTok Shop (limit {$limit})...");

        $result = $service->syncPendingFromShopify($limit);

        $this->info($result['message']);

        return ($result['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
