<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\WayfairLinkMapSyncService;
use Illuminate\Console\Command;

class SyncWayfairManagerLinkMap extends Command
{
    protected $signature = 'wayfair:sync-link-map
                            {--page=1 : Page to sync}
                            {--reset : Reset progress and fetch from API on page 1}';

    protected $description = 'Refresh Wayfair SKU link map (wayfair_pricing_prices + listing statuses).';

    public function handle(WayfairLinkMapSyncService $sync): int
    {
        $page = max(1, (int) $this->option('page'));
        $reset = (bool) $this->option('reset');
        $result = $sync->syncPage($page, 200, $reset || $page === 1);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
