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
            if ($this->context->log) {
                $this->context->log->update([
                    'job_name' => $this->context->jobName,
                    'command' => $this->context->command,
                    'meta' => $this->context->meta,
                ]);
            }

            return $this->context;
        }

        if (! config('cron-monitor.enabled', true)) {
            $this->context = new CronExecutionContext;
            $this->context->jobName = $jobName;
            $this->context->command = $command;
            $this->context->started = true;

            return $this->context;
        }

        $ctx = new CronExecutionContext;
        $ctx->jobName = $jobName;
        $ctx->command = $command;
        $ctx->started = true;
        $ctx->log = $this->repository->createRunning($jobName, $command);
        $this->context = $ctx;

        event(new CronExecutionStarted($ctx->log));

        Log::info('[CronMonitor] Started', [
            'job' => $jobName,
            'log_id' => $ctx->log->id,
        ]);

        return $ctx;
    }

    /**
     * Persist mid-run counters (optional heartbeat).
     */
    public function sync(): void
    {
        if (! $this->context?->log || ! config('cron-monitor.enabled', true)) {
            return;
        }

        $this->context->log->update(array_merge(
            $this->context->toMetricsArray(),
            ['memory_usage' => $this->repository->memoryUsage()]
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

        $validation = $this->validator->validate($ctx);
        $hadException = $exception !== null;
        $statusResult = $this->statusResolver->resolve($ctx, $validation['passed'], $hadException);
        $health = $this->healthCalculator->calculate($ctx, $validation['passed'] && ! $hadException);

        $startedAt = $ctx->log->started_at ?? now();
        $finishedAt = now();
        $duration = max(0, $finishedAt->diffInSeconds($startedAt));

        $status = $statusResult['status'];
        $healthLabel = $health['label'];

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
            'meta' => array_merge($ctx->meta, [
                'health_breakdown' => $health['breakdown'],
            ]),
        ]));

        // Detect anomalies after duration is known
        $anomalies = $this->anomalyDetector->detect($ctx, $ctx->log);
        $ctx->log->anomalies = $anomalies ?: null;

        // Escalate status on critical anomalies when otherwise "success"
        if (
            $ctx->log->status === CronExecutionLog::STATUS_SUCCESS
            && collect($anomalies)->contains(fn ($a) => ($a['severity'] ?? '') === 'critical')
        ) {
            $ctx->log->status = CronExecutionLog::STATUS_PARTIAL_SUCCESS;
        }

        // Align display label with final status
        $ctx->log->health_label = match ($ctx->log->status) {
            CronExecutionLog::STATUS_SUCCESS => $healthLabel === 'critical' ? 'warning' : $healthLabel,
            CronExecutionLog::STATUS_PARTIAL_SUCCESS => 'warning',
            default => 'critical',
        };

        $ctx->log->save();

        $this->persistFailures($ctx);

        event(new CronExecutionFinished($ctx->log->fresh(['failures'])));

        Log::info('[CronMonitor] Finished', [
            'job' => $ctx->jobName,
            'log_id' => $ctx->log->id,
            'status' => $ctx->log->status,
            'success_percentage' => $ctx->log->success_percentage,
            'health_score' => $ctx->log->health_score,
        ]);

        $log = $ctx->log;
        $this->context = null;

        return $log;
    }

    /**
     * Finish an auto-monitored Kernel schedule run (no business metrics required).
     * Exit code 0 => success; used by AutoMonitorScheduledCommand.
     */
    public function finishBasic(): CronExecutionLog
    {
        $ctx = $this->context;
        if (! $ctx) {
            throw new \RuntimeException('CronMonitorService::finishBasic() called without an active context.');
        }

        // Soft metrics so validation / health treat "ran cleanly" as healthy
        $ctx->mergeMeta(['mode' => 'schedule', 'auto' => true]);
        $ctx->markApiConnected(true);
        if ($ctx->fetchedRecords === 0 && $ctx->expectedRecords === null) {
            $ctx->setExpected(0);
        }

        return $this->finish();
    }

    /**
     * Run a callable inside a monitored lifecycle.
     *
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
