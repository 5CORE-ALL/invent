<?php

namespace App\Services\CronMonitor;

use App\Jobs\CronMonitor\DispatchCronAlertJob;
use App\Jobs\CronMonitor\DispatchGroupedAlertJob;
use App\Models\CronAlertBatch;
use App\Models\CronMonitorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertGroupingService
{
    public function buffer(CronMonitorAlert $alert): void
    {
        $window = (int) config('cron-monitor.alerts.group_window_minutes', 15);
        if ($window <= 0) {
            DispatchCronAlertJob::dispatch($alert->id)
                ->onQueue(config('cron-monitor.notifications.queue', 'default'));

            return;
        }

        $cacheKey = 'cron-monitor:alert-batch:open';
        $batchId = Cache::get($cacheKey);

        $batch = $batchId ? CronAlertBatch::find($batchId) : null;
        if (! $batch || $batch->notified) {
            $batch = CronAlertBatch::create([
                'window_started_at' => now(),
                'summary' => 'Cron Health Warning',
                'payload' => ['alerts' => []],
                'notified' => false,
            ]);
            Cache::put($cacheKey, $batch->id, now()->addMinutes($window + 5));
        }

        $payload = $batch->payload ?? ['alerts' => []];
        $payload['alerts'][] = [
            'id' => $alert->id,
            'job_name' => $alert->job_name,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'root_cause' => $alert->executionLog?->root_cause,
            'status' => $alert->executionLog?->status,
        ];
        $batch->update(['payload' => $payload]);

        $alert->update([
            'payload' => array_merge($alert->payload ?? [], ['batch_id' => $batch->id]),
        ]);

        $flushKey = 'cron-monitor:alert-batch:flush:' . $batch->id;
        if (! Cache::get($flushKey)) {
            Cache::put($flushKey, true, now()->addMinutes($window + 5));
            DispatchGroupedAlertJob::dispatch($batch->id)
                ->delay(now()->addMinutes($window))
                ->onQueue(config('cron-monitor.notifications.queue', 'default'));
        }

        if (
            config('cron-monitor.alerts.flush_on_critical', false)
            && $alert->severity === 'critical'
        ) {
            try {
                DispatchGroupedAlertJob::dispatchSync($batch->id);
            } catch (\Throwable $e) {
                Log::warning('[CronMonitor] Critical flush failed: ' . $e->getMessage());
            }
        }
    }
}
