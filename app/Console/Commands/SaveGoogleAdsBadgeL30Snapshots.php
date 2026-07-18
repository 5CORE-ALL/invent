<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Http\Controllers\Campaigns\GoogleSerpCampaignsController;
use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Http\Controllers\Campaigns\GoogleYoutubeAdsCampaignsController;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Persist each channel's rolling L30 badge metrics (spend/sales/clicks/sold/bgt/ACOS)
 * into google_ads_sbgt_snapshots for one day — or backfill a window. Powers the
 * Google Shopping / SERP / YouTube toolbar charts with true 30-day averages.
 */
class SaveGoogleAdsBadgeL30Snapshots extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'google:save-badge-l30-snapshots
                            {--backfill=0 : Also backfill this many past calendar days (inclusive of today)}
                            {--channel=all : shopping|serp|youtube|all}
                            {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Save Google Ads L30 badge metric snapshots (for ACOS / spend charts)';

    protected string $monitorJobName = 'Google Ads Badge L30 Snapshots';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSave($m),
            $this->monitorJobName
        );
    }

    protected function executeSave(CronExecutionContext $monitor): int
    {
        $backfill = max(0, (int) $this->option('backfill'));
        $channelOpt = strtolower(trim((string) $this->option('channel')));
        $chunkSize = $this->monitoredChunkSize();
        $controllers = $this->controllersFor($channelOpt);
        if ($controllers === []) {
            $this->error('Unknown --channel. Use shopping, serp, youtube, or all.');

            return self::FAILURE;
        }

        $end = null;
        foreach ($controllers as $controller) {
            $end = $controller->badgeChartCompletedEndDate();
            break;
        }
        $end = $end ?? Carbon::now('America/Los_Angeles')->subDay()->startOfDay();
        $days = $backfill > 0 ? $backfill : 1;
        $start = $end->copy()->subDays($days - 1);

        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $workItems = [];
        foreach ($controllers as $label => $controller) {
            foreach ($dates as $date) {
                $workItems[] = ['label' => $label, 'controller' => $controller, 'date' => $date];
            }
        }

        $monitor->setFetched(count($workItems));
        $monitor->setExpected(count($workItems));

        $total = 0;
        foreach (array_chunk($workItems, $chunkSize) as $chunk) {
            $chunkUpdated = 0;
            foreach ($chunk as $item) {
                $this->info("Channel {$item['label']}: {$item['date']} (completed US day)");
                $n = $item['controller']->persistBadgeL30SnapshotsForDate($item['date'], true);
                $total += $n;
                $chunkUpdated += $n;
                $this->line("  {$item['date']}: {$n} campaign row(s)");
            }
            $monitor->incrementProcessed(count($chunk));
            if ($chunkUpdated > 0) {
                $monitor->incrementUpdated($chunkUpdated);
            }
            $monitor->checkpoint(['phase' => 'badge_l30', 'total' => $total], $monitor->processedRecords);
        }

        $this->info("Done. Upserted {$total} snapshot row(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, GoogleShoppingCampaignsController>
     */
    private function controllersFor(string $channelOpt): array
    {
        $all = [
            'shopping' => app(GoogleShoppingCampaignsController::class),
            'serp' => app(GoogleSerpCampaignsController::class),
            'youtube' => app(GoogleYoutubeAdsCampaignsController::class),
        ];

        if ($channelOpt === '' || $channelOpt === 'all') {
            return $all;
        }

        return isset($all[$channelOpt]) ? [$channelOpt => $all[$channelOpt]] : [];
    }
}
