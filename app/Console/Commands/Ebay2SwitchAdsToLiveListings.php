<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SwitchesEbayAdsToLiveListings;
use App\Models\Ebay2Metric;
use App\Services\Ebay2ApiService;
use Illuminate\Console\Command;

/**
 * eBay 2 — move PMT ads from an ended / replaced item id to the SKU's current live listing
 * (same campaign, same bid). See {@see SwitchesEbayAdsToLiveListings}.
 */
class Ebay2SwitchAdsToLiveListings extends Command
{
    use SwitchesEbayAdsToLiveListings;

    protected $signature = 'ebay2:switch-ads-to-live-listings
        {--dry-run : Show what would change without calling eBay create/writing the DB}
        {--limit=60 : Max ghost ads to process per run}
        {--prune : Also delete confirmed ghost rows that have no live listing for their SKU}';

    protected $description = 'Move eBay 2 PMT ads from ended/old item ids to the SKU\'s current live listing (same campaign, same bid)';

    protected function adsTable(): string
    {
        return 'ebay2_campaign_ads';
    }

    protected function metricClass(): string
    {
        return Ebay2Metric::class;
    }

    protected function accessToken(): string
    {
        return (new Ebay2ApiService())->generateBearerToken();
    }

    protected function historySources(): array
    {
        return [['ebay2_order_items', 'item_id', 'sku']];
    }

    protected function cachePrefix(): string
    {
        return 'ebay2_switch_ads';
    }
}
