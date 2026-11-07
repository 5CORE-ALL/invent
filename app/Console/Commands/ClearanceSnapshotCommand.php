<?php

namespace App\Console\Commands;

use App\Support\ClearanceHistory;
use Illuminate\Console\Command;

/**
 * Saves today's Clearance count + INV (history graph in every Sprc Dil modal) and rebuilds missing earlier days.
 */
class ClearanceSnapshotCommand extends Command
{
    protected $signature = 'clearance:snapshot {--backfill=30 : Rebuild missing earlier days (0 = skip)}';

    protected $description = 'Save the daily Clearance SKU count and INV for the Clearance history graph.';

    public function handle(): int
    {
        $days = (int) $this->option('backfill');
        $filled = $days > 0 ? ClearanceHistory::backfill($days) : 0;
        $t = ClearanceHistory::snapshotToday();
        if ($t === null) {
            $this->error('Could not save today\'s clearance snapshot (is the migration run?).');

            return self::FAILURE;
        }
        $this->info(sprintf('Clearance today: %d SKUs, INV %d (backfilled %d day(s))', $t['count'], $t['inv'], $filled));

        return self::SUCCESS;
    }
}
