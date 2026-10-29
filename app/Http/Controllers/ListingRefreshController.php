<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshListingChannelJob;
use App\Services\MarketplaceManager\ListingChannelRefresher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** "Refresh listings" button on every /listing-<marketplace> page. */
class ListingRefreshController extends Controller
{
    public function start(string $channel): JsonResponse
    {
        $slug = ListingChannelRefresher::normalizeSlug($channel);
        if ($slug === '' || strlen($slug) > 40) {
            return response()->json(['state' => 'failed', 'message' => 'Unknown marketplace.'], 422);
        }

        $current = ListingChannelRefresher::status($slug);
        $startedAt = isset($current['started_at']) ? strtotime((string) $current['started_at']) : 0;
        // A run that has been "running" for over an hour is dead (worker killed): allow a new one.
        if (($current['state'] ?? '') === 'running' && $startedAt > time() - 3600) {
            return response()->json($current);
        }

        ListingChannelRefresher::markQueued($slug);
        RefreshListingChannelJob::dispatch($slug, true);

        return response()->json(ListingChannelRefresher::status($slug));
    }

    public function status(string $channel): JsonResponse
    {
        $slug = ListingChannelRefresher::normalizeSlug($channel);
        $status = ListingChannelRefresher::status($slug);
        $status['has_sources'] = isset(ListingChannelRefresher::plan()[$slug]);
        $status['last_daily_run'] = Cache::get('listing_refresh_daily_last_run');

        return response()->json($status);
    }
}
