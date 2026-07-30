<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\SheinInventorySyncService;
use Illuminate\Console\Command;

class SyncSheinInventoryFromShopify extends Command
{
    protected $signature = 'shein:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Shein API}';

    protected $description = 'Sync Shein listing inventory and prices from Shopify (source of truth).';

    public function handle(SheinInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
