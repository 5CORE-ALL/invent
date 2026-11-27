<?php

namespace App\Console\Commands;

use App\Services\AmazonSpApiService;
use App\Services\MarketplaceManager\Amz1DataService;
use App\Support\Marketplace\AmazonListingCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the browse category (the breadcrumb on the Amazon product page, e.g. Musical Instruments > Microphones &
 * Accessories > Microphones) of every listed SKU of the Amz Data page and keeps it for the "Amazon category" column.
 * Read only: nothing is changed on Amazon. By default only SKUs never read, or read more than a week ago.
 */
class AmzDataBrowseCategories extends Command
{
    protected $signature = 'amz-data:browse-categories
        {--all : Read every listed SKU again, not only new or old ones}
        {--days=7 : A category read this many days ago or earlier is read again}
        {--limit=0 : Read at most this many ASINs (0 = no limit)}';

    protected $description = 'Amz Data: read the Amazon browse category (shopper breadcrumb) of every listed SKU';

    public function handle(AmazonSpApiService $api): int
    {
        Amz1DataService::ensureTables();
        if (! Schema::hasTable(Amz1DataService::BROWSE_TABLE)) {
            $this->error('The browse category table could not be created.');

            return self::FAILURE;
        }
        if (! $api->isConfigured()) {
            $this->error('Amazon SP-API is not connected.');

            return self::FAILURE;
        }

        // SKU key (upper case, as the page keys rows) => ASIN of its live Amazon listing.
        $listings = AmazonListingCounts::listingsByNormalizedSku();
        $skuAsin = [];
        foreach (ListingCountsEngine::productSkus() as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || AmazonListingCounts::skuLooksLikeFba($sku)) {
                continue;
            }
            $asin = strtoupper(AmazonListingCounts::asinFromApi(AmazonListingCounts::pickListingForProductSku($sku, $listings)));
            if (preg_match('/^[A-Z0-9]{10}$/', $asin) === 1) {
                $skuAsin[strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)))] = $asin;
            }
        }

        if (! $this->option('all')) {
            $cutoff = now()->subDays(max(0, (int) $this->option('days')));
            $fresh = DB::table(Amz1DataService::BROWSE_TABLE)
                ->where('fetched_at', '>', $cutoff)
                ->whereNotNull('path')
                ->pluck('asin', 'sku')
                ->all();
            foreach ($skuAsin as $key => $asin) {
                if (($fresh[$key] ?? null) === $asin) {
                    unset($skuAsin[$key]);
                }
            }
        }

        $asins = array_values(array_unique($skuAsin));
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $asins = array_slice($asins, 0, $limit);
            $skuAsin = array_filter($skuAsin, static fn ($asin) => in_array($asin, $asins, true));
        }
        $this->info(count($skuAsin).' SKU(s) with '.count($asins).' ASIN(s) to read.');
        if ($asins === []) {
            return self::SUCCESS;
        }

        $service = new Amz1DataService($api);
        $stored = 0;
        $unread = 0;
        // 20 ASINs a call; saved after every 200 ASINs so a stopped run keeps what it read.
        foreach (array_chunk($asins, 200) as $part) {
            $found = $service->browseCategories($part);
            $unread += count($part) - count($found);
            $partSkus = array_filter($skuAsin, static fn ($asin) => in_array($asin, $part, true));
            $stored += Amz1DataService::storeBrowseCategories($partSkus, $found);
            $this->line('Saved '.$stored.' so far...');
        }

        $this->info('Done: '.$stored.' SKU(s) saved, '.$unread.' ASIN(s) without a browse category.'.($service->lastError !== '' ? ' Last error: '.$service->lastError : ''));

        return self::SUCCESS;
    }
}
