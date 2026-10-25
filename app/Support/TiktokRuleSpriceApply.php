<?php

namespace App\Support;

use App\Services\TiktokSprcDilAutoPushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok and TikTok 2: re-save S PRC from the rule that is ON right after the page switch (Sprc Dil <-> Std prc vs dil)
 * or a Sprc Dil rule changed, so the saved S PRC (and SGROI, SNROI, the blue triangle) follow the rules at once instead
 * of at the next scheduled run. Nothing is pushed to TikTok here; the scheduled run and the blue catch-up push.
 *
 * Same as ReverbRuleSpriceApply, one run per channel: rapid edits (the Sprc Dil modal autosaves) share one run. A change
 * that arrives while a run is going sets a flag and the run repeats once at its end with the newest rules.
 */
class TiktokRuleSpriceApply
{
    public static function handles(string $channel): bool
    {
        return in_array(strtolower(trim($channel)), TiktokSprcDilAutoPushService::CHANNELS, true);
    }

    private static function runningKey(string $channel): string
    {
        return $channel.'-rule-apply-running';
    }

    private static function againKey(string $channel): string
    {
        return $channel.'-rule-apply-again';
    }

    /** True while a re-save started by a rule change is going. */
    public static function running(string $channel): bool
    {
        return Cache::has(self::runningKey(TiktokSprcDilAutoPushService::normalizeChannel($channel)));
    }

    /** Run after the HTTP response is sent. */
    public static function dispatch(string $channel): void
    {
        if (! self::handles($channel)) {
            return;
        }
        $channel = TiktokSprcDilAutoPushService::normalizeChannel($channel);
        if (! Cache::add(self::runningKey($channel), 1, 1800)) {
            Cache::put(self::againKey($channel), 1, 1800);

            return;
        }
        app()->terminating(static function () use ($channel): void {
            try {
                self::runNow($channel, self::againKey($channel));
            } catch (Throwable $e) {
                try {
                    Log::warning('[TiktokRuleSpriceApply] failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget(self::runningKey($channel));
            }
        });
    }

    /**
     * Save S PRC for every SKU now. Waits for a scheduled run that holds the lock, then runs.
     *
     * @return bool true when every row was recalculated
     */
    public static function runNow(string $channel, ?string $againKey = null): bool
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');
        $channel = TiktokSprcDilAutoPushService::normalizeChannel($channel);
        $ok = true;
        $rounds = 0;
        do {
            if ($againKey !== null) {
                Cache::forget($againKey);
            }
            StdPrcVsDilPricer::flush();
            $deadline = microtime(true) + 120;
            do {
                $summary = TiktokSprcDilAutoPushService::forChannel($channel)->run(
                    skipPush: true,
                    sleepMs: 0,
                    cooldownMinutes: 0,
                    errorCooldownMinutes: 0,
                    blueCatchUp: true
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
