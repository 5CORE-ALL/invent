<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\TopDawgLinkMapSyncService;
use Illuminate\Console\Command;

class SyncTopDawgManagerLinkMap extends Command
{
    protected $signature = 'topdawg:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh TopDawg SKU ↔ product_id link map from TopDawg API (local only).';

    public function handle(TopDawgLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('topdawg')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in TopDawg Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
