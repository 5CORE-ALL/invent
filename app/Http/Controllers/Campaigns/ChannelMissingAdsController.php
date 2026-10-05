<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Support\Ads\ChannelListingMissingAds;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ChannelMissingAdsController extends Controller
{
    public function index(string $channel)
    {
        $cfg = ChannelListingMissingAds::find($channel);
        abort_if($cfg === null, 404);

        $title = $cfg['title'].' Missing Ads';

        return view('campaign.listing-missing-ads', [
            'pageTitle' => $title,
            'pageSubtitle' => $cfg['mode'] === 'temu3'
                ? $cfg['title'].' goods with Status No ad and Inv > 0.'
                : $cfg['title'].' listings with no ad and Inv > 0.',
            'adsUrl' => route('channel.title.ads', ['channel' => $cfg['slug']]).'?title='.rawurlencode($cfg['title']),
            'adsLabel' => $cfg['title'].' Ads',
            'idField' => 'listing_id',
            'idLabel' => $cfg['mode'] === 'temu3' ? 'Goods ID' : 'Listing ID',
            'dataUrl' => route('channel.ads.missing.data', ['channel' => $cfg['slug']]),
        ]);
    }

    public function data(string $channel): JsonResponse
    {
        $cfg = ChannelListingMissingAds::find($channel);
        abort_if($cfg === null, 404);

        $rows = ChannelListingMissingAds::rows($cfg['slug'], true);
        $this->rememberCount($cfg['slug'], $rows->count());

        return response()->json([
            'data' => $rows->values(),
            'total' => $rows->count(),
        ]);
    }

    private function rememberCount(string $slug, int $count): void
    {
        try {
            $cached = Cache::get('channel_listing_missing_ads_counts');
            $counts = is_array($cached) ? $cached : [];
            $counts[$slug] = $count;
            Cache::put('channel_listing_missing_ads_counts', $counts, now()->addMinutes(5));
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
