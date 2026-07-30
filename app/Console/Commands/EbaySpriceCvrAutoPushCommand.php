<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\EbaySpriceCvrAutoPushService;
use Illuminate\Console\Command;

class EbaySpriceCvrAutoPushCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'ebay:sprice-cvr-auto-push
        {--channels=ebay1,ebay2,ebay3 : Comma-separated channels (ebay1,ebay2,ebay3)}
        {--dry-run : Clear + Apply SPRICE in DB, but do NOT push prices to eBay}
        {--skip-clear : Skip Clear SPRICE step}
        {--skip-push : Skip push to eBay (same as dry-run for the push step)}
        {--limit= : Max SKUs per channel (for testing)}
        {--sleep-ms=300 : Delay between eBay push API calls (ms)}';

    protected $description = 'Clear SPRICE → Apply % Sprice×CVR → Push SPRICE to eBay 1/2/3 (daily 2PM IST cron). --dry-run saves SPRICE but skips eBay push.';

    protected string $monitorJobName = 'eBay Sprice×CVR Auto Push';

    public function handle(EbaySpriceCvrAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executePipeline($service, $m),
            $this->monitorJobName
        );
    }

    protected function executePipeline(EbaySpriceCvrAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $channels = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('channels'))
        )));
        $dryRun = (bool) $this->option('dry-run');
        $skipClear = (bool) $this->option('skip-clear');
        $skipPush = (bool) $this->option('skip-push');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('eBay Sprice×CVR Auto Push'.($dryRun ? ' [DRY RUN = Clear+Apply, no push]' : ''));
        $this->info('Channels: '.implode(', ', $channels ?: EbaySpriceCvrAutoPushService::CHANNELS));
        $this->info('Schedule target: daily 14:00 Asia/Kolkata');
        if ($dryRun) {
            $this->warn('Dry-run writes SPRICE to the database so tabulator can show the rule result; eBay push is skipped.');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $summary = $service->run(
            channels: $channels,
            dryRun: $dryRun,
            skipClear: $skipClear,
            skipPush: $skipPush,
            limit: $limit,
            sleepMs: $sleepMs,
            logger: fn (string $msg) => $this->line($msg)
        );

        $totalExpected = 0;
        $totalApplied = 0;
        $totalPushed = 0;
        $totalFailed = 0;
        foreach ($summary['channels'] as $ch => $stats) {
            $totalExpected += (int) ($stats['candidates'] ?? 0);
            $totalApplied += (int) ($stats['applied'] ?? 0);
            $totalPushed += (int) ($stats['pushed'] ?? 0);
            $totalFailed += (int) ($stats['push_failed'] ?? 0);
            $errCount = count($stats['errors'] ?? []);
            $this->newLine();
            $this->info(strtoupper((string) $ch).': candidates='.$stats['candidates']
                .' cleared='.$stats['cleared']
                .' applied='.$stats['applied']
                .' pushed='.$stats['pushed']
                .' failed='.$stats['push_failed']
                .($errCount ? " errors={$errCount}" : ''));
            foreach (array_slice($stats['errors'] ?? [], 0, 10) as $err) {
                $this->warn('  · '.$err);
            }
        }

        $monitor->markApiConnected();
        $monitor->setExpected($totalExpected);
        $monitor->setFetched($totalExpected);
        $monitor->setProcessed($totalApplied);
        // Dry-run updates DB (apply) but does not push — count applied as updated.
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
