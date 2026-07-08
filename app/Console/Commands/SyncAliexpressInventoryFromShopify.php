<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AliexpressInventorySyncService;
use Illuminate\Console\Command;

class SyncAliexpressInventoryFromShopify extends Command
{
    protected $signature = 'aliexpress:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling AliExpress API}';

    protected $description = 'Sync AliExpress listing inventory and prices from Shopify (source of truth).';

    public function handle(AliexpressInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
