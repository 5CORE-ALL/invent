<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\NeweggInventorySyncService;
use Illuminate\Console\Command;

class SyncNeweggInventoryFromShopify extends Command
{
    protected $signature = 'newegg:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Newegg API}';

    protected $description = 'Sync Newegg listing inventory and prices from Shopify (source of truth).';

    public function handle(NeweggInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
