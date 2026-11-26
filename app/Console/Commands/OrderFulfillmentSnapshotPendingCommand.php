<?php

namespace App\Console\Commands;

use App\Services\OrderFulfillment\PendingSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Records how many orders are Pending per site (the numbers behind /order-fulfillment/pending).
 *
 * Scheduled every hour, and at 3:30 PM Ohio time with --daily for the once-a-day record.
 */
class OrderFulfillmentSnapshotPendingCommand extends Command
{
    protected $signature = 'order-fulfillment:snapshot-pending
        {--daily : Also save the 3:30 PM Ohio daily record (if today has none yet)}';

    protected $description = 'Save pending orders per site for the history badge on /order-fulfillment/pending.';

    public function handle(PendingSnapshotService $service): int
    {
        @ini_set('memory_limit', '1G');
        @set_time_limit(0);

        if (! PendingSnapshotService::available()) {
            $this->error('Table order_fulfillment_pending_snapshots is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        try {
            $result = $service->capture((bool) $this->option('daily'));
        } catch (\Throwable $e) {
            Log::error('order-fulfillment:snapshot-pending failed', ['error' => $e->getMessage()]);
            $this->error('Could not save the snapshot: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Saved: %d pending orders across %d sites%s.',
            $result['total'],
            $result['sites'],
            $result['daily'] ? ' (daily record for '.$result['daily_date'].' saved)' : ''
        ));

        return self::SUCCESS;
    }
}
