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

    /** A run "running" for longer than this is treated as dead (worker killed). */
    private const RUNNING_STALE_SECONDS = 3600;

    /** A channel not refreshed for this long is reported as stale. */
    private const STALE_AFTER_HOURS = 26;

    /**
     * "Refresh all Missing L" on /missing-listing: queue one refresh job per marketplace
     * recounting from the data already in the app (the same numbers each listing page shows).
     * Nothing is fetched from the marketplaces here; the daily run does that.
     */
    public function startAll(): JsonResponse
    {
        if (config('queue.default') === 'sync') {
            return response()->json(['success' => false, 'message' => 'Queue is not configured (QUEUE_CONNECTION=sync) — cannot run refreshes in the background.'], 422);
        }

        $queued = 0;
        $skipped = 0;
        foreach (ListingChannelRefresher::orderedSlugs() as $slug) {
            // Only skip a marketplace whose refresh is really executing now (a leftover "queued" flag must not block this).
            if (ListingChannelRefresher::isRunning($slug)) {
                $skipped++;

                continue;
            }
            ListingChannelRefresher::markQueued($slug);
            RefreshListingChannelJob::dispatch($slug, true, true);
            $queued++;
        }

        return response()->json(['success' => true, 'queued' => $queued, 'already_running' => $skipped] + $this->allStatus());
    }

    public function statusAll(): JsonResponse
    {
        return response()->json(['success' => true] + $this->allStatus());
    }

    /**
     * @return array<string, mixed>
     */
    private function allStatus(): array
    {
        $rows = [];
        $summary = ['total' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'never' => 0, 'stale' => 0];
        $staleBefore = now()->subHours(self::STALE_AFTER_HOURS)->getTimestamp();

        foreach (ListingChannelRefresher::orderedSlugs() as $slug) {
            $status = ListingChannelRefresher::status($slug);
            $state = (string) ($status['state'] ?? 'idle');
            $startedAt = isset($status['started_at']) ? (int) strtotime((string) $status['started_at']) : 0;
            if ($state === 'running' && $startedAt < time() - self::RUNNING_STALE_SECONDS) {
                $state = 'failed';
                $status['message'] = 'Stopped without finishing (worker restarted). Run it again.';
            }
            $finishedAt = isset($status['finished_at']) ? (int) strtotime((string) $status['finished_at']) : 0;

            $summary['total']++;
            if ($state === 'running') {
                $summary['running']++;
            } elseif ($state === 'done') {
                $summary['done']++;
            } elseif ($state === 'failed') {
                $summary['failed']++;
            } else {
                $summary['never']++;
            }
            if ($state !== 'running' && ($finishedAt === 0 || $finishedAt < $staleBefore)) {
                $summary['stale']++;
            }

            $failedSteps = [];
            foreach ((array) ($status['steps'] ?? []) as $step) {
                if (is_array($step) && empty($step['ok'])) {
                    $failedSteps[] = trim((string) ($step['label'] ?? '').': '.(string) ($step['message'] ?? ''));
                }
            }

            $rows[] = [
                'slug' => $slug,
                'state' => $state,
                'message' => (string) ($status['message'] ?? ''),
                'finished_at' => $finishedAt > 0 ? date('c', $finishedAt) : null,
                'missing_l' => isset($status['counts']['Pending']) ? (int) $status['counts']['Pending'] : null,
                'failed_steps' => $failedSteps,
            ];
        }

        $payload = Cache::get(\App\Http\Controllers\MarketPlace\MissingListingController::PAGE_CACHE_KEY);

        return [
            'summary' => $summary,
            'rows' => $rows,
            'computed_at' => is_array($payload) ? ($payload['computed_at'] ?? null) : null,
            'total_missing_l' => is_array($payload) ? ($payload['total_missing_l'] ?? null) : null,
        ];
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
