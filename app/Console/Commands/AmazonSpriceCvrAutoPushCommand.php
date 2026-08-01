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
        {--skip-price-refresh : Skip prerequisite price sync / listing fetch}
        {--limit= : Max SKUs (for testing)}
        {--sleep-ms=300 : Delay between Amazon Listings API calls (ms)}';

    protected $description = '1) Run price commands 2) Clear SPRICE 3) Apply % Sprice×CVR 4) Push to Amazon (daily 2PM IST).';

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
        $skipPriceRefresh = (bool) $this->option('skip-price-refresh');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Amazon Sprice×CVR Auto Push'.($dryRun ? ' [DRY RUN = Clear+Apply, no push]' : ''));
        $this->info('Schedule target: daily 14:00 Asia/Kolkata');
        $this->info('Pipeline: Price commands → Clear → Apply → Push');
        if ($dryRun) {
            $this->warn('Dry-run writes SPRICE to the database so tabulator can show the rule result; Amazon push is skipped.');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // ── Step 1: Price commands (must finish before Apply) ──
        if (! $skipPriceRefresh) {
            $this->runPriceCommands();
        } else {
            $this->warn('Step 1/2 SKIPPED: price refresh (--skip-price-refresh). Using existing amazon_datsheets.price.');
        }

        // ── Step 2: Apply remaining (Clear → Apply Sprice×CVR → Push) ──
        $this->newLine();
        $this->info('━━━ Step 2/2: Apply remaining (Clear → Apply % Sprice×CVR → Push) ━━━');

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

    /**
     * Step 1 — run price commands before Clear/Apply/Push.
     * Sprice×CVR base = amazon_datsheets.price (from listings report).
     */
    protected function runPriceCommands(): void
    {
        $this->newLine();
        $this->info('━━━ Step 1/2: Run price commands ━━━');

        $this->info('1a) sync:amazon-prices (LMPA → price_lmpa)...');
        try {
            $exit = $this->call('sync:amazon-prices');
            if ((int) $exit !== self::SUCCESS) {
                $this->warn("sync:amazon-prices exited with code {$exit} — continuing.");
            } else {
                $this->info('✓ sync:amazon-prices done.');
            }
        } catch (\Throwable $e) {
            $this->warn('sync:amazon-prices failed (non-blocking): '.$e->getMessage());
        }

        $this->info('1b) app:fetch-amazon-listings (Amazon price + sessions/units for CVR)...');
        try {
            $exit = $this->call('app:fetch-amazon-listings');
            if ((int) $exit !== self::SUCCESS) {
                $this->warn("app:fetch-amazon-listings exited with code {$exit} — continuing with existing price.");
            } else {
                $this->info('✓ app:fetch-amazon-listings done.');
            }
        } catch (\Throwable $e) {
            $this->warn('app:fetch-amazon-listings failed (non-blocking): '.$e->getMessage());
        }

        $this->info('✓ Price commands finished — continuing to Apply remaining.');
    }
}
