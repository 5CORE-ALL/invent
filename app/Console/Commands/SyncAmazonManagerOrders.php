<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AmazonOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAmazonManagerOrders extends Command
{
    protected $signature = 'amazon:sync-orders
                            {--days=7 : Days of order history}
                            {--from= : Fetch orders from this date onward (YYYY-MM-DD); overrides --days}
                            {--import : Reserved (Shopify auto-import not implemented for Amazon yet)}
                            {--force : Run even if Fetch orders setting is Off}';

    protected $description = 'Fetch Amazon orders from SP-API into amazon_orders (Marketplace Manager).';

    public function handle(AmazonOrderSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canFetchOrders('amazon')) {
            $this->info('Skipped: Fetch orders is Off in Amazon Marketplace Manager settings.');

            return self::SUCCESS;
        }

        $from = trim((string) $this->option('from'));
        if ($from === '') {
            $from = Carbon::now('America/Los_Angeles')
                ->subDays(max(0, (int) $this->option('days')))
                ->toDateString();
        }

        $result = $sync->sync($from, (bool) $this->option('import'));
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
