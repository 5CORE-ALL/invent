<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use App\Services\AliExpressApiService;
use Illuminate\Console\Command;

class SaveAliexpressOrderMetrics extends Command
{
    protected $signature = 'aliexpress:save-order-metrics {--days=30} {--page-size=100}';

    protected $description = 'Fetch AliExpress orders from official API and save L30/L60 metrics';

    public function handle(AliExpressApiService $api): int
    {
        if (empty($api->getAccessToken())) {
            $this->error('ALIEXPRESS_ACCESS_TOKEN is missing in .env');

            return self::FAILURE;
        }

        $days = max(1, min(180, (int) $this->option('days')));
        $pageSize = max(1, min(100, (int) $this->option('page-size')));
        $dateRange = $api->buildOrderDateRange($days);

        $this->info("Fetching orders for last {$days} days...");

        $page = 1;
        $totalProcessed = 0;

        do {
            $this->info("Processing page {$page}...");

            $result = $api->getOrders($page, $pageSize, $dateRange);

            if (empty($result['success'])) {
                $this->error('Failed to fetch orders: '.($result['message'] ?? 'unknown error'));

                return self::FAILURE;
            }

            $orders = $result['data']['orders'] ?? [];

            if ($orders === []) {
                $this->info('No more orders to process.');
                break;
            }

            $this->info('Found '.count($orders).' orders on page '.$page);

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }

                $orderPayload = [
                    'order_id' => (string) ($order['order_id'] ?? ''),
                    'gmt_create' => $order['gmt_create'] ?? now()->toDateTimeString(),
                    'order_status' => (string) ($order['order_status'] ?? ''),
                ];

                foreach ($api->extractOrderProductLines($order) as $product) {
                    $sku = trim((string) ($product['sku_code'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }

                    AliexpressMetric::updateOrderMetrics(
                        (string) ($product['product_id'] ?? ''),
                        $sku,
                        $orderPayload,
                        $product
                    );

                    $totalProcessed++;
                }
            }

            $page++;
            usleep(100000);
        } while ($orders !== []);

        $this->info("Successfully processed {$totalProcessed} product lines from orders across ".($page - 1).' page(s).');

        return self::SUCCESS;
    }
}
