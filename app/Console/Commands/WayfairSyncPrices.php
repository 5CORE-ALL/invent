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
                            {--show : List every changed SKU when saving too}
                            {--no-sleep : Do not pause between Wayfair pages}';

    protected $description = 'Pull the current Wayfair price per SKU from the Wayfair API (newest PO line) into wayfair_pricing_prices.price';

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

        $stats = $sync->syncFromPurchaseOrders($purchaseOrders, (bool) $this->option('include-event'), (bool) $this->option('dry-run'));
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
