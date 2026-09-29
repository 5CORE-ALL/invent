<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillOrderFulfillmentTrackingCommand extends Command
{
    protected $signature = 'order-fulfillment:backfill-tracking
        {--groups=60 : Orders to resolve per run}
        {--budget=540 : Seconds this run may spend}';

    protected $description = 'Fill Order Fulfillment tracking numbers (Veeqo → marketplace API → 4Seller) for orders that still have none, so the page shows them without waiting on the browser.';

    public function handle(): int
    {
        @set_time_limit(0);

        try {
            $result = app(OrderFulfillmentController::class)->backfillTracking(
                max(1, (int) $this->option('groups')),
                max(30, (int) $this->option('budget'))
            );
        } catch (\Throwable $e) {
            Log::warning('order-fulfillment:backfill-tracking failed', ['error' => $e->getMessage()]);
            $this->error('FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK: %d order(s) checked, %d tracking number(s) found, %d still missing, %d waiting for a later run (%.1fs).',
            $result['groups'],
            $result['found'],
            $result['missed'],
            $result['pending'],
            $result['seconds']
        ));

        return self::SUCCESS;
    }
}
