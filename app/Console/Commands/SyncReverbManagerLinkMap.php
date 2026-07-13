<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\ReverbLinkMapSyncService;
use Illuminate\Console\Command;

class SyncReverbManagerLinkMap extends Command
{
    protected $signature = 'reverb:manager-sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Reverb SKU ↔ product_id link map from Reverb API (local only).';

    public function handle(ReverbLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('reverb')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Reverb Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll(50);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
