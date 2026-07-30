<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\Ebay3InventorySyncService;
use Illuminate\Console\Command;

class SyncEbay3InventoryFromShopify extends Command
{
    protected $signature = 'ebay3:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling eBay 3 API}';

    protected $description = 'Sync eBay 3 listing inventory and prices from Shopify (source of truth).';

    public function handle(Ebay3InventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
