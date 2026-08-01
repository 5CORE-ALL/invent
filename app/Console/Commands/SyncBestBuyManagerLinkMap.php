<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\BestBuyLinkMapSyncService;
use Illuminate\Console\Command;

class SyncBestBuyManagerLinkMap extends Command
{
    protected $signature = 'bestbuy:sync-link-map
                            {--page=1 : Page to sync}
                            {--reset : Reset progress and fetch from API on page 1}';

    protected $description = 'Refresh Best Buy SKU link map (bestbuy_usa_products via Mirakl Connect inventory).';

    public function handle(BestBuyLinkMapSyncService $sync): int
    {
        $page = max(1, (int) $this->option('page'));
        $reset = (bool) $this->option('reset');
        $result = $sync->syncPage($page, 200, $reset || $page === 1);
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
