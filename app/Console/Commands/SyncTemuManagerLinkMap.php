<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\TemuLinkMapSyncService;
use Illuminate\Console\Command;

class SyncTemuManagerLinkMap extends Command
{
    protected $signature = 'temu:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Temu SKU ↔ product_id link map from Temu API (local only).';

    public function handle(TemuLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('temu')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Temu Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
