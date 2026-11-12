<?php

namespace App\Http\Controllers\Campaigns;

/**
 * Reddit Ads Tabulator page (/reddit-ads).
 */
class RedditAdsController extends AdsTabulatorController
{
    protected function viewName(): string
    {
        return 'campaign.reddit.reddit-ads';
    }

    protected function channelKey(): string
    {
        return 'reddit_ads_tabulator';
    }

    protected function pageTitle(): string
    {
        return 'Reddit Ads';
    }

    protected function dataRouteName(): string
    {
        return 'reddit.ads.data';
    }
}
