<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\Temu2InventorySyncService;
use Illuminate\Console\Command;

class SyncTemu2InventoryFromShopify extends Command
{
    protected $signature = 'temu2:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Temu 2 API}';

    protected $description = 'Sync Temu 2 listing inventory and prices from Shopify (source of truth).';

    public function handle(Temu2InventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
