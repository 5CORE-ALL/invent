<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TikTok2InventorySyncService;
use Illuminate\Console\Command;

class TikTok2SyncInventoryFromShopify extends Command
{
    protected $signature = 'tiktok2:sync-inventory-from-shopify
        {--dry-run : Preview without pushing to TikTok}';

    protected $description = 'Push live Shopify inventory to TikTok 2 for all linked SKUs';

    public function handle(TikTok2InventorySyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Syncing Shopify inventory → TikTok 2'.($dryRun ? ' [DRY RUN]' : '').'...');

        $result = $service->syncFromShopify($dryRun);

        $this->info($result['message']);
        $this->table(
            ['Updated', 'Failed', 'Skipped'],
            [[$result['updated'], $result['failed'], $result['skipped']]]
        );

        return ($result['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
