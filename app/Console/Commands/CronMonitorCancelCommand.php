<?php

namespace App\Console\Commands;

use App\Models\CronExecutionLog;
use App\Services\CronMonitor\ManualActionService;
use Illuminate\Console\Command;

class CronMonitorCancelCommand extends Command
{
    protected $signature = 'cron-monitor:cancel {id : cron_execution_logs.id}';

    protected $description = 'Cancel a running/stuck monitored execution and release its lock';

    public function handle(ManualActionService $actions): int
    {
        $log = CronExecutionLog::find($this->argument('id'));
        if (! $log) {
            $this->error('Execution log not found.');

            return self::FAILURE;
        }

        $actions->cancelRunning($log);
        $this->info("Cancelled execution #{$log->id}");

        return self::SUCCESS;
    }
}
