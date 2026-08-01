<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\PurchasingPowerLinkMapSyncService;
use Illuminate\Console\Command;

class SyncPurchasingPowerManagerLinkMap extends Command
{
    protected $signature = 'purchasingpower:sync-link-map
                            {--page=1 : Page to sync}
                            {--reset : Reset progress and fetch from API on page 1}';

    protected $description = 'Refresh Purchasing Power SKU link map (purchasing_power_products from MCM OF21).';

    public function handle(PurchasingPowerLinkMapSyncService $sync): int
    {
        $page = max(1, (int) $this->option('page'));
        $reset = (bool) $this->option('reset');
        $result = $sync->syncPage($page, 200, $reset || $page === 1);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
