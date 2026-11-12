<?php

namespace App\Http\Controllers\Campaigns;

/**
 * Pinterest Ads Tabulator page (/pinterest-ads).
 */
class PinterestAdsController extends AdsTabulatorController
{
    protected function viewName(): string
    {
        return 'campaign.pinterest.pinterest-ads';
    }

    protected function channelKey(): string
    {
        return 'pinterest_ads_tabulator';
    }

    protected function pageTitle(): string
    {
        return 'Pinterest Ads';
    }

    protected function dataRouteName(): string
    {
        return 'pinterest.ads.data';
    }
}
