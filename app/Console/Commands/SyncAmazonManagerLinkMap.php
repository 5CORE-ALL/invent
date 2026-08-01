<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AmazonLinkMapSyncService;
use Illuminate\Console\Command;

class SyncAmazonManagerLinkMap extends Command
{
    protected $signature = 'amazon:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}';

    protected $description = 'Refresh Amazon SKU ↔ ASIN link map from amazon_listing_statuses (+ optional SP-API pull).';

    public function handle(AmazonLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('amazon')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Amazon Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
