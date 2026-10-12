<?php

namespace App\Console\Commands;

use App\Http\Controllers\ProductMaster\InvDaysController;
use Illuminate\Console\Command;

/**
 * Rebuilds the cached Age Days map (Shopify push clock) read by every analytics page's Age Disc column.
 * Read-only: it writes only the cache, never prices or listings.
 */
class InvDaysWarmAgeMapCommand extends Command
{
    protected $signature = 'inv-days:warm-age-map';

    protected $description = 'Rebuild the cached Age Days map used by Age Disc on analytics pages';

    public function handle(): int
    {
        $started = microtime(true);
        $count = app(InvDaysController::class)->warmAgeDayMap();
        $this->info('Age map warmed: '.$count.' SKUs in '.round(microtime(true) - $started, 1).'s');

        return self::SUCCESS;
    }
}
