<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MacyTrackingSyncService;
use Illuminate\Console\Command;

class SyncMacyTrackingFromShopify extends Command
{
    protected $signature = 'macy:sync-tracking
                            {--limit=40 : Max orders per run}';

    protected $description = 'Push Shopify tracking to Macy\'s (stub).';

    public function handle(MacyTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
