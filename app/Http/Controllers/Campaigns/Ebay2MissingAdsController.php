<?php

namespace App\Http\Controllers\Campaigns;

use App\Support\Ads\EbayMissingListingQuery;
use Illuminate\Support\Collection;

/**
 * eBay 2 listings with no campaign, a price, and Shopify Inv > 0.
 */
class Ebay2MissingAdsController extends ListingMissingAdsController
{
    protected function cacheKey(): string
    {
        return 'ebay2_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'eBay 2 Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'eBay 2 listings not in a campaign, with a price and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('ebay2.campaign.ads');
    }

    protected function adsLabel(): string
    {
        return 'eBay 2 Campaign Ads';
    }

    protected function idField(): string
    {
        return 'listing_id';
    }

    protected function idLabel(): string
    {
        return 'Listing ID';
    }

    public static function dataRouteName(): string
    {
        return 'ebay2.ads.missing.data';
    }

    protected function countMissing(): int
    {
        return EbayMissingListingQuery::count('ebay2_campaign_ads', 'ebay_2_metrics');
    }

    protected function collectMissingRows(bool $withImages = true): Collection
    {
        return EbayMissingListingQuery::rows('ebay2_campaign_ads', 'ebay_2_metrics', $withImages);
    }
}
