<?php

namespace App\Console\Commands;

use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Services\AliExpressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FetchAliexpressMetrics extends Command
{
    protected $signature = 'app:fetch-aliexpress-metrics
                            {--listed : Fetch listed product prices from AliExpress product-list API}
                            {--orders : Fetch orders and update L30/L60 sold counts}
                            {--views : Fetch L30 page views (queryproductviewedinfoeverydaybyid / business viewedCount)}
                            {--reviews : Fetch review count + avg rating (social.product.evaluation.query)}
                            {--no-sync-pricing : Skip updating aliexpress_pricing_prices (price + stock)}
                            {--fast : Skip product.info calls (no merchant SKU / stock from API)}
                            {--page-size=50 : Products or orders per API page}
                            {--days=60 : Days of order history for --orders}
                            {--replace : Remove listed-only metric rows before --listed}
                            {--cleanup : Remove invalid metric rows (price 0, sku = product_id, no orders)}';

    protected $description = 'Fetch AliExpress price, stock, sold (L30/L60), page views, and reviews via official API';

    public function handle(AliExpressApiService $api): int
    {
        if ($this->option('cleanup')) {
            return $this->runCleanup();
        }

        if (empty($api->getAccessToken())) {
            $this->error('ALIEXPRESS_ACCESS_TOKEN is missing. Authorize your app in AliExpress Open Platform and set the token in .env.');

            return self::FAILURE;
        }

        $explicit = $this->option('listed') || $this->option('orders') || $this->option('views') || $this->option('reviews');
        $runListed = $this->option('listed') || ! $explicit;
        $runOrders = $this->option('orders') || ! $explicit;
        $runViews = $this->option('views');
        $runReviews = $this->option('reviews');

        $exit = self::SUCCESS;

        if ($runListed) {
            $listedExit = $this->fetchListedProducts($api);
            $exit = $listedExit !== self::SUCCESS ? $listedExit : $exit;
        }

        if ($runOrders) {
            $ordersExit = $this->fetchOrders($api);
            $exit = $ordersExit !== self::SUCCESS ? $ordersExit : $exit;
        }

        if ($runViews) {
            $viewsExit = $this->fetchPageViews($api);
            $exit = $viewsExit !== self::SUCCESS ? $viewsExit : $exit;
        }

        if ($runReviews) {
            $reviewsExit = $this->fetchReviews($api);
            $exit = $reviewsExit !== self::SUCCESS ? $reviewsExit : $exit;
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

                    // Pricing grid reads aliexpress_pricing_prices — always write merchant SKUs.
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
        $normalized = $this->normalizePricingSku($sku);
        if ($normalized === '') {
            return false;
        }

        $row = AliexpressPricingPrice::query()->where('sku', $normalized)->first()
            ?? AliexpressPricingPrice::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->first();
        if (! $row) {
            $row = new AliexpressPricingPrice(['sku' => $normalized]);
        }
        $changed = ! $row->exists;

        if ($price > 0 && round((float) $row->price, 2) !== round($price, 2)) {
            $row->price = round($price, 2);
            $changed = true;
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

    private function normalizePricingSku(string $sku): string
    {
        $sku = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xA0"], ' ', trim($sku));
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $sku);

        return strtoupper((string) preg_replace('/\s+/u', ' ', $clean !== false ? $clean : $sku));
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

    private function fetchPageViews(AliExpressApiService $api): int
    {
        if (! Schema::hasTable('aliexpress_metric') || ! Schema::hasColumn('aliexpress_metric', 'views')) {
            $this->error('aliexpress_metric.views is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        $productIds = AliexpressMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->distinct()
            ->pluck('product_id')
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            $this->warn('No AliExpress product_id values in aliexpress_metric. Run --listed first.');

            return self::SUCCESS;
        }

        $this->info('Fetching L30 page views + CVR for '.$productIds->count().' product(s)...');

        $updated = 0;
        $failed = 0;
        $totalViews = 0;
        $hasOutputOrder = Schema::hasColumn('aliexpress_metric', 'output_order');
        $hasCvr = Schema::hasColumn('aliexpress_metric', 'cvr');

        foreach ($productIds as $index => $productId) {
            $result = $api->getProductPageViewsL30($productId);
            if (empty($result['success'])) {
                $failed++;
                if ($failed <= 5) {
                    $this->warn("Views failed for {$productId}: ".($result['message'] ?? 'unknown error'));
                }
                usleep(80000);

                continue;
            }

            $views = max(0, (int) ($result['views'] ?? 0));
            $outputOrder = max(0, (int) ($result['output_order'] ?? 0));
            // Prefer API outputOrder for CVR; if missing, leave cvr for UI to derive from AL30 later.
            $cvr = $views > 0 ? round(($outputOrder / $views) * 100, 2) : 0.0;
            $payload = ['views' => $views];
            if ($hasOutputOrder) {
                $payload['output_order'] = $outputOrder;
            }
            if ($hasCvr) {
                $payload['cvr'] = (float) ($result['cvr'] ?? $cvr);
            }
            AliexpressMetric::query()
                ->where('product_id', $productId)
                ->update($payload);
            $updated++;
            $totalViews += $views;

            if ((($index + 1) % 25) === 0) {
                $this->info('Views/CVR progress: '.($index + 1).'/'.$productIds->count());
            }

            usleep(120000);
        }

        $this->info("Page views + CVR: {$updated} product(s) updated (sum views {$totalViews})".($failed ? ", {$failed} failed" : '').'.');

        return $updated > 0 || $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fetchReviews(AliExpressApiService $api): int
    {
        if (! Schema::hasTable('aliexpress_metric') || ! Schema::hasColumn('aliexpress_metric', 'reviews')) {
            $this->error('aliexpress_metric.reviews is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        $productIds = AliexpressMetric::query()
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->distinct()
            ->pluck('product_id')
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            $this->warn('No AliExpress product_id values in aliexpress_metric. Run --listed first.');

            return self::SUCCESS;
        }

        $this->info('Fetching reviews for '.$productIds->count().' product(s)...');

        $updated = 0;
        $failed = 0;
        $totalReviews = 0;
        $hasRating = Schema::hasColumn('aliexpress_metric', 'avg_rating');

        foreach ($productIds as $index => $productId) {
            $result = $api->getProductReviews($productId);
            if (empty($result['success'])) {
                $failed++;
                if ($failed <= 5) {
                    $this->warn("Reviews failed for {$productId}: ".($result['message'] ?? 'unknown error'));
                }
                usleep(120000);

                continue;
            }

            $reviews = max(0, (int) ($result['reviews'] ?? 0));
            $payload = ['reviews' => $reviews];
            if ($hasRating) {
                $payload['avg_rating'] = round((float) ($result['avg_rating'] ?? 0), 2);
            }
            AliexpressMetric::query()
                ->where('product_id', $productId)
                ->update($payload);
            $updated++;
            $totalReviews += $reviews;

            if ((($index + 1) % 25) === 0) {
                $this->info('Reviews progress: '.($index + 1).'/'.$productIds->count());
            }

            usleep(120000);
        }

        $this->info("Reviews: {$updated} product(s) updated (sum {$totalReviews})".($failed ? ", {$failed} failed" : '').'.');

        return $updated > 0 || $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
