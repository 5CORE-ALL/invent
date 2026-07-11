<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AliexpressOrderSyncService;
use Illuminate\Console\Command;

class SyncAliexpressOrders extends Command
{
    protected $signature = 'aliexpress:sync-orders
                            {--days=0 : Days of order history (0 = all available, up to 2 years)}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch AliExpress orders from API and store in aliexpress_order_metrics.';

    public function handle(AliexpressOrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('aliexpress')) {
            $this->info('Skipped: Fetch orders is Off in AliExpress Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $from = trim((string) $this->option('from'));
        $result = $from !== ''
            ? $sync->fetchAndStoreFromDate($from)
            : $sync->fetchAndStore(max(0, (int) $this->option('days')));

        $this->info($result['message']);

        if ($this->option('import')) {
            $dispatched = $sync->dispatchImportsForNewOrders();
            $this->info("Dispatched {$dispatched} import job(s) to Shopify.");
        }

        return self::SUCCESS;
    }
}
