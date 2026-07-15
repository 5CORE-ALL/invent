<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\NeweggLinkMapSyncService;
use Illuminate\Console\Command;

class SyncNeweggManagerLinkMap extends Command
{
    protected $signature = 'newegg:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Newegg SKU ↔ product_id link map from Newegg API (local only).';

    public function handle(NeweggLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('newegg')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Newegg Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
