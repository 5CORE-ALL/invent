<?php

namespace App\Services\CronMonitor;

use App\Jobs\CronMonitor\DispatchCronAlertJob;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use Illuminate\Support\Facades\Log;

class CronNotificationDispatcher
{
    public function shouldNotify(CronExecutionLog $log): bool
    {
        if (! config('cron-monitor.notifications.enabled', true)) {
            return false;
        }

        if ($log->status === CronExecutionLog::STATUS_SUCCESS && empty($log->anomalies)) {
            return false;
        }

        $alertOn = config('cron-monitor.notifications.alert_on', []);

        if (in_array($log->status, $alertOn, true)) {
            return true;
        }

        if ($log->status === CronExecutionLog::STATUS_FAILED && in_array('validation_failed', $alertOn, true) && $log->validation_message) {
            return true;
        }

        if ((int) $log->updated_records === 0 && in_array('no_updates', $alertOn, true)) {
            return true;
        }

        if (! empty($log->anomalies) && in_array('anomaly', $alertOn, true)) {
            return true;
        }

        return false;
    }

    public function dispatchForExecution(CronExecutionLog $log): void
    {
        if (! $this->shouldNotify($log)) {
            return;
        }

        $alertType = match ($log->status) {
            CronExecutionLog::STATUS_PARTIAL_SUCCESS => 'partial_success',
            CronExecutionLog::STATUS_FAILED => $log->validation_message ? 'validation_failed' : 'failed',
            CronExecutionLog::STATUS_TIMED_OUT => 'timed_out',
            CronExecutionLog::STATUS_MISSED => 'cron_missed',
            default => 'anomaly',
        };

        if ((int) $log->updated_records === 0 && $log->status !== CronExecutionLog::STATUS_SUCCESS) {
            $alertType = 'no_updates';
        }

        $alert = CronMonitorAlert::create([
            'execution_log_id' => $log->id,
            'job_name' => $log->job_name,
            'alert_type' => $alertType,
            'severity' => $log->status === CronExecutionLog::STATUS_PARTIAL_SUCCESS ? 'warning' : 'critical',
            'title' => $this->titleFor($log, $alertType),
            'message' => $this->messageFor($log),
            'payload' => [
                'status' => $log->status,
                'success_percentage' => $log->success_percentage,
                'health_score' => $log->health_score,
                'updated_records' => $log->updated_records,
                'failed_records' => $log->failed_records,
                'anomalies' => $log->anomalies,
            ],
        ]);

        $this->queueAlert($alert);
    }

    public function dispatchAlert(CronMonitorAlert $alert): void
    {
        $this->queueAlert($alert);
    }

    protected function queueAlert(CronMonitorAlert $alert): void
    {
        $queue = config('cron-monitor.notifications.queue', 'default');

        try {
            DispatchCronAlertJob::dispatch($alert->id)->onQueue($queue);
        } catch (\Throwable $e) {
            Log::error('[CronMonitor] Failed to queue alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function titleFor(CronExecutionLog $log, string $type): string
    {
        return match ($type) {
            'partial_success' => "Partial success: {$log->job_name}",
            'validation_failed' => "Validation failed: {$log->job_name}",
            'no_updates' => "No updates: {$log->job_name}",
            'timed_out' => "Timed out: {$log->job_name}",
            'cron_missed' => "Missed: {$log->job_name}",
            'anomaly' => "Anomaly detected: {$log->job_name}",
            default => "Cron failed: {$log->job_name}",
        };
    }

    protected function messageFor(CronExecutionLog $log): string
    {
        $parts = [
            "Status: {$log->status}",
            "Success: {$log->success_percentage}%",
            "Health: {$log->health_score}/100 ({$log->health_label})",
            "Fetched: {$log->fetched_records}",
            "Updated: {$log->updated_records}",
            "Failed: {$log->failed_records}",
            "Duration: {$log->duration_seconds}s",
        ];

        if ($log->validation_message) {
            $parts[] = "Validation: {$log->validation_message}";
        }

        if ($log->error_message) {
            $parts[] = "Error: {$log->error_message}";
        }

        if (! empty($log->anomalies)) {
            foreach ($log->anomalies as $anomaly) {
                $parts[] = '⚠ ' . ($anomaly['message'] ?? 'Anomaly');
            }
        }

        return implode("\n", $parts);
    }
}
