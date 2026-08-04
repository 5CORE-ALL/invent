<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TikTokTrackingSyncService;
use Illuminate\Console\Command;

class TikTokSyncTracking extends Command
{
    protected $signature = 'tiktok:sync-tracking
        {--limit=40 : Max orders to check}
        {--force : Ignore push_tracking_to_tiktok setting}';

    protected $description = 'Push Shopify fulfillment tracking to TikTok Shop for linked orders';

    public function handle(TikTokTrackingSyncService $service): int
    {
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
