<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AlibabaLinkMapSyncService;
use Illuminate\Console\Command;

class SyncAlibabaManagerLinkMap extends Command
{
    protected $signature = 'alibaba:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Alibaba SKU ↔ product_id link map from Alibaba API (local only).';

    public function handle(AlibabaLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('alibaba')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Alibaba Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll(50);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
