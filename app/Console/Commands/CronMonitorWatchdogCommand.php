<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\CronWatchdogService;
use Illuminate\Console\Command;

class CronMonitorWatchdogCommand extends Command
{
    protected $signature = 'cron-monitor:watchdog';

    protected $description = 'Watchdog: detect missed, timed-out, or stuck cron jobs';

    public function handle(CronWatchdogService $watchdog): int
    {
        $alerts = $watchdog->run();

        if ($alerts === []) {
            $this->info('Cron watchdog OK — no missed or stuck jobs.');

            return self::SUCCESS;
        }

        $this->warn(count($alerts) . ' alert(s) generated:');
        foreach ($alerts as $alert) {
            $this->line("  [{$alert->severity}] {$alert->title}");
        }

        return self::SUCCESS;
    }
}
