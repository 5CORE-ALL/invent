<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\ReverbSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless catch-up for /reverb-pricing blue triangles (INV > 0, S PRC ≠ RV Price).
 * Same engine as reverb:sprc-dil-auto-push, but run every 10 minutes with a short
 * cooldown so a blue SKU is not left waiting for the 04:25 / 20:25 IST run.
 * The page does not need to be open.
 */
class ReverbPushBluePricesCommand extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'reverb:push-blue-prices
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to Reverb}
        {--limit= : Max SKUs (for testing)}
        {--max-pushes=300 : Max Reverb pushes per run (rest next run)}
        {--cooldown=10 : Minutes before the same SKU is pushed / retried again}
        {--sleep-ms=300 : Delay between Reverb price API calls (ms)}';

    protected $description = 'Reverb: push every blue-triangle SKU (RV Price ≠ S PRC) without waiting for the twice-daily run.';

    protected string $monitorJobName = 'Reverb Push Blue Prices';

    public function handle(ReverbSprcDilAutoPushService $service): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRun($service, $m),
            $this->monitorJobName
        );
    }

    protected function executeRun(ReverbSprcDilAutoPushService $service, CronExecutionContext $monitor): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(1, (int) $limitOpt) : null;

        $summary = $service->run(
            dryRun: $dryRun,
            skipPush: false,
            limit: $limit,
            sleepMs: max(0, (int) $this->option('sleep-ms')),
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg),
            pushAll: false,
            cooldownMinutes: max(0, (int) $this->option('cooldown')),
            blueCatchUp: true,
            errorCooldownMinutes: max(0, (int) $this->option('cooldown')),
            maxPushes: max(0, (int) $this->option('max-pushes'))
        );

        $stats = $summary['stats'] ?? [];
        $pushed = (int) ($stats['pushed'] ?? 0);
        $failed = (int) ($stats['push_failed'] ?? 0);
        $unchanged = (int) ($stats['skipped_unchanged'] ?? 0);
        $skipped = (int) ($stats['skipped'] ?? 0);
        $candidates = (int) ($stats['candidates'] ?? 0);

        $monitor->markApiConnected();
        $monitor->setExpected($candidates);
        $monitor->setFetched($candidates);
        $monitor->setProcessed($pushed + $unchanged + $skipped + $failed);
        $monitor->setUpdated($pushed + $unchanged);
        $monitor->setSkipped($unchanged + $skipped);
        $monitor->setFailed($failed);

        foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
            $this->warn('  · '.$err);
        }

        $this->info(sprintf(
            'Done. candidates=%d pushed=%d unchanged=%d skipped=%d failed=%d',
            $candidates,
            $pushed,
            $unchanged,
            $skipped,
            $failed
        ));

        return ($failed > 0 && $pushed === 0 && ! $dryRun) ? self::FAILURE : self::SUCCESS;
    }
}
