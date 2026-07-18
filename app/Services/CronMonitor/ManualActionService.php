<?php

namespace App\Services\CronMonitor;

use App\Jobs\CronMonitor\RetryEntireJobJob;
use App\Jobs\CronMonitor\RetryFailedRecordsJob;
use App\Models\CronExecutionLog;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualActionService
{
    public function __construct(
        protected DuplicateLockService $locks,
        protected CheckpointService $checkpoints,
    ) {}

    public function retryJob(CronExecutionLog $log): array
    {
        $command = $log->command;
        if (! $command) {
            throw new RuntimeException('No command associated with this execution log.');
        }

        RetryEntireJobJob::dispatch($command, false)
            ->onQueue(config('cron-monitor.retry.queue', 'default'));

        return ['ok' => true, 'queued' => true, 'command' => $command];
    }

    public function resumeJob(CronExecutionLog $log): array
    {
        $command = $log->command;
        if (! $command) {
            throw new RuntimeException('No command associated with this execution log.');
        }

        RetryEntireJobJob::dispatch($command, true)
            ->onQueue(config('cron-monitor.retry.queue', 'default'));

        return ['ok' => true, 'queued' => true, 'resume' => true, 'command' => $command];
    }

    public function retryFailedRecords(string $jobName, ?int $limit = 100): array
    {
        RetryFailedRecordsJob::dispatch($jobName, $limit)
            ->onQueue(config('cron-monitor.retry.queue', 'default'));

        return ['ok' => true, 'queued' => true, 'job' => $jobName];
    }

    public function cancelRunning(CronExecutionLog $log): array
    {
        if (! $log->isCancellable()) {
            throw new RuntimeException('Execution is not running/stuck.');
        }

        $log->update([
            'cancelled_at' => now(),
            'status' => CronExecutionLog::STATUS_CANCELLED,
            'finished_at' => now(),
            'root_cause' => trim(($log->root_cause ?? '') . ' | Cancelled by admin'),
            'error_message' => 'Cancelled by admin',
        ]);

        if ($log->command) {
            $this->locks->forceRelease($log->command);
        }
        if ($log->lock_key) {
            $this->locks->forceRelease($log->command ?: $log->job_name);
        }

        return ['ok' => true, 'status' => 'cancelled'];
    }

    public function unlock(string $commandOrJob): array
    {
        $ok = $this->locks->forceRelease($commandOrJob);

        // Also clear stuck running markers older than timeout
        CronExecutionLog::query()
            ->where(function ($q) use ($commandOrJob) {
                $q->where('command', $commandOrJob)->orWhere('job_name', $commandOrJob);
            })
            ->whereIn('status', [CronExecutionLog::STATUS_RUNNING, CronExecutionLog::STATUS_STUCK])
            ->update([
                'status' => CronExecutionLog::STATUS_CANCELLED,
                'finished_at' => now(),
                'error_message' => 'Unlocked by admin',
                'root_cause' => 'Lock forcibly released',
            ]);

        return ['ok' => $ok, 'command' => $commandOrJob];
    }

    public function downloadLog(CronExecutionLog $log): StreamedResponse
    {
        $filename = 'cron-log-' . $log->id . '.json';
        $payload = $log->load('failures')->toArray();

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function runCommandNow(string $command, bool $resume = false): int
    {
        // Soft resume flag via env for commands that check it
        if ($resume) {
            putenv('CRON_MONITOR_RESUME=1');
            $_ENV['CRON_MONITOR_RESUME'] = '1';
        }

        return Artisan::call($command);
    }
}
