<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AliexpressLinkMapSyncService;
use Illuminate\Console\Command;

class SyncAliexpressManagerLinkMap extends Command
{
    protected $signature = 'aliexpress:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh AliExpress SKU ↔ product_id link map from AliExpress API (local only).';

    public function handle(AliexpressLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('aliexpress')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in AliExpress Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll(50);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
