<?php

namespace App\Console\Commands;

use App\Services\Support\YesterdayViewsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Amazon 1-day sessions saved before the single-date fix were the sum of two calendar days.
 * This re-fetches settled days from SP-API and overwrites them (and the Y Views / Y CVR history).
 *
 *   php artisan channel:refetch-amazon-views --dry-run --days=3   # fetch + compare only, writes nothing
 *   php artisan channel:refetch-amazon-views --days=30            # overwrite the last 30 settled days
 */
class RefetchAmazonYesterdayViews extends Command
{
    protected $signature = 'channel:refetch-amazon-views
                            {--days=30 : How many settled Pacific days to re-fetch, newest first (max 90)}
                            {--from= : Oldest Pacific date (Y-m-d). Overrides --days}
                            {--to= : Newest Pacific date (Y-m-d). Defaults to the newest settled day}
                            {--dry-run : Fetch and show old vs new values without saving anything}';

    protected $description = 'Re-fetch Amazon 1-day sessions (single-date report) and overwrite the doubled values in channel_yesterday_views';

    public function handle(YesterdayViewsService $svc): int
    {
        $tz = 'America/Los_Angeles';
        $newest = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), $tz)->startOfDay()
            : Carbon::yesterday($tz)->subDays(YesterdayViewsService::AMAZON_SETTLE_DAYS - 1)->startOfDay();
        $days = max(1, min(90, (int) $this->option('days')));
        $oldest = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), $tz)->startOfDay()
            : $newest->copy()->subDays($days - 1);
        if ($oldest->gt($newest)) {
            $this->error('--from is after --to.');

            return 1;
        }

        $dates = [];
        for ($d = $newest->copy(); $d->gte($oldest); $d->subDay()) {
            $dates[] = $d->toDateString();
        }
        $write = ! (bool) $this->option('dry-run');

        $this->info(sprintf('%s %d Amazon day(s): %s → %s (about 20-60 s each, SP-API reports are throttled)',
            $write ? 'Re-fetching and overwriting' : 'DRY RUN, fetching only,', count($dates), $dates[count($dates) - 1], $dates[0]));

        $done = $svc->refetchAmazonSettled($dates, $write, function (string $date, ?int $stored, ?int $fetched) {
            $this->line(sprintf('  %s  stored %s  ->  fetched %s%s', $date,
                $stored === null ? '—' : number_format($stored),
                $fetched === null ? 'FAILED' : number_format($fetched),
                ($stored && $fetched) ? sprintf('  (x%.2f)', $stored / max(1, $fetched)) : ''));
        });

        $this->info(($write ? 'Saved ' : 'Would save ').count($done).' of '.count($dates).' day(s).');

        return count($done) === count($dates) ? 0 : 2;
    }
}
