<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\AlibabaOrderSyncService;
use Illuminate\Console\Command;

class SyncAlibabaOrders extends Command
{
    protected $signature = 'alibaba:sync-orders
                            {--days=0 : Days of order history (0 = all available, up to 2 years)}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}';

    protected $description = 'Fetch Alibaba orders from API and store in alibaba_order_metrics.';

    public function handle(AlibabaOrderSyncService $sync): int
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
