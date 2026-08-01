<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Console\Command;

class SyncWayfairTrackingFromShopify extends Command
{
    protected $signature = 'wayfair:sync-tracking
                            {--limit=40 : Max orders per run}';

    protected $description = 'Push Shopify tracking to Wayfair (stub).';

    public function handle(WayfairTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
