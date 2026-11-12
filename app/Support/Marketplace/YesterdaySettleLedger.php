<?php

namespace App\Support\Marketplace;

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
 * It is stored in a JSON file (not the cache) so `php artisan optimize:clear` on deploy does not
 * erase it and trigger a full re-pull.
 */
class YesterdaySettleLedger
{
    public const TZ = 'America/Los_Angeles';

    /**
     * Minutes after Pacific midnight at which each pass becomes due:
     * 0 = just after close, 1 = a few hours later, 2 = late (pending orders priced, status changes).
     *
     * @var list<int>
     */
    public const PASS_OFFSET_MINUTES = [40, 180, 390];

    /** Give up on a task/pass after this many failed attempts (it is reported, not retried forever). */
    public const MAX_ATTEMPTS = 3;

    /**
     * Every command whose order data feeds the Yesterday badge.
     * Quick API pulls first; Amazon (throttled order-items calls) last so it never delays the rest.
     *
     * 'passes'  : which pass indexes (see PASS_OFFSET_MINUTES) this task takes part in.
     * 'timeout' : seconds before the subprocess is killed.
     *
     * @return array<string, array{command: string, passes: list<int>, timeout: int}>
     */
    public static function tasks(): array
    {
        $all = [0, 1, 2];

        return [
            'ebay' => ['command' => 'app:fetch-ebay-orders', 'passes' => $all, 'timeout' => 1800],
            'ebay2' => ['command' => 'app:fetch-ebay2-orders', 'passes' => $all, 'timeout' => 1800],
            'ebay3' => ['command' => 'ebay3:daily --days=60', 'passes' => $all, 'timeout' => 1800],
            'temu' => ['command' => 'app:fetch-temu-orders', 'passes' => $all, 'timeout' => 1800],
            'temu2' => ['command' => 'app:fetch-temu2-orders --days=60', 'passes' => $all, 'timeout' => 1800],
            'temu3' => ['command' => 'app:fetch-temu3-orders --days=60', 'passes' => $all, 'timeout' => 1800],
            'wayfair_api' => ['command' => 'sync:wayfair-l30-api', 'passes' => $all, 'timeout' => 1800],
            'wayfair' => ['command' => 'wayfair:daily --days=60', 'passes' => $all, 'timeout' => 1800],
            'mirakl' => ['command' => 'mirakl:daily --days=60', 'passes' => $all, 'timeout' => 1800],
            'doba' => ['command' => 'doba:daily --days=60', 'passes' => $all, 'timeout' => 1800],
            'shein_l30' => ['command' => 'shein:fetch orders --days=30 --target=l30', 'passes' => $all, 'timeout' => 1800],
            'shein_l60' => ['command' => 'shein:fetch orders --days=60 --target=l60', 'passes' => $all, 'timeout' => 1800],
            'tiktok' => ['command' => 'tiktok:fetch-orders --days=60 --prune', 'passes' => $all, 'timeout' => 1800],
            'tiktok2' => ['command' => 'tiktok:fetch-orders --channel=tiktok2 --days=60 --prune', 'passes' => $all, 'timeout' => 1800],
            'pls' => ['command' => 'app:fetch-pls-sales-data --days=90', 'passes' => [1, 2], 'timeout' => 1800],
            // Ad spend for Y Ads%. Amazon / eBay finish a day's report hours after the day ends and
            // the commands used to keep the FIRST pull ("yesterday row exists -> skip"), so an early,
            // partial pull stayed forever and Y Ads% fell back to the L30 rate or read low.
            // --yesterday-only replaces just that day's rows (small, idempotent upsert).
            'ads_amazon_sp' => ['command' => 'app:amazon-sp-campaign-reports --yesterday-only', 'passes' => [1, 2], 'timeout' => 3600],
            'ads_amazon_sb' => ['command' => 'app:amazon-sb-campaign-reports --yesterday-only', 'passes' => [1, 2], 'timeout' => 3600],
            'ads_ebay' => ['command' => 'app:ebay-campaign-reports --yesterday-only', 'passes' => [1, 2], 'timeout' => 3600],
            'ads_ebay2' => ['command' => 'app:ebay2-campaign-reports --yesterday-only', 'passes' => [1, 2], 'timeout' => 3600],
            'ads_ebay3' => ['command' => 'app:ebay3-campaign-reports --yesterday-only', 'passes' => [1, 2], 'timeout' => 3600],
            // Listing views for Y Views / Y CVR. The marketplace L1 columns are refreshed by these
            // fetches (once a day, shortly after Pacific midnight, so often a partial day); the late
            // pass re-reads them, then stores the dated one-day figures. Amazon traffic is only
            // complete ~3 days after a day ends, so its settled days are (re)collected too.
            'views_ebay' => ['command' => 'app:fetch-ebay-reports', 'passes' => [2], 'timeout' => 3600],
            'views_ebay2' => ['command' => 'app:fetch-ebay-two-metrics', 'passes' => [2], 'timeout' => 3600],
            'views_ebay3' => ['command' => 'app:fetch-ebay-three-metrics', 'passes' => [2], 'timeout' => 3600],
            'views_temu' => ['command' => 'app:fetch-temu-metrics', 'passes' => [2], 'timeout' => 3600],
            'views_temu2' => ['command' => 'app:fetch-temu2-metrics', 'passes' => [2], 'timeout' => 3600],
            'views_temu3' => ['command' => 'app:fetch-temu3-metrics', 'passes' => [2], 'timeout' => 3600],
            'views_store' => ['command' => 'channel:collect-yesterday-views --skip-amazon', 'passes' => [2], 'timeout' => 3600],
            'views_amazon_settled' => ['command' => 'channel:collect-yesterday-views --amazon-only --amazon-backfill=5', 'passes' => [2], 'timeout' => 7200],
            // Amazon: full Pacific-yesterday pull right after close and once more; the LastUpdated
            // refresh (prices pending orders) only on the late pass — it costs one throttled
            // order-items call per updated order.
            'amazon_close' => ['command' => 'app:fetch-amazon-orders --close-yesterday --with-items', 'passes' => [0, 1], 'timeout' => 7200],
            'amazon_refresh' => ['command' => 'app:fetch-amazon-orders --incremental-only --with-items --incremental-lookback-hours=24', 'passes' => [2], 'timeout' => 7200],
            // Rebuild the channel matrix from the freshly pulled orders (it also runs every 5 min).
            'calculate' => ['command' => 'channel:calculate-data --force', 'passes' => $all, 'timeout' => 1800],
        ];
    }

    public static function pacificYesterday(?Carbon $now = null): string
    {
        $now = ($now ?? Carbon::now(self::TZ))->copy()->setTimezone(self::TZ);

        return $now->copy()->subDay()->toDateString();
    }

    /**
     * Highest pass index that is due for $date at $now (-1 when the day has not closed + 40 min yet).
     */
    public static function highestDuePass(string $date, ?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now(self::TZ))->copy()->setTimezone(self::TZ);
        $midnight = Carbon::parse($date, self::TZ)->addDay()->startOfDay();
        $minutes = (int) $midnight->diffInMinutes($now, false);

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
     * @param  array{command: string, passes: list<int>, timeout: int}  $task
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
     *
     * @return array{date: string, settled: bool, passes_due: int, passes_total: int, pending: list<string>, failed: list<string>, settled_at: ?string}
     */
    public static function status(?string $date = null, ?Carbon $now = null): array
    {
        $date ??= self::pacificYesterday($now);
        $hi = self::highestDuePass($date, $now);
        $ledger = self::read($date);
        $total = count(self::PASS_OFFSET_MINUTES);

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
                if ((int) ($state['attempts'][(string) $p] ?? 0) >= self::MAX_ATTEMPTS) {
                    $failed[$key] = $key;
                } else {
                    $pending[$key] = $key;
                }
            }
        }

        $settled = $hi === $total - 1 && $pending === [] && $failed === [];

        return [
            'date' => $date,
            'settled' => $settled,
            'passes_due' => $hi + 1,
            'passes_total' => $total,
            'pending' => array_values($pending),
            'failed' => array_values($failed),
            'settled_at' => $settled ? $latest : null,
        ];
    }
}
