<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use Illuminate\Console\Command;

class SyncAlibabaInventoryFromShopify extends Command
{
    protected $signature = 'alibaba:sync-inventory-from-shopify
                            {--dry-run : Show what would be updated without calling Alibaba API}';

    protected $description = 'Sync Alibaba listing inventory and prices from Shopify (source of truth).';

    public function handle(AlibabaInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
