<?php

namespace App\Services\Veeqo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SKUs for Veeqo order numbers from the order-fulfillment sources:
 * Veeqo shipments, Amazon orders/items, Shopify raw orders (#123, Amz111-…, 01-…, #TT-…), eBay 1-3, TikTok 1-2.
 */
class OrderSkuLookup
{
    /**
     * @param  list<string>  $orderNumbers
     * @return array<string, string> order number => comma-separated SKUs
     */
    public function forOrders(array $orderNumbers): array
    {
        $want = array_values(array_unique(array_filter(array_map(fn ($o) => trim((string) $o), $orderNumbers))));
        $found = [];
        if ($want === []) {
            return [];
        }

        $this->collect($found, $want, fn (array $missing) => $this->pairs('veeqo_billing_shipments', 'order_number', 'skus', $missing, true));
        $this->collect($found, $want, fn (array $missing) => $this->amazon($missing));
        $this->collect($found, $want, fn (array $missing) => $this->shopify($missing));
        foreach (['ebay1_order_metrics', 'ebay2_order_metrics', 'ebay3_order_metrics'] as $table) {
            $this->collect($found, $want, fn (array $missing) => $this->pairs($table, 'order_number', 'sku', $missing));
        }
        foreach (['tiktok_orders' => '#TT-', 'tiktok2_orders' => '#TT2-'] as $table => $prefix) {
            $this->collect($found, $want, fn (array $missing) => $this->prefixed($table, 'order_id', 'seller_sku', $missing, $prefix));
        }

        return array_map(fn (array $skus) => implode(', ', array_keys($skus)), $found);
    }

    /**
     * @param  array<string, array<string, true>>  $found
     * @param  list<string>  $want
     * @param  callable(list<string>): iterable<array{0: string, 1: string}>  $source
     */
    private function collect(array &$found, array $want, callable $source): void
    {
        $missing = array_values(array_diff($want, array_keys($found)));
        if ($missing === []) {
            return;
        }
        try {
            foreach ($source($missing) as [$order, $sku]) {
                foreach ($this->split($sku) as $one) {
                    $found[$order][$one] = true;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<string>  $orders
     * @return list<array{0: string, 1: string}>
     */
    private function pairs(string $table, string $orderCol, string $skuCol, array $orders, bool $csv = false): array
    {
        if (! $this->has($table, [$orderCol, $skuCol])) {
            return [];
        }

        return DB::table($table)->whereIn($orderCol, $orders)->whereNotNull($skuCol)->where($skuCol, '!=', '')
            ->get([$orderCol, $skuCol])
            ->map(fn ($r) => [(string) $r->{$orderCol}, (string) $r->{$skuCol}])
            ->all();
    }

    /**
     * @param  list<string>  $orders
     * @return list<array{0: string, 1: string}>
     */
    private function prefixed(string $table, string $orderCol, string $skuCol, array $orders, string $prefix): array
    {
        $map = [];
        foreach ($orders as $order) {
            if (str_starts_with($order, $prefix)) {
                $map[substr($order, strlen($prefix))] = $order;
            }
        }
        if ($map === []) {
            return [];
        }

        return array_map(fn ($p) => [$map[$p[0]], $p[1]], $this->pairs($table, $orderCol, $skuCol, array_map('strval', array_keys($map))));
    }

    /**
     * @param  list<string>  $orders
     * @return list<array{0: string, 1: string}>
     */
    private function amazon(array $orders): array
    {
        $orders = array_values(array_filter($orders, fn ($o) => (bool) preg_match('/^\d{3}-\d{7}-\d{7}$/', $o)));
        if ($orders === [] || ! $this->has('amazon_order_items', ['amazon_order_id', 'sku']) || ! $this->has('amazon_orders', ['id', 'amazon_order_id'])) {
            return [];
        }

        return DB::table('amazon_order_items as i')
            ->join('amazon_orders as o', 'o.id', '=', 'i.amazon_order_id')
            ->whereIn('o.amazon_order_id', $orders)
            ->whereNotNull('i.sku')->where('i.sku', '!=', '')
            ->get(['o.amazon_order_id as order_number', 'i.sku'])
            ->map(fn ($r) => [(string) $r->order_number, (string) $r->sku])
            ->all();
    }

    /**
     * @param  list<string>  $orders
     * @return list<array{0: string, 1: string}>
     */
    private function shopify(array $orders): array
    {
        $map = [];
        foreach ($orders as $order) {
            $map[$order] = $order;
            if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $order)) {
                $map['Amz'.$order] = $order;
            }
        }

        return array_map(fn ($p) => [$map[$p[0]] ?? $p[0], $p[1]], $this->pairs('shopify_raw_orders', 'order_number', 'sku', array_keys($map)));
    }

    /**
     * @return list<string>
     */
    private function split(string $skus): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $skus)), fn ($s) => $s !== ''));
    }

    /**
     * @param  list<string>  $columns
     */
    private function has(string $table, array $columns): bool
    {
        static $cache = [];
        $key = $table.':'.implode(',', $columns);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasTable($table) && Schema::hasColumns($table, $columns);
        }

        return $cache[$key];
    }
}
