<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\BadgeData;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use Illuminate\Console\Command;

class SaveAllMarketplaceMasterBadgeData extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'badges:save-all-marketplace-master';

    protected $description = 'Snapshot All Marketplace Master badge metrics into badges_data (runs without opening the page).';

    protected string $monitorJobName = 'Save All Marketplace Master Badge Data';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSave($m),
            $this->monitorJobName
        );
    }

    protected function executeSave(CronExecutionContext $monitor): int
    {
        $monitor->setExpected(1);
        $saved = BadgeData::saveForCalculator(AllMarketplaceMasterBadgeCalculator::class);
        $data = $saved['data'];
        $monitor->setFetched(1);
        $monitor->incrementProcessed(1);
        $monitor->incrementUpdated(1);

        $this->info(sprintf(
            'All Marketplace Master badges saved: channels=%d, sales=$%s, orders=%s, ad_spend=$%s, cvr=%s%%',
            $data['channels'] ?? 0,
            number_format($data['l30_sales'] ?? 0),
            number_format($data['l30_orders'] ?? 0),
            number_format($data['ad_spend'] ?? 0),
            $data['cvr_pct'] ?? '—',
        ));

        return self::SUCCESS;
    }
}
