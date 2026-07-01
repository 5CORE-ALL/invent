<?php

namespace App\Http\Controllers\Campaigns;

use App\Services\GoogleAdsSbidService;
use App\Support\GoogleShoppingCampaignsRawRule;

/**
 * YouTube variant of {@see GoogleShoppingCampaignsController} — same grid, controls, and rule storage,
 * but scoped to campaigns whose name ends with the suffix " YT" (e.g. "CAR AUDIO Curiosity Gap Hook YT").
 */
class GoogleYoutubeAdsCampaignsController extends GoogleShoppingCampaignsController
{
    /**
     * Render the duplicated grid view tied to YouTube Ads routes.
     */
    public function index()
    {
        return view('campaign.google-youtube-ads', [
            'googleShoppingRule' => GoogleShoppingCampaignsRawRule::resolvedRule(),
        ]);
    }

    /**
     * Restrict every raw-grid query to campaigns whose name ends with " YT".
     * Leading space ensures we match the word suffix and not substrings like "LYT".
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyCampaignNameScope($query, string $columnExpression = 'campaign_name'): void
    {
        $query->whereRaw("UPPER({$columnExpression}) LIKE ?", ['% YT']);
    }

    /**
     * YouTube (VIDEO) campaigns use ad-group bids only — not Shopping product listing groups.
     *
     * @param  array<string, mixed>  $row
     */
    protected function pushSbidToGoogleAds(GoogleAdsSbidService $sbidService, string $customerId, string $campaignId, float $sbid, array $row = []): string
    {
        $sbidService->updateCampaignSbids($customerId, $campaignId, $sbid, false);

        return 'Video campaign — ad group bids updated';
    }

    protected function pushSbgtCommandLabel(): string
    {
        return 'push-sbgt-youtube';
    }

    protected function pushSbidCommandLabel(): string
    {
        return 'push-sbid-youtube';
    }
}
