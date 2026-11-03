<?php

namespace App\Services\Wayfair;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull the current Wayfair price (base cost) for each SKU from the Wayfair API and store it in
 * wayfair_pricing_prices.price, which is the Price column on /wayfair-pricing.
 *
 * Wayfair exposes no standalone "read price" call to this app (the old price-feed endpoint 404s and the
 * catalog / inventory queries return no price). The dependable API source is the `price` on each
 * dropship purchase-order line: the unit cost Wayfair is paying us, i.e. the live Base Cost.
 *
 * Rules:
 *  - For every SKU the newest PO line wins.
 *  - Event / promo lines (Way Day, flash events) carry event pricing, so they are ignored unless asked for.
 *  - A SKU's Price only moves when its newest PO is NEWER than the PO the stored Price came from. A manual
 *    sheet upload or a just-pushed S PRC therefore stays put until a fresh PO proves Wayfair's real cost.
 *  - Only SKUs already in wayfair_pricing_prices are updated; rows are never created here.
 */
class WayfairPriceSyncService
{
    /**
     * Newest usable price per SKU across the given purchase orders.
     *
     * @param  array<int, array<string, mixed>>  $purchaseOrders  Raw PO arrays from the Wayfair API
     * @return array<string, array{sku: string, price: float, po_number: string, po_date: Carbon}>  keyed by normalized SKU
     */
    public function latestPriceBySku(array $purchaseOrders, bool $includeEventLines = false): array
    {
        $latest = [];

        foreach ($purchaseOrders as $po) {
            if (! is_array($po)) {
                continue;
            }
            $poDateRaw = $po['poDate'] ?? null;
            if (! $poDateRaw) {
                continue;
            }
            try {
                $poDate = Carbon::parse((string) $poDateRaw)->utc();
            } catch (\Throwable) {
                continue;
            }
            $poNumber = trim((string) ($po['poNumber'] ?? ''));

            foreach ((array) ($po['products'] ?? []) as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $sku = trim((string) ($product['partNumber'] ?? ''));
                $price = $product['price'] ?? null;
                if ($sku === '' || ! is_numeric($price) || (float) $price <= 0) {
                    continue;
                }
                if (! $includeEventLines && $this->isEventLine($product)) {
                    continue;
                }

                $key = self::normalizeSku($sku);
                $current = $latest[$key] ?? null;
                if ($current !== null) {
                    $newer = $poDate->gt($current['po_date'])
                        || ($poDate->eq($current['po_date']) && strcmp($poNumber, $current['po_number']) > 0);
                    if (! $newer) {
                        continue;
                    }
                }

                $latest[$key] = [
                    'sku' => $sku,
                    'price' => round((float) $price, 2),
                    'po_number' => $poNumber,
                    'po_date' => $poDate,
                ];
            }
        }

        return $latest;
    }

    /**
     * Write the newest PO prices into wayfair_pricing_prices.
     *
     * @param  array<int, array<string, mixed>>  $purchaseOrders
     * @return array{success: bool, message: string, skus_in_orders: int, updated: int, unchanged: int, stale: int, no_row: int, skipped_events: int, changes: list<array{sku: string, old: float, new: float, po_number: string, po_date: string}>}
     */
    public function syncFromPurchaseOrders(array $purchaseOrders, bool $includeEventLines = false, bool $dryRun = false): array
    {
        $stats = [
            'success' => true,
            'message' => '',
            'skus_in_orders' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'stale' => 0,
            'no_row' => 0,
            'skipped_events' => 0,
            'changes' => [],
        ];

        foreach (['price_po_number', 'price_po_date', 'price_synced_at'] as $column) {
            if (! Schema::hasTable('wayfair_pricing_prices') || ! Schema::hasColumn('wayfair_pricing_prices', $column)) {
                $stats['success'] = false;
                $stats['message'] = 'wayfair_pricing_prices is missing '.$column.'. Run `php artisan migrate`.';

                return $stats;
            }
        }

        $latest = $this->latestPriceBySku($purchaseOrders, $includeEventLines);
        $stats['skus_in_orders'] = count($latest);
        if (! $includeEventLines) {
            $withEvents = $this->latestPriceBySku($purchaseOrders, true);
            $stats['skipped_events'] = count(array_diff_key($withEvents, $latest));
        }
        if ($latest === []) {
            $stats['message'] = 'No Wayfair purchase-order lines with a price to sync.';

            return $stats;
        }

        $rowsByKey = [];
        DB::table('wayfair_pricing_prices')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$rowsByKey) {
                foreach ($rows as $row) {
                    $rowsByKey[self::normalizeSku((string) $row->sku)][] = $row;
                }
            });

        $now = now();
        foreach ($latest as $key => $hit) {
            $rows = $rowsByKey[$key] ?? [];
            if ($rows === []) {
                $stats['no_row']++;
                continue;
            }

            foreach ($rows as $row) {
                $storedPoDate = $row->price_po_date
                    ? Carbon::parse((string) $row->price_po_date, 'UTC')
                    : null;
                if ($storedPoDate !== null && ! $hit['po_date']->gt($storedPoDate)) {
                    $stats['stale']++;
                    continue;
                }

                $priceChanged = abs((float) $row->price - $hit['price']) >= 0.005;
                if ($priceChanged) {
                    $stats['updated']++;
                    $stats['changes'][] = [
                        'sku' => (string) $row->sku,
                        'old' => round((float) $row->price, 2),
                        'new' => $hit['price'],
                        'po_number' => $hit['po_number'],
                        'po_date' => $hit['po_date']->toDateString(),
                    ];
                } else {
                    $stats['unchanged']++;
                }

                if ($dryRun) {
                    continue;
                }

                $update = [
                    'price_po_number' => mb_substr($hit['po_number'], 0, 64),
                    'price_po_date' => $hit['po_date']->format('Y-m-d H:i:s'),
                    'price_synced_at' => $now,
                ];
                if ($priceChanged) {
                    $update['price'] = $hit['price'];
                    $update['updated_at'] = $now;
                }
                DB::table('wayfair_pricing_prices')->where('id', $row->id)->update($update);
            }
        }

        $stats['message'] = sprintf(
            '%d SKU price(s) %s, %d already current, %d with no newer PO since the stored price, %d not in wayfair_pricing_prices%s.',
            $stats['updated'],
            $dryRun ? 'would change' : 'updated from Wayfair POs',
            $stats['unchanged'],
            $stats['stale'],
            $stats['no_row'],
            $stats['skipped_events'] > 0 ? ", {$stats['skipped_events']} only had event pricing (ignored)" : ''
        );
        Log::info('Wayfair price sync', $stats + ['dry_run' => $dryRun]);

        return $stats;
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', $sku)));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function isEventLine(array $product): bool
    {
        $event = $product['event'] ?? null;
        if (! is_array($event)) {
            return false;
        }

        return trim((string) ($event['id'] ?? '')) !== '';
    }
}
