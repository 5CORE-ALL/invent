<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\ManualActionService;
use Illuminate\Console\Command;

class CronMonitorUnlockCommand extends Command
{
    protected $signature = 'cron-monitor:unlock {command : Artisan command or job name to unlock}';

    protected $description = 'Force-release a cron-monitor duplicate lock';

    public function handle(ManualActionService $actions): int
    {
        $result = $actions->unlock((string) $this->argument('command'));
        $this->info($result['ok'] ? 'Lock released.' : 'No lock found / already free.');

        return self::SUCCESS;
    }
}
