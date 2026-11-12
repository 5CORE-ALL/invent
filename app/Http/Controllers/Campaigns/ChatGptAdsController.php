<?php

namespace App\Http\Controllers\Campaigns;

/**
 * ChatGPT Ads Tabulator page (/chatgpt-ads).
 */
class ChatGptAdsController extends AdsTabulatorController
{
    protected function viewName(): string
    {
        return 'campaign.chatgpt.chatgpt-ads';
    }

    protected function channelKey(): string
    {
        return 'chatgpt_ads_tabulator';
    }

    protected function pageTitle(): string
    {
        return 'ChatGPT Ads';
    }

    protected function dataRouteName(): string
    {
        return 'chatgpt.ads.data';
    }
}
