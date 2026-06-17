<?php

namespace App\Http\Controllers\Campaigns;

use App\Support\GoogleShoppingCampaignsRawRule;

/**
 * SERP variant of {@see GoogleShoppingCampaignsController} — same grid, controls, and rule storage,
 * but scoped to campaigns whose name contains the word "SEARCH" (e.g. "DRUM THRONES SEARCH").
 */
class GoogleSerpCampaignsController extends GoogleShoppingCampaignsController
{
    /**
     * Render the duplicated grid view tied to SERP routes.
     */
    public function index()
    {
        return view('campaign.google-serp', [
            'googleShoppingRule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    /**
     * Restrict every raw-grid query to campaigns whose name contains the word "SEARCH".
     * Leading space ensures we match the word boundary (e.g. "DRUM THRONES SEARCH"
     * or "DRUM THRONES SEARCH.") and not substrings like "RESEARCH".
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyCampaignNameScope($query, string $columnExpression = 'campaign_name'): void
    {
        $query->whereRaw("UPPER({$columnExpression}) LIKE ?", ['% SEARCH%']);
    }

    // Note: L30 Sales semantics (GA4 actual revenue only — no fallback to Google Ads
    // metrics.conversionsValue) are inherited unchanged from the parent. Both
    // `/google/shopping/google-shopping` and `/google/shopping/google-serp` show the
    // same GA4-only number; this page only differs in which campaigns are listed.
}
