<?php

namespace App\Services\CronMonitor\Healers;

use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueWatchdogHealer implements HealerInterface
{
    public function supports(array $classification, ?Throwable $exception = null): bool
    {
        if (! config('cron-monitor.self_healing.queue_watchdog', true)) {
            return false;
        }

        $text = strtolower(($classification['root_cause'] ?? '') . ' ' . ($exception?->getMessage() ?? ''));

        return str_contains($text, 'queue') || str_contains($text, 'worker');
    }

    public function heal(CronExecutionContext $context, array $classification, ?Throwable $exception = null): bool
    {
        try {
            Artisan::call('queue:ensure-watchdog-daemon');
            $context->mergeMeta([
                'healed' => array_merge($context->meta['healed'] ?? [], [
                    'queue_watchdog' => true,
                    'queue_watchdog_output' => Artisan::output(),
                ]),
            ]);
            Log::info('[CronMonitor] Queue watchdog invoked', ['job' => $context->jobName]);

            return true;
        } catch (Throwable $e) {
            Log::warning('[CronMonitor] Queue watchdog heal failed: ' . $e->getMessage());

            return false;
        }
    }
}
