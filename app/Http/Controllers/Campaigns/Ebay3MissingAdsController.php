<?php

namespace App\Http\Controllers\Campaigns;

use App\Support\Ads\EbayMissingListingQuery;
use Illuminate\Support\Collection;

/**
 * eBay 3 listings with no campaign, a price, and Shopify Inv > 0.
 */
class Ebay3MissingAdsController extends ListingMissingAdsController
{
    protected function cacheKey(): string
    {
        return 'ebay3_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'eBay 3 Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'eBay 3 listings not in a campaign, with a price and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('ebay3.campaign.ads');
    }

    protected function adsLabel(): string
    {
        return 'eBay 3 Campaign Ads';
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
        return 'ebay3.ads.missing.data';
    }

    protected function countMissing(): int
    {
        return EbayMissingListingQuery::count('ebay3_campaign_ads', 'ebay_3_metrics');
    }

    protected function collectMissingRows(bool $withImages = true): Collection
    {
        return EbayMissingListingQuery::rows('ebay3_campaign_ads', 'ebay_3_metrics', $withImages);
    }
}
