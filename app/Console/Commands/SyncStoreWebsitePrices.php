<?php

namespace App\Console\Commands;

use App\Services\StorePriceSyncService;
use Illuminate\Console\Command;

class SyncStoreWebsitePrices extends Command
{
    protected $signature = 'store:sync-prices
        {--sku= : Optional SKU (or comma-separated SKUs) to sync from the store API}';

    protected $description = 'Pull FleetCart website prices from /api/listings/prices and match them to product master by SKU';

    public function handle(StorePriceSyncService $sync): int
    {
        $skuOption = trim((string) $this->option('sku'));
        $sku = $skuOption !== '' ? $skuOption : null;

        $this->info($sku
            ? "Syncing website prices for SKU: {$sku}"
            : 'Syncing all website prices from '.config('services.store.url'));

        try {
            $result = $sync->sync($sku, function (int $page, int $lastPage, int $count) {
                $this->line("  Page {$page}/{$lastPage}: {$count} listing(s)");
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
