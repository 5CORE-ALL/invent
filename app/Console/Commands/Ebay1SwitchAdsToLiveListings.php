<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SwitchesEbayAdsToLiveListings;
use App\Models\EbayMetric;
use App\Services\EbayApiService;
use Illuminate\Console\Command;

/**
 * eBay 1 — move PMT ads from an ended / replaced item id to the SKU's current live listing
 * (same campaign, same bid). See {@see SwitchesEbayAdsToLiveListings}.
 *
 * eBay 1 ads sit in per-listing "Campaign <date>" campaigns with no SKU on the row, so the SKU of an
 * old item id comes from the SKU stamped on the row, else from order history.
 */
class Ebay1SwitchAdsToLiveListings extends Command
{
    use SwitchesEbayAdsToLiveListings;

    protected $signature = 'ebay:switch-ads-to-live-listings
        {--dry-run : Show what would change without calling eBay create/writing the DB}
        {--limit=60 : Max ghost ads to process per run}
        {--prune : Also delete confirmed ghost rows that have no live listing for their SKU}';

    protected $description = 'Move eBay 1 PMT ads from ended/old item ids to the SKU\'s current live listing (same campaign, same bid)';

    protected function adsTable(): string
    {
        return 'ebay_campaign_ads';
    }

    protected function metricClass(): string
    {
        return EbayMetric::class;
    }

    protected function accessToken(): string
    {
        return (new EbayApiService())->generateBearerToken();
    }

    protected function historySources(): array
    {
        return [['ebay_order_items', 'item_id', 'sku']];
    }

    protected function cachePrefix(): string
    {
        return 'ebay1_switch_ads';
    }
}
