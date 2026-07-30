<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Ebay3LinkMapSyncService;
use Illuminate\Console\Command;

class SyncEbay3ManagerLinkMap extends Command
{
    protected $signature = 'ebay3:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh eBay 3 SKU ↔ item_id link map from ebay_3_metrics (local only).';

    public function handle(Ebay3LinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('ebay3')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in eBay 3 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
