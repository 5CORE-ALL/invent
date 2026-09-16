<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\B5cB2bInventorySyncService;
use Illuminate\Console\Command;

class SyncB5cB2bInventoryFromShopify extends Command
{
    protected $signature = 'b5cb2b:sync-inventory-from-shopify';

    protected $description = 'Sync Business 5 Core (B2B) inventory from Shopify (source of truth).';

    public function handle(B5cB2bInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify(false);
        $this->info($result['message'] ?? 'Done.');

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
