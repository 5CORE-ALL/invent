<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\AmazonSpriceCvrAutoPushService;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

class AmazonSpriceCvrAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:sprice-cvr-auto-push
        {--dry-run : Clear + Apply SPRICE in DB, but do NOT push prices to Amazon}
        {--skip-clear : Skip Clear SPRICE step}
        {--skip-push : Skip push to Amazon (same as dry-run for the push step)}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between Amazon Listings API calls (ms)}';

    protected $description = 'Clear SPRICE → Apply % Sprice×CVR → Push SPRICE to Amazon (daily 2PM IST cron). --dry-run saves SPRICE but skips Amazon push.';

    protected string $monitorJobName = 'Amazon Sprice×CVR Auto Push';

    public function handle(AmazonSpriceCvrAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executePipeline($service, $m),
            $this->monitorJobName
        );
    }

    protected function executePipeline(AmazonSpriceCvrAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $skipClear = (bool) $this->option('skip-clear');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Amazon Sprice×CVR Auto Push'.($dryRun ? ' [DRY RUN = Clear+Apply, no push]' : ''));
        $this->info('Schedule target: daily 14:00 Asia/Kolkata');
        if ($dryRun) {
            $this->warn('Dry-run writes SPRICE to the database so tabulator can show the rule result; Amazon push is skipped.');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            dryRun: $dryRun,
            skipClear: $skipClear,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            logger: fn (string $msg) => $this->line($msg)
        );

        $stats = $summary['stats'] ?? [];
        $totalExpected = (int) ($stats['candidates'] ?? 0);
        $totalApplied = (int) ($stats['applied'] ?? 0);
        $totalPushed = (int) ($stats['pushed'] ?? 0);
        $totalFailed = (int) ($stats['push_failed'] ?? 0);
        $errCount = count($stats['errors'] ?? []);

        $this->newLine();
        $this->info('AMAZON: candidates='.($stats['candidates'] ?? 0)
            .' cleared='.($stats['cleared'] ?? 0)
            .' applied='.($stats['applied'] ?? 0)
            .' pushed='.($stats['pushed'] ?? 0)
            .' failed='.($stats['push_failed'] ?? 0)
            .($errCount ? " errors={$errCount}" : ''));
        foreach (array_slice($stats['errors'] ?? [], 0, 10) as $err) {
            $this->warn('  · '.$err);
        }

        $monitor->markApiConnected();
        $monitor->setExpected($totalExpected);
        $monitor->setFetched($totalExpected);
        $monitor->setProcessed($totalApplied);
        $monitor->setUpdated($dryRun || $skipPush ? $totalApplied : $totalPushed);
        $monitor->setFailed($totalFailed);

        $this->newLine();
        if ($dryRun || $skipPush) {
            $this->info("Done. Applied {$totalApplied} SPRICE in DB (push skipped). Candidates {$totalExpected}.");
        } else {
            $this->info("Done. Pushed {$totalPushed}, failed {$totalFailed}, candidates {$totalExpected}.");
        }

        return ($totalFailed > 0 && $totalPushed === 0 && ! $dryRun && ! $skipPush) ? self::FAILURE : self::SUCCESS;
    }
}
