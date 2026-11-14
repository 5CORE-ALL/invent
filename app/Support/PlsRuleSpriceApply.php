<?php

namespace App\Support;

use App\Services\PlsSprcDilAutoPushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PLS: re-save S PRC from the rule that is ON right after the page switch (Sprc Dil <-> Std prc vs dil) or a
 * Sprc Dil rule changed, so the saved S PRC (and SGROI, SNROI, the blue triangle) follow the rules at once instead of at
 * the next scheduled run. Nothing is pushed to PLS here; the scheduled run and the blue catch-up push.
 *
 * Same as ShopifyB2cRuleSpriceApply: rapid edits (the Sprc Dil modal autosaves) share one run. A change that arrives
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
     * Push the freshly saved S PRC to ProLightSounds right away, as a detached background process.
     *
     * A rule change used to wait for the next 10-minute pls:push-blue-prices tick, then trickle out at one SKU
     * every ~2s, so ~600 blue triangles stayed unpushed for 30+ minutes after an edit. The same command now starts
     * at once; the service lock keeps it from running twice, and the scheduled catch-up still picks up anything left.
     */
    public static function pushNow(bool $throttled = false): bool
    {
        try {
            // Another push is already going (the service holds this lock for the whole run): nothing to start.
            $probe = Cache::lock(PlsSprcDilAutoPushService::LOCK_KEY, 5);
            if (! $probe->get()) {
                return true;
            }
            $probe->release();
            // Page opens and reloads can come in bursts; one spawn per window is enough (loading the rows is heavy).
            if ($throttled && ! Cache::add('pls-push-now-kick', 1, 120)) {
                return true;
            }

            return DetachedArtisan::spawn('pls:push-blue-prices');
        } catch (Throwable $e) {
            Log::warning('[PlsRuleSpriceApply] could not start the push: '.$e->getMessage());

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
                $summary = app(PlsSprcDilAutoPushService::class)->run(
                    skipPush: true,
                    sleepMs: 0,
                    cooldownMinutes: 0,
                    errorCooldownMinutes: 0
                );
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
