<?php

namespace App\Services\MarketplaceManager;

use App\Models\ReverbMetric;
use App\Models\ReverbPricingPrice;
use App\Models\MarketplaceSyncSettings;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\ReverbApiService;
use App\Services\ReverbManagerApiService;
use App\Services\ShopifyApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReverbInventorySyncService
{
    public function __construct(
        protected ReverbManagerApiService $aliExpressApi,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * Immediately sync specific SKUs from live Shopify → live Reverb listing IDs.
     * Listing id + SKU come from live Reverb API (not stored reverb_metric) when possible.
     *
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus, ?array $shopifyConfig = null): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true))));

        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (empty($this->aliExpressApi->getAccessToken())) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'REVERB_TOKEN missing.'];
        }

        $settings = MarketplaceSyncSettings::getFor('reverb');
        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;

        // Fast path: listing ids from metric / SKU lookup, then LIVE listing GET for state (no full catalog crawl).
        $listingIdsBySku = [];
        $metrics = ReverbMetric::query()
            ->whereIn('sku', $skus)
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->get()
            ->keyBy(fn ($m) => strtoupper(trim((string) $m->sku)));

        foreach ($skus as $sku) {
            $key = strtoupper($sku);
            $metric = $metrics->get($key);
            if (! $metric) {
                foreach ($metrics as $m) {
                    if (strtoupper(trim((string) $m->sku)) === $key) {
                        $metric = $m;
                        break;
                    }
                }
            }
            $pid = $metric ? trim((string) $metric->product_id) : '';
            if ($pid === '') {
                try {
                    $pid = (string) (app(ReverbApiService::class)->getListingIdBySku($sku) ?? '');
                } catch (\Throwable $e) {
                    $pid = '';
                }
            }
            if ($pid !== '') {
                $listingIdsBySku[$sku] = $pid;
            }
        }

        $liveDetails = app(ReverbLiveListingsService::class)
            ->liveDetailsByListingIds(array_values($listingIdsBySku));

        // Always LIVE Shopify API qty.
        $shopifyQty = $this->fetchLiveShopifyQuantities($skus, $shopifyConfig);

        $inventoryRows = [];
        $skipped = 0;

        foreach ($skus as $requestedSku) {
            $productId = (string) ($listingIdsBySku[$requestedSku] ?? '');
            if ($productId === '' || ! isset($liveDetails[$productId])) {
                // Fall back to live catalog resolve only if page-size sync misses.
                $listing = $this->resolveLiveListingForSku($requestedSku, []);
                if ($listing === null) {
                    $skipped++;
                    continue;
                }
                $sku = (string) $listing['sku'];
                $productId = (string) $listing['product_id'];
                $state = (string) ($listing['state'] ?? '');
            } else {
                $live = $liveDetails[$productId];
                $sku = trim((string) ($live['sku'] ?? $requestedSku)) ?: $requestedSku;
                $state = (string) ($live['state'] ?? '');
            }

            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }
            if (! MarketplaceLiveInventoryRules::reverbMayUpdateInventory($state)) {
                $skipped++;
                continue;
            }

            $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
            if ($shopifyStock === null) {
                $shopifyStock = $this->resolveShopifyQty($shopifyQty, $requestedSku);
            }
            // Not on Shopify ⇒ skip. Shopify 0 ⇒ push 0. Shopify >= 1 on sold ⇒ restock+publish.
            // Draft ⇒ inventory only (never publish).
            if ($shopifyStock === null) {
                $skipped++;
                continue;
            }

            $pushQty = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);

            $inventoryRows[] = [
                'product_id' => $productId,
                'sku_code' => $sku,
                'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock),
                'shopify_qty' => $shopifyStock,
                'state' => $state,
            ];
        }

        if ($inventoryRows === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'No linked live Reverb SKUs found for inventory sync.',
            ];
        }

        $this->persistLiveLinkMap($inventoryRows);

        $invResult = $this->aliExpressApi->batchUpdateInventory($inventoryRows);
        if (! empty($invResult['success'])) {
            $this->updateLocalStock($inventoryRows);
            $this->updateLocalPlatformQuantities($inventoryRows);

            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'message' => 'Synced '.count($inventoryRows).' SKU(s) to Reverb from live API.',
            ];
        }

        $this->updateLocalPlatformQuantities($inventoryRows, false);

        Log::warning('ReverbInventorySyncService: post-order SKU inventory sync failed', [
            'skus' => $skus,
            'result' => $invResult,
        ]);

        return [
            'updated' => 0,
            'failed' => count($inventoryRows),
            'skipped' => $skipped,
            'message' => $invResult['message'] ?? 'Reverb inventory update failed.',
        ];
    }

    /**
     * @return array{updated: int, failed: int, skipped: int, price_updated: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $settings = MarketplaceSyncSettings::getFor('reverb');
        if (! ($settings['inventory']['inventory_sync'] ?? false) && ! ($settings['pricing']['price_sync'] ?? false)) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'Inventory and price sync are disabled in settings.',
            ];
        }

        if (empty($this->aliExpressApi->getAccessToken())) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'REVERB_TOKEN missing.',
            ];
        }

        // Source of truth: LIVE Reverb listings (SKU + listing id + state) — not stored reverb_metric.
        Log::info('ReverbInventorySyncService: fetching live Reverb listings');
        $liveListings = $this->fetchLiveReverbListings();
        if ($liveListings === []) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'price_updated' => 0,
                'message' => 'No listings returned from live Reverb API.',
            ];
        }

        if (MarketplaceSyncSettings::reverbCanCreateProducts($settings)) {
            Log::info('ReverbInventorySyncService: create_products_on_reverb is enabled but listing creation is not implemented yet; only existing linked SKUs will be updated.');
        }

        $eligible = [];
        $skipped = 0;
        foreach ($liveListings as $listing) {
            $sku = (string) ($listing['sku'] ?? '');
            $productId = (string) ($listing['product_id'] ?? '');
            $state = (string) ($listing['state'] ?? '');

            if (! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                $skipped++;
                continue;
            }
            if (! MarketplaceLiveInventoryRules::reverbMayUpdateInventory($state)) {
                $skipped++;
                continue;
            }

            $eligible[] = $listing;
        }

        $skus = array_values(array_unique(array_map(
            static fn ($row) => (string) $row['sku'],
            $eligible
        )));

        // Live Shopify inventory for the live Reverb SKUs only.
        Log::info('ReverbInventorySyncService: fetching live Shopify inventory', ['sku_count' => count($skus)]);
        $shopifyQty = $this->shopifyApi->getInventoryQuantitiesBySku($skus);

        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($shopifyQty, (string) $sku) === null) {
                $missing[] = (string) $sku;
            }
        }
        if ($missing !== []) {
            Log::info('ReverbInventorySyncService: live variant fallback for missing SKUs', ['count' => count($missing)]);
            foreach ($this->fetchLiveShopifyQuantities($missing) as $sku => $qty) {
                $shopifyQty[$sku] = $qty;
            }
        }

        $coverage = MarketplaceLiveInventoryRules::shopifyLiveCoverageReport(
            $skus,
            fn (string $sku) => $this->resolveShopifyQty($shopifyQty, $sku)
        );
        Log::info('ReverbInventorySyncService: Shopify live coverage', $coverage);
        if (! $coverage['ok'] && ($settings['inventory']['inventory_sync'] ?? false) && ! $dryRun) {
            Log::error('ReverbInventorySyncService: aborting inventory push — Shopify live coverage too low', $coverage);

            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => count($skus),
                'price_updated' => 0,
                'message' => $coverage['message'],
            ];
        }

        $shopifyDetails = ($settings['pricing']['price_sync'] ?? false)
            ? $this->shopifyApi->getProductDetailsBySkuMap($skus)
            : [];

        $qtyPercent = max(0, min(100, (int) ($settings['inventory']['quantity_calc_percent'] ?? 100)));
        $maxQty = $settings['inventory']['max_quantity'] ?? null;
        $useSalePrice = (bool) ($settings['pricing']['use_sale_price'] ?? false);

        $inventoryRows = [];
        $priceRows = [];

        foreach ($eligible as $listing) {
            $sku = (string) $listing['sku'];
            $productId = (string) $listing['product_id'];
            $state = (string) ($listing['state'] ?? '');

            if ($settings['inventory']['inventory_sync'] ?? false) {
                $shopifyStock = $this->resolveShopifyQty($shopifyQty, $sku);
                // Not present on live Shopify ⇒ not linked for inventory ⇒ skip (never invent).
                if ($shopifyStock === null) {
                    $skipped++;
                    continue;
                }

                $pushQty = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyStock, $qtyPercent, $maxQty);
                $inventoryRows[] = [
                    'product_id' => $productId,
                    'sku_code' => $sku,
                    'inventory' => MarketplaceLiveInventoryRules::clampPushQty($pushQty, $shopifyStock),
                    'shopify_qty' => $shopifyStock,
                    'state' => $state,
                ];
            }

            if ($settings['pricing']['price_sync'] ?? false) {
                $detail = $shopifyDetails[$sku] ?? null;
                $price = null;
                if (is_array($detail)) {
                    $price = $useSalePrice
                        ? ($detail['price'] ?? $detail['sale_price'] ?? null)
                        : ($detail['compare_at_price'] ?? $detail['price'] ?? null);
                }
                $price = $this->applyPriceAdjustment((float) ($price ?? 0), $settings['pricing'] ?? []);
                if ($price > 0) {
                    $priceRows[] = [
                        'product_id' => $productId,
                        'sku_code' => $sku,
                        'price' => $price,
                    ];
                }
            }
        }

        $this->persistLiveLinkMap($inventoryRows);

        if ($dryRun) {
            return [
                'updated' => count($inventoryRows),
                'failed' => 0,
                'skipped' => $skipped,
                'price_updated' => count($priceRows),
                'message' => '[dry-run] Would update '.count($inventoryRows).' inventory row(s) from LIVE Reverb+Shopify, '.count($priceRows).' price row(s).',
            ];
        }

        $updated = 0;
        $failed = 0;
        $priceUpdated = 0;

        if ($inventoryRows !== []) {
            Log::info('ReverbInventorySyncService: pushing live Shopify qty to live Reverb listings', [
                'rows' => count($inventoryRows),
            ]);
            $invResult = $this->aliExpressApi->batchUpdateInventory($inventoryRows);
            if (! empty($invResult['success'])) {
                $updated = count($inventoryRows);
                $this->updateLocalStock($inventoryRows);
                $this->updateLocalPlatformQuantities($inventoryRows);
            } else {
                $failed = count($inventoryRows);
                Log::warning('ReverbInventorySyncService: inventory batch failed', $invResult);
            }
        }

        if ($priceRows !== []) {
            $priceResult = $this->aliExpressApi->batchUpdatePrice($priceRows);
            if (! empty($priceResult['success'])) {
                $priceUpdated = count($priceRows);
                $this->updateLocalPrices($priceRows);
            } else {
                Log::warning('ReverbInventorySyncService: price batch failed', $priceResult);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'price_updated' => $priceUpdated,
            'message' => "Inventory: {$updated} updated (live API), {$failed} failed. Prices: {$priceUpdated} updated. Skipped: {$skipped}.",
        ];
    }

  /**
     * @param  array<string, mixed>  $pricingSettings
     */
    protected function applyPriceAdjustment(float $price, array $pricingSettings): float
    {
        if ($price <= 0) {
            return 0;
        }

        $value = (float) ($pricingSettings['adjustment_value'] ?? 0);
        if ($value <= 0) {
            return round($price, 2);
        }

        $method = $pricingSettings['adjustment_method'] ?? 'percent';
        $type = $pricingSettings['adjustment_type'] ?? 'increase';
        $delta = $method === 'fixed' ? $value : ($price * ($value / 100));
        $adjusted = $type === 'decrease' ? $price - $delta : $price + $delta;

        return round(max(0, $adjusted), 2);
    }

    /**
     * Live Reverb my/listings — SKU + listing id + state (never trust stored metric for this).
     *
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int}>
     */
    protected function fetchLiveReverbListings(): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return [];
        }

        $out = [];
        $url = 'https://api.reverb.com/api/my/listings?state=all&per_page=100';
        $guard = 0;

        while ($url && $guard < 200) {
            $guard++;
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->timeout(60)
                ->get($url);

            if ($response->status() === 429) {
                sleep(2);
                continue;
            }
            if (! $response->successful()) {
                Log::warning('ReverbInventorySyncService: live listings page failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);
                break;
            }

            $data = $response->json() ?? [];
            $listings = $data['listings'] ?? $data['_embedded']['listings'] ?? [];
            if (! is_array($listings)) {
                break;
            }

            foreach ($listings as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $productId = trim((string) ($item['id'] ?? ''));
                $sku = trim((string) ($item['sku'] ?? $item['manufacturer_sku'] ?? ''));
                $stateRaw = $item['state'] ?? null;
                $state = is_array($stateRaw)
                    ? strtolower(trim((string) ($stateRaw['slug'] ?? $stateRaw['description'] ?? '')))
                    : strtolower(trim((string) $stateRaw));
                $inv = (int) ($item['inventory'] ?? $item['quantity'] ?? 0);
                if ($productId === '' || $sku === '') {
                    continue;
                }
                $out[] = [
                    'product_id' => $productId,
                    'sku' => $sku,
                    'state' => $state,
                    'inventory' => $inv,
                ];
            }

            $next = $data['_links']['next']['href'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
            if ($url) {
                usleep(200000);
            }
        }

        Log::info('ReverbInventorySyncService: live Reverb listings fetched', ['count' => count($out)]);

        return $out;
    }

    /**
     * @param  array<int, string>  $limitSkus
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int}>
     */
    protected function fetchLiveReverbListingsIndexedBySku(array $limitSkus): array
    {
        $want = [];
        foreach ($limitSkus as $sku) {
            $want[strtoupper(trim((string) $sku))] = true;
        }

        $indexed = [];
        foreach ($this->fetchLiveReverbListings() as $listing) {
            $key = strtoupper(trim((string) $listing['sku']));
            if ($want !== [] && ! isset($want[$key])) {
                continue;
            }
            // Prefer live / sold-out over draft if duplicates.
            if (! isset($indexed[$key]) || MarketplaceLiveInventoryRules::reverbMayUpdateInventory($listing['state'])) {
                $indexed[$key] = $listing;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, array{product_id: string, sku: string, state: string, inventory: int}>  $liveBySku
     * @return array{product_id: string, sku: string, state: string, inventory: int}|null
     */
    protected function resolveLiveListingForSku(string $requestedSku, array $liveBySku): ?array
    {
        $key = strtoupper(trim($requestedSku));
        if (isset($liveBySku[$key])) {
            return $liveBySku[$key];
        }

        // Fallback: resolve listing id via Reverb API SKU search (still live, not metric table).
        try {
            $listingId = app(ReverbApiService::class)->getListingIdBySku($requestedSku);
            if ($listingId) {
                return [
                    'product_id' => (string) $listingId,
                    'sku' => $requestedSku,
                    'state' => '',
                    'inventory' => 0,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ReverbInventorySyncService: live listing resolve failed', [
                'sku' => $requestedSku,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Write-through live link map into reverb_metric so UI catches up — never read it for push.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory?: int, state?: string}>  $rows
     */
    protected function persistLiveLinkMap(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('reverb_metric')) {
            return;
        }

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku_code'] ?? ''));
            $productId = trim((string) ($row['product_id'] ?? ''));
            if ($sku === '' || $productId === '' || ! MarketplaceLiveInventoryRules::isLinked($productId, $sku)) {
                continue;
            }
            try {
                ReverbMetric::updateOrCreate(
                    ['sku' => $sku],
                    ['product_id' => $productId]
                );
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array{store_url?: string, token?: string}|null  $shopifyConfig
     * @return array<string, int>
     */
    protected function fetchLiveShopifyQuantities(array $skus, ?array $shopifyConfig = null): array
    {
        $storeUrl = $this->normalizeStoreUrl((string) ($shopifyConfig['store_url'] ?? ''));
        $token = (string) ($shopifyConfig['token'] ?? '');

        if ($storeUrl === '' || $token === '') {
            $storeUrl = $this->normalizeStoreUrl((string) config('services.shopify.store_url', ''));
            $token = (string) (config('services.shopify.access_token') ?: config('services.shopify.password') ?: '');
        }

        if ($storeUrl === '' || $token === '') {
            return $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        }

        // Prefer GraphQL inventoryQuantity (same live source as Listings UI).
        $map = [];
        foreach (array_chunk($skus, 15) as $chunk) {
            foreach (app(ReverbLiveListingsService::class)->liveShopifyQtyBySkus($chunk) as $upper => $qty) {
                $map[(string) $upper] = (int) $qty;
                foreach ($chunk as $sku) {
                    if (strtoupper(trim((string) $sku)) === strtoupper((string) $upper)) {
                        $map[(string) $sku] = (int) $qty;
                    }
                }
            }
        }

        // REST SKU fallback for misses only (never prefer negative REST over missing GraphQL).
        foreach ($skus as $sku) {
            if ($this->resolveShopifyQty($map, (string) $sku) !== null) {
                continue;
            }
            try {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get('https://'.$storeUrl.'/admin/api/2025-01/variants.json', [
                    'sku' => $sku,
                    'limit' => 1,
                ]);
                if ($response->successful()) {
                    $variants = $response->json('variants') ?? [];
                    if (is_array($variants) && $variants !== []) {
                        $map[$sku] = (int) ($variants[0]['inventory_quantity'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ReverbInventorySyncService: sku qty fetch failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $map;
    }

    protected function normalizeStoreUrl(string $url): string
    {
        return strtolower(rtrim(str_replace(['https://', 'http://'], '', trim($url)), '/'));
    }

    /**
     * @param  array<string, int>  $shopifyQty
     */
    protected function resolveShopifyQty(array $shopifyQty, string $sku): ?int
    {
        if (array_key_exists($sku, $shopifyQty)) {
            return (int) $shopifyQty[$sku];
        }

        $needle = strtoupper(trim($sku));
        foreach ($shopifyQty as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $needle) {
                return (int) $qty;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalStock(array $rows): void
    {
        if (! Schema::hasTable('reverb_pricing_prices')) {
            return;
        }

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) $row['sku_code']));
            if ($sku === '') {
                continue;
            }
            ReverbPricingPrice::updateOrCreate(
                ['sku' => $sku],
                ['rv_stock' => (int) $row['inventory']]
            );
        }
    }

    /**
     * Keep marketplace UI qty columns in sync after AE inventory push.
     *
     * @param  array<int, array{product_id: string, sku_code: string, inventory: int, shopify_qty?: int}>  $rows
     */
    protected function updateLocalPlatformQuantities(array $rows, bool $updateAeStock = true): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) $row['sku_code']);
            if ($sku === '') {
                continue;
            }

            $aeQty = (int) $row['inventory'];
            $shopifyQty = array_key_exists('shopify_qty', $row) ? (int) $row['shopify_qty'] : $aeQty;

            $shopifyRow = ShopifySku::firstForProductSku($sku);
            if ($shopifyRow) {
                $shopifyRow->fill([
                    'available_to_sell' => $shopifyQty,
                    'inv' => $shopifyQty,
                    'on_hand' => $shopifyQty,
                ])->save();
            }

            if (Schema::hasTable('product_stock_mappings')) {
                $payload = ['inventory_shopify' => $shopifyQty];
                if ($updateAeStock) {
                    $payload['inventory_reverb'] = $aeQty;
                }
                ProductStockMapping::query()
                    ->where(function ($q) use ($sku) {
                        $q->where('sku', $sku)->orWhere('sku', strtoupper($sku));
                    })
                    ->update($payload);
            }
        }
    }

    /**
     * @param  array<int, array{product_id: string, sku_code: string, price: float|string}>  $rows
     */
    protected function updateLocalPrices(array $rows): void
    {
        foreach ($rows as $row) {
            $sku = (string) $row['sku_code'];
            ReverbMetric::query()
                ->where('sku', $sku)
                ->update(['price' => (float) $row['price']]);

            if (Schema::hasTable('reverb_pricing_prices')) {
                ReverbPricingPrice::updateOrCreate(
                    ['sku' => strtoupper(trim($sku))],
                    ['price' => (float) $row['price']]
                );
            }
        }
    }
}
