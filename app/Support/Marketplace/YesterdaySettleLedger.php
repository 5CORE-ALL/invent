<?php

namespace App\Support\Marketplace;

use App\Support\Marketplace\Settle\SettleTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Tracks which "settle" pulls for a Pacific calendar day have really finished.
 *
 * "Yesterday" on /all-marketplace-master is the Pacific day, and its numbers are summed from each
 * marketplace's synced order tables. A day is only trustworthy once every marketplace has re-pulled
 * it AFTER the Pacific day closed (and again later, for late / pending orders). This ledger is the
 * single source of truth for that, so the pulls no longer depend on a cron minute being hit:
 *
 *   - orders:settle-yesterday runs often, reads this ledger, and runs whatever is due but not done.
 *   - the page reads status() to show whether yesterday is settled or still provisional.
 *
 * Everything must be finished before 09:00 Pacific (the start of the US business day): passes are
 * due early enough, independent marketplaces run in parallel lanes, no new pull starts after
 * START_CUTOFF_MINUTES, and anything still running at HARD_STOP_MINUTES is stopped.
 *
 * It is stored in a JSON file (not the cache) so `php artisan optimize:clear` on deploy does not
 * erase it and trigger a full re-pull.
 */
class YesterdaySettleLedger
{
    public const TZ = 'America/Los_Angeles';

    /**
     * Minutes after Pacific midnight at which each pass becomes due:
     *   0 = 00:40 PT  just after close
     *   1 = 02:00 PT  a few hours later (ad reports, delayed batches)
     *   2 = 05:00 PT  late pass (pending Amazon orders priced, views, final recalculation);
     *                 leaves ~4 h before the 09:00 PT deadline for the slowest (throttled) lane.
     *
     * @var list<int>
     */
    public const PASS_OFFSET_MINUTES = [40, 120, 300];

    /** No new pull is started after this many minutes past Pacific midnight (08:30 PT). */
    public const START_CUTOFF_MINUTES = 510;

    /** Anything still running at this point is stopped (09:00 PT). Unfinished tasks show as missed. */
    public const HARD_STOP_MINUTES = 540;

    /** Give up on a task/pass after this many failed attempts (it is reported, not retried forever). */
    public const MAX_ATTEMPTS = 3;

    /** How many lanes (independent marketplaces) run at the same time. */
    public const DEFAULT_CONCURRENCY = 4;

    /** Lane that runs last, after every other lane has finished. */
    public const FINAL_LANE = '_final';

    /**
     * Every command whose data feeds the Yesterday badge.
     *
     * 'lane'    : tasks in one lane run one after another (they share an API / rate limit); different
     *             lanes run in parallel. Order inside a lane = order in this array.
     * 'passes'  : which pass indexes (see PASS_OFFSET_MINUTES) this task takes part in.
     * 'timeout' : seconds before the subprocess is killed.
     *
     * @return array<string, array{command: string, lane: string, passes: list<int>, timeout: int}>
     */
    public static function tasks(): array
    {
        $all = [0, 1, 2];
        $t = static fn (string $command, string $lane, array $passes, int $timeout = 1800): array => [
            'command' => $command,
            'lane' => $lane,
            'passes' => $passes,
            'timeout' => $timeout,
        ];

        return [
            // --- eBay: order pulls, then that day's ad report (re-pulled; the first pull can be partial)
            'ebay' => $t('app:fetch-ebay-orders', 'ebay', $all),
            'ebay2' => $t('app:fetch-ebay2-orders', 'ebay', $all),
            'ebay3' => $t('ebay3:daily --days=60', 'ebay', $all),
            // Ad spend for Y Ads%. Amazon / eBay finish a day's report hours after the day ends and
            // the commands used to keep the FIRST pull ("yesterday row exists -> skip"), so an early,
            // partial pull stayed forever and Y Ads% fell back to the L30 rate or read low.
            // --yesterday-only replaces just that day's rows (small, idempotent upsert).
            'ads_ebay' => $t('app:ebay-campaign-reports --yesterday-only', 'ebay', [1, 2], 3600),
            'ads_ebay2' => $t('app:ebay2-campaign-reports --yesterday-only', 'ebay', [1, 2], 3600),
            'ads_ebay3' => $t('app:ebay3-campaign-reports --yesterday-only', 'ebay', [1, 2], 3600),

            // --- Temu
            'temu' => $t('app:fetch-temu-orders', 'temu', $all),
            'temu2' => $t('app:fetch-temu2-orders --days=60', 'temu', $all),
            'temu3' => $t('app:fetch-temu3-orders --days=60', 'temu', $all),

            // --- Other marketplaces (each its own lane so one slow API never delays the rest)
            'wayfair_api' => $t('sync:wayfair-l30-api', 'wayfair', $all),
            'wayfair' => $t('wayfair:daily --days=60', 'wayfair', $all),
            'shein_l30' => $t('shein:fetch orders --days=30 --target=l30', 'shein', $all),
            'shein_l60' => $t('shein:fetch orders --days=60 --target=l60', 'shein', $all),
            'tiktok' => $t('tiktok:fetch-orders --days=60 --prune', 'tiktok', $all),
            'tiktok2' => $t('tiktok:fetch-orders --channel=tiktok2 --days=60 --prune', 'tiktok', $all),
            'mirakl' => $t('mirakl:daily --days=60', 'misc', $all),
            'doba' => $t('doba:daily --days=60', 'misc', $all),
            'pls' => $t('app:fetch-pls-sales-data --days=90', 'misc', [1, 2]),

            // --- Amazon (throttled order-items API: one lane, never parallel with itself)
            'ads_amazon_sp' => $t('app:amazon-sp-campaign-reports --yesterday-only', 'amazon', [1, 2], 3600),
            'ads_amazon_sb' => $t('app:amazon-sb-campaign-reports --yesterday-only', 'amazon', [1, 2], 3600),
            // Full Pacific-yesterday pull right after close and once more; the LastUpdated refresh
            // (prices pending orders) only on the late pass — one throttled call per updated order.
            'amazon_close' => $t('app:fetch-amazon-orders --close-yesterday --with-items', 'amazon', [0, 1], 5400),
            'amazon_refresh' => $t('app:fetch-amazon-orders --incremental-only --with-items --incremental-lookback-hours=24', 'amazon', [2], 5400),

            // --- Listing views for Y Views / Y CVR. The marketplace L1 columns are refreshed by these
            // fetches (once a day, shortly after Pacific midnight, so often a partial day); the late
            // pass re-reads them, then stores the dated one-day figures. Amazon traffic is only
            // complete ~3 days after a day ends, so its settled days are (re)collected too.
            'views_ebay' => $t('app:fetch-ebay-reports', 'views', [2], 3600),
            'views_ebay2' => $t('app:fetch-ebay-two-metrics', 'views', [2], 3600),
            'views_ebay3' => $t('app:fetch-ebay-three-metrics', 'views', [2], 3600),
            'views_temu' => $t('app:fetch-temu-metrics', 'views', [2], 3600),
            'views_temu2' => $t('app:fetch-temu2-metrics', 'views', [2], 3600),
            'views_temu3' => $t('app:fetch-temu3-metrics', 'views', [2], 3600),
            'views_store' => $t('channel:collect-yesterday-views --skip-amazon', 'views', [2], 3600),
            'views_amazon_settled' => $t('channel:collect-yesterday-views --amazon-only --amazon-backfill=5', 'amazon', [2], 3600),

            // --- Rebuild the channel matrix from the freshly pulled data (also runs every 5 min).
            'calculate' => $t('channel:calculate-data --force', self::FINAL_LANE, $all),
        ];
    }

    public static function pacificYesterday(?Carbon $now = null): string
    {
        $now = ($now ?? Carbon::now(self::TZ))->copy()->setTimezone(self::TZ);

        return $now->copy()->subDay()->toDateString();
    }

    /** Pacific midnight that closes $date (= 00:00 of the next day). */
    public static function dayClose(string $date): Carbon
    {
        return Carbon::parse($date, self::TZ)->addDay()->startOfDay();
    }

    /** Minutes since $date closed in Pacific time (negative while the day is still open). */
    public static function minutesSinceClose(string $date, ?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now(self::TZ))->copy()->setTimezone(self::TZ);

        return (int) self::dayClose($date)->diffInMinutes($now, false);
    }

    /** True once no new pull may start for $date (08:30 PT). */
    public static function pastStartCutoff(string $date, ?Carbon $now = null): bool
    {
        return self::minutesSinceClose($date, $now) >= self::START_CUTOFF_MINUTES;
    }

    /** True once running pulls must be stopped (09:00 PT). */
    public static function pastHardStop(string $date, ?Carbon $now = null): bool
    {
        return self::minutesSinceClose($date, $now) >= self::HARD_STOP_MINUTES;
    }

    /**
     * Highest pass index that is due for $date at $now (-1 when the day has not closed + 40 min yet).
     */
    public static function highestDuePass(string $date, ?Carbon $now = null): int
    {
        $minutes = self::minutesSinceClose($date, $now);

        $hi = -1;
        foreach (self::PASS_OFFSET_MINUTES as $i => $offset) {
            if ($minutes >= $offset) {
                $hi = $i;
            }
        }

        return $hi;
    }

    public static function path(string $date): string
    {
        return storage_path('app/settle_yesterday/'.$date.'.json');
    }

    /**
     * @return array{date: string, tasks: array<string, array{done: array<string, string>, attempts: array<string, int>, error?: string}>}
     */
    public static function read(string $date): array
    {
        $file = self::path($date);
        if (is_file($file)) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (is_array($data) && isset($data['tasks']) && is_array($data['tasks'])) {
                $data['date'] = $date;

                return $data;
            }
        }

        return ['date' => $date, 'tasks' => []];
    }

    /**
     * @param  array<string, mixed>  $ledger
     */
    public static function write(string $date, array $ledger): void
    {
        $file = self::path($date);
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        // Keep the folder small.
        foreach (glob(dirname($file).'/*.json') ?: [] as $old) {
            if (filemtime($old) !== false && filemtime($old) < time() - 14 * 86400) {
                @unlink($old);
            }
        }
    }

    /**
     * Pass indexes of $task that are due, not done, and still allowed to retry.
     *
     * @param  array<string, mixed>  $ledger
     * @param  array{command: string, lane: string, passes: list<int>, timeout: int}  $task
     * @return list<int>
     */
    public static function pendingPasses(array $ledger, string $key, array $task, int $hi): array
    {
        $state = $ledger['tasks'][$key] ?? ['done' => [], 'attempts' => []];
        $pending = [];
        foreach ($task['passes'] as $p) {
            if ($p > $hi) {
                continue;
            }
            if (isset($state['done'][(string) $p])) {
                continue;
            }
            if ((int) ($state['attempts'][(string) $p] ?? 0) >= self::MAX_ATTEMPTS) {
                continue;
            }
            $pending[] = $p;
        }

        return $pending;
    }

    /**
     * Status for the page. `settled` is true only when the final pass is due and every task is done.
     * After the 09:00 PT hard stop, anything not done is reported as failed ("missed"), because it
     * will not be retried that day.
     *
     * `settled` = every ledger pull is done AND every task is verified (see SettleTracker). The extra
     * keys `ledger_settled`, `verification` and `tasks` are additive; all earlier keys are unchanged.
     *
     * @return array{date: string, settled: bool, ledger_settled: bool, verification: array<string, mixed>, tasks: array<string, array<string, mixed>>, passes_due: int, passes_total: int, pending: list<string>, failed: list<string>, settled_at: ?string, deadline_passed: bool}
     */
    public static function status(?string $date = null, ?Carbon $now = null): array
    {
        $date ??= self::pacificYesterday($now);
        $hi = self::highestDuePass($date, $now);
        $ledger = self::read($date);
        $total = count(self::PASS_OFFSET_MINUTES);
        $deadlinePassed = self::pastHardStop($date, $now);

        $pending = [];
        $failed = [];
        $latest = null;

        foreach (self::tasks() as $key => $task) {
            $state = $ledger['tasks'][$key] ?? ['done' => [], 'attempts' => []];
            foreach ($state['done'] ?? [] as $ts) {
                if ($latest === null || strcmp((string) $ts, $latest) > 0) {
                    $latest = (string) $ts;
                }
            }
            foreach ($task['passes'] as $p) {
                if ($p > $hi) {
                    continue;
                }
                if (isset($state['done'][(string) $p])) {
                    continue;
                }
                $exhausted = (int) ($state['attempts'][(string) $p] ?? 0) >= self::MAX_ATTEMPTS;
                if ($exhausted || $deadlinePassed) {
                    $failed[$key] = $key;
                } else {
                    $pending[$key] = $key;
                }
            }
        }

        $ledgerSettled = $hi === $total - 1 && $pending === [] && $failed === [];

        // Phase 1/2: per-task detail and data verification. "Settled" additionally needs every task
        // to be VERIFIED against the stored data (exit code 0 alone is not enough). If the tracking
        // tables cannot be read the day is reported as unverified, never as settled.
        $required = (bool) config('settle.require_verification', true);
        $verification = [
            'required' => $required,
            'available' => false,
            'all_verified' => false,
            'verified' => 0,
            'total' => count(self::tasks()),
            'failed' => [],
            'unverified' => [],
        ];
        $taskDetail = [];
        try {
            $described = SettleTracker::describe($date, $ledger, $hi, $deadlinePassed, $now);
            $taskDetail = $described['tasks'];
            $verification['available'] = $described['available'];
            $verification['all_verified'] = $described['summary']['all_verified'];
            $verification['verified'] = $described['summary']['verified'];
            $verification['failed'] = $described['summary']['verification_failed'];
            $verification['unverified'] = $described['summary']['unverified'];
        } catch (\Throwable $e) {
            // keep the defaults: unverified
        }
        $settled = $ledgerSettled && (! $required || $verification['all_verified']);

        return [
            'date' => $date,
            'settled' => $settled,
            'ledger_settled' => $ledgerSettled,
            'verification' => $verification,
            'tasks' => $taskDetail,
            'passes_due' => $hi + 1,
            'passes_total' => $total,
            'pending' => array_values($pending),
            'failed' => array_values($failed),
            'settled_at' => $settled ? $latest : null,
            'deadline_passed' => $deadlinePassed,
        ];
    }
}
