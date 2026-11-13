<?php

namespace App\Console\Commands;

use App\Services\ShopifyMarketingReportService;
use Illuminate\Console\Command;

class FetchShopifyMarketingReport extends Command
{
    protected $signature = 'shopify:marketing-report
                            {--days=30 : Lookback window in days}
                            {--channel=meta : meta or all}
                            {--limit=20 : Rows to print}';

    protected $description = 'Check the Shopify "Performance by marketing campaign" report (ShopifyQL) used on /facebook-video-ad';

    public function handle(ShopifyMarketingReportService $service): int
    {
        $channel = $this->option('channel') === 'all' ? 'all' : 'meta';
        $result  = $service->campaignPerformance((int) $this->option('days'), $channel, true);

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Request failed');

            return self::FAILURE;
        }

        $this->info('Query: '.$result['query']);
        $this->table(
            ['Campaign', 'Channel', 'Traffic', 'Total sales', 'Sessions', 'Orders'],
            array_map(fn ($r) => [
                $r['marketing_campaign'], $r['marketing_channel'], $r['traffic_type'],
                number_format($r['total_sales'], 2), $r['sessions'], $r['orders'],
            ], array_slice($result['rows'], 0, (int) $this->option('limit')))
        );
        $t = $result['totals'];
        $this->info("Rows: ".count($result['rows'])." | Sales: {$t['total_sales']} | Sessions: {$t['sessions']} | Orders: {$t['orders']}");

        return self::SUCCESS;
    }
}
