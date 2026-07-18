<?php

namespace App\Services\CronMonitor;

use App\Jobs\CronMonitor\RetryFailedRecordsJob;
use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IntelligentRetryService
{
    public function __construct(
        protected FailureClassifier $classifier,
        protected SelfHealingService $healing,
    ) {}

    public function delayForAttempt(int $attempt): int
    {
        $delays = config('cron-monitor.retry.retry_delay', [1 => 30, 2 => 120, 3 => 300]);

        if (array_key_exists($attempt, $delays)) {
            return max(0, (int) $delays[$attempt]);
        }

        $fallback = end($delays);

        return max(0, (int) ($fallback !== false ? $fallback : 30));
    }

    public function maxAttempts(): int
    {
        return (int) config('cron-monitor.retry.max_attempts', 3);
    }

    /**
     * Execute a recoverable operation with heal + backoff retries.
     *
     * @param  callable(): mixed  $operation
     */
    public function runWithRetry(CronExecutionContext $ctx, callable $operation, ?string $label = null): mixed
    {
        $attempt = 0;
        $max = $this->maxAttempts();
        $lastException = null;

        while ($attempt <= $max) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;
                $classification = $this->classifier->classify($e);

                if (! $classification['recoverable'] || $attempt >= $max) {
                    $ctx->mergeMeta([
                        'last_classification' => $classification,
                        'retry_exhausted' => $attempt >= $max,
                    ]);
                    throw $e;
                }

                $attempt++;
                $ctx->retryCount = $attempt;
                $ctx->mergeMeta([
                    'recovery_status' => CronExecutionLog::RECOVERY_ATTEMPTING,
                    'last_classification' => $classification,
                ]);

                if ($ctx->log) {
                    $ctx->log->update([
                        'retry_count' => $attempt,
                        'last_retry_at' => now(),
                        'recovery_status' => CronExecutionLog::RECOVERY_ATTEMPTING,
                        'failure_category' => $classification['category'],
                        'root_cause' => $classification['root_cause'],
                    ]);
                }

                $this->healing->attempt($ctx, $classification, $e);

                $delay = $this->delayForAttempt($attempt);
                Log::warning('[CronMonitor] Recoverable failure — retrying', [
                    'job' => $ctx->jobName,
                    'label' => $label,
                    'attempt' => $attempt,
                    'delay' => $delay,
                    'root_cause' => $classification['root_cause'],
                ]);

                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Retry loop failed without exception');
    }

    /**
     * Retry unresolved recoverable failures for a job.
     *
     * @return array{attempted: int, resolved: int, failed: int, skipped: int}
     */
    public function retryUnresolved(string $jobName, Closure $handler, ?int $limit = 100): array
    {
        $max = $this->maxAttempts();
        $stats = ['attempted' => 0, 'resolved' => 0, 'failed' => 0, 'skipped' => 0];

        $failures = CronExecutionFailure::query()
            ->where('resolved', false)
            ->where('retry_count', '<', $max)
            ->where(function ($q) {
                $q->where('recoverable', true)
                    ->orWhereNull('recoverable');
            })
            ->whereHas('executionLog', fn ($q) => $q->where('job_name', $jobName))
            ->orderBy('id')
            ->limit($limit ?? 100)
            ->get();

        foreach ($failures as $failure) {
            $category = $failure->failure_category;
            if ($category && in_array($category, config('cron-monitor.retry.non_recoverable_categories', []), true)) {
                $stats['skipped']++;
                continue;
            }

            $stats['attempted']++;
            $attempt = (int) $failure->retry_count + 1;
            $failure->update([
                'retry_count' => $attempt,
                'last_retry_at' => now(),
            ]);

            $delay = $this->delayForAttempt($attempt);
            if ($delay > 0 && $attempt > 1) {
                sleep(min($delay, 5)); // cap sleep in batch retries
            }

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
                $classification = $this->classifier->classify($e);
                $failure->update([
                    'failure_reason' => trim(($failure->failure_reason ?? '') . ' | Retry: ' . $e->getMessage()),
                    'failure_category' => $classification['category'],
                    'recoverable' => $classification['recoverable'],
                    'root_cause' => $classification['root_cause'],
                    'api_response' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    public function queueFailedRecordsRetry(string $jobName, ?int $limit = 100): void
    {
        RetryFailedRecordsJob::dispatch($jobName, $limit)
            ->onQueue(config('cron-monitor.retry.queue', 'default'));
    }

    public function unresolvedForJob(string $jobName, ?int $limit = null): Collection
    {
        $max = $this->maxAttempts();

        return CronExecutionFailure::query()
            ->where('resolved', false)
            ->where('retry_count', '<', $max)
            ->where(function ($q) {
                $q->where('recoverable', true)->orWhereNull('recoverable');
            })
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
        $unresolved = $log->unresolvedFailures()->count();
        $updated = (int) $log->updated_records + 1;
        $failed = max(0, (int) $log->failed_records - 1);

        $denominator = max(1, (int) ($log->expected_records ?: $log->processed_records ?: $log->fetched_records ?: 1));
        $percentage = round(($updated / $denominator) * 100, 2);

        $successMin = (float) config('cron-monitor.thresholds.success_min', 95);
        $partialMin = (float) config('cron-monitor.thresholds.partial_min', 60);

        $status = match (true) {
            $unresolved === 0 && $percentage >= $successMin => CronExecutionLog::STATUS_RECOVERED,
            $percentage >= $partialMin => CronExecutionLog::STATUS_PARTIAL_SUCCESS,
            default => CronExecutionLog::STATUS_FAILED,
        };

        $log->update([
            'updated_records' => $updated,
            'failed_records' => $failed,
            'success_percentage' => $percentage,
            'status' => $status,
            'recovery_status' => $unresolved === 0
                ? CronExecutionLog::RECOVERY_RECOVERED
                : CronExecutionLog::RECOVERY_ATTEMPTING,
            'last_retry_at' => now(),
        ]);
    }
}
