<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\TikTokOrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TikTokSyncOrders extends Command
{
    protected $signature = 'tiktok:sync-orders
        {--days=60 : Number of days back to fetch orders}
        {--from= : Start date (Y-m-d) overrides --days}
        {--import : Also dispatch Shopify import jobs for new orders}';

    protected $description = 'Fetch TikTok Shop orders and optionally import to Shopify';

    public function handle(TikTokOrderSyncService $service): int
    {
        $from = trim((string) $this->option('from'));
        if ($from === '') {
            $from = Carbon::now()->subHours(max(1, (int) $this->option('days')) * 24)->toDateTimeString();
        }

        $import = (bool) $this->option('import');

        $this->info("Fetching TikTok Shop orders from {$from}".($import ? ' (with import)' : '').'...');

        $result = $service->sync($from, $import);

        if (! empty($result['success'])) {
            $this->info($result['message']);
        } else {
            $this->error($result['message']);
        }

        return empty($result['success']) ? 1 : 0;
    }
}
