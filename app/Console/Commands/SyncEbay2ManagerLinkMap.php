<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Ebay2LinkMapSyncService;
use Illuminate\Console\Command;

class SyncEbay2ManagerLinkMap extends Command
{
    protected $signature = 'ebay2:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh eBay 2 SKU ↔ item_id link map from ebay_2_metrics (local only).';

    public function handle(Ebay2LinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('ebay2')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in eBay 2 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
