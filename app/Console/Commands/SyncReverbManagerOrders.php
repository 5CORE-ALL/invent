<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\ReverbOrderSyncService;
use Illuminate\Console\Command;

class SyncReverbManagerOrders extends Command
{
    protected $signature = 'reverb:manager-sync-orders
                            {--days=0 : Days of order history (0 = all available, up to 2 years)}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}';

    protected $description = 'Fetch Reverb orders from API and store in reverb_order_metrics.';

    public function handle(ReverbOrderSyncService $sync): int
    {
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
