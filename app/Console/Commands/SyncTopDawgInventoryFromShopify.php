<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TopDawgInventorySyncService;
use Illuminate\Console\Command;

class SyncTopDawgInventoryFromShopify extends Command
{
    protected $signature = 'topdawg:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling TopDawg API}';

    protected $description = 'Sync TopDawg listing inventory and prices from Shopify (source of truth).';

    public function handle(TopDawgInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
