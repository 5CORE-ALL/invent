<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\ScheduledJobRegistry;
use Illuminate\Console\Command;

class CronMonitorListJobsCommand extends Command
{
    protected $signature = 'cron-monitor:jobs {--watchdog : Only jobs eligible for miss detection}';

    protected $description = 'List Kernel scheduled jobs discovered by Cron Monitor';

    public function handle(ScheduledJobRegistry $registry): int
    {
        $jobs = $this->option('watchdog')
            ? $registry->watchedJobsForWatchdog()
            : $registry->jobs();

        $rows = [];
        foreach ($jobs as $command => $cfg) {
            $rows[] = [
                $command,
                $cfg['job_name'] ?? '',
                $cfg['schedule'] ?? '',
                $cfg['expected_at'] ?? '—',
                $cfg['timezone'] ?? '',
                ($cfg['timeout_minutes'] ?? '') . 'm',
                ($cfg['grace_minutes'] ?? '') . 'm',
            ];
        }

        $this->info(count($rows) . ' scheduled job(s) discovered from Kernel.php');
        $this->table(
            ['Command', 'Job name', 'Schedule', 'Expected', 'TZ', 'Timeout', 'Grace'],
            $rows
        );

        return self::SUCCESS;
    }
}
