<?php

namespace App\Console\Commands;

use App\Services\Support\YesterdayViewsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CollectYesterdayViews extends Command
{
    protected $signature = 'channel:collect-yesterday-views
                            {--date= : Pacific calendar date (Y-m-d). Defaults to yesterday}
                            {--skip-amazon : Skip the slow Amazon SP-API 1-day sessions report}';

    protected $description = 'Store 1-day listing views (Pacific yesterday) for Yesterday CVR';

    public function handle(YesterdayViewsService $svc): int
    {
        $date = $this->option('date') ?: Carbon::yesterday('America/Los_Angeles')->toDateString();
        $includeAmazon = ! (bool) $this->option('skip-amazon');

        $this->info("Collecting 1-day views for {$date} PT".($includeAmazon ? ' (includes Amazon report)' : ' (Amazon skipped)'));

        $rows = $svc->collect($date, $includeAmazon);
        if ($rows === []) {
            $this->warn('No yesterday views stored.');

            return 0;
        }

        foreach ($rows as $channel => $row) {
            $l7 = isset($row['l7_views']) ? ' / L7 '.number_format((int) $row['l7_views']) : '';
            $this->line(sprintf('  %s: L1 %s%s (%s)', $channel, number_format((int) $row['views']), $l7, $row['source']));
        }

        $this->info('Stored '.count($rows).' channel(s).');

        return 0;
    }
}
