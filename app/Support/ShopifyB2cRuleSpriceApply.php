<?php

namespace App\Support;

use App\Services\ShopifyB2cSprcDilAutoPushService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shopify B2C: re-save S PRC from the rule that is ON right after the page switch (Sprc Dil <-> Std prc vs dil) or a
 * Sprc Dil rule changed, so the saved S PRC (and SGROI, SNROI, the blue triangle) follow the rules at once instead of at
 * the next scheduled run. Nothing is pushed to Shopify here; the scheduled blue catch-up pushes.
 *
 * Same as EbayRuleSpriceApply: rapid edits (the Sprc Dil modal autosaves) share one run. A change that arrives while a
 * run is going sets a flag and the run repeats once at its end with the newest rules.
 */
class ShopifyB2cRuleSpriceApply
{
    public const CHANNEL = 'shopify_b2c';

    private const RUNNING = 'shopify-b2c-rule-apply-running';

    private const AGAIN = 'shopify-b2c-rule-apply-again';

    public static function handles(string $channel): bool
    {
        return strtolower(trim($channel)) === self::CHANNEL;
    }

    /** True while a re-save started by a rule change is going. The page polls this, then reloads its rows. */
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
            } catch (Throwable $e) {
                try {
                    Log::warning('[ShopifyB2cRuleSpriceApply] failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget(self::RUNNING);
            }
        });
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
                $summary = app(ShopifyB2cSprcDilAutoPushService::class)->run();
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
