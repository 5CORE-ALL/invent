<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MissingListingCatalogRefresh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshMissingListingCatalogs extends Command
{
    protected $signature = 'missing-listing:refresh-catalogs {--force : Ignore the 30-minute freshness cache}';

    protected $description = 'Pull live marketplace catalogs (PLS, TopDawg, Faire, Macy, Best Buy) used by /missing-listing.';

    public function handle(MissingListingCatalogRefresh $refresh): int
    {
        if ($this->option('force')) {
            foreach (['pls', 'topdawg', 'faire', 'macy', 'bestbuy', 'macys', 'bestbuyusa', 'shopifyb2c'] as $channel) {
                Cache::forget('ml.listed_catalog.fresh.'.$channel);
            }
        }

        $this->info('Refreshing missing-listing catalogs from live APIs…');
        $results = $refresh->refreshApiChannelsFromCpMaster();
        foreach ($results as $channel => $status) {
            $this->line('  '.$channel.': '.$status);
        }

        return self::SUCCESS;
    }
}
