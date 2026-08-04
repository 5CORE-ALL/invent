<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TikTokInventorySyncService;
use Illuminate\Console\Command;

class TikTokSyncInventoryFromShopify extends Command
{
    protected $signature = 'tiktok:sync-inventory-from-shopify
        {--dry-run : Preview without pushing to TikTok}';

    protected $description = 'Push live Shopify inventory to TikTok Shop for all linked SKUs';

    public function handle(TikTokInventorySyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Syncing Shopify inventory → TikTok Shop'.($dryRun ? ' [DRY RUN]' : '').'...');

        $result = $service->syncFromShopify($dryRun);

        $this->info($result['message']);
        $this->table(
            ['Updated', 'Failed', 'Skipped'],
            [[$result['updated'], $result['failed'], $result['skipped']]]
        );

        return ($result['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
