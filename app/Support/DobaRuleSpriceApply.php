<?php

namespace App\Support;

use App\Services\DilRuleSpriceApplyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Doba: re-save S PRC from the rule that is ON right after the page switch (Sprc Dil <-> Std prc vs dil) or a Sprc Dil
 * rule changed, so the saved S PRC (and SGROI, SNROI, the blue triangle) follow the rules at once instead of at the next
 * scheduled run (dil:rule-sprice-apply doba at 6:35 AM / 10:35 PM). Doba is save-only (no auto push), so this only saves.
 *
 * Rapid edits (the Sprc Dil modal autosaves) share one run. A change that arrives while a run is going sets a flag and the
 * run repeats once at its end with the newest rules.
 */
class DobaRuleSpriceApply
{
    public const CHANNEL = 'doba';

    private const RUNNING = 'doba-rule-apply-running';

    private const AGAIN = 'doba-rule-apply-again';

    public static function handles(string $channel): bool
    {
        return strtolower(trim($channel)) === self::CHANNEL;
    }

    /** True while a re-save started by a rule change is going. */
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
                    Log::warning('[DobaRuleSpriceApply] failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget(self::RUNNING);
            }
        });
    }

    /**
     * Save S PRC for every Doba SKU now (no push).
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
            $result = DilRuleSpriceApplyService::for(self::CHANNEL)->run(false);
            $ok = $ok && ((array) ($result['stats']['errors'] ?? [])) === [];
            $rounds++;
        } while ($againKey !== null && Cache::pull($againKey) && $rounds < 5);

        return $ok;
    }
}
