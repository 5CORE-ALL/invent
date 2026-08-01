<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TemuInventorySyncService;
use Illuminate\Console\Command;

class SyncTemuInventoryFromShopify extends Command
{
    protected $signature = 'temu:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Temu API}';

    protected $description = 'Sync Temu listing inventory and prices from Shopify (source of truth).';

    public function handle(TemuInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
