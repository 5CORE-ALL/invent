<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AmazonInventorySyncService;
use Illuminate\Console\Command;

class SyncAmazonZeroInventoryFromShopify extends Command
{
    protected $signature = 'amazon:sync-zero-inventory
                            {--limit=250 : Max SKUs to push to 0 this run}';

    protected $description = 'Push Amazon listings to 0 when Shopify stock is already 0.';

    public function handle(AmazonInventorySyncService $sync): int
    {
        $result = $sync->syncConfirmedZerosFromShopify(max(1, (int) $this->option('limit')));
        $this->info($result['message'] ?? 'Done.');

        return ((int) ($result['failed'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
