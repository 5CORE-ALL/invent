<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Temu3LinkMapSyncService;
use Illuminate\Console\Command;

class SyncTemu3ManagerLinkMap extends Command
{
    protected $signature = 'temu3:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Temu 3 SKU ↔ product_id link map from Temu 3 API (local only).';

    public function handle(Temu3LinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('temu3')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Temu 3 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
