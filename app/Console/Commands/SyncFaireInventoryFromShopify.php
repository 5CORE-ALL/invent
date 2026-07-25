<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\FaireInventorySyncService;
use Illuminate\Console\Command;

class SyncFaireInventoryFromShopify extends Command
{
    protected $signature = 'faire:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Faire API}';

    protected $description = 'Sync Faire listing inventory and prices from Shopify (source of truth).';

    public function handle(FaireInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
