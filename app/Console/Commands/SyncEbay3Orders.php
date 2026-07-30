<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Ebay3OrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncEbay3Orders extends Command
{
    protected $signature = 'ebay3:sync-orders
                            {--days=7 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch eBay 3 orders from API and store in ebay3_order_metrics.';

    public function handle(Ebay3OrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('ebay3')) {
            $this->info('Skipped: Fetch orders is Off in eBay 3 Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $from = trim((string) $this->option('from'));
        if ($from === '') {
            $from = Carbon::now()->subDays(max(0, (int) $this->option('days')))->toDateString();
        }

        $result = $sync->sync($from, (bool) $this->option('import'));
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
