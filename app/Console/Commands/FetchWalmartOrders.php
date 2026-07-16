<?php

namespace App\Console\Commands;

use App\Services\WalmartApiService;
use Illuminate\Console\Command;

class FetchWalmartOrders extends Command
{
    protected $signature = 'walmart:fetch-orders
        {--days=60 : How many days of orders to pull (max 90)}
        {--limit=200 : Page size for GET /v3/orders (max 200)}
        {--dry-run : Fetch from API but do not write to walmart_daily_data}';

    protected $description = 'Fetch Walmart orders from GET /v3/orders and sync into walmart_daily_data';

    public function handle(WalmartApiService $walmart): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Fetching Walmart orders from /v3/orders (last {$days} day(s))...");

        try {
            if ($dryRun) {
                $orders = $walmart->fetchOrders($days, function (array $pageOrders, int $pageNum, ?int $totalCount) {
                    $totalLabel = $totalCount !== null ? (string) $totalCount : '?';
                    $this->line("  Page {$pageNum}: " . count($pageOrders) . " order(s) (totalCount={$totalLabel})");
                }, $limit);

                $lineCount = 0;
                $sample = [];
                foreach ($orders as $order) {
                    $lines = data_get($order, 'orderLines.orderLine', []);
                    if (!is_array($lines)) {
                        $lines = [];
                    }
                    if ($lines !== [] && !array_is_list($lines) && isset($lines['lineNumber'])) {
                        $lines = [$lines];
                    }
                    foreach ($lines as $line) {
                        if (!is_array($line)) {
                            continue;
                        }
                        $lineCount++;
                        if (count($sample) < 10) {
                            $row = $walmart->mapOrderLineToDailyRow($order, $line);
                            if ($row) {
                                $sample[] = [
                                    $row['sku'],
                                    $row['quantity'],
                                    $row['unit_price'],
                                    $row['period'],
                                    $row['status'] ?? '',
                                ];
                            }
                        }
                    }
                }

                $this->info('Dry run complete. Fetched ' . count($orders) . ' order(s), ' . $lineCount . ' line(s).');
                if ($sample !== []) {
                    $this->table(['SKU', 'Qty', 'Unit Price', 'Period', 'Status'], $sample);
                }

                return self::SUCCESS;
            }

            $stats = $walmart->syncOrders($days, function (array $pageOrders, int $pageNum, ?int $totalCount) {
                $totalLabel = $totalCount !== null ? (string) $totalCount : '?';
                $this->line("  Page {$pageNum}: " . count($pageOrders) . " order(s) (totalCount={$totalLabel})");
            }, $limit);

            $this->info('Walmart orders synced into walmart_daily_data.');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Days', $stats['days']],
                    ['Fetched orders', $stats['fetched_orders']],
                    ['Upserted lines', $stats['upserted_lines']],
                    ['Skipped', $stats['skipped']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
