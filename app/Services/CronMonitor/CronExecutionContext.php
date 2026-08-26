<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionLog;
use Throwable;

/**
 * Mutable metrics bag for a single cron run.
 * Commands interact with this via CronMonitor facade/service helpers.
 */
class CronExecutionContext
{
    public ?CronExecutionLog $log = null;

    public string $jobName = '';

    public ?string $command = null;

    public bool $apiConnected = false;

    public int $apiCalls = 0;

    public ?int $expectedRecords = null;

    public int $fetchedRecords = 0;

    public int $processedRecords = 0;

    public int $updatedRecords = 0;

    public int $insertedRecords = 0;

    public int $skippedRecords = 0;

    public int $failedRecords = 0;

    public int $retryCount = 0;

    public ?int $resumeFrom = null;

    public mixed $checkpointCursor = null;

    public int $apiLatencyTotalMs = 0;

    public int $apiLatencySamples = 0;

    public ?string $failureCategory = null;

    public ?string $rootCause = null;

    public string $recoveryStatus = CronExecutionLog::RECOVERY_NONE;

    public ?string $lockKey = null;

    public float $cpuStart = 0.0;

    /** @var array<int, array<string, mixed>> */
    public array $pendingFailures = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    public ?string $errorMessage = null;

    public ?string $exception = null;

    public ?string $validationMessage = null;

    public bool $started = false;

    public function setExpected(?int $count): self
    {
        $this->expectedRecords = $count;

        return $this;
    }

    public function markApiConnected(bool $connected = true): self
    {
        $this->apiConnected = $connected;

        return $this;
    }

    /**
     * Daily snapshot jobs must rewrite every SKU. A leftover checkpoint from an
     * earlier run (especially one that failed health-check after finishing) would
     * resume past the last id and update 0 rows.
     */
    public function startFresh(): self
    {
        $this->resumeFrom = null;
        $this->checkpointCursor = null;
        $this->mergeMeta(['fresh_run' => true]);
        if ($this->jobName !== '') {
            app(CheckpointService::class)->clear($this->jobName);
        }

        return $this;
    }

    /**
     * Local DB-only jobs (no marketplace HTTP). Skips require_api_data validation.
     */
    public function markLocalOnly(): self
    {
        $this->mergeMeta(['local_only' => true]);
        $this->apiConnected = true;

        return $this;
    }

    public function incrementApiCalls(int $by = 1): self
    {
        $this->apiCalls += $by;

        return $this;
    }

    public function incrementApiLatency(int $ms): self
    {
        $this->apiLatencyTotalMs += max(0, $ms);
        $this->apiLatencySamples++;

        return $this;
    }

    public function averageApiLatencyMs(): ?int
    {
        if ($this->apiLatencySamples === 0) {
            return null;
        }

        return (int) round($this->apiLatencyTotalMs / $this->apiLatencySamples);
    }

    public function setFetched(int $count): self
    {
        $this->fetchedRecords = $count;

        return $this;
    }

    public function incrementFetched(int $by = 1): self
    {
        $this->fetchedRecords += $by;

        return $this;
    }

    public function setProcessed(int $count): self
    {
        $this->processedRecords = $count;

        return $this;
    }

    public function incrementProcessed(int $by = 1): self
    {
        $this->processedRecords += $by;

        return $this;
    }

    public function setUpdated(int $count): self
    {
        $this->updatedRecords = $count;

        return $this;
    }

    public function incrementUpdated(int $by = 1): self
    {
        $this->updatedRecords += $by;

        return $this;
    }

    public function setInserted(int $count): self
    {
        $this->insertedRecords = $count;

        return $this;
    }

    public function incrementInserted(int $by = 1): self
    {
        $this->insertedRecords += $by;

        return $this;
    }

    public function setSkipped(int $count): self
    {
        $this->skippedRecords = $count;

        return $this;
    }

    public function incrementSkipped(int $by = 1): self
    {
        $this->skippedRecords += $by;

        return $this;
    }

    public function setFailed(int $count): self
    {
        $this->failedRecords = $count;

        return $this;
    }

    public function incrementFailed(int $by = 1): self
    {
        $this->failedRecords += $by;

        return $this;
    }

    public function checkpoint(mixed $cursor, ?int $processedOffset = null): self
    {
        $this->checkpointCursor = $cursor;
        $offset = $processedOffset ?? $this->processedRecords;
        $this->resumeFrom = $offset;

        app(CheckpointService::class)->save(
            $this->jobName,
            $cursor,
            $offset,
            $this->command,
            $this->log
        );

        return $this;
    }

    public function resumeFrom(): mixed
    {
        if ($this->checkpointCursor !== null) {
            return $this->checkpointCursor;
        }

        $service = app(CheckpointService::class);
        $this->checkpointCursor = $service->resumeCursor($this->jobName);
        $this->resumeFrom = $service->resumeOffset($this->jobName);

        return $this->checkpointCursor;
    }

    public function resumeOffset(): int
    {
        if ($this->resumeFrom !== null) {
            return $this->resumeFrom;
        }

        return app(CheckpointService::class)->resumeOffset($this->jobName);
    }

    public function recordFailure(
        ?string $sku = null,
        ?string $marketplace = null,
        ?string $reason = null,
        mixed $apiResponse = null,
        array $meta = [],
        ?string $category = null,
        ?int $httpStatus = null,
        ?bool $recoverable = null,
        ?string $rootCause = null
    ): self {
        $classifier = app(FailureClassifier::class);
        $classified = $classifier->classify(null, $reason, $httpStatus, $apiResponse);

        $this->failedRecords++;
        $this->pendingFailures[] = [
            'sku' => $sku,
            'marketplace' => $marketplace,
            'failure_reason' => $reason,
            'api_response' => is_string($apiResponse)
                ? $apiResponse
                : (json_encode($apiResponse) ?: null),
            'failure_category' => $category ?? $classified['category'],
            'http_status' => $httpStatus ?? $classified['http_status'],
            'recoverable' => $recoverable ?? $classified['recoverable'],
            'root_cause' => $rootCause ?? $classified['root_cause'],
            'meta' => $meta,
        ];

        $this->failureCategory ??= $category ?? $classified['category'];
        $this->rootCause ??= $rootCause ?? $classified['root_cause'];

        return $this;
    }

    public function classifyAndRecord(Throwable $e, ?string $sku = null, ?string $marketplace = null): self
    {
        $classified = app(FailureClassifier::class)->classify($e);
        $this->captureException($e);
        $this->failureCategory = $classified['category'];
        $this->rootCause = $classified['root_cause'];

        return $this->recordFailure(
            sku: $sku,
            marketplace: $marketplace,
            reason: $e->getMessage(),
            apiResponse: null,
            category: $classified['category'],
            httpStatus: $classified['http_status'],
            recoverable: $classified['recoverable'],
            rootCause: $classified['root_cause']
        );
    }

    public function mergeMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function captureException(Throwable $e): self
    {
        $this->errorMessage = $e->getMessage();
        $this->exception = $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();

        $classified = app(FailureClassifier::class)->classify($e);
        $this->failureCategory ??= $classified['category'];
        $this->rootCause ??= $classified['root_cause'];

        return $this;
    }

    public function effectiveUpdated(): int
    {
        return $this->updatedRecords + $this->insertedRecords;
    }

    public function successDenominator(): int
    {
        if ($this->expectedRecords !== null && $this->expectedRecords > 0) {
            return $this->expectedRecords;
        }

        if ($this->processedRecords > 0) {
            return $this->processedRecords;
        }

        if ($this->fetchedRecords > 0) {
            return $this->fetchedRecords;
        }

        return max(1, $this->effectiveUpdated() + $this->failedRecords + $this->skippedRecords);
    }

    public function toMetricsArray(): array
    {
        return [
            'expected_records' => $this->expectedRecords,
            'fetched_records' => $this->fetchedRecords,
            'processed_records' => $this->processedRecords,
            'updated_records' => $this->updatedRecords,
            'inserted_records' => $this->insertedRecords,
            'skipped_records' => $this->skippedRecords,
            'failed_records' => $this->failedRecords,
            'api_calls' => $this->apiCalls,
            'api_latency_ms_avg' => $this->averageApiLatencyMs(),
            'api_connected' => $this->apiConnected,
            'retry_count' => $this->retryCount,
            'resume_from' => $this->resumeFrom,
            'failure_category' => $this->failureCategory,
            'root_cause' => $this->rootCause,
            'recovery_status' => $this->recoveryStatus,
            'lock_key' => $this->lockKey,
            'pid' => getmypid() ?: null,
            'meta' => $this->meta,
            'error_message' => $this->errorMessage,
            'exception' => $this->exception,
        ];
    }
}
