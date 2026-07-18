<?php

namespace App\Services\CronMonitor\Healers;

use App\Services\CronMonitor\CronExecutionContext;
use App\Services\CronMonitor\FailureClassifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseReconnectHealer implements HealerInterface
{
    public function supports(array $classification, ?Throwable $exception = null): bool
    {
        if (! config('cron-monitor.self_healing.db_reconnect', true)) {
            return false;
        }

        return ($classification['category'] ?? '') === FailureClassifier::CATEGORY_DATABASE;
    }

    public function heal(CronExecutionContext $context, array $classification, ?Throwable $exception = null): bool
    {
        try {
            foreach (array_keys(config('database.connections', [])) as $name) {
                try {
                    DB::connection($name)->reconnect();
                } catch (Throwable) {
                    // continue other connections
                }
            }

            DB::connection()->getPdo();
            $context->mergeMeta([
                'healed' => array_merge($context->meta['healed'] ?? [], ['database_reconnect' => true]),
            ]);
            Log::info('[CronMonitor] Database reconnected', ['job' => $context->jobName]);

            return true;
        } catch (Throwable $e) {
            Log::warning('[CronMonitor] Database reconnect failed: ' . $e->getMessage());

            return false;
        }
    }
}
