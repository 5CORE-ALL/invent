<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\BestBuyTrackingSyncService;
use Illuminate\Console\Command;

class SyncBestBuyTrackingFromShopify extends Command
{
    protected $signature = 'bestbuy:sync-tracking
                            {--limit=40 : Max orders per run}';

    protected $description = 'Push Shopify tracking to Best Buy (stub).';

    public function handle(BestBuyTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
