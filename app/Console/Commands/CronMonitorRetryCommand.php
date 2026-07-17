<?php

namespace App\Console\Commands;

use App\Services\CronMonitor\CronRetryService;
use Illuminate\Console\Command;

class CronMonitorRetryCommand extends Command
{
    protected $signature = 'cron-monitor:retry
        {job : Job name as stored in cron_execution_logs.job_name}
        {--limit=100 : Max failures to retry}
        {--dry-run : List failures without retrying}';

    protected $description = 'Retry unresolved failed records for a monitored cron job';

    public function handle(CronRetryService $retryService): int
    {
        $job = (string) $this->argument('job');
        $limit = (int) $this->option('limit');

        if ($this->option('dry-run')) {
            $failures = $retryService->unresolvedForJob($job, $limit);
            $this->info("Unresolved failures for [{$job}]: " . $failures->count());
            foreach ($failures as $f) {
                $this->line("  #{$f->id} sku={$f->sku} marketplace={$f->marketplace} retries={$f->retry_count}");
            }

            return self::SUCCESS;
        }

        // Default handler cannot re-run business logic; jobs should call CronRetryService
        // with a custom closure. This command marks dry inventory / documents usage.
        $this->warn('Provide a job-specific retry handler via CronRetryService::retryUnresolved().');
        $this->line('Example:');
        $this->line('  $retry->retryUnresolved("Amazon Bid Sync", fn ($f) => $this->retrySku($f));');

        $failures = $retryService->unresolvedForJob($job, $limit);
        $this->info("Found {$failures->count()} unresolved failure(s) eligible for retry.");

        return self::SUCCESS;
    }
}
