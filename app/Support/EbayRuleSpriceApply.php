<?php

namespace App\Support;

use App\Services\EbaySprcDilAutoPushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * eBay 1/2/3: re-save S PRC from the rules right after a rule changed, so the saved S PRC (and SGROI, SNROI, the
 * blue triangle) follow the rules at once instead of at the next 10-minute run. Nothing is pushed to eBay here;
 * the scheduled blue catch-up pushes.
 */
class EbayRuleSpriceApply
{
    /**
     * Run after the HTTP response is sent. Rapid rule edits (the Sprc Dil modal autosaves) share one run: a change
     * that arrives while a run is going sets a flag, and the run repeats once at its end with the newest rules.
     */
    public static function dispatch(string $channel): void
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, EbayRuleSprice::CHANNELS, true)) {
            return;
        }
        $running = 'ebay-rule-apply-running-'.$channel;
        $again = 'ebay-rule-apply-again-'.$channel;
        if (! Cache::add($running, 1, 1800)) {
            Cache::put($again, 1, 1800);

            return;
        }
        app()->terminating(static function () use ($channel, $running, $again): void {
            try {
                self::runNow($channel, $again);
            } catch (Throwable $e) {
                try {
                    Log::warning('[EbayRuleSpriceApply] '.$channel.' failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget($running);
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
        $ok = true;
        $rounds = 0;
        do {
            if ($againKey !== null) {
                Cache::forget($againKey);
            }
            StdPrcVsDilPricer::flush();
            $deadline = microtime(true) + 120;
            do {
                $summary = app(EbaySprcDilAutoPushService::class)->run($channel);
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
