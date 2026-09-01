<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\BestBuyOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncBestBuyManagerOrders extends Command
{
    protected $signature = 'bestbuy:sync-orders
                            {--days=60 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Dispatch import jobs for new orders after fetch}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch Best Buy orders via mirakl:daily (Mirakl Connect Best Buy USA).';

    public function handle(BestBuyOrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('bestbuy')) {
            $this->info('Skipped: Fetch orders is Off in Best Buy Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $from = trim((string) $this->option('from'));
        if ($from === '') {
            $from = Carbon::now()->subDays(max(0, (int) $this->option('days')))->toDateString();
        }

        $result = $sync->sync($from, (bool) $this->option('import'));
        $message = (string) ($result['message'] ?? 'Done.');
        if (strlen($message) > 400) {
            $message = substr($message, 0, 400).'…';
        }
        $this->info($message);

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
