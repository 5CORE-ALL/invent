<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\PurchasingPowerInventorySyncService;
use Illuminate\Console\Command;

class SyncPurchasingPowerInventoryFromShopify extends Command
{
    protected $signature = 'purchasingpower:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling API}';

    protected $description = 'Sync Purchasing Power listing inventory from Shopify (source of truth).';

    public function handle(PurchasingPowerInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
