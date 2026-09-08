<?php

namespace App\Support;

use App\Jobs\RunMacysRuleSpriceApplyJob;
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
}
