<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Ebay1LinkMapSyncService;
use Illuminate\Console\Command;

class SyncEbay1ManagerLinkMap extends Command
{
    protected $signature = 'ebay1:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh eBay 1 SKU ↔ item_id link map from ebay_metrics (local only).';

    public function handle(Ebay1LinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('ebay1')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in eBay 1 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
