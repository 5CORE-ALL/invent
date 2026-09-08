<?php

namespace App\Support;

use App\Jobs\RunPurchasingPowerRuleSpriceApplyJob;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchasingPowerRuleSpriceApply
{
    /**
     * Queue Dil apply + MCM price push. Safe to call from HTTP or cron.
     *
     * @param  list<string>|null  $onlySkus
     */
    public static function dispatch(?array $onlySkus = null): void
    {
        try {
            RunPurchasingPowerRuleSpriceApplyJob::dispatch($onlySkus);
        } catch (Throwable $e) {
            Log::warning('[PurchasingPowerRuleSpriceApply] dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
