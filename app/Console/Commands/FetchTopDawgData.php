<?php

namespace App\Console\Commands;

use App\Models\TopDawgOrderMetric;
use App\Models\TopDawgProduct;
use App\Models\TopDawgSyncState;
use App\Services\TopDawgApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FetchTopDawgData extends Command
{
    protected $signature = 'topdawg:fetch {--force : Force full fetch (ignore last sync)}';

    protected $description = 'Fetch TopDawg orders and products, calculate L30/L60 and store in DB';

    public function handle(): int
    {
        $startTime = microtime(true);
        $api = app(TopDawgApiService::class);

        $this->info('Fetching TopDawg orders...');
        $this->fetchAllOrders($api);

        $this->info('Fetching TopDawg products...');
        $productResult = $api->fetchProducts(null, function (int $page, int $lastPage, int $totalSoFar) {
            $this->info("  Products page {$page}/{$lastPage} ({$totalSoFar} so far)");
        });
        $products = $productResult['data'] ?? [];
        $this->info('Fetched ' . count($products) . ' products from API.');

        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $l30End = $today->copy();
        $l30Start = $today->copy()->subDays(30);
        $l60End = $l30Start->copy()->subDay();
        $l60Start = $l60End->copy()->subDays(29);

        $rL30 = $this->calculateQuantitiesFromMetrics($l30Start, $l30End);
        $rL60 = $this->calculateQuantitiesFromMetrics($l60Start, $l60End);

        $bulkData = $this->mapProductsFromResults($products, $rL30, $rL60);

        if (!empty($bulkData)) {
            $this->info('Bulk updating ' . count($bulkData) . ' product records...');
            $this->bulkUpsertProducts($bulkData);
        }

        if (Schema::hasTable('topdawg_sync_states')) {
            TopDawgSyncState::setLastSync(TopDawgSyncState::KEY_PRODUCTS_LAST_SYNC);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("TopDawg data stored successfully in {$duration} seconds.");
        return 0;
    }

    /**
     * Map API 'results' array to product rows.
     * product_code → sku, product_name → product_title, cost → price, qty_available → remaining_inventory, msrp → msrp, tdid → tdid.
     */
    protected function mapProductsFromResults(array $results, array $rL30, array $rL60): array
    {
        $bulkData = [];
        foreach ($results as $item) {
            $sku = $this->normalizeSku($item['product_code'] ?? $item['sku'] ?? $item['id'] ?? null);
            if ($sku === null || $sku === '') {
                continue;
            }
            $bulkData[] = [
                'sku' => (string) $sku,
                'topdawg_listing_id' => $item['id'] ?? $item['tdid'] ?? null,
                'tdid' => $item['tdid'] ?? null,
                'image_src' => $this->extractFirstImage($item['picture_url'] ?? null),
                'listing_state' => isset($item['status']) ? strtolower((string) $item['status']) : 'active',
                'product_title' => $item['product_name'] ?? $item['product_title'] ?? $item['title'] ?? null,
                'r_l30' => $rL30[$sku] ?? 0,
                'r_l60' => $rL60[$sku] ?? 0,
                'price' => $item['cost'] ?? $item['price'] ?? null,
                'msrp' => $item['msrp'] ?? null,
                'views' => $item['views'] ?? null,
                'remaining_inventory' => $item['qty_available'] ?? $item['remaining_inventory'] ?? $item['inventory'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }
        return $bulkData;
    }

    protected function extractFirstImage(?string $pictureUrl): ?string
    {
        if (empty($pictureUrl)) {
            return null;
        }
        $urls = explode(',', $pictureUrl);
        $first = trim($urls[0] ?? '');
        return $first !== '' ? $first : null;
    }

    protected function getOrdersLastSyncIso(): ?string
    {
        if ($this->option('force')) {
            return null;
        }
        $at = TopDawgSyncState::getLastSync(TopDawgSyncState::KEY_ORDERS_LAST_SYNC);
        return $at ? $at->toIso8601String() : null;
    }

    protected function fetchAllOrders(TopDawgApiService $api): void
    {
        $updatedSince = $this->option('force') ? null : $this->getOrdersLastSyncIso();
        $result = $api->fetchOrders($updatedSince);
        $orders = $result['data'] ?? [];
        $this->info('Fetched ' . count($orders) . ' orders from API.');

        $bulk = [];
        foreach ($orders as $order) {
            $orderNumber = $order['order_id'] ?? $order['order_number'] ?? $order['id'] ?? null;
            if (!$orderNumber) {
                continue;
            }
            $createdAt = $order['created_at'] ?? $order['order_date'] ?? null;
            $orderDate = $createdAt ? Carbon::parse($createdAt)->toDateString() : null;
            $orderPaidAt = $createdAt ? Carbon::parse($createdAt)->toDateTimeString() : null;

            $statusCode = $order['status'] ?? $order['status_id'] ?? null;
            $status = $this->mapOrderStatus($statusCode);

            $amount = $order['total'] ?? $order['amount'] ?? $order['total_amount'] ?? null;
            if (is_array($amount)) {
                $amount = $amount['amount'] ?? $amount['value'] ?? (isset($amount['amount_cents']) ? $amount['amount_cents'] / 100 : null);
            }

            $transactions = $order['transactions'] ?? [];
            $firstTxn = is_array($transactions) && !empty($transactions) ? $transactions[0] : [];
            $sku = $this->normalizeSku($firstTxn['product_code'] ?? $firstTxn['tdid'] ?? $order['product_code'] ?? $order['tdid'] ?? null);
            $displaySku = $firstTxn['product_name'] ?? $firstTxn['title'] ?? $order['title'] ?? $order['display_sku'] ?? $sku;
            $quantity = (int) ($firstTxn['quantity'] ?? $order['quantity'] ?? 1);

            $bulk[] = [
                'order_number' => (string) $orderNumber,
                'order_date' => $orderDate,
                'order_paid_at' => $orderPaidAt,
                'status' => $status,
                'amount' => $amount,
                'display_sku' => $displaySku,
                'sku' => $sku,
                'quantity' => $quantity,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (!empty($bulk)) {
            $this->bulkUpsertOrders($bulk);
        }

        if (Schema::hasTable('topdawg_sync_states')) {
            TopDawgSyncState::setLastSync(TopDawgSyncState::KEY_ORDERS_LAST_SYNC);
        }
    }

    protected function mapOrderStatus($statusCode): string
    {
        $map = [
            1 => 'saved',
            2 => 'pending',
            3 => 'processing',
            4 => 'shipped',
            5 => 'delivered',
            6 => 'returned',
            7 => 'failed',
            8 => 'cancelled',
            9 => 'declined',
        ];
        if (is_numeric($statusCode)) {
            return $map[(int) $statusCode] ?? 'unknown';
        }
        return is_string($statusCode) ? strtolower($statusCode) : 'unknown';
    }

    protected function normalizeSku($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return trim((string) $value);
    }

    protected function calculateQuantitiesFromMetrics(Carbon $start, Carbon $end): array
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return [];
        }
        return TopDawgOrderMetric::query()
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['returned', 'refunded', 'cancelled', 'declined', 'failed'])
            ->whereNotNull('sku')
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku')
            ->toArray();
    }

    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }
        try {
            foreach (array_chunk($orders, 100) as $chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $row) {
                        DB::table('topdawg_order_metrics')->updateOrInsert(
                            ['order_number' => $row['order_number']],
                            $row
                        );
                    }
                });
            }
        } catch (\Throwable $e) {
            $this->error('Error bulk upserting orders: ' . $e->getMessage());
            foreach ($orders as $row) {
                try {
                    TopDawgOrderMetric::updateOrCreate(
                        ['order_number' => $row['order_number']],
                        $row
                    );
                } catch (\Throwable $ex) {
                    $this->warn('Failed order ' . ($row['order_number'] ?? '?') . ': ' . $ex->getMessage());
                }
            }
        }
    }

    protected function bulkUpsertProducts(array $data): void
    {
        if (empty($data)) {
            return;
        }
        try {
            foreach (array_chunk($data, 500) as $chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $item) {
                        DB::table('topdawg_products')->updateOrInsert(
                            ['sku' => $item['sku']],
                            $item
                        );
                    }
                });
            }
        } catch (\Throwable $e) {
            $this->error('Error bulk upserting products: ' . $e->getMessage());
            foreach ($data as $item) {
                try {
                    TopDawgProduct::updateOrCreate(
                        ['sku' => $item['sku']],
                        $item
                    );
                } catch (\Throwable $ex) {
                    $this->warn('Failed product ' . ($item['sku'] ?? '?') . ': ' . $ex->getMessage());
                }
            }
        }
    }
}
