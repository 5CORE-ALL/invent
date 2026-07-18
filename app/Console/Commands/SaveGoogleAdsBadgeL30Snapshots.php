<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\GoogleSerpCampaignsController;
use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Http\Controllers\Campaigns\GoogleYoutubeAdsCampaignsController;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Persist each channel's rolling L30 badge metrics (spend/sales/clicks/sold/bgt/ACOS)
 * into google_ads_sbgt_snapshots for one day — or backfill a window. Powers the
 * Google Shopping / SERP / YouTube toolbar charts with true 30-day averages.
 */
class SaveGoogleAdsBadgeL30Snapshots extends Command
{
    protected $signature = 'google:save-badge-l30-snapshots
                            {--backfill=0 : Also backfill this many past calendar days (inclusive of today)}
                            {--channel=all : shopping|serp|youtube|all}';

    protected $description = 'Save Google Ads L30 badge metric snapshots (for ACOS / spend charts)';

    public function handle(): int
    {
        $backfill = max(0, (int) $this->option('backfill'));
        $channelOpt = strtolower(trim((string) $this->option('channel')));
        $controllers = $this->controllersFor($channelOpt);
        if ($controllers === []) {
            $this->error('Unknown --channel. Use shopping, serp, youtube, or all.');

            return self::FAILURE;
        }

        // Anchor on the last completed USA (Pacific) day — never the incomplete current day.
        $end = null;
        foreach ($controllers as $controller) {
            $end = $controller->badgeChartCompletedEndDate();
            break;
        }
        $end = $end ?? Carbon::now('America/Los_Angeles')->subDay()->startOfDay();
        $days = $backfill > 0 ? $backfill : 1;
        $start = $end->copy()->subDays($days - 1);

        $total = 0;
        foreach ($controllers as $label => $controller) {
            $this->info("Channel {$label}: {$start->toDateString()} → {$end->toDateString()} (completed US day)");
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $n = $controller->persistBadgeL30SnapshotsForDate($d->toDateString(), true);
                $total += $n;
                $this->line("  {$d->toDateString()}: {$n} campaign row(s)");
            }
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
