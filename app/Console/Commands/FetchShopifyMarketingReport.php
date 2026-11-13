<?php

namespace App\Console\Commands;

use App\Services\ShopifyMarketingReportService;
use Illuminate\Console\Command;

class FetchShopifyMarketingReport extends Command
{
    protected $signature = 'shopify:marketing-report
                            {--days=30 : Lookback window in days}
                            {--channel=meta : meta or all}
                            {--limit=20 : Rows to print}
                            {--probe : Try every candidate ShopifyQL query/API version and show which ones Shopify accepts}';

    protected $description = 'Check the Shopify "Performance by marketing campaign" report (ShopifyQL) used on /facebook-video-ad';

    public function handle(ShopifyMarketingReportService $service): int
    {
        if ($this->option('probe')) {
            return $this->probe($service);
        }

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
        $this->info('Rows: '.count($result['rows'])." | Sales: {$t['total_sales']} | Sessions: {$t['sessions']} | Orders: {$t['orders']}");

        return self::SUCCESS;
    }

    private function probe(ShopifyMarketingReportService $service): int
    {
        $results = $service->probe((int) $this->option('days'));
        if ($results === []) {
            $this->error('Shopify credentials are missing (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN).');

            return self::FAILURE;
        }

        $current = null;
        foreach ($results as $r) {
            if ($current !== $r['query']) {
                $current = $r['query'];
                $this->newLine();
                $this->line("<options=bold>{$r['query']}</>");
            }
            if ($r['ok']) {
                $this->line("  <info>OK    {$r['version']}</info>  rows={$r['rows']}  sample=".json_encode($r['sample']));
            } else {
                $this->line("  <fg=red>FAIL  {$r['version']}</>  {$r['error']}");
            }
        }

        return self::SUCCESS;
    }
}
