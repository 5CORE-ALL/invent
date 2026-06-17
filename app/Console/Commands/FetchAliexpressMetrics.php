<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Services\AliExpressApiService;
use Illuminate\Console\Command;

class FetchAliexpressMetrics extends Command
{
    protected $signature = 'app:fetch-aliexpress-metrics
                            {--listed : Fetch listed product prices from AliExpress product-list API}
                            {--orders : Fetch orders and update L30/L60 sold counts}
                            {--no-sync-pricing : Skip updating aliexpress_pricing_prices (price + stock)}
                            {--fast : Skip product.info calls (no merchant SKU / stock from API)}
                            {--page-size=50 : Products or orders per API page}
                            {--days=60 : Days of order history for --orders}
                            {--replace : Remove listed-only metric rows before --listed}
                            {--cleanup : Remove invalid metric rows (price 0, sku = product_id, no orders)}';

    protected $description = 'Fetch AliExpress price, stock, and sold (L30/L60) via official API';

    public function handle(AliExpressApiService $api): int
    {
        if ($this->option('cleanup')) {
            return $this->runCleanup();
        }

        if (empty($api->getAccessToken())) {
            $this->error('ALIEXPRESS_ACCESS_TOKEN is missing. Authorize your app in AliExpress Open Platform and set the token in .env.');

            return self::FAILURE;
        }

        $runListed = $this->option('listed') || ! $this->option('orders');
        $runOrders = $this->option('orders') || ! $this->option('listed');

        $exit = self::SUCCESS;

        if ($runListed) {
            $listedExit = $this->fetchListedProducts($api);
            $exit = $listedExit !== self::SUCCESS ? $listedExit : $exit;
        }

        if ($runOrders) {
            $ordersExit = $this->fetchOrders($api);
            $exit = $ordersExit !== self::SUCCESS ? $ordersExit : $exit;
        }

        return $exit;
    }

    private function runCleanup(): int
    {
        $deleted = AliexpressMetric::query()
            ->where(function ($q) {
                $q->where('price', '<=', 0)
                    ->orWhereNull('price');
            })
            ->whereNull('order_dates')
            ->whereColumn('sku', 'product_id')
            ->delete();

        $this->info("Cleanup: removed {$deleted} invalid row(s).");

        return self::SUCCESS;
    }

    private function fetchListedProducts(AliExpressApiService $api): int
    {
        if ($this->option('replace')) {
            $removed = AliexpressMetric::query()->whereNull('order_dates')->delete();
            $this->info("Replace: removed {$removed} existing listed-only row(s).");
        }

        $pageSize = max(1, min(100, (int) $this->option('page-size')));
        $withSkus = ! $this->option('fast');
        $syncPricing = ! $this->option('no-sync-pricing');

        $mode = $withSkus
            ? 'product list + product.info (SKU, price, stock)'
            : 'product list only (price; no stock)';
        $this->info("Fetching listed products from AliExpress API — {$mode}...");

        $page = 1;
        $saved = 0;
        $pricingSaved = 0;
        $stockUpdated = 0;

        while (true) {
            $result = $api->getInventory($page, $pageSize);
            if (empty($result['success'])) {
                $this->error('Product list failed: '.($result['message'] ?? 'unknown error'));
                if (! empty($result['response'])) {
                    $this->line(json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                return self::FAILURE;
            }

            $products = $result['data']['products'] ?? [];
            if ($products === []) {
                if ($page === 1) {
                    $this->warn('No products returned on page 1.');
                }
                break;
            }

            $this->info("Page {$page}: ".count($products).' product(s)');

            if ($page === 1 && isset($products[0]) && is_array($products[0])) {
                $this->line('Sample keys: '.implode(', ', array_keys($products[0])));
            }

            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }

                $rows = $api->extractSkuRowsFromListItem($product, $withSkus);

                foreach ($rows as $row) {
                    $sku = trim((string) $row['sku']);
                    $productId = (string) $row['product_id'];
                    $price = (float) $row['price'];
                    $stock = $row['stock'];

                    $hasMerchantSku = $sku !== '' && $sku !== $productId;
                    if ($sku === '' || (! $hasMerchantSku && $price <= 0 && $stock === null)) {
                        continue;
                    }

                    AliexpressMetric::updateOrCreate(
                        ['product_id' => $productId, 'sku' => $sku],
                        [
                            'price' => $price,
                            'product_name' => $row['product_name'] ?? null,
                        ]
                    );
                    $saved++;

                    if ($syncPricing && $hasMerchantSku) {
                        if ($this->upsertPricingRow($sku, $price, $stock)) {
                            $pricingSaved++;
                            if ($stock !== null) {
                                $stockUpdated++;
                            }
                        }
                    }
                }

                if ($withSkus) {
                    usleep(120000);
                }
            }

            $page++;
            usleep(150000);
        }

        $msg = "Listed products: {$saved} metric row(s) saved.";
        if ($syncPricing) {
            $msg .= " Pricing: {$pricingSaved} SKU(s) updated ({$stockUpdated} with stock).";
        } elseif ($withSkus) {
            $this->warn('Pricing table not updated — run without --no-sync-pricing to save price + stock.');
        }
        $this->info($msg);

        return self::SUCCESS;
    }

    /**
     * @return bool True if a pricing row was created or updated
     */
    private function upsertPricingRow(string $sku, float $price, ?int $stock): bool
    {
        $normalized = strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
        if ($normalized === '') {
            return false;
        }

        $row = AliexpressPricingPrice::firstOrNew(['sku' => $normalized]);
        $changed = ! $row->exists;

        if ($price > 0) {
            if ((float) $row->price !== $price) {
                $row->price = $price;
                $changed = true;
            }
        }

        if ($stock !== null) {
            $stock = max(0, $stock);
            if ((int) $row->ae_stock !== $stock) {
                $row->ae_stock = $stock;
                $changed = true;
            }
        }

        if ($changed) {
            $row->save();
        }

        return $changed;
    }

    private function fetchOrders(AliExpressApiService $api): int
    {
        $days = max(1, min(180, (int) $this->option('days')));
        $pageSize = max(1, min(100, (int) $this->option('page-size')));
        $dateRange = $api->buildOrderDateRange($days);

        $this->info("Fetching orders (last {$days} days): {$dateRange['create_date_start']} → {$dateRange['create_date_end']}");

        $page = 1;
        $orderCount = 0;
        $productUpdates = 0;

        while (true) {
            $result = $api->getOrders($page, $pageSize, $dateRange);

            if (empty($result['success'])) {
                $this->error('Order list failed: '.($result['message'] ?? 'unknown error'));
                if (! empty($result['response'])) {
                    $this->line(json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }

                return self::FAILURE;
            }

            $orders = $result['data']['orders'] ?? [];
            if ($orders === []) {
                break;
            }

            $this->info("Orders page {$page}: ".count($orders).' order(s)');

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }

                $orderCount++;
                $gmtCreate = $order['gmt_create'] ?? $order['gmt_pay_time'] ?? now()->toDateTimeString();
                $orderId = (string) ($order['order_id'] ?? $order['id'] ?? '');
                $orderStatus = (string) ($order['order_status'] ?? '');

                $orderPayload = [
                    'order_id' => $orderId,
                    'gmt_create' => $gmtCreate,
                    'order_status' => $orderStatus,
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
                    $productUpdates++;
                }
            }

            $page++;
            usleep(150000);
        }

        $this->info("Orders: processed {$orderCount} order(s), {$productUpdates} product line(s) updated (L30/L60 + price).");

        return self::SUCCESS;
    }
}
