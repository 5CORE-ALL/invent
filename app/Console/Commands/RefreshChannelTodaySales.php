<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\BadgeData;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\Support\ChannelTodaySalesService;
use Illuminate\Console\Command;

class RefreshChannelTodaySales extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'channel:refresh-today-sales';

    protected $description = 'Refresh /all-marketplace-master Today Sales (Eastern midnight → now) and persist the snapshot';

    protected string $monitorJobName = 'Channel Refresh Today Sales';

    protected int $monitorLockTtlSeconds = 3300;

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeRefresh($m),
            $this->monitorJobName
        );
    }

    protected function executeRefresh(CronExecutionContext $monitor): int
    {
        $this->info('Refreshing Today Sales (America/New_York)...');

        $result = app(ChannelTodaySalesService::class)->refreshAndPersist();
        $sales = $result['sales'];
        $monitor->markLocalOnly();
        $monitor->setFetched(count($sales));
        $monitor->setExpected($result['updated'] + $result['skipped']);
        $monitor->incrementProcessed($result['updated'] + $result['skipped']);
        $monitor->incrementUpdated($result['updated']);
        $monitor->incrementSkipped($result['skipped']);

        $this->info("Eastern date: {$result['date']}");
        $this->info("Persisted {$result['updated']} channel(s); skipped {$result['skipped']}");
        $this->info('Today Sales total: $'.number_format($result['total'], 2));

        $existing = BadgeData::dataForPage('all-marketplace-master', []);
        $existing['today_sales'] = $result['total'];
        BadgeData::saveForPage('all-marketplace-master', $existing);

        return self::SUCCESS;
    }
}
