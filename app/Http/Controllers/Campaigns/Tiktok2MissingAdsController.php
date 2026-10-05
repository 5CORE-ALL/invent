<?php

namespace App\Http\Controllers\Campaigns;

/**
 * TikTok 2 / GMV products with no GMV ad row and Shopify Inv > 0.
 */
class Tiktok2MissingAdsController extends Tiktok1MissingAdsController
{
    protected function cacheKey(): string
    {
        return 'tiktok2_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'TikTok 2 Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'TikTok 2 products with no GMV ad and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('tiktok.gmv.ads.raw');
    }

    protected function adsLabel(): string
    {
        return 'GMV TikTok Ads';
    }

    public static function dataRouteName(): string
    {
        return 'tiktok2.ads.missing.data';
    }

    protected function collectMissingRows(bool $withImages = true): \Illuminate\Support\Collection
    {
        return $this->productsMissingAds('tiktok_products_two', 'tiktok_gmv_ads', $withImages, true);
    }
}
