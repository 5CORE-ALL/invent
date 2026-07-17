<?php

namespace App\Facades;

use App\Services\CronMonitor\CronExecutionContext;
use App\Services\CronMonitor\CronMonitorService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static CronExecutionContext|null context()
 * @method static CronExecutionContext start(string $jobName, ?string $command = null)
 * @method static void sync()
 * @method static \App\Models\CronExecutionLog finish(?\Throwable $exception = null)
 * @method static mixed run(string $jobName, callable $callback, ?string $command = null)
 *
 * @see CronMonitorService
 */
class CronMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CronMonitorService::class;
    }
}
