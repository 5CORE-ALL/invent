<?php

namespace App\Console\Commands;

use App\Models\Tiktok2Order;
use App\Services\MarketplaceManager\TikTok2TrackingSyncService;
use Illuminate\Console\Command;

class TikTok2SyncTracking extends Command
{
    protected $signature = 'tiktok2:sync-tracking
        {--limit=40 : Max orders to check}
        {--force : Ignore push_tracking_to_tiktok2 setting}
        {--order= : Push tracking for a single TikTok 2 order_id}';

    protected $description = 'Push Shopify fulfillment tracking to TikTok 2 for linked orders';

    public function handle(TikTok2TrackingSyncService $service): int
    {
        $orderId = trim((string) $this->option('order'));
        if ($orderId !== '') {
            $line = Tiktok2Order::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->first();
            if (! $line) {
                $this->error("TikTok 2 order {$orderId} not found.");

                return self::FAILURE;
            }

            $result = $service->pushTrackingForOrder($line);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        if (! $force && ! TikTok2TrackingSyncService::canAutoPush()) {
            $this->warn('push_tracking_to_tiktok2 is OFF in settings. Use --force to override.');

            return 0;
        }

        $this->info("Syncing tracking from Shopify → TikTok 2 (limit {$limit})...");

        $result = $service->syncPendingFromShopify($limit);

        $this->info($result['message']);

        return ($result['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
