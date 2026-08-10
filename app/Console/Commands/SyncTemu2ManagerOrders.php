<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\Temu2OrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncTemu2ManagerOrders extends Command
{
    protected $signature = 'temu2:sync-orders
                            {--days=7 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch Temu 2 orders from a date (default / schedule: 2026-07-07) into temu2_orders. Does not auto-push to Shopify unless --import and Settings allow it.';

    public function handle(Temu2OrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('temu2')) {
            $this->info('Skipped: Fetch orders is Off in Temu 2 Marketplace Manager settings.');

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
