<?php

namespace App\Services\MarketplaceManager;

use App\Models\FaireMetric;
use App\Services\FaireApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Build / refresh faire_metric from Faire products API (listed + price + inventory).
 * No sheet / faire_pricing_prices fallback — API only.
 */
class FaireLinkMapSyncService
{
    private const CACHE_KEY = 'faire_link_map_sync';

    public function __construct(
        protected FaireApiService $faireApi
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool
     * }
     */
    public function syncPage(int $page = 1, int $pageSize = 50, bool $reset = false): array
    {
        if (! Schema::hasTable('faire_metric')) {
            return $this->fail('faire_metric table missing.');
        }

        if (! $this->faireApi->isConfigured()) {
            return $this->fail('Faire API credentials missing.');
        }

        $page = max(1, $page);
        // Faire products: limit must be within [10, 250]
        $pageSize = max(10, min(250, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
        }

        $state = $this->getProgress();
        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Fetching Faire products page {$page}…",
        ]);

        $res = $this->faireApi->getProducts([
            'limit' => $pageSize,
            'page' => $page,
        ]);

        if (! empty($res['blocked_by_cloudflare'])) {
            return $this->fail('Blocked by Cloudflare while fetching Faire products.');
        }

        if (empty($res['ok'])) {
            return $this->fail($res['error'] ?? ('Product fetch failed HTTP '.($res['status'] ?? 0)));
        }

        $json = is_array($res['json']) ? $res['json'] : [];
        $products = $json['products'] ?? $json['data'] ?? [];
        if (! is_array($products)) {
            $products = [];
        }

        $pageUpserted = 0;
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $pageUpserted += $this->upsertProduct($product);
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = count($products) < $pageSize;

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from Faire products ({$page} page(s))."
            : "Page {$page}: {$pageUpserted} SKU link(s) saved…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $done ? $page : null,
            'total_upserted' => $totalUpserted,
            'message' => $message,
            'done' => $done,
            'error' => false,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'page' => $page,
            'total_page' => $done ? $page : null,
            'page_upserted' => $pageUpserted,
            'total_upserted' => $totalUpserted,
            'done' => $done,
        ];
    }

    /**
     * @return array{success: bool, message: string, total_upserted: int}
     */
    public function syncAll(): array
    {
        $page = 1;
        $total = 0;
        $lastMessage = 'Done.';

        do {
            $result = $this->syncPage($page, 50, $page === 1);
            if (empty($result['success'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Link map sync failed.',
                    'total_upserted' => $total,
                ];
            }
            $total = (int) ($result['total_upserted'] ?? $total);
            $lastMessage = (string) ($result['message'] ?? $lastMessage);
            if (! empty($result['done'])) {
                break;
            }
            $page++;
        } while ($page <= 200);

        return [
            'success' => true,
            'message' => $lastMessage,
            'total_upserted' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function upsertProduct(array $product): int
    {
        $productId = trim((string) ($product['id'] ?? ''));
        $name = trim((string) ($product['name'] ?? $product['title'] ?? ''));
        $variants = $product['variants'] ?? $product['product_variants'] ?? [];
        if (! is_array($variants) || $variants === []) {
            $sku = trim((string) ($product['sku'] ?? ''));
            if ($sku === '' || $productId === '') {
                return 0;
            }
            $variants = [[
                'sku' => $sku,
                'id' => $productId,
                'available_quantity' => $product['available_quantity'] ?? null,
                'wholesale_price_cents' => data_get($product, 'wholesale_price_cents'),
                'wholesale_price' => $product['wholesale_price'] ?? null,
            ]];
        }

        $count = 0;
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $sku = trim((string) ($variant['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $qty = data_get($variant, 'available_quantity')
                ?? data_get($variant, 'on_hand_quantity')
                ?? data_get($variant, 'inventory');
            $priceMinor = data_get($variant, 'wholesale_price.amount_minor')
                ?? data_get($variant, 'wholesale_price_cents')
                ?? data_get($variant, 'retail_price.amount_minor')
                ?? data_get($variant, 'retail_price_cents');
            $price = is_numeric($priceMinor) ? round(((float) $priceMinor) / 100, 2) : null;

            $payload = [
                'product_id' => $productId !== '' ? $productId : $sku,
                'product_name' => $name !== '' ? $name : null,
            ];
            if ($price !== null) {
                $payload['price'] = $price;
            }
            if (is_numeric($qty)) {
                $payload['inventory'] = max(0, (int) $qty);
            }

            FaireMetric::updateOrCreate(
                ['sku' => $sku],
                $payload
            );

            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? $cached : [
                'running' => false,
                'page' => 0,
                'done' => true,
                'message' => 'Idle',
            ];
        } catch (\Throwable $e) {
            return ['running' => false, 'page' => 0, 'done' => true, 'message' => 'Idle'];
        }
    }

    protected function resetProgress(): void
    {
        $this->updateProgress([
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_upserted' => 0,
            'message' => 'Starting…',
            'done' => false,
            'error' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function updateProgress(array $patch): void
    {
        try {
            $current = $this->getProgress();
            Cache::put(self::CACHE_KEY, array_merge($current, $patch), now()->addHours(6));
        } catch (\Throwable $e) {
            Log::warning('FaireLinkMapSyncService: could not update progress', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool
     * }
     */
    protected function fail(string $message): array
    {
        $this->updateProgress([
            'running' => false,
            'done' => true,
            'error' => true,
            'message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'page' => (int) ($this->getProgress()['page'] ?? 0),
            'total_page' => null,
            'page_upserted' => 0,
            'total_upserted' => (int) ($this->getProgress()['total_upserted'] ?? 0),
            'done' => true,
        ];
    }
}
