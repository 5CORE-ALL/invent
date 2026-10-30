<?php

namespace App\Services;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Support\GoogleShoppingLiveSyncStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Keeps Google Shopping LBid / LBgt equal to SBID / SBGT without anyone opening the page.
 *
 * Pull live from Google Ads, compare with the grid SBID / SBGT, push only the ENABLED
 * campaigns that differ, pull again to confirm. The push is the same code the page buttons use.
 * SBGT below 1 is never pushed here, because the page button turns that into a campaign pause.
 *
 * Hooks (tests): targets, pull, states, pushBid, pushBgt.
 */
class GoogleShoppingLiveMatchService
{
    /** A campaign Google keeps rejecting is not pushed again for this long (the daily run retries it anyway). */
    public const FAILED_RETRY_AFTER_HOURS = 3;

    /** @param  array<string, callable>  $hooks */
    public function __construct(private array $hooks = []) {}

    /**
     * @return array{
     *   checked: int, bid_mismatch: int, bgt_mismatch: int,
     *   bid_pushed: int, bgt_pushed: int, bid_left: int, bgt_left: int, waiting: int,
     *   push_errors: list<string>, error: ?string
     * }
     */
    public function run(bool $dryRun = false, ?string $onlyCampaignId = null, bool $retryFailed = false): array
    {
        $targets = [];
        $stats = $this->execute($dryRun, $onlyCampaignId, $retryFailed, $targets);

        if (! $dryRun) {
            $this->alertUnmatched($targets, $stats);
        }

        return $stats;
    }

    /**
     * After the push + confirm run, tell Shobha (5Core Chat + WhatsApp) about every campaign whose
     * SBGT/SBID still does not match LBgt/LBid, with the reason. Never breaks the match run.
     *
     * @param  array<string, array<string, mixed>>  $targets
     * @param  array<string, mixed>  $stats
     */
    private function alertUnmatched(array $targets, array $stats): void
    {
        // Tests that inject their own grid (hooks) only alert when they also inject the 'alert' hook.
        if (isset($this->hooks['targets']) && ! isset($this->hooks['alert'])) {
            return;
        }

        try {
            if (isset($this->hooks['alert'])) {
                ($this->hooks['alert'])($targets, $stats, $this->states(array_keys($targets)));

                return;
            }

            $alerts = new GoogleShoppingMismatchAlertService;
            if ($stats['error'] !== null) {
                $alerts->notify([$alerts->pullFailedIssue((string) $stats['error'])]);

                return;
            }
            if ($targets === []) {
                return;
            }

            $issues = $alerts->findIssues(
                $targets,
                $this->states(array_keys($targets), true),
                fn (string $field, string $id): bool => $this->isWaiting($field, $id)
            );
            $alerts->notify($issues);
        } catch (Throwable $e) {
            Log::error('Google Shopping live match: mismatch alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $targets  filled with the ENABLED targets of this run
     */
    private function execute(bool $dryRun, ?string $onlyCampaignId, bool $retryFailed, array &$targets): array
    {
        $stats = [
            'checked' => 0, 'bid_mismatch' => 0, 'bgt_mismatch' => 0,
            'bid_pushed' => 0, 'bgt_pushed' => 0, 'bid_left' => 0, 'bgt_left' => 0, 'waiting' => 0,
            'push_errors' => [], 'error' => null,
        ];

        $targets = $this->enabledTargets($onlyCampaignId);
        $stats['checked'] = count($targets);
        if ($targets === []) {
            return $stats;
        }

        try {
            $this->pull($targets);
        } catch (Throwable $e) {
            $stats['error'] = 'Live pull failed: '.$e->getMessage();
            Log::error('Google Shopping live match: pull failed', ['error' => $e->getMessage()]);

            return $stats;
        }

        [$bidIds, $bgtIds] = $this->mismatches($targets);
        $stats['bid_mismatch'] = count($bidIds);
        $stats['bgt_mismatch'] = count($bgtIds);
        $stats['bid_left'] = $stats['bid_mismatch'];
        $stats['bgt_left'] = $stats['bgt_mismatch'];
        if ($dryRun || ($bidIds === [] && $bgtIds === [])) {
            return $stats;
        }

        // Campaigns whose last push did not stick wait before the next try.
        if (! $retryFailed) {
            $bidWait = array_values(array_filter($bidIds, fn (string $id) => $this->isWaiting('bid', $id)));
            $bgtWait = array_values(array_filter($bgtIds, fn (string $id) => $this->isWaiting('bgt', $id)));
            $stats['waiting'] = count($bidWait) + count($bgtWait);
            $bidIds = array_values(array_diff($bidIds, $bidWait));
            $bgtIds = array_values(array_diff($bgtIds, $bgtWait));
            if ($bidIds === [] && $bgtIds === []) {
                return $stats;
            }
        }

        foreach ([['bid', $bidIds], ['bgt', $bgtIds]] as [$field, $ids]) {
            if ($ids === []) {
                continue;
            }
            try {
                $result = $field === 'bid' ? $this->pushBid($ids) : $this->pushBgt($ids);
            } catch (Throwable $e) {
                $stats['push_errors'][] = $field.': '.$e->getMessage();
                Log::error('Google Shopping live match: push failed', ['field' => $field, 'error' => $e->getMessage()]);

                continue;
            }
            if (! ($result['ok'] ?? false)) {
                $stats['push_errors'][] = $field.': '.trim((string) ($result['message'] ?? 'push failed'));
            }
        }

        // Pull again so the page dots and the numbers below reflect what Google Ads now holds.
        $pushedTargets = array_intersect_key($targets, array_flip(array_merge($bidIds, $bgtIds)));
        try {
            $this->pull($pushedTargets);
        } catch (Throwable $e) {
            $stats['push_errors'][] = 'verify pull failed: '.$e->getMessage();

            return $stats;
        }

        [$bidStill, $bgtStill] = $this->mismatches($pushedTargets);
        $stats['bid_left'] = count($bidStill) + $this->waitingCount('bid', $stats['bid_mismatch'], $bidIds);
        $stats['bgt_left'] = count($bgtStill) + $this->waitingCount('bgt', $stats['bgt_mismatch'], $bgtIds);
        $stats['bid_pushed'] = count($bidIds) - count(array_intersect($bidIds, $bidStill));
        $stats['bgt_pushed'] = count($bgtIds) - count(array_intersect($bgtIds, $bgtStill));

        foreach ($bidIds as $id) {
            in_array($id, $bidStill, true) ? $this->markFailed('bid', $id) : $this->clearFailed('bid', $id);
        }
        foreach ($bgtIds as $id) {
            in_array($id, $bgtStill, true) ? $this->markFailed('bgt', $id) : $this->clearFailed('bgt', $id);
        }

        return $stats;
    }

    /**
     * Campaigns (ENABLED only) that have an SBID or SBGT to compare.
     *
     * @return array<string, array{sbid: mixed, sbgt: mixed, status?: string}>
     */
    private function enabledTargets(?string $onlyCampaignId): array
    {
        $all = isset($this->hooks['targets'])
            ? ($this->hooks['targets'])()
            : app(GoogleShoppingCampaignsController::class)->shoppingLiveVerificationTargets();

        $only = $onlyCampaignId !== null ? preg_replace('/\D+/', '', $onlyCampaignId) : null;
        $out = [];
        foreach ($all as $id => $row) {
            $cid = preg_replace('/\D+/', '', (string) $id) ?? '';
            if ($cid === '' || ($only !== null && $only !== '' && $cid !== $only)) {
                continue;
            }
            if (strtoupper(trim((string) ($row['status'] ?? ''))) !== 'ENABLED') {
                continue;
            }
            $out[$cid] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $targets
     */
    private function pull(array $targets): void
    {
        if ($targets === []) {
            return;
        }
        if (isset($this->hooks['pull'])) {
            ($this->hooks['pull'])($targets);

            return;
        }
        $customerId = str_replace('-', '', trim((string) config('services.google_ads.login_customer_id')));
        $stats = app(GoogleShoppingLiveBidBgtService::class)->verifyAndStore($customerId, $targets, 'shopping');
        if (($stats['error'] ?? null) !== null) {
            throw new \RuntimeException((string) $stats['error']);
        }
    }

    /**
     * Only a campaign whose live amount was read is compared. A failed read never causes a push.
     *
     * @param  array<string, array<string, mixed>>  $targets
     * @return array{0: list<string>, 1: list<string>}
     */
    private function mismatches(array $targets): array
    {
        $states = $this->states(array_keys($targets));
        $bid = [];
        $bgt = [];
        foreach ($targets as $cid => $target) {
            $state = $states[$cid] ?? [];

            $sbid = self::positive($target['sbid'] ?? null);
            $liveBid = self::positive($state['live_bid'] ?? null);
            if ($sbid !== null && $liveBid !== null
                && abs($liveBid - round($sbid, 2)) > GoogleShoppingLiveSyncStatus::BID_TOLERANCE) {
                $bid[] = (string) $cid;
            }

            $sbgt = self::positive($target['sbgt'] ?? null);
            $liveBgt = self::positive($state['live_bgt'] ?? null);
            if ($sbgt !== null && $sbgt >= 1 && $liveBgt !== null
                && abs($liveBgt - $sbgt) > GoogleShoppingLiveSyncStatus::BGT_TOLERANCE) {
                $bgt[] = (string) $cid;
            }
        }

        return [$bid, $bgt];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, mixed>>
     */
    private function states(array $ids, bool $withFetchInfo = false): array
    {
        if (isset($this->hooks['states'])) {
            return ($this->hooks['states'])($ids);
        }
        if ($ids === [] || ! Schema::hasTable('google_shopping_live_syncs')) {
            return [];
        }
        $columns = ['campaign_id', 'live_bid', 'live_bgt'];
        if ($withFetchInfo) {
            foreach (['bid_fetch_ok', 'bid_fetch_error', 'bgt_fetch_ok', 'bgt_fetch_error'] as $col) {
                if (Schema::hasColumn('google_shopping_live_syncs', $col)) {
                    $columns[] = $col;
                }
            }
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('google_shopping_live_syncs')
                ->where('channel', 'shopping')
                ->whereIn('campaign_id', $chunk)
                ->get($columns) as $row) {
                $out[(string) $row->campaign_id] = (array) $row;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array{ok: bool, message: string, output?: string}
     */
    private function pushBid(array $ids): array
    {
        if (isset($this->hooks['pushBid'])) {
            return ($this->hooks['pushBid'])($ids);
        }

        return app(GoogleShoppingCampaignsController::class)->pushSbidForLiveMatch($ids);
    }

    /**
     * @param  list<string>  $ids
     * @return array{ok: bool, message: string, output?: string}
     */
    private function pushBgt(array $ids): array
    {
        if (isset($this->hooks['pushBgt'])) {
            return ($this->hooks['pushBgt'])($ids);
        }

        return app(GoogleShoppingCampaignsController::class)->pushSbgtForLiveMatch($ids);
    }

    private static function failKey(string $field, string $id): string
    {
        return 'google-shopping:match-failed:'.$field.':'.$id;
    }

    private function isWaiting(string $field, string $id): bool
    {
        return Cache::has(self::failKey($field, $id));
    }

    private function markFailed(string $field, string $id): void
    {
        Cache::put(self::failKey($field, $id), now()->toDateTimeString(), now()->addHours(self::FAILED_RETRY_AFTER_HOURS));
    }

    private function clearFailed(string $field, string $id): void
    {
        Cache::forget(self::failKey($field, $id));
    }

    /**
     * Mismatches that were held back this run (still waiting after an earlier failed push).
     *
     * @param  list<string>  $pushedIds
     */
    private function waitingCount(string $field, int $totalMismatch, array $pushedIds): int
    {
        return max(0, $totalMismatch - count($pushedIds));
    }

    private static function positive(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return is_finite($n) && $n > 0 ? $n : null;
    }
}
