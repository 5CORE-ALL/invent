<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AmazonLinkMapSyncService;
use Illuminate\Console\Command;

class SyncAmazonManagerLinkMap extends Command
{
    protected $signature = 'amazon:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}
                            {--import-only : Only import amazon_listings_raw → amazon_listing_statuses (no SP-API fetch)}';

    protected $description = 'Refresh Amazon SKU ↔ ASIN link map (imports Active listings from amazon_listings_raw into amazon_listing_statuses).';

    public function handle(AmazonLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('amazon')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Amazon Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);

        if ($this->option('import-only')) {
            $n = $sync->upsertFromListingsRaw();
            $this->info("Imported/updated {$n} SKU(s) from amazon_listings_raw into amazon_listing_statuses.");

            return self::SUCCESS;
        }

        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
