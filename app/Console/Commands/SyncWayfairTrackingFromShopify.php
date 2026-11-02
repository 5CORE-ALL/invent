<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Console\Command;

class SyncWayfairTrackingFromShopify extends Command
{
    protected $signature = 'wayfair:sync-tracking
                            {--limit=40 : Max orders per run}';

    protected $description = 'Send Wayfair an ASN for recent POs that already have a tracking number.';

    public function handle(WayfairTrackingSyncService $sync): int
    {
        $result = $sync->syncPending((int) $this->option('limit'));
        $this->info($result['message'] ?? 'Done.');

        return self::SUCCESS;
    }
}
