<?php

namespace App\Support;

use App\Services\PlsSprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLS: re-save S PRC from the Std prc vs dil rules right after they change, so the saved S PRC (and SGROI, SNROI,
 * the blue triangle) follow the rules at once instead of at the next scheduled run. The re-save pushes nothing itself;
 * pushNow() queues the push right after it.
 *
 * Same as ShopifyB2cRuleSpriceApply: rapid edits (the rules modal autosaves) share one run. A change that arrives
 * while a run is going sets a flag and the run repeats once at its end with the newest rules.
 */
class PlsRuleSpriceApply
{
    public const CHANNEL = 'pls';

    private const RUNNING = 'pls-rule-apply-running';

    private const AGAIN = 'pls-rule-apply-again';

    public static function handles(string $channel): bool
    {
        return strtolower(trim($channel)) === self::CHANNEL;
    }

    /** True while a re-save started by a rule change is going.  */
    public static function running(): bool
    {
        return Cache::has(self::RUNNING);
    }

    /** Run after the HTTP response is sent. */
    public static function dispatch(string $channel): void
    {
        if (! self::handles($channel)) {
            return;
        }
        if (! Cache::add(self::RUNNING, 1, 1800)) {
            Cache::put(self::AGAIN, 1, 1800);

            return;
        }
        app()->terminating(static function (): void {
            try {
                self::runNow(self::AGAIN);
                self::pushNow();
            } catch (Throwable $e) {
                try {
                    Log::warning('[PlsRuleSpriceApply] failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget(self::RUNNING);
            }
        });
    }

    /**
     * Queue the push of the freshly saved S PRC right away, through the same shared S PRC worker Shopify B2C uses
     * (ChannelPushSpriceDailyEnqueue → channel:push-sprice-run pls).
     *
     * A rule change used to wait for the next 10-minute pls:push-blue-prices tick, so hundreds of blue triangles stayed
     * unpushed for 30+ minutes after an edit. The worker holds its own file lock (survives a cache clear), so two never
     * run at once, and the scheduled catch-up still picks up anything left.
     *
     * @param  bool  $throttled  page opens: at most one queue request per 2 minutes
     */
    public static function pushNow(bool $throttled = false): bool
    {
        try {
            if ($throttled && ! Cache::add('pls-push-now-kick', 1, 120)) {
                return true;
            }
            app(ChannelPushSpriceDailyEnqueue::class)->enqueueChannel('pls');

            return true;
        } catch (Throwable $e) {
            Log::warning('[PlsRuleSpriceApply] could not queue the push: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Save S PRC for every SKU now. Waits for a scheduled run that holds the lock, then runs.
     *
     * @return bool true when every row was recalculated
     */
    public static function runNow(?string $againKey = null): bool
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');
        $ok = true;
        $rounds = 0;
        do {
            if ($againKey !== null) {
                Cache::forget($againKey);
            }
            StdPrcVsDilPricer::flush();
            $deadline = microtime(true) + 120;
            do {
                $summary = app(PlsSprcDilAutoPushService::class)->run();
                $errors = (array) ($summary['stats']['errors'] ?? []);
                $locked = in_array('lock: already running', $errors, true);
                if ($locked) {
                    sleep(2);
                }
            } while ($locked && microtime(true) < $deadline);
            $ok = $ok && $errors === [];
            $rounds++;
        } while ($againKey !== null && Cache::pull($againKey) && $rounds < 5);

        return $ok;
    }
}
