<?php

namespace App\Console\Commands;

use App\Services\StorePriceSyncService;
use Illuminate\Console\Command;

class SyncStoreWebsitePrices extends Command
{
    protected $signature = 'store:sync-prices
        {--sku= : Optional SKU (or comma-separated SKUs) to sync from the store API}';

    protected $aliases = ['store:sync-price-sold-views'];

    protected $description = 'Pull website price, sold, and views from business5core.com /api/listings/prices and match them to product master by SKU';

    public function handle(StorePriceSyncService $sync): int
    {
        $skuOption = trim((string) $this->option('sku'));
        $sku = $skuOption !== '' ? $skuOption : null;

        $this->info($sku
            ? "Syncing website price / sold / views for SKU: {$sku}"
            : 'Syncing website price / sold / views from '.config('services.store.url'));

        try {
            $result = $sync->sync($sku, function (...$args) {
                $source = is_string($args[0] ?? null) ? (string) $args[0] : 'listings';
                $page = (int) ($args[1] ?? $args[0] ?? 0);
                $lastPage = (int) ($args[2] ?? $args[1] ?? 0);
                $count = (int) ($args[3] ?? $args[2] ?? 0);
                $this->line("  {$source} {$page}/{$lastPage}: {$count} item(s)");
            });
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Fetched', $result['fetched']],
                ['Stored', $result['stored']],
                ['Matched', $result['matched']],
                ['With views', $result['with_views'] ?? 0],
                ['With sold', $result['with_sold'] ?? 0],
                ['With qty', $result['with_qty'] ?? 0],
                ['Unmatched', count($result['unmatched'])],
                ['Failed', count($result['failed'])],
            ]
        );

        if ($result['unmatched'] !== []) {
            $this->warn('Unmatched SKUs (no product master row):');
            foreach (array_slice($result['unmatched'], 0, 50) as $unmatchedSku) {
                $this->line('  - '.$unmatchedSku);
            }
            if (count($result['unmatched']) > 50) {
                $this->line('  ... and '.(count($result['unmatched']) - 50).' more');
            }
        }

        if ($result['failed'] !== []) {
            $this->error('Failed rows:');
            foreach ($result['failed'] as $fail) {
                $this->line('  - '.($fail['sku'] ?: '(no sku)').': '.$fail['error']);
            }
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
