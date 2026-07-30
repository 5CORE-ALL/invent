<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\SheinLinkMapSyncService;
use Illuminate\Console\Command;

class SyncSheinManagerLinkMap extends Command
{
    protected $signature = 'shein:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Shein SKU ↔ product_id link map from Shein API (local only).';

    public function handle(SheinLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('shein')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Shein Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
