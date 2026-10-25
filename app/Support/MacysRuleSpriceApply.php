<?php

namespace App\Support;

use App\Jobs\RunMacysRuleSpriceApplyJob;
use App\Services\MacysRuleSpriceApplyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MacysRuleSpriceApply
{
    /**
     * Queue a background wipe + Dil/A-Price SPRICE apply. Safe to call from HTTP or cron.
     *
     * @param  list<string>|null  $onlySkus
     */
    public static function dispatch(?array $onlySkus = null): void
    {
        try {
            RunMacysRuleSpriceApplyJob::dispatch($onlySkus);
        } catch (Throwable $e) {
            Log::warning('[MacysRuleSpriceApply] dispatch failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Re-save S PRC from the rule that is ON right after the HTTP response is sent (no queue worker needed), so the saved
     * S PRC follows a rule change at once, like Amazon. Rapid edits share one run: a change that arrives while a run is going
     * sets a flag and the run repeats once at its end with the newest rules. Nothing is pushed here.
     */
    public static function dispatchAfterResponse(): void
    {
        $running = 'macys-rule-apply-running';
        $again = 'macys-rule-apply-again';
        if (! Cache::add($running, 1, 1800)) {
            Cache::put($again, 1, 1800);

            return;
        }
        app()->terminating(static function () use ($running, $again): void {
            try {
                self::runNow($again);
            } catch (Throwable $e) {
                try {
                    Log::warning('[MacysRuleSpriceApply] run failed: '.$e->getMessage());
                } catch (Throwable $ignored) {
                    // no logger
                }
            } finally {
                Cache::forget($running);
            }
        });
    }

    /** True while a rule change is still re-saving S PRC. */
    public static function running(): bool
    {
        return Cache::has('macys-rule-apply-running');
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
            $deadline = microtime(true) + 120;
            do {
                $summary = app(MacysRuleSpriceApplyService::class)->run();
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
