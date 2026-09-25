<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\B5cB2bOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncB5cB2bOrders extends Command
{
    protected $signature = 'b5cb2b:sync-orders
                            {--days=45 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch Business 5 Core (B2B) orders from the Laravel sync API.';

    public function handle(B5cB2bOrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('b5cb2b')) {
            $this->info('Skipped: Fetch orders is Off in Business 5 Core (B2B) Marketplace Manager settings.');

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
