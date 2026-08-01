<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairInventorySyncService;
use Illuminate\Console\Command;

class SyncWayfairInventoryFromShopify extends Command
{
    protected $signature = 'wayfair:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling API}';

    protected $description = 'Sync Wayfair listing inventory from Shopify (source of truth).';

    public function handle(WayfairInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
