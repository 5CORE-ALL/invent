<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CronRetryService
{
    /**
     * Retry unresolved failures for a job using a caller-provided handler.
     *
     * Handler signature: fn (CronExecutionFailure $failure): bool
     * Return true when the retry succeeded.
     *
     * @return array{attempted: int, resolved: int, failed: int, skipped: int}
     */
    public function retryUnresolved(string $jobName, Closure $handler, ?int $limit = 100): array
    {
        $max = (int) config('cron-monitor.retry.max_attempts', 3);
        $stats = ['attempted' => 0, 'resolved' => 0, 'failed' => 0, 'skipped' => 0];

        $failures = CronExecutionFailure::query()
            ->where('resolved', false)
            ->where('retry_count', '<', $max)
            ->whereHas('executionLog', fn ($q) => $q->where('job_name', $jobName))
            ->orderBy('id')
            ->limit($limit ?? 100)
            ->get();

        foreach ($failures as $failure) {
            $stats['attempted']++;
            $failure->increment('retry_count');

            try {
                $ok = (bool) $handler($failure);
                if ($ok) {
                    $failure->markResolved();
                    $stats['resolved']++;
                    $this->bumpExecutionRetryStats($failure->executionLog);
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $failure->update([
                    'failure_reason' => trim(($failure->failure_reason ?? '') . ' | Retry error: ' . $e->getMessage()),
                    'api_response' => $e->getMessage(),
                ]);
                Log::warning('[CronMonitor] Retry failed', [
                    'failure_id' => $failure->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    public function unresolvedForJob(string $jobName, ?int $limit = null): Collection
    {
        $max = (int) config('cron-monitor.retry.max_attempts', 3);

        return CronExecutionFailure::query()
            ->where('resolved', false)
            ->where('retry_count', '<', $max)
            ->whereHas('executionLog', fn ($q) => $q->where('job_name', $jobName))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->orderBy('id')
            ->get();
    }

    protected function bumpExecutionRetryStats(?CronExecutionLog $log): void
    {
        if (! $log) {
            return;
        }

        $log->increment('retry_count');

        // Recalculate crude success metrics after a successful retry
        $unresolved = $log->unresolvedFailures()->count();
        $updated = (int) $log->updated_records + 1;
        $failed = max(0, (int) $log->failed_records - 1);

        $denominator = max(1, (int) ($log->expected_records ?: $log->processed_records ?: $log->fetched_records ?: 1));
        $percentage = round(($updated / $denominator) * 100, 2);

        $successMin = (float) config('cron-monitor.thresholds.success_min', 95);
        $partialMin = (float) config('cron-monitor.thresholds.partial_min', 60);

        $status = match (true) {
            $unresolved === 0 && $percentage >= $successMin => CronExecutionLog::STATUS_SUCCESS,
            $percentage >= $partialMin => CronExecutionLog::STATUS_PARTIAL_SUCCESS,
            default => CronExecutionLog::STATUS_FAILED,
        };

        $log->update([
            'updated_records' => $updated,
            'failed_records' => $failed,
            'success_percentage' => $percentage,
            'status' => $status,
        ]);
    }
}
