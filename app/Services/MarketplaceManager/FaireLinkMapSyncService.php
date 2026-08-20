<?php

namespace App\Services\MarketplaceManager;

use App\Models\FaireMetric;
use App\Services\FaireApiService;
use App\Support\Marketplace\MappingChannelCounts;
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
        $this->ensureListingStatusColumn();

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
        $pageSkus = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $pageUpserted += $this->upsertProduct($product, $pageSkus);
        }
        $this->hydrateInventoryFromFaireApi($pageSkus);

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

        if ($done) {
            $this->clearListingCaches();
        }

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

        $this->clearListingCaches();

        return [
            'success' => true,
            'message' => $lastMessage,
            'total_upserted' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  list<string>  $pageSkus
     */
    protected function upsertProduct(array $product, array &$pageSkus = []): int
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
                'on_hand_quantity' => $product['on_hand_quantity'] ?? null,
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
            $pageSkus[] = $sku;

            $qty = $this->faireApi->extractOnHandQuantity($variant);
            // Wholesale only — no retail_price fallback
            $priceMinor = data_get($variant, 'wholesale_price.amount_minor')
                ?? data_get($variant, 'wholesale_price_cents');
            $price = is_numeric($priceMinor) ? round(((float) $priceMinor) / 100, 2) : null;

            $payload = [
                'product_id' => $productId !== '' ? $productId : $sku,
                'product_name' => $name !== '' ? $name : null,
            ];
            $listingStatus = $this->listingStatusFromProduct($product);
            if ($listingStatus !== null && Schema::hasColumn('faire_metric', 'listing_status')) {
                $payload['listing_status'] = $listingStatus;
            }
            if ($price !== null) {
                $payload['price'] = $price;
            }
            if ($qty !== null) {
                $payload['inventory'] = $qty;
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
     * Persist Faire portal lifecycle_state onto faire_metric.listing_status.
     *
     * @return array{ok: bool, done: bool, upserted: int, inactive: int, error?: string}
     */
    public function syncListingStatuses(int $timeBudgetSeconds = 90): array
    {
        if (! Schema::hasTable('faire_metric')) {
            return ['ok' => false, 'done' => false, 'upserted' => 0, 'inactive' => 0, 'error' => 'faire_metric missing'];
        }
        if (! $this->faireApi->isConfigured()) {
            return ['ok' => false, 'done' => false, 'upserted' => 0, 'inactive' => 0, 'error' => 'Faire API credentials missing'];
        }
        $this->ensureListingStatusColumn();
        if (! Schema::hasColumn('faire_metric', 'listing_status')) {
            return ['ok' => false, 'done' => false, 'upserted' => 0, 'inactive' => 0, 'error' => 'listing_status missing'];
        }

        $pageKey = 'mm.faire.portal_status_page_v1';
        $page = max(1, (int) Cache::get($pageKey, 1));
        $deadline = microtime(true) + max(15, $timeBudgetSeconds);
        $upserted = 0;
        $inactive = 0;
        $pageSize = 250;

        try {
            do {
                if (microtime(true) >= $deadline) {
                    Cache::put($pageKey, $page, now()->addHours(6));

                    return ['ok' => true, 'done' => false, 'upserted' => $upserted, 'inactive' => $inactive];
                }

                $res = $this->faireApi->getProducts([
                    'limit' => $pageSize,
                    'page' => $page,
                ]);
                if (empty($res['ok']) || ! is_array($res['json'] ?? null)) {
                    return [
                        'ok' => false,
                        'done' => false,
                        'upserted' => $upserted,
                        'inactive' => $inactive,
                        'error' => $res['error'] ?? 'Faire products fetch failed',
                    ];
                }
                $json = $res['json'];
                $products = $json['products'] ?? $json['data'] ?? [];
                if (! is_array($products)) {
                    $products = [];
                }

                foreach ($products as $product) {
                    if (! is_array($product)) {
                        continue;
                    }
                    $status = $this->listingStatusFromProduct($product);
                    if ($status === null) {
                        continue;
                    }
                    $productId = trim((string) ($product['id'] ?? ''));
                    $name = trim((string) ($product['name'] ?? $product['title'] ?? ''));
                    $variants = $product['variants'] ?? $product['product_variants'] ?? [];
                    if (! is_array($variants) || $variants === []) {
                        $sku = trim((string) ($product['sku'] ?? ''));
                        $variants = $sku !== '' ? [['sku' => $sku]] : [];
                    }
                    foreach ($variants as $variant) {
                        if (! is_array($variant)) {
                            continue;
                        }
                        $sku = trim((string) ($variant['sku'] ?? ''));
                        if ($sku === '') {
                            continue;
                        }
                        FaireMetric::updateOrCreate(
                            ['sku' => $sku],
                            array_filter([
                                'listing_status' => $status,
                                'product_id' => $productId !== '' ? $productId : null,
                                'product_name' => $name !== '' ? $name : null,
                            ], static fn ($v) => $v !== null)
                        );
                        $upserted++;
                        if ($status === 'inactive') {
                            $inactive++;
                        }
                    }
                }

                if (count($products) < $pageSize) {
                    Cache::forget($pageKey);
                    $this->clearListingCaches();

                    return ['ok' => true, 'done' => true, 'upserted' => $upserted, 'inactive' => $inactive];
                }
                $page++;
            } while (true);
        } catch (\Throwable $e) {
            Log::warning('FaireLinkMapSyncService: listing status sync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'done' => false,
                'upserted' => $upserted,
                'inactive' => $inactive,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function listingStatusFromProduct(array $product): ?string
    {
        $raw = $product['lifecycle_state'] ?? $product['lifecycleState'] ?? $product['state'] ?? '';
        $s = strtolower(str_replace([' ', '-'], '_', trim((string) $raw)));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['published', 'live', 'active', 'for_sale'], true)) {
            return 'active';
        }
        if (in_array($s, ['draft', 'unpublished', 'retired', 'deleted', 'archived', 'inactive'], true)) {
            return 'inactive';
        }
        $bucket = MarketplacePortalStatusTabs::bucket($s);

        return $bucket === 'other' ? null : $bucket;
    }

    protected function ensureListingStatusColumn(): void
    {
        if (! Schema::hasTable('faire_metric') || Schema::hasColumn('faire_metric', 'listing_status')) {
            return;
        }
        try {
            Schema::table('faire_metric', function ($blueprint) {
                $blueprint->string('listing_status', 32)->nullable()->index();
            });
        } catch (\Throwable $e) {
            Log::warning('FaireLinkMapSyncService: could not add listing_status', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Faire products list often omits stock — fill faire_metric.inventory from
     * GET /product-inventory/by-skus so listings Faire Qty is not blank.
     *
     * @param  list<string>  $skus
     */
    protected function hydrateInventoryFromFaireApi(array $skus): void
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '')));
        if ($skus === []) {
            return;
        }

        $live = $this->faireApi->getInventoryBySkus($skus);
        foreach ($live as $sku => $row) {
            if (! is_array($row) || ! array_key_exists('qty', $row) || $row['qty'] === null) {
                continue;
            }
            FaireMetric::query()->where('sku', $sku)->update([
                'inventory' => max(0, (int) $row['qty']),
            ]);
        }

        $missing = [];
        foreach ($skus as $sku) {
            if (! isset($live[$sku])) {
                $missing[] = $sku;
            }
        }
        if ($missing === []) {
            return;
        }

        $productIds = FaireMetric::query()
            ->whereIn('sku', $missing)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->pluck('product_id', 'sku');
        $seenProducts = [];
        foreach ($productIds as $sku => $productId) {
            $productId = trim((string) $productId);
            if ($productId === '' || isset($seenProducts[$productId])) {
                continue;
            }
            $seenProducts[$productId] = true;
            foreach ($this->faireApi->getInventoryByProductId($productId) as $faireSku => $row) {
                if (! is_array($row) || $row['qty'] === null) {
                    continue;
                }
                FaireMetric::query()->where('sku', $faireSku)->update([
                    'inventory' => max(0, (int) $row['qty']),
                ]);
            }
        }
    }

    protected function clearListingCaches(): void
    {
        try {
            app(FaireLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'faire');
            MappingChannelCounts::forgetMasterCaches();
        } catch (\Throwable $e) {
            // ignore
        }
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
