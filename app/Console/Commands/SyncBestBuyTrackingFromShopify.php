<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\BestBuyTrackingSyncService;
use Illuminate\Console\Command;

class SyncBestBuyTrackingFromShopify extends Command
{
    protected $signature = 'bestbuy:sync-tracking
                            {--limit=120 : Max Best Buy orders per run}';

    protected $description = 'Copy unused Veeqo labels to Shopify, then push each tracking to Best Buy.';

    public function handle(BestBuyTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
