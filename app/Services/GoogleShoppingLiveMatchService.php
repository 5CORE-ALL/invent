<?php

namespace App\Services;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Support\GoogleShoppingLiveSyncStatus;
use App\Support\SchedulerLease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps Google Shopping LBid / LBgt equal to SBID / SBGT without anyone opening the page.
 *
 * Pull live from Google Ads, compare with the grid SBID / SBGT, push only the ENABLED
 * campaigns that differ, pull again to confirm. The push is the same code the page buttons use.
 * SBGT below 1 is never pushed here, because the page button turns that into a campaign pause.
 *
 * One run at a time is enforced by the caller with a SchedulerLease; the run renews it between steps and
 * stops pushing if it was taken over. Campaigns are pushed in chunks, so one failure never stops the rest.
 *
 * Hooks (tests): targets, pull, states, pushBid, pushBgt, sleep.
 */
class GoogleShoppingLiveMatchService
{
    /** A campaign Google keeps rejecting is not pushed again for this long (the daily run retries it anyway). */
    public const FAILED_RETRY_AFTER_HOURS = 3;

    /** @param  array<string, callable>  $hooks */
    public function __construct(private array $hooks = []) {}

    /** Campaigns per push call: one bad campaign or one failed call never stops the rest of the run. */
    public const PUSH_CHUNK = 10;

    /** A live value read longer ago than this is read again right before it is pushed. */
    public const FRESH_SECONDS = 60;

    /** Wait before the single in-run retry of a temporary Google Ads failure. */
    public const RETRY_BACKOFF_SECONDS = 3;

    private ?SchedulerLease $lease = null;

    private string $execution = '';

    /** @var array<string, float> campaign id => when its live value was last read from Google Ads */
    private array $freshAt = [];

    /**
     * @return array{
     *   checked: int, bid_mismatch: int, bgt_mismatch: int,
     *   bid_pushed: int, bgt_pushed: int, bid_left: int, bgt_left: int, waiting: int,
     *   push_errors: list<string>, error: ?string,
     *   execution: string, retried: int, pushed: int,
     *   pushed_ids: array{bid: list<string>, bgt: list<string>},
     *   held_ids: list<string>, skipped: array<string, string>, failed: array<string, string>,
     *   status: string, summary: array<string, int>
     * }
     */
    public function run(bool $dryRun = false, ?string $onlyCampaignId = null, bool $retryFailed = false, ?SchedulerLease $lease = null): array
    {
        $this->lease = $lease;
        $this->execution = $lease?->token ?? (string) Str::uuid();
        $this->freshAt = [];
        $t0 = microtime(true);

        $stats = $this->execute($dryRun, $onlyCampaignId, $retryFailed);
        $stats['execution'] = $this->execution;
        $stats['summary'] = self::summarize($stats);
        $stats['status'] = self::resolveStatus($stats, $dryRun);

        Log::log($stats['status'] === self::STATUS_FAILED ? 'error' : ($stats['status'] === self::STATUS_PARTIAL ? 'warning' : 'info'), '[GoogleShoppingLiveMatch] finished', [
            'execution' => $this->execution,
            'dry_run' => $dryRun,
            'status' => $stats['status'],
            'duration_s' => round(microtime(true) - $t0, 2),
            'summary' => $stats['summary'],
            'still_different' => ['bid' => $stats['bid_left'], 'bgt' => $stats['bgt_left']],
            'pushed_and_confirmed' => $stats['pushed_ids'],
            'held_after_failed_push' => $stats['held_ids'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
            'retried_temporary_failures' => $stats['retried'],
            'error' => $stats['error'],
        ]);

        return $stats;
    }

    /** Run outcomes. `skipped` (another run holds the lease) is decided by the command, not here. */
    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial_success';

    public const STATUS_FAILED = 'failed';

    /**
     * One count per campaign operation (a bid push and a budget push of one campaign are two operations).
     * pushed = Google Ads accepted the push; confirmed = the live re-read now matches; held = waiting after an
     * earlier failed push; busy = another push (e.g. the page button) held the campaign; skipped = not pushed
     * for another reason; failed = Google rejected it or the live value still differs after the push.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, int>
     */
    public static function summarize(array $stats): array
    {
        $busy = count(array_filter((array) $stats['skipped'], fn ($m) => str_starts_with((string) $m, 'busy:')));

        return [
            'checked' => (int) $stats['checked'],
            'sbid_mismatch' => (int) $stats['bid_mismatch'],
            'sbgt_mismatch' => (int) $stats['bgt_mismatch'],
            'pushed' => (int) $stats['pushed'],
            'confirmed' => count($stats['pushed_ids']['bid']) + count($stats['pushed_ids']['bgt']),
            'held' => (int) $stats['waiting'],
            'skipped' => count((array) $stats['skipped']) - $busy,
            'busy' => $busy,
            'failed' => count((array) $stats['failed']),
        ];
    }

    /**
     * success: nothing failed. partial_success: something was confirmed and something failed.
     * failed: a run-level error (pull failed, lease lost) or every operation failed. Held, busy and skipped
     * campaigns are not failures of this run; they are reported in the summary and picked up by a later run.
     *
     * @param  array<string, mixed>  $stats
     */
    public static function resolveStatus(array $stats, bool $dryRun = false): string
    {
        if ($stats['error'] !== null) {
            return self::STATUS_FAILED;
        }
        if ($dryRun || $stats['summary']['failed'] === 0) {
            return self::STATUS_SUCCESS;
        }

        return $stats['summary']['confirmed'] > 0 ? self::STATUS_PARTIAL : self::STATUS_FAILED;
    }

    private function execute(bool $dryRun, ?string $onlyCampaignId, bool $retryFailed): array
    {
        $stats = [
            'checked' => 0, 'bid_mismatch' => 0, 'bgt_mismatch' => 0,
            'bid_pushed' => 0, 'bgt_pushed' => 0, 'bid_left' => 0, 'bgt_left' => 0, 'waiting' => 0,
            'push_errors' => [], 'error' => null,
            'execution' => $this->execution, 'retried' => 0, 'pushed' => 0,
            'pushed_ids' => ['bid' => [], 'bgt' => []], 'held_ids' => [], 'skipped' => [], 'failed' => [],
        ];

        $targets = $this->enabledTargets($onlyCampaignId);
        $stats['checked'] = count($targets);
        if ($targets === []) {
            return $stats;
        }

        if (! $this->leaseAlive()) {
            return $this->leaseLost($stats);
        }
        try {
            $this->pull($targets);
        } catch (Throwable $e) {
            $stats['error'] = 'Live pull failed: '.$e->getMessage();
            Log::error('Google Shopping live match: pull failed', ['execution' => $this->execution, 'error' => $e->getMessage()]);

            return $stats;
        }

        [$bidIds, $bgtIds] = $this->mismatches($targets);
        $stats['bid_mismatch'] = count($bidIds);
        $stats['bgt_mismatch'] = count($bgtIds);
        $stats['bid_left'] = $stats['bid_mismatch'];
        $stats['bgt_left'] = $stats['bgt_mismatch'];
        Log::info('[GoogleShoppingLiveMatch] compared', [
            'execution' => $this->execution, 'checked' => $stats['checked'],
            'bid_mismatch' => $stats['bid_mismatch'], 'bgt_mismatch' => $stats['bgt_mismatch'], 'dry_run' => $dryRun,
        ]);
        if ($dryRun || ($bidIds === [] && $bgtIds === [])) {
            return $stats;
        }

        // Campaigns whose last push did not stick wait before the next try.
        if (! $retryFailed) {
            $bidWait = array_values(array_filter($bidIds, fn (string $id) => $this->isWaiting('bid', $id)));
            $bgtWait = array_values(array_filter($bgtIds, fn (string $id) => $this->isWaiting('bgt', $id)));
            $stats['waiting'] = count($bidWait) + count($bgtWait);
            $stats['held_ids'] = array_values(array_unique(array_merge(
                array_map(fn ($id) => 'bid:'.$id, $bidWait),
                array_map(fn ($id) => 'bgt:'.$id, $bgtWait)
            )));
            $bidIds = array_values(array_diff($bidIds, $bidWait));
            $bgtIds = array_values(array_diff($bgtIds, $bgtWait));
            if ($bidIds === [] && $bgtIds === []) {
                return $stats;
            }
        }

        // Push in small chunks; every chunk is isolated, so a failing campaign never blocks the others.
        $outcomes = ['bid' => [], 'bgt' => []];
        foreach ([['bid', $bidIds], ['bgt', $bgtIds]] as [$field, $ids]) {
            if (! $this->pushField($field, $ids, $targets, $stats, $outcomes)) {
                return $this->leaseLost($stats);
            }
        }

        // Pull again so the page dots and the numbers below reflect what Google Ads now holds.
        $pushedIds = ['bid' => $bidIds, 'bgt' => $bgtIds];
        if (! $this->verify($targets, $pushedIds, $stats, $bidStill, $bgtStill)) {
            return $stats;
        }

        // One retry of temporary failures (rate limit, timeout, 5xx). Only campaigns that the live re-read
        // still shows as different are pushed again, so nothing is ever pushed twice with the same result.
        $retry = ['bid' => [], 'bgt' => []];
        foreach ([['bid', $bidStill], ['bgt', $bgtStill]] as [$field, $still]) {
            foreach ($still as $id) {
                $o = $outcomes[$field][$id] ?? null;
                if ($o !== null && $o['status'] === 'error' && self::isTemporary($o['message'])) {
                    $retry[$field][] = $id;
                }
            }
        }
        if ($retry['bid'] !== [] || $retry['bgt'] !== []) {
            $stats['retried'] = count($retry['bid']) + count($retry['bgt']);
            Log::info('[GoogleShoppingLiveMatch] retrying temporary failures', ['execution' => $this->execution, 'bid' => $retry['bid'], 'bgt' => $retry['bgt']]);
            $this->sleep(self::RETRY_BACKOFF_SECONDS);
            foreach ($retry as $field => $ids) {
                if (! $this->pushField($field, $ids, $targets, $stats, $outcomes)) {
                    return $this->leaseLost($stats);
                }
            }
            if (! $this->verify($targets, $pushedIds, $stats, $bidStill, $bgtStill)) {
                return $stats;
            }
        }

        $stats['bid_left'] = count($bidStill) + $this->waitingCount('bid', $stats['bid_mismatch'], $bidIds);
        $stats['bgt_left'] = count($bgtStill) + $this->waitingCount('bgt', $stats['bgt_mismatch'], $bgtIds);
        $stats['bid_pushed'] = count($bidIds) - count(array_intersect($bidIds, $bidStill));
        $stats['bgt_pushed'] = count($bgtIds) - count(array_intersect($bgtIds, $bgtStill));

        foreach ($outcomes as $byId) {
            $stats['pushed'] += count(array_filter($byId, fn (array $o) => $o['status'] === 'ok'));
        }

        foreach (['bid' => [$bidIds, $bidStill], 'bgt' => [$bgtIds, $bgtStill]] as $field => [$ids, $still]) {
            foreach ($ids as $id) {
                if (in_array($id, $still, true)) {
                    $o = $outcomes[$field][$id] ?? null;
                    if ($o !== null && $o['status'] === 'busy') {
                        // Another push (a manual one from the page) holds this campaign: not a Google rejection,
                        // so no failed-push hold; the next scheduled run looks at it again.
                        $stats['skipped'][$field.':'.$id] = 'busy: '.$o['message'];

                        continue;
                    }
                    $this->markFailed($field, $id);
                    $reason = $o !== null && $o['message'] !== '' ? $o['message'] : 'Google Ads still shows a different value after the push.';
                    if ($o !== null && $o['status'] === 'skip') {
                        $stats['skipped'][$field.':'.$id] = $reason;
                    } else {
                        $stats['failed'][$field.':'.$id] = $reason;
                    }
                } else {
                    $this->clearFailed($field, $id);
                    if (isset($outcomes[$field][$id])) {
                        $stats['pushed_ids'][$field][] = $id;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Push $ids for one field in chunks. Returns false only when this run lost its lease and must stop.
     *
     * @param  list<string>  $ids
     * @param  array<string, array<string, mixed>>  $targets
     * @param  array<string, mixed>  $stats
     * @param  array{bid: array<string, array{status: string, message: string}>, bgt: array<string, array{status: string, message: string}>}  $outcomes
     */
    private function pushField(string $field, array $ids, array $targets, array &$stats, array &$outcomes): bool
    {
        foreach (array_chunk($ids, self::PUSH_CHUNK) as $chunk) {
            if (! $this->leaseAlive()) {
                return false;
            }
            $chunk = $this->stillDifferent($field, $chunk, $targets);
            if ($chunk === []) {
                continue;
            }

            try {
                $result = $field === 'bid' ? $this->pushBid($chunk) : $this->pushBgt($chunk);
            } catch (Throwable $e) {
                $stats['push_errors'][] = $field.': '.$e->getMessage();
                Log::error('Google Shopping live match: push failed', ['execution' => $this->execution, 'field' => $field, 'ids' => $chunk, 'error' => $e->getMessage()]);
                foreach ($chunk as $id) {
                    $outcomes[$field][$id] = ['status' => 'error', 'message' => $e->getMessage()];
                }

                continue;
            }

            if (! ($result['ok'] ?? false)) {
                $stats['push_errors'][] = $field.': '.trim((string) ($result['message'] ?? 'push failed'));
            }
            foreach ($this->parseOutcomes($result, $chunk) as $id => $outcome) {
                $outcomes[$field][$id] = $outcome;
            }
            Log::info('[GoogleShoppingLiveMatch] pushed chunk', ['execution' => $this->execution, 'field' => $field, 'ids' => $chunk, 'ok' => (bool) ($result['ok'] ?? false)]);
        }

        return true;
    }

    /**
     * Right before pushing, read again any campaign whose live value is older than FRESH_SECONDS and drop
     * the ones that already match, so a stale comparison never produces a duplicate or wrong push.
     *
     * @param  list<string>  $ids
     * @param  array<string, array<string, mixed>>  $targets
     * @return list<string>
     */
    private function stillDifferent(string $field, array $ids, array $targets): array
    {
        $stale = array_values(array_filter($ids, fn (string $id) => (microtime(true) - ($this->freshAt[$id] ?? 0)) > self::FRESH_SECONDS));
        if ($stale === []) {
            return $ids;
        }

        $subset = array_intersect_key($targets, array_flip($stale));
        try {
            $this->pull($subset);
        } catch (Throwable $e) {
            // Cannot confirm the live value: do not push blind.
            Log::warning('[GoogleShoppingLiveMatch] live re-read before push failed, campaigns left for the next run', ['execution' => $this->execution, 'ids' => $stale, 'error' => $e->getMessage()]);

            return array_values(array_diff($ids, $stale));
        }
        [$bid, $bgt] = $this->mismatches($subset);
        $stillDiffer = $field === 'bid' ? $bid : $bgt;
        $matchedNow = array_values(array_diff($stale, $stillDiffer));
        if ($matchedNow !== []) {
            Log::info('[GoogleShoppingLiveMatch] already matches live, not pushed', ['execution' => $this->execution, 'field' => $field, 'ids' => $matchedNow]);
        }

        return array_values(array_diff($ids, $matchedNow));
    }

    /**
     * Pull the pushed campaigns again and list the ones that still differ.
     *
     * @param  array<string, array<string, mixed>>  $targets
     * @param  array{bid: list<string>, bgt: list<string>}  $pushedIds
     * @param  array<string, mixed>  $stats
     * @param  list<string>|null  $bidStill
     * @param  list<string>|null  $bgtStill
     */
    private function verify(array $targets, array $pushedIds, array &$stats, ?array &$bidStill, ?array &$bgtStill): bool
    {
        $pushedTargets = array_intersect_key($targets, array_flip(array_merge($pushedIds['bid'], $pushedIds['bgt'])));
        try {
            $this->pull($pushedTargets);
        } catch (Throwable $e) {
            $stats['push_errors'][] = 'verify pull failed: '.$e->getMessage();

            return false;
        }
        [$bidStill, $bgtStill] = $this->mismatches($pushedTargets);
        // `mismatches()` looks at both fields of every pushed campaign; only count the field that was pushed.
        $bidStill = array_values(array_intersect($bidStill, $pushedIds['bid']));
        $bgtStill = array_values(array_intersect($bgtStill, $pushedIds['bgt']));

        return true;
    }

    /**
     * Per-campaign result of one push call, read from the `[OK] / [SKIP] / [BUSY] / [ERROR] name (id): text` lines.
     *
     * @param  array<string, mixed>  $result
     * @param  list<string>  $ids
     * @return array<string, array{status: string, message: string}>
     */
    private function parseOutcomes(array $result, array $ids): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) ($result['output'] ?? '')) ?: [] as $line) {
            if (preg_match('/^\[(OK|SKIP|ERROR|PAUSED|BUSY)\]\s.*\((\d+)\):\s*(.*)$/', $line, $m) && in_array($m[2], $ids, true)) {
                $out[$m[2]] = [
                    'status' => match ($m[1]) {'OK' => 'ok', 'SKIP' => 'skip', 'BUSY' => 'busy', default => 'error'},
                    'message' => trim($m[3]),
                ];
            }
        }
        foreach ($ids as $id) {
            $out[$id] ??= ['status' => ($result['ok'] ?? false) ? 'ok' : 'error', 'message' => ($result['ok'] ?? false) ? '' : trim((string) ($result['message'] ?? ''))];
        }

        return $out;
    }

    /** Rate limits, timeouts and Google/network 5xx are worth one more try; validation errors are not. */
    private static function isTemporary(string $message): bool
    {
        return (bool) preg_match('/rate|quota|RESOURCE_EXHAUSTED|UNAVAILABLE|INTERNAL|DEADLINE|timeout|timed out|temporar|try again|\b(429|500|502|503|504)\b|cURL error|connection (reset|refused)/i', $message);
    }

    /** Renew the lease between steps. False = another run owns it now, stop without pushing anything more. */
    private function leaseAlive(): bool
    {
        // Forced: renews AND re-verifies, with the owner token, that this run still owns the lease before each step.
        return $this->lease === null || $this->lease->beat(true);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function leaseLost(array $stats): array
    {
        $stats['error'] = 'Lease lost: another run took over, this execution stopped before pushing more.';
        Log::error('[GoogleShoppingLiveMatch] stopped: lease lost', ['execution' => $this->execution]);

        return $stats;
    }

    private function sleep(int $seconds): void
    {
        isset($this->hooks['sleep']) ? ($this->hooks['sleep'])($seconds) : sleep($seconds);
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
            $this->markFresh($targets);

            return;
        }
        $customerId = str_replace('-', '', trim((string) config('services.google_ads.login_customer_id')));
        $stats = app(GoogleShoppingLiveBidBgtService::class)->verifyAndStore($customerId, $targets, 'shopping');
        if (($stats['error'] ?? null) !== null) {
            throw new \RuntimeException((string) $stats['error']);
        }
        $this->markFresh($targets);
    }

    /** @param  array<string, array<string, mixed>>  $targets */
    private function markFresh(array $targets): void
    {
        $now = microtime(true);
        foreach (array_keys($targets) as $id) {
            $this->freshAt[(string) $id] = $now;
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
    private function states(array $ids): array
    {
        if (isset($this->hooks['states'])) {
            return ($this->hooks['states'])($ids);
        }
        if ($ids === [] || ! Schema::hasTable('google_shopping_live_syncs')) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('google_shopping_live_syncs')
                ->where('channel', 'shopping')
                ->whereIn('campaign_id', $chunk)
                ->get(['campaign_id', 'live_bid', 'live_bgt']) as $row) {
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
