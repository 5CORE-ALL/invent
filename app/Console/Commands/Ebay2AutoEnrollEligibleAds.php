<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\Campaigns\Ebay2CampaignAdsController;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

class Ebay2AutoEnrollEligibleAds extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'ebay2:auto-enroll-eligible
        {--dry-run : Show what would be enrolled without calling eBay}
        {--limit= : Max Eligible listings to process}';

    protected $description = 'Enroll eBay 2 Eligible (RECOMMENDED) listings into the matching parent PMT campaign so ads start RUNNING';

    protected string $monitorJobName = 'eBay2 Auto-Enroll Eligible Ads';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeEnroll($m),
            $this->monitorJobName
        );
    }

    protected function executeEnroll(CronExecutionContext $monitor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $this->info('eBay 2 auto-enroll Eligible → RUNNING'.($dryRun ? ' [DRY RUN]' : ''));

        $out = app(Ebay2CampaignAdsController::class)->autoEnrollEligible($dryRun, $limit);

        if (! empty($out['error'])) {
            $this->error($out['error']);
            $monitor->classifyAndRecord(new \RuntimeException($out['error']));

            return self::FAILURE;
        }

        $monitor->markApiConnected();
        $monitor->setExpected((int) (($out['success'] ?? 0) + ($out['failed'] ?? 0) + ($out['skipped'] ?? 0)));
        $monitor->mergeMeta([
            'dry_run' => $dryRun,
            'enrolled' => $out['success'] ?? 0,
            'failed' => $out['failed'] ?? 0,
            'skipped' => $out['skipped'] ?? 0,
            'created_campaigns' => $out['created_campaigns'] ?? 0,
        ]);

        $this->info('Enrolled: '.($out['success'] ?? 0)
            .' | Failed: '.($out['failed'] ?? 0)
            .' | Skipped: '.($out['skipped'] ?? 0)
            .' | Campaigns created: '.($out['created_campaigns'] ?? 0));

        foreach ($out['results'] ?? [] as $row) {
            $this->line(sprintf(
                '  %s %s → %s%s',
                $row['sku'] ?? $row['listing_id'] ?? '',
                $row['listing_id'] ?? '',
                $row['status'] ?? '',
                ! empty($row['reason']) ? ' ('.$row['reason'].')' : (! empty($row['bid']) ? ' @ '.$row['bid'] : '')
            ));
        }

        return self::SUCCESS;
    }
}
