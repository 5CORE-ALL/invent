<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AmazonInventorySyncService;
use Illuminate\Console\Command;

class SyncAmazonInventoryFromShopify extends Command
{
    protected $signature = 'amazon:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Amazon SP-API}';

    protected $description = 'Sync Amazon listing inventory from Shopify (source of truth).';

    public function handle(AmazonInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
