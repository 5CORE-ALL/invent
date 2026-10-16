<?php

namespace App\Console\Commands;

use App\Models\ChannelMaster;
use App\Services\Support\YesterdayMarketplaceMetricsService;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use App\Support\Marketplace\AmmColumnHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordAmmColumnHistory extends Command
{
    protected $signature = 'badges:record-amm-column-history
        {--days=3 : Pacific days to (re)record, ending yesterday. Use 30+ once to backfill.}
        {--end= : Last Pacific date to record (Y-m-d). Defaults to yesterday.}';

    protected $description = 'Save daily Y Orders / Y Views / Y CVR per channel (and all channels) into badges_data_histories so their graphs have history.';

    private const TZ = 'America/Los_Angeles';

    public function handle(): int
    {
        $days = max(1, min(120, (int) $this->option('days')));
        $end = $this->option('end')
            ? Carbon::parse((string) $this->option('end'), self::TZ)->startOfDay()
            : Carbon::now(self::TZ)->subDay()->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('id')
            ->pluck('channel')
            ->map(fn ($name) => AllMarketplaceMasterBadgeCalculator::snapshotKey((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $svc = app(YesterdayMarketplaceMetricsService::class);
        $total = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $ymd = $day->toDateString();
            $rows = [];
            foreach ($channels as $key) {
                try {
                    $row = $svc->metricsForPacificDate($key, $ymd);
                } catch (\Throwable $e) {
                    report($e);
                    $row = null;
                }
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            $written = AmmColumnHistory::recordYesterday($rows, $day->copy());
            $total += $written;
            $this->info(sprintf('%s: %d channel row(s), %d value(s) saved', $ymd, count($rows), $written));
        }

        $this->info("Done. {$total} history value(s) written.");

        return self::SUCCESS;
    }
}
