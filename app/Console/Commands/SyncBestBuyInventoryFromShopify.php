<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\BestBuyInventorySyncService;
use Illuminate\Console\Command;

class SyncBestBuyInventoryFromShopify extends Command
{
    protected $signature = 'bestbuy:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling API}';

    protected $description = 'Sync Best Buy listing inventory from Shopify (source of truth).';

    public function handle(BestBuyInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
