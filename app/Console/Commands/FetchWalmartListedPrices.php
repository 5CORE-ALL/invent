<?php

namespace App\Console\Commands;

use App\Services\WalmartApiService;
use Illuminate\Console\Command;

class FetchWalmartListedPrices extends Command
{
    protected $signature = 'walmart:fetch-listed-prices
        {--limit=200 : Page size for GET /v3/items (max 200)}
        {--dry-run : Fetch from API but do not write to walmart_pricing}';

    protected $description = 'Fetch listed prices from Walmart GET /v3/items and sync into walmart_pricing.current_price';

    public function handle(WalmartApiService $walmart): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Fetching Walmart listed prices from /v3/items...');

        try {
            if ($dryRun) {
                $items = $walmart->fetchAllItems(function (array $pageItems, int $pageNum, ?int $totalItems) {
                    $totalLabel = $totalItems !== null ? (string) $totalItems : '?';
                    $this->line("  Page {$pageNum}: " . count($pageItems) . " item(s) (totalItems={$totalLabel})");
                }, $limit);

                $withPrice = 0;
                $missing = 0;
                $sample = [];

                foreach ($items as $item) {
                    $sku = trim((string) ($item['sku'] ?? ''));
                    $price = $walmart->extractListedPrice($item);
                    if ($price !== null) {
                        $withPrice++;
                    } else {
                        $missing++;
                    }
                    if ($sku !== '' && count($sample) < 10) {
                        $sample[] = [$sku, $price !== null ? number_format($price, 2, '.', '') : '(none)'];
                    }
                }

                $this->info('Dry run complete. Fetched ' . count($items) . ' item(s).');
                $this->line("  With price: {$withPrice}");
                $this->line("  Missing price: {$missing}");
                if ($sample !== []) {
                    $this->table(['SKU', 'Listed Price'], $sample);
                }

                return self::SUCCESS;
            }

            $stats = $walmart->syncListedPrices(function (array $pageItems, int $pageNum, ?int $totalItems) {
                $totalLabel = $totalItems !== null ? (string) $totalItems : '?';
                $this->line("  Page {$pageNum}: " . count($pageItems) . " item(s) (totalItems={$totalLabel})");
            }, $limit);

            $this->info('Walmart listed prices synced into walmart_pricing.');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Fetched', $stats['fetched']],
                    ['Upserted', $stats['upserted']],
                    ['With price', $stats['with_price']],
                    ['Missing price', $stats['missing_price']],
                    ['Skipped (no SKU)', $stats['skipped']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
