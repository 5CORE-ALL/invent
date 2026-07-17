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

    /** @var array<int, array<string, mixed>> */
    public array $pendingFailures = [];

    /** @var array<string, mixed> */
    public array $meta = [];

    public ?string $errorMessage = null;

    public ?string $exception = null;

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

    public function incrementApiCalls(int $by = 1): self
    {
        $this->apiCalls += $by;

        return $this;
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

    public function recordFailure(
        ?string $sku = null,
        ?string $marketplace = null,
        ?string $reason = null,
        mixed $apiResponse = null,
        array $meta = []
    ): self {
        $this->failedRecords++;
        $this->pendingFailures[] = [
            'sku' => $sku,
            'marketplace' => $marketplace,
            'failure_reason' => $reason,
            'api_response' => is_string($apiResponse)
                ? $apiResponse
                : (json_encode($apiResponse) ?: null),
            'meta' => $meta,
        ];

        return $this;
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

        return $this;
    }

    /**
     * Effective "done" count used for success rate (updated + inserted).
     */
    public function effectiveUpdated(): int
    {
        return $this->updatedRecords + $this->insertedRecords;
    }

    /**
     * Denominator for success percentage.
     */
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
            'api_connected' => $this->apiConnected,
            'retry_count' => $this->retryCount,
            'meta' => $this->meta,
            'error_message' => $this->errorMessage,
            'exception' => $this->exception,
        ];
    }
}
