<?php

namespace App\Services\CronMonitor\Healers;

use App\Services\CronMonitor\CronExecutionContext;
use Throwable;

interface HealerInterface
{
    /**
     * Whether this healer can attempt recovery for the classified failure.
     *
     * @param  array{category: string, recoverable: bool, root_cause: string, http_status: int|null}  $classification
     */
    public function supports(array $classification, ?Throwable $exception = null): bool;

    /**
     * Attempt recovery. Return true if the caller should retry the operation.
     *
     * @param  array{category: string, recoverable: bool, root_cause: string, http_status: int|null}  $classification
     */
    public function heal(CronExecutionContext $context, array $classification, ?Throwable $exception = null): bool;
}
