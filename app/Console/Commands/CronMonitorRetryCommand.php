<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\CronRetryService;
use App\Services\CronMonitor\ManualActionService;
use Illuminate\Console\Command;

class CronMonitorRetryCommand extends Command
{
    protected $signature = 'cron-monitor:retry
        {job : Job name as stored in cron_execution_logs.job_name}
        {--limit=100 : Max failures to retry}
        {--dry-run : List failures without retrying}
        {--queue : Queue RetryFailedRecordsJob instead of inline}';

    protected $description = 'Retry unresolved recoverable failed records for a monitored cron job';

    public function handle(CronRetryService $retryService, ManualActionService $actions): int
    {
        $job = (string) $this->argument('job');
        $limit = (int) $this->option('limit');

        if ($this->option('dry-run')) {
            $failures = $retryService->unresolvedForJob($job, $limit);
            $this->info("Unresolved failures for [{$job}]: " . $failures->count());
            foreach ($failures as $f) {
                $this->line("  #{$f->id} sku={$f->sku} cat={$f->failure_category} recoverable=" . ($f->recoverable ? '1' : '0') . " retries={$f->retry_count}");
            }

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            $actions->retryFailedRecords($job, $limit);
            $this->info('Queued RetryFailedRecordsJob.');

            return self::SUCCESS;
        }

        $this->warn('Inline retry requires a job-specific handler. Queueing instead.');
        $actions->retryFailedRecords($job, $limit);
        $this->line('Use CronRetryService::retryUnresolved($job, $handler) inside your command for real reprocessing.');

        return self::SUCCESS;
    }
}
