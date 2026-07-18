<?php

namespace App\Services\CronMonitor;

use Closure;
use Illuminate\Support\Collection;

/**
 * Backward-compatible facade over IntelligentRetryService.
 */
class CronRetryService
{
    public function __construct(protected IntelligentRetryService $intelligent) {}

    /**
     * @return array{attempted: int, resolved: int, failed: int, skipped: int}
     */
    public function retryUnresolved(string $jobName, Closure $handler, ?int $limit = 100): array
    {
        return $this->intelligent->retryUnresolved($jobName, $handler, $limit);
    }

    public function unresolvedForJob(string $jobName, ?int $limit = null): Collection
    {
        return $this->intelligent->unresolvedForJob($jobName, $limit);
    }

    public function queueFailedRecordsRetry(string $jobName, ?int $limit = 100): void
    {
        $this->intelligent->queueFailedRecordsRetry($jobName, $limit);
    }
}
