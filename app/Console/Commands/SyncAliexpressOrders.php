<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AliexpressOrderSyncService;
use Illuminate\Console\Command;

class SyncAliexpressOrders extends Command
{
    protected $signature = 'aliexpress:sync-orders
                            {--days=7 : Days of order history to fetch}
                            {--import : Dispatch import jobs for new orders after fetch}';

    protected $description = 'Fetch AliExpress orders from API and store in aliexpress_order_metrics.';

    public function handle(AliexpressOrderSyncService $sync): int
    {
        $days = max(1, (int) $this->option('days'));
        $result = $sync->fetchAndStore($days);
        $this->info($result['message']);

        if ($this->option('import')) {
            $dispatched = $sync->dispatchImportsForNewOrders();
            $this->info("Dispatched {$dispatched} import job(s) to Shopify.");
        }

        return ($result['fetched'] ?? 0) >= 0 ? self::SUCCESS : self::FAILURE;
    }
}
