<?php

namespace App\Console\Commands;

use App\Support\Marketplace\AmmColumnHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Re-saves the Y column history (Y Orders / Y Views / Y CVR / Y Ads%) for past Pacific days on
 * /all-marketplace-master, using each day's own sales and ad spend.
 *
 * Needed once after the Y Ads% fix: older points divided the newest imported ad day (L1) by
 * another day's Total Sales, so they were wrong, mostly on the last couple of days.
 */
class BackfillAmmYesterdayHistory extends Command
{
    protected $signature = 'amm:backfill-y-history
        {--days=30 : Number of Pacific days to rewrite, ending at --end}
        {--end= : Last Pacific day YYYY-MM-DD (default yesterday)}';

    protected $description = 'Rewrite /all-marketplace-master Y column history (incl. Y Ads%) for past days';

    public function handle(): int
    {
        $days = max(1, min(120, (int) $this->option('days')));
        $end = $this->option('end')
            ? Carbon::parse((string) $this->option('end'), 'America/Los_Angeles')
            : null;

        $written = AmmColumnHistory::backfillYesterday($days, $end, fn (string $line) => $this->line($line));
        $this->info("Y history backfill saved {$written} value(s) over {$days} day(s).");

        return self::SUCCESS;
    }
}
