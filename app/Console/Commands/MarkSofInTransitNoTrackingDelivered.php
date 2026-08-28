<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use Illuminate\Console\Command;

/**
 * One-time: mark current In Transit orders that have no tracking number as Delivered.
 * Not scheduled. Refuses a second run unless --force is passed.
 */
class MarkSofInTransitNoTrackingDelivered extends Command
{
    protected $signature = 'sof:mark-in-transit-no-tracking-delivered
                            {--dry-run : Count matching rows without writing}
                            {--force : Run again even if the one-time sentinel already exists}';

    protected $description = 'ONE-TIME: set In Transit orders with no tracking ID to Delivered (not scheduled).';

    public function handle(SalesOrderFulfillmentController $sof): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $result = $sof->markInTransitNoTrackingDeliveredOnce($dryRun, $force);

        if (! ($result['success'] ?? false)) {
            $this->warn((string) ($result['message'] ?? 'Skipped.'));

            return self::SUCCESS;
        }

        $this->info((string) ($result['message'] ?? 'Done.'));
        $this->line('matched='.((int) ($result['matched'] ?? 0))
            .' written='.((int) ($result['written'] ?? 0))
            .' dry_run='.(! empty($result['dry_run']) ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
