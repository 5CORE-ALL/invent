<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\PurchasingPowerOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncPurchasingPowerManagerOrders extends Command
{
    protected $signature = 'purchasingpower:sync-orders
                            {--days=60 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch Purchasing Power orders via purchasing-power:sync --orders.';

    public function handle(PurchasingPowerOrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('purchasingpower')) {
            $this->info('Skipped: Fetch orders is Off in Purchasing Power Marketplace Manager settings.');

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
