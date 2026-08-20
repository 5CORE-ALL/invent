<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\EbayPortalListingStatusSync;
use Illuminate\Console\Command;

class FetchEbayListingStatus extends Command
{
    protected $signature = 'ebay:fetch-listing-status {--store=1 : eBay store 1, 2, 3, or all}';

    protected $description = 'Fetch Active/Unsold/Sold from eBay GetMyeBaySelling and store listing_status for MM tabs';

    public function handle(EbayPortalListingStatusSync $sync): int
    {
        $storeOpt = strtolower(trim((string) $this->option('store')));
        $stores = $storeOpt === 'all' ? [1, 2, 3] : [(int) $storeOpt];
        $stores = array_values(array_filter($stores, static fn ($s) => in_array($s, [1, 2, 3], true)));
        if ($stores === []) {
            $stores = [1];
        }

        $failed = false;
        foreach ($stores as $store) {
            $this->info("Fetching eBay {$store} listing status (GetMyeBaySelling Active/Unsold/Sold)...");
            $result = $sync->sync($store);
            if (! ($result['ok'] ?? false)) {
                $this->error($result['error'] ?? 'Sync failed');
                $failed = true;
                continue;
            }
            $this->info("ACTIVE: {$result['active']}  INACTIVE: {$result['inactive']}  MISSING: {$result['missing']}");
        }

        return $failed ? 1 : 0;
    }
}
