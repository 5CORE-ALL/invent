<?php

namespace App\Console\Commands;

use App\Jobs\SyncDobaTrackingJob;
use Illuminate\Console\Command;

class SyncDobaTrackingFromShopify extends Command
{
    protected $signature = 'doba:sync-tracking
                            {--sync : Run synchronously instead of queueing}';

    protected $description = 'Push Shopify fulfillment tracking to Doba (stub until API wired).';

    public function handle(): int
    {
        if ($this->option('sync')) {
            (new SyncDobaTrackingJob(false, 40))->handle(app(\App\Services\MarketplaceManager\DobaTrackingSyncService::class));
            $this->info('Doba tracking sync finished (sync mode).');

            return self::SUCCESS;
        }

        SyncDobaTrackingJob::dispatch(false, 40);
        $this->info('Doba tracking sync queued.');

        return self::SUCCESS;
    }
}
