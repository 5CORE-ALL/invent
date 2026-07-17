<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts cron health status to the existing Task Manager API
 * (same endpoint / key as EventServiceProvider scheduler hooks).
 */
class TaskManagerStatusReporter
{
    public function post(array $payload): bool
    {
        $url = config('services.taskmanager.url');
        $key = config('services.taskmanager.api_key');

        if (! $url || ! $key) {
            Log::warning('[CronMonitor] TaskManager URL or API key missing from .env');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-TASKMANAGER-KEY' => $key,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->retry(2, 1000)
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('TaskManager response (' . $response->status() . '): ' . $response->body());

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to post scheduler status: ' . $e->getMessage());

            return false;
        }
    }

    public function reportExecution(CronExecutionLog $log): bool
    {
        return $this->post([
            'source' => 'cron-monitor',
            'command' => $log->command ?: $log->job_name,
            'job_name' => $log->job_name,
            'status' => $this->mapStatus($log->status),
            'monitor_status' => $log->status,
            'started_at' => optional($log->started_at)->toDateTimeString(),
            'finished_at' => optional($log->finished_at)->toDateTimeString(),
            'runtime' => $log->duration_seconds,
            'error' => $log->error_message,
            'meta' => [
                'execution_log_id' => $log->id,
                'success_percentage' => $log->success_percentage,
                'health_score' => $log->health_score,
                'health_label' => $log->health_label,
                'expected_records' => $log->expected_records,
                'fetched_records' => $log->fetched_records,
                'processed_records' => $log->processed_records,
                'updated_records' => $log->updated_records,
                'inserted_records' => $log->inserted_records,
                'skipped_records' => $log->skipped_records,
                'failed_records' => $log->failed_records,
                'api_connected' => $log->api_connected,
                'api_calls' => $log->api_calls,
                'validation_message' => $log->validation_message,
                'anomalies' => $log->anomalies,
                'dashboard_url' => url('/cron-monitor/' . $log->id),
            ],
        ]);
    }

    public function reportAlert(CronMonitorAlert $alert): bool
    {
        $log = $alert->executionLog;

        return $this->post([
            'source' => 'cron-monitor',
            'command' => $log?->command ?: $alert->job_name,
            'job_name' => $alert->job_name,
            'status' => $this->mapStatus($log?->status ?? 'failed'),
            'monitor_status' => $log?->status,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'error' => $alert->message,
            'started_at' => optional($log?->started_at)->toDateTimeString(),
            'finished_at' => optional($log?->finished_at)->toDateTimeString(),
            'runtime' => $log?->duration_seconds,
            'meta' => array_merge($alert->payload ?? [], [
                'alert_id' => $alert->id,
                'execution_log_id' => $alert->execution_log_id,
                'dashboard_url' => $alert->execution_log_id
                    ? url('/cron-monitor/' . $alert->execution_log_id)
                    : url('/cron-monitor'),
            ]),
        ]);
    }

    /**
     * Map monitor statuses onto the simple running|success|failed vocabulary
     * your Task Manager already understands.
     */
    protected function mapStatus(?string $status): string
    {
        return match ($status) {
            CronExecutionLog::STATUS_RUNNING => 'running',
            CronExecutionLog::STATUS_SUCCESS => 'success',
            CronExecutionLog::STATUS_PARTIAL_SUCCESS => 'partial_success',
            default => 'failed',
        };
    }
}
