<?php

namespace App\Console\Commands\Concerns;

use App\Models\CronExecutionLog;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\CronMonitor\CronMonitorService;
use App\Services\CronMonitor\DuplicateLockService;
use App\Services\CronMonitor\IntelligentRetryService;
use Throwable;

/**
 * Drop-in monitoring for any Artisan command.
 */
trait MonitorsCronExecution
{
    protected function cronMonitor(): CronMonitorService
    {
        return app(CronMonitorService::class);
    }

    protected function monitor(): CronExecutionContext
    {
        $ctx = $this->cronMonitor()->context();
        if (! $ctx) {
            throw new \RuntimeException('No active cron monitor context. Wrap logic in runMonitored().');
        }

        return $ctx;
    }

    /**
     * @param  callable(CronExecutionContext): int  $callback
     */
    protected function runMonitored(callable $callback, ?string $jobName = null): int
    {
        $jobName ??= $this->monitoredJobName();
        $command = method_exists($this, 'getName') ? $this->getName() : ($this->signature ?? null);
        $commandName = is_string($command) ? explode(' ', $command)[0] : $jobName;

        $locks = app(DuplicateLockService::class);
        $lockKey = '';

        try {
            $lockKey = $locks->acquire($commandName, $this->monitoredLockTtl());
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $monitor = $this->cronMonitor();
            $ctx = $monitor->start($jobName, $commandName);
            $ctx->lockKey = $lockKey;
            $ctx->cpuStart = $ctx->cpuStart ?: (microtime(true) * 1000);

            if ($ctx->log && $lockKey) {
                $ctx->log->update(['lock_key' => $lockKey, 'pid' => getmypid() ?: null]);
            }

            if ($ctx->resumeFrom) {
                $this->warn("Resuming from offset {$ctx->resumeFrom}");
            }

            try {
                $retry = app(IntelligentRetryService::class);
                $exitCode = (int) $retry->runWithRetry(
                    $ctx,
                    fn () => $callback($ctx),
                    $jobName
                );
                $log = $monitor->finish();
                $this->renderMonitorSummary($log);

                return $this->mapStatusToExitCode($log, $exitCode);
            } catch (Throwable $e) {
                $log = $monitor->finish($e);
                $this->renderMonitorSummary($log);
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            // start()/createRunning failed before finish() — still free the lock
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $locks->release($lockKey);
        }
    }

    protected function monitoredLockTtl(): ?int
    {
        if (property_exists($this, 'monitorLockTtlSeconds') && $this->monitorLockTtlSeconds) {
            return (int) $this->monitorLockTtlSeconds;
        }

        return null;
    }

    protected function monitoredJobName(): string
    {
        if (property_exists($this, 'monitorJobName') && ! empty($this->monitorJobName)) {
            return (string) $this->monitorJobName;
        }

        if (method_exists($this, 'getName') && $this->getName()) {
            return (string) $this->getName();
        }

        return class_basename(static::class);
    }

    protected function mapStatusToExitCode(CronExecutionLog $log, int $businessExitCode): int
    {
        if ($businessExitCode === self::FAILURE) {
            return self::FAILURE;
        }

        return match ($log->status) {
            CronExecutionLog::STATUS_FAILED,
            CronExecutionLog::STATUS_TIMED_OUT,
            CronExecutionLog::STATUS_MISSED,
            CronExecutionLog::STATUS_STUCK,
            CronExecutionLog::STATUS_CANCELLED => self::FAILURE,
            default => self::SUCCESS,
        };
    }

    protected function renderMonitorSummary(CronExecutionLog $log): void
    {
        $this->newLine();
        $this->line('✅ Cron Started');
        $this->line(($log->api_connected ? '✅' : '❌') . ' API Connected');
        $this->line(($log->fetched_records > 0 ? '✅' : '⚠') . " {$log->fetched_records} Records Fetched");
        $this->line(($log->processed_records > 0 ? '✅' : '⚠') . " {$log->processed_records} Records Processed");
        $this->line(($log->updated_records > 0 ? '✅' : '⚠') . " {$log->updated_records} Records Updated");

        if ($log->inserted_records > 0) {
            $this->line("✅ {$log->inserted_records} Records Inserted");
        }
        if ($log->skipped_records > 0) {
            $this->line("⏭ {$log->skipped_records} Records Skipped");
        }
        if ($log->failed_records > 0) {
            $this->warn("⚠ {$log->failed_records} Records Failed");
        }
        if ($log->retry_count > 0) {
            $this->line("🔁 Retries = {$log->retry_count}");
        }
        if ($log->root_cause) {
            $this->line('Root Cause: ' . $log->root_cause);
        }
        if ($log->recovery_status && $log->recovery_status !== 'none') {
            $this->line('Recovery: ' . $log->recovery_status);
        }

        $rateIcon = match (true) {
            ($log->success_percentage ?? 0) >= 95 => '✅',
            ($log->success_percentage ?? 0) >= 60 => '⚠',
            default => '❌',
        };
        $this->line("{$rateIcon} Update Success Rate = {$log->success_percentage}%");
        $this->line("Health Score = {$log->health_score}/100 ({$log->health_label})");
        $this->line('Duration = ' . ($log->duration_seconds ?? 0) . 's');

        $statusLine = 'Overall Status: ' . strtoupper(str_replace('_', ' ', $log->status));
        match ($log->status) {
            CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED => $this->info($statusLine),
            CronExecutionLog::STATUS_PARTIAL_SUCCESS => $this->warn($statusLine),
            default => $this->error($statusLine),
        };

        if ($log->validation_message) {
            $this->warn('Validation: ' . $log->validation_message);
        }

        if (! empty($log->anomalies)) {
            foreach ($log->anomalies as $anomaly) {
                $this->warn('Anomaly: ' . ($anomaly['message'] ?? 'detected'));
            }
        }
    }
}
