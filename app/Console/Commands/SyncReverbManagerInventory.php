<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\ReverbInventorySyncService;
use Illuminate\Console\Command;

class SyncReverbManagerInventory extends Command
{
    protected $signature = 'reverb:manager-sync-inventory
                            {--dry-run : Show what would be updated without calling Reverb API}';

    protected $description = 'Sync Reverb listing inventory and prices from Shopify (source of truth).';

    public function handle(ReverbInventorySyncService $sync): int
    {
        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message']);

        if (($result['failed'] ?? 0) > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
