<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\PurchasingPowerTrackingSyncService;
use Illuminate\Console\Command;

class SyncPurchasingPowerTrackingFromShopify extends Command
{
    protected $signature = 'purchasingpower:sync-tracking
                            {--limit=40 : Max orders per run}';

    protected $description = 'Push Shopify tracking to Purchasing Power (stub).';

    public function handle(PurchasingPowerTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
