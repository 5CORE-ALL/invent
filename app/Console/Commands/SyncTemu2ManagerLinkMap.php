<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Temu2LinkMapSyncService;
use Illuminate\Console\Command;

class SyncTemu2ManagerLinkMap extends Command
{
    protected $signature = 'temu2:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Temu 2 SKU ↔ product_id link map from Temu 2 API (local only).';

    public function handle(Temu2LinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('temu2')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Temu 2 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
