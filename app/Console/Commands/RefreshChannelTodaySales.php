<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\BadgeData;
use App\Support\Badges\AllMarketplaceMasterBadgeAggregator;
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

        // Whole Today block (Orders, Nprft%, Groi%, NROI%, Ads%, Views) goes into
        // badges_data + badges_data_histories, same as the other badges.
        try {
            $payload = app(ChannelMasterController::class)->getAllMarketplaceMasterChannelPayload();
            $today = AllMarketplaceMasterBadgeAggregator::todayMetrics($payload['data'] ?? []);
            unset($today['today_sales']);
            $existing = array_merge($existing, $today);
            $this->info(sprintf(
                'Today badges: orders=%d, nprft=%s%%, groi=%s%%, nroi=%s%%, ads=%s%%, views=%d',
                $today['t_orders'],
                $today['t_npft_pct'],
                $today['t_groi_pct'],
                $today['t_nroi_pct'],
                $today['t_ads_pct'],
                $today['t_views'],
            ));
        } catch (\Throwable $e) {
            report($e);
            $this->warn('Today block metrics not refreshed: '.$e->getMessage());
        }

        BadgeData::saveForPage('all-marketplace-master', $existing);

        return self::SUCCESS;
    }
}
