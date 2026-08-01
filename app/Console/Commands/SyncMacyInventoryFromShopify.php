<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MacyInventorySyncService;
use Illuminate\Console\Command;

class SyncMacyInventoryFromShopify extends Command
{
    protected $signature = 'macy:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling API}';

    protected $description = 'Sync Macy\'s listing inventory from Shopify (source of truth).';

    public function handle(MacyInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
