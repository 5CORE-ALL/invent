<?php

namespace App\Console\Commands;

use App\Repositories\CronExecutionLogRepository;
use Illuminate\Console\Command;

class CronMonitorCleanupCommand extends Command
{
    protected $signature = 'cron-monitor:cleanup {--days= : Override retention days}';

    protected $description = 'Purge old cron execution logs based on retention policy';

    public function handle(CronExecutionLogRepository $repository): int
    {
        $days = (int) ($this->option('days') ?: config('cron-monitor.retention_days', 90));
        $deleted = $repository->purgeOlderThan($days);
        $this->info("Deleted {$deleted} cron execution log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
