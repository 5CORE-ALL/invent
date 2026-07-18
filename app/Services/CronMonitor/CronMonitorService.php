<?php

namespace App\Services\CronMonitor;

use App\Events\CronMonitor\CronExecutionFinished;
use App\Events\CronMonitor\CronExecutionStarted;
use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use App\Repositories\CronExecutionLogRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class CronMonitorService
{
    protected ?CronExecutionContext $context = null;

    public function __construct(
        protected CronExecutionLogRepository $repository,
        protected CronValidationService $validator,
        protected CronStatusResolver $statusResolver,
        protected CronHealthScoreCalculator $healthCalculator,
        protected CronAnomalyDetector $anomalyDetector,
        protected RootCauseAnalyzer $rootCauseAnalyzer,
        protected CheckpointService $checkpoints,
        protected HistoricalAnalysisService $historical,
    ) {}

    public function context(): ?CronExecutionContext
    {
        return $this->context;
    }

    public function start(string $jobName, ?string $command = null): CronExecutionContext
    {
        // Reuse auto-monitor context started by CommandStarting (Kernel schedule).
        if ($this->context?->started) {
            $this->context->jobName = $jobName ?: $this->context->jobName;
            $this->context->command = $command ?: $this->context->command;
            $this->context->meta['auto'] = false;
            $this->context->meta['mode'] = 'rich';
            $this->context->cpuStart = $this->context->cpuStart ?: $this->cpuMs();
            $this->hydrateResume($this->context);
            if ($this->context->log) {
                $this->context->log->update([
                    'job_name' => $this->context->jobName,
                    'command' => $this->context->command,
                    'meta' => $this->context->meta,
                    'resume_from' => $this->context->resumeFrom,
                    'pid' => getmypid() ?: null,
                ]);
            }

            return $this->context;
        }

        if (! config('cron-monitor.enabled', true)) {
            $this->context = new CronExecutionContext;
            $this->context->jobName = $jobName;
            $this->context->command = $command;
            $this->context->started = true;
            $this->context->cpuStart = $this->cpuMs();

            return $this->context;
        }

        $ctx = new CronExecutionContext;
        $ctx->jobName = $jobName;
        $ctx->command = $command;
        $ctx->started = true;
        $ctx->cpuStart = $this->cpuMs();
        $ctx->log = $this->repository->createRunning($jobName, $command);
        $this->hydrateResume($ctx);
        $ctx->log->update([
            'resume_from' => $ctx->resumeFrom,
            'pid' => getmypid() ?: null,
            'consecutive_failures' => $this->previousConsecutiveFailures($jobName),
        ]);
        $this->context = $ctx;

        event(new CronExecutionStarted($ctx->log));

        Log::info('[CronMonitor] Started', [
            'job' => $jobName,
            'log_id' => $ctx->log->id,
            'resume_from' => $ctx->resumeFrom,
        ]);

        return $ctx;
    }

    public function sync(): void
    {
        if (! $this->context?->log || ! config('cron-monitor.enabled', true)) {
            return;
        }

        $this->context->log->update(array_merge(
            $this->context->toMetricsArray(),
            [
                'memory_usage' => $this->repository->memoryUsage(),
                'cpu_time_ms' => max(0, (int) ($this->cpuMs() - $this->context->cpuStart)),
            ]
        ));
    }

    public function finish(?Throwable $exception = null): CronExecutionLog
    {
        $ctx = $this->context;

        if (! $ctx) {
            throw new \RuntimeException('CronMonitorService::finish() called without an active context.');
        }

        if (! config('cron-monitor.enabled', true)) {
            $stub = new CronExecutionLog([
                'job_name' => $ctx->jobName,
                'command' => $ctx->command,
                'status' => $exception
                    ? CronExecutionLog::STATUS_FAILED
                    : CronExecutionLog::STATUS_SUCCESS,
            ]);
            $this->context = null;

            return $stub;
        }

        if ($exception) {
            $ctx->captureException($exception);
        }

        // Cancel requested mid-run
        if ($ctx->log?->fresh()?->cancelled_at) {
            $ctx->log->fill(array_merge($ctx->toMetricsArray(), [
                'status' => CronExecutionLog::STATUS_CANCELLED,
                'finished_at' => now(),
                'duration_seconds' => max(0, now()->diffInSeconds($ctx->log->started_at ?? now())),
                'memory_usage' => $this->repository->memoryUsage(),
                'cpu_time_ms' => max(0, (int) ($this->cpuMs() - $ctx->cpuStart)),
                'root_cause' => 'Cancelled by admin',
            ]));
            $ctx->log->save();
            $this->persistFailures($ctx);
            $log = $ctx->log;
            $this->context = null;
            event(new CronExecutionFinished($log->fresh(['failures'])));

            return $log;
        }

        $validation = $this->validator->validate($ctx);
        $hadException = $exception !== null;
        $statusResult = $this->statusResolver->resolve($ctx, $validation['passed'], $hadException);
        $historical = $this->historical->compare($ctx);
        $health = $this->healthCalculator->calculate(
            $ctx,
            $validation['passed'] && ! $hadException,
            $historical
        );

        $startedAt = $ctx->log->started_at ?? now();
        $finishedAt = now();
        $duration = max(0, $finishedAt->diffInSeconds($startedAt));

        $status = $statusResult['status'];
        $healthLabel = $health['label'];

        $classification = $ctx->meta['last_classification'] ?? [
            'category' => $ctx->failureCategory,
            'recoverable' => false,
            'root_cause' => $ctx->rootCause,
            'http_status' => null,
        ];

        $recovered = $ctx->retryCount > 0
            && ! $hadException
            && $validation['passed']
            && in_array($status, [
                CronExecutionLog::STATUS_SUCCESS,
                CronExecutionLog::STATUS_RECOVERED,
                CronExecutionLog::STATUS_PARTIAL_SUCCESS,
            ], true);

        if ($recovered && in_array($status, [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED], true)) {
            $status = CronExecutionLog::STATUS_RECOVERED;
            $ctx->recoveryStatus = CronExecutionLog::RECOVERY_RECOVERED;
        } elseif ($hadException || ! $validation['passed']) {
            $ctx->recoveryStatus = $ctx->retryCount > 0
                ? CronExecutionLog::RECOVERY_EXHAUSTED
                : CronExecutionLog::RECOVERY_NONE;
        } elseif ($ctx->retryCount > 0) {
            $ctx->recoveryStatus = CronExecutionLog::RECOVERY_RECOVERED;
        }

        $anomalies = array_merge(
            $this->anomalyDetector->detect($ctx, $ctx->log),
            $historical['anomalies'] ?? []
        );

        $rootCause = $this->rootCauseAnalyzer->summarize(
            $ctx,
            is_array($classification) ? $classification : [],
            $recovered,
            $anomalies
        );

        $consecutive = $this->resolveConsecutiveFailures($ctx->jobName, $status, $ctx->log->id);

        $ctx->log->fill(array_merge($ctx->toMetricsArray(), [
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_seconds' => $duration,
            'success_percentage' => $statusResult['success_percentage'],
            'health_score' => $health['score'],
            'health_label' => $healthLabel,
            'validation_message' => $validation['messages']
                ? implode(' ', $validation['messages'])
                : null,
            'memory_usage' => $this->repository->memoryUsage(),
            'cpu_time_ms' => max(0, (int) ($this->cpuMs() - $ctx->cpuStart)),
            'failure_category' => $ctx->failureCategory ?? ($classification['category'] ?? null),
            'root_cause' => $rootCause,
            'recovery_status' => $ctx->recoveryStatus,
            'consecutive_failures' => $consecutive,
            'last_retry_at' => $ctx->retryCount > 0 ? ($ctx->log->last_retry_at ?? now()) : $ctx->log->last_retry_at,
            'meta' => array_merge($ctx->meta, [
                'health_breakdown' => $health['breakdown'],
                'historical' => $historical['summary'] ?? null,
            ]),
        ]));

        $ctx->log->anomalies = $anomalies ?: null;

        if (
            $ctx->log->status === CronExecutionLog::STATUS_SUCCESS
            && collect($anomalies)->contains(fn ($a) => ($a['severity'] ?? '') === 'critical')
        ) {
            $ctx->log->status = CronExecutionLog::STATUS_PARTIAL_SUCCESS;
        }

        $ctx->log->health_label = match ($ctx->log->status) {
            CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED => $healthLabel === 'critical' ? 'warning' : $healthLabel,
            CronExecutionLog::STATUS_PARTIAL_SUCCESS, CronExecutionLog::STATUS_STUCK => 'warning',
            default => 'critical',
        };

        $ctx->log->save();

        $this->persistFailures($ctx);

        // Clear resume cursor when the run completed its work without hard failure
        if (in_array($ctx->log->status, [
            CronExecutionLog::STATUS_SUCCESS,
            CronExecutionLog::STATUS_RECOVERED,
            CronExecutionLog::STATUS_PARTIAL_SUCCESS,
        ], true)) {
            $this->checkpoints->clear($ctx->jobName);
        }

        event(new CronExecutionFinished($ctx->log->fresh(['failures'])));

        Log::info('[CronMonitor] Finished', [
            'job' => $ctx->jobName,
            'log_id' => $ctx->log->id,
            'status' => $ctx->log->status,
            'success_percentage' => $ctx->log->success_percentage,
            'health_score' => $ctx->log->health_score,
            'root_cause' => $ctx->log->root_cause,
        ]);

        $log = $ctx->log;
        $this->context = null;

        return $log;
    }

    public function finishBasic(): CronExecutionLog
    {
        $ctx = $this->context;
        if (! $ctx) {
            throw new \RuntimeException('CronMonitorService::finishBasic() called without an active context.');
        }

        $ctx->mergeMeta(['mode' => 'schedule', 'auto' => true]);
        $ctx->markApiConnected(true);
        if ($ctx->fetchedRecords === 0 && $ctx->expectedRecords === null) {
            $ctx->setExpected(0);
        }

        return $this->finish();
    }

    /**
     * @param  callable(CronExecutionContext): mixed  $callback
     */
    public function run(string $jobName, callable $callback, ?string $command = null): mixed
    {
        $ctx = $this->start($jobName, $command);

        try {
            $result = $callback($ctx);
            $this->finish();

            return $result;
        } catch (Throwable $e) {
            $this->finish($e);
            throw $e;
        }
    }

    protected function hydrateResume(CronExecutionContext $ctx): void
    {
        $checkpoint = $this->checkpoints->load($ctx->jobName);
        if (! $checkpoint) {
            return;
        }

        $ctx->checkpointCursor = $checkpoint->decodedCursor();
        $ctx->resumeFrom = (int) $checkpoint->processed_offset;
        $ctx->mergeMeta(['resuming' => true, 'resume_offset' => $ctx->resumeFrom]);
    }

    protected function previousConsecutiveFailures(string $jobName): int
    {
        $prev = CronExecutionLog::query()
            ->forJob($jobName)
            ->finished()
            ->orderByDesc('id')
            ->first();

        if (! $prev) {
            return 0;
        }

        if (in_array($prev->status, [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED], true)) {
            return 0;
        }

        return (int) $prev->consecutive_failures + 1;
    }

    protected function resolveConsecutiveFailures(string $jobName, string $status, ?int $currentId = null): int
    {
        if (in_array($status, [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED], true)) {
            return 0;
        }

        $prev = CronExecutionLog::query()
            ->forJob($jobName)
            ->finished()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->orderByDesc('id')
            ->first();

        if (! $prev || in_array($prev->status, [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED], true)) {
            return 1;
        }

        return (int) $prev->consecutive_failures + 1;
    }

    protected function cpuMs(): float
    {
        if (function_exists('getrusage')) {
            $r = getrusage();
            $user = ($r['ru_utime.tv_sec'] ?? 0) * 1000 + ($r['ru_utime.tv_usec'] ?? 0) / 1000;
            $sys = ($r['ru_stime.tv_sec'] ?? 0) * 1000 + ($r['ru_stime.tv_usec'] ?? 0) / 1000;

            return $user + $sys;
        }

        return microtime(true) * 1000;
    }

    protected function persistFailures(CronExecutionContext $ctx): void
    {
        if (! $ctx->log || $ctx->pendingFailures === []) {
            return;
        }

        $rows = [];
        $now = now();
        foreach ($ctx->pendingFailures as $failure) {
            $rows[] = [
                'execution_log_id' => $ctx->log->id,
                'sku' => $failure['sku'] ?? null,
                'marketplace' => $failure['marketplace'] ?? null,
                'failure_reason' => $failure['failure_reason'] ?? null,
                'failure_category' => $failure['failure_category'] ?? null,
                'http_status' => $failure['http_status'] ?? null,
                'recoverable' => (bool) ($failure['recoverable'] ?? false),
                'root_cause' => $failure['root_cause'] ?? null,
                'api_response' => $failure['api_response'] ?? null,
                'retry_count' => 0,
                'resolved' => false,
                'meta' => isset($failure['meta']) ? json_encode($failure['meta']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            CronExecutionFailure::insert($chunk);
        }
    }
}
