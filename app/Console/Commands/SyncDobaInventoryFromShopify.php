<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\DobaInventorySyncService;
use Illuminate\Console\Command;

class SyncDobaInventoryFromShopify extends Command
{
    protected $signature = 'doba:sync-inventory-from-shopify
                            {--dry-run : Report only, no API push}
                            {--force : Run even if inventory sync is Off in settings}';

    protected $description = 'Push Shopify inventory to Doba for linked SKUs in doba_metrics.';

    public function handle(DobaInventorySyncService $sync): int
    {
        if (! $this->option('force')) {
            $settings = MarketplaceSyncSettings::getFor('doba');
            if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
                $this->info('Skipped: Inventory sync is Off in Doba Marketplace Manager settings.');

                return self::SUCCESS;
            }
        }

        $result = $sync->syncFromShopify((bool) $this->option('dry-run'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
