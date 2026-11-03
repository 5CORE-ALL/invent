<?php

namespace App\Console\Commands;

use App\Services\Wayfair\WayfairPriceSyncService;
use App\Services\WayfairApiService;
use App\Services\WayfairDailyOrderFetchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WayfairSyncPrices extends Command
{
    protected $signature = 'wayfair:sync-prices
                            {--days=60 : How many days of Wayfair purchase orders to read}
                            {--include-event : Also use event / promo PO lines (ignored by default)}
                            {--dry-run : Show what would change without saving (lists every change)}
                            {--overwrite : Replace existing stored prices too (NOT recommended: PO lines can carry discounts)}
                            {--sku=* : Print every PO line (date, price, event) for these SKUs and stop, no saving}
                            {--show : List every changed SKU when saving too}
                            {--no-sleep : Do not pause between Wayfair pages}';

    protected $description = 'Fill missing Wayfair prices from API purchase-order lines (never overwrites a stored price unless --overwrite)';

    public function handle(WayfairApiService $api, WayfairDailyOrderFetchService $orders, WayfairPriceSyncService $sync): int
    {
        if (! $api->isConfigured()) {
            $this->error('WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET must be set in .env');

            return self::FAILURE;
        }

        try {
            $token = $api->getAccessTokenWithScope(null);
        } catch (\Throwable $e) {
            $this->error('Failed to retrieve Wayfair access token: '.$e->getMessage());

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $from = Carbon::today()->subDays($days);
        $this->info("Reading Wayfair purchase orders since {$from->toDateString()}...");

        $result = $orders->fetchPurchaseOrders($from, $token, ! $this->option('no-sleep'));
        if (! empty($result['errors'])) {
            $this->warn('Wayfair GraphQL errors: '.json_encode($result['errors']));
        }
        $purchaseOrders = $result['orders'] ?? [];
        $this->info('POs fetched: '.count($purchaseOrders).' (source: '.($result['source'] ?? 'unknown').')');
        if ($purchaseOrders === []) {
            $this->error('No purchase orders came back from the Wayfair API, nothing to sync.');

            return self::FAILURE;
        }

        $wanted = array_filter(array_map(
            static fn ($s) => WayfairPriceSyncService::normalizeSku((string) $s),
            (array) $this->option('sku')
        ));
        if ($wanted !== []) {
            $lines = [];
            foreach ($purchaseOrders as $po) {
                foreach ((array) ($po['products'] ?? []) as $product) {
                    $key = WayfairPriceSyncService::normalizeSku((string) ($product['partNumber'] ?? ''));
                    if (! in_array($key, $wanted, true)) {
                        continue;
                    }
                    $lines[] = [
                        (string) ($product['partNumber'] ?? ''),
                        substr((string) ($po['poDate'] ?? ''), 0, 10),
                        (string) ($po['poNumber'] ?? ''),
                        (string) ($product['quantity'] ?? ''),
                        (string) ($product['price'] ?? ''),
                        trim((string) (($product['event']['id'] ?? '').' '.($product['event']['type'] ?? '').' '.($product['event']['name'] ?? ''))),
                    ];
                }
            }
            usort($lines, static fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $this->table(['SKU', 'PO date', 'PO', 'Qty', 'PO price', 'Event'], $lines);

            return self::SUCCESS;
        }

        $overwrite = (bool) $this->option('overwrite');
        if ($overwrite && ! $this->option('dry-run')) {
            $this->warn('--overwrite replaces stored prices with PO prices. PO lines can carry Wayfair-side discounts (e.g. 70% of Base Cost), so this can LOWER correct prices.');
            if (! $this->confirm('Overwrite stored Wayfair prices from PO lines?', false)) {
                return self::FAILURE;
            }
        }

        $stats = $sync->syncFromPurchaseOrders($purchaseOrders, (bool) $this->option('include-event'), (bool) $this->option('dry-run'), $overwrite);
        $this->line($stats['message']);

        if (($this->option('dry-run') || $this->option('show')) && ! empty($stats['changes'])) {
            $changes = $stats['changes'];
            usort($changes, static fn ($a, $b) => abs($b['new'] - $b['old']) <=> abs($a['new'] - $a['old']));
            $this->table(
                ['SKU', 'Stored price', 'Wayfair PO price', 'Diff', 'Diff %', 'PO', 'PO date'],
                array_map(static fn ($c) => [
                    $c['sku'],
                    number_format($c['old'], 2),
                    number_format($c['new'], 2),
                    number_format($c['new'] - $c['old'], 2),
                    $c['old'] > 0 ? number_format((($c['new'] - $c['old']) / $c['old']) * 100, 1).'%' : 'n/a (stored 0)',
                    $c['po_number'],
                    $c['po_date'],
                ], $changes)
            );
        }

        return $stats['success'] ? self::SUCCESS : self::FAILURE;
    }
}
