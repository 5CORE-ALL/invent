<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use App\Services\ShopifyApiService;
use App\Services\ShopifyCatalogSyncService;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push 5Core (main) Shopify qty onto the PLS Shopify store (Admin inventory_levels/set).
 * 5Core is the inventory master — PLS catalog qty is never the source for a push.
 */
class PlsInventorySyncService
{
    private const LOCATION_CACHE_KEY = 'mm.pls.primary_location_id';

    private const API_VERSION = '2025-01';

    public function __construct(
        protected ShopifyPlsTokenService $tokenService,
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * @param  array<int, string>  $skus
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncSkusFromShopify(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn (string $s) => $s !== '')));

        if ($skus === []) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'No SKUs to sync.'];
        }

        if (! $this->tokenService->isConfigured()) {
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'Shopify PLS is not connected.'];
        }

        $fetchSkus = $skus;
        foreach ($skus as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && $norm !== $sku) {
                $fetchSkus[] = $norm;
            }
        }
        $fetchSkus = array_values(array_unique($fetchSkus));

        $shopifyQty = app(ShopifyQtySource::class)->fetchQuantitiesForPush(
            $fetchSkus,
            fn (array $need) => $this->fetchLive5CoreQuantities($need)
        );
        foreach (MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($fetchSkus) as $key => $qty) {
            $shopifyQty[$key] = (int) $qty;
        }
        if ($shopifyQty === []) {
            $shopifyQty = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($fetchSkus);
        }

        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $errorSamples = [];
        $overlay = [];

        foreach ($skus as $sku) {
            if (MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                $skipped++;
                continue;
            }

            $qty = $this->resolve5CoreQty($shopifyQty, $sku);
            foreach ($fetchSkus as $alt) {
                if ($qty !== null) {
                    break;
                }
                if (ShopifySku::normalizeSkuForShopifyLookup($alt)
                    === ShopifySku::normalizeSkuForShopifyLookup($sku)) {
                    $qty = $this->resolve5CoreQty($shopifyQty, $alt);
                }
            }
            if ($qty === null) {
                $skipped++;
                $errorSamples['No 5Core Shopify qty'] = ($errorSamples['No 5Core Shopify qty'] ?? 0) + 1;
                continue;
            }

            $pushQty = MarketplaceLiveInventoryRules::clampPushQty(
                MarketplaceLiveInventoryRules::pushQtyFromLiveShopify($qty),
                $qty
            );
            $result = $this->pushSku($sku, $pushQty);
            if ($result['success']) {
                $updated++;
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '') {
                    $overlay[$norm] = $pushQty;
                }
            } else {
                $failed++;
                $msg = $result['message'] ?? 'Push failed';
                $errorSamples[$msg] = ($errorSamples[$msg] ?? 0) + 1;
                Log::warning('PlsInventorySyncService: push failed', [
                    'sku' => $sku,
                    'qty' => $pushQty,
                    'error' => $msg,
                ]);
            }
        }

        app(PlsLiveListingsService::class)->clearCache();
        if ($overlay !== []) {
            app(ShopifyCatalogSyncService::class)->overlayCachedInventory('pls', $overlay);
        }

        $message = "Updated {$updated}, failed {$failed}, skipped {$skipped}.";
        if ($errorSamples !== []) {
            arsort($errorSamples);
            $top = array_slice($errorSamples, 0, 3, true);
            $bits = [];
            foreach ($top as $err => $count) {
                $bits[] = $err.' ('.$count.')';
            }
            $message .= ' '.implode('; ', $bits);
        }

        return compact('updated', 'failed', 'skipped', 'message');
    }

    /**
     * Full mismatch push for the queued inventory-sync job.
     *
     * @return array{updated: int, failed: int, skipped: int, message: string}
     */
    public function syncFromShopify(bool $dryRun = false): array
    {
        $builder = app(PlsListingsPageBuilder::class);
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        $linkedSkus = $builder->linkedSkus();
        $verified = $catalog->filterLinkedToVerified($linkedSkus);
        $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            app(PlsLiveListingsService::class)->peekCached(),
            $builder->stockMapForSkus($verified)
        );
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock, marketplace: 'pls') ?? [];
        $mismatch = $classified['mismatch'] ?? [];

        if ($dryRun) {
            return [
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'Dry run: '.count($mismatch).' mismatch SKU(s).',
            ];
        }

        return $this->syncSkusFromShopify($mismatch);
    }

    /**
     * Refresh one PLS catalog row from Admin API (qty / price / status).
     *
     * @return array{success: bool, message: string}
     */
    public function refreshSkuFromPls(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is empty.'];
        }

        $variant = $this->catalogVariantForSku($sku);
        if (! $variant || trim((string) ($variant->shopify_variant_id ?? '')) === '') {
            return ['success' => false, 'message' => 'SKU is not in the PLS catalog. Sync catalog first.'];
        }

        $variantId = preg_replace('/\D+/', '', (string) $variant->shopify_variant_id);
        $response = $this->request('GET', "/variants/{$variantId}.json");
        if (! $response['ok']) {
            return ['success' => false, 'message' => $response['message']];
        }

        $data = $response['json']['variant'] ?? [];
        $qty = isset($data['inventory_quantity']) ? (int) $data['inventory_quantity'] : null;
        $price = isset($data['price']) ? (float) $data['price'] : null;
        $productId = $data['product_id'] ?? $variant->shopify_product_id ?? null;

        if (Schema::hasTable('shopify_catalog_variants')) {
            $update = ['updated_at' => now()];
            if ($qty !== null) {
                $update['inventory_quantity'] = $qty;
            }
            if ($price !== null) {
                $update['price'] = $price;
            }
            DB::table('shopify_catalog_variants')
                ->where('store', 'pls')
                ->where('id', $variant->id)
                ->update($update);
        }

        if ($productId) {
            $prod = $this->request('GET', "/products/{$productId}.json", ['fields' => 'id,status,title']);
            if ($prod['ok'] && Schema::hasTable('shopify_catalog_products')) {
                $p = $prod['json']['product'] ?? [];
                $patch = ['updated_at' => now()];
                if (isset($p['status'])) {
                    $patch['status'] = (string) $p['status'];
                }
                if (isset($p['title'])) {
                    $patch['title'] = (string) $p['title'];
                }
                DB::table('shopify_catalog_products')
                    ->where('store', 'pls')
                    ->where('shopify_id', $productId)
                    ->update($patch);
            }
        }

        app(PlsLiveListingsService::class)->clearCache();

        return [
            'success' => true,
            'message' => 'Pulled live PLS listing (qty '.($qty !== null ? $qty : '—').').',
        ];
    }

    /**
     * Live 5Core (main) Shopify inventory. PLS store qty is never used as the source.
     *
     * @param  array<int, string>  $skus
     * @return array<string, int>
     */
    protected function fetchLive5CoreQuantities(array $skus): array
    {
        $live = [];
        try {
            $live = $this->shopifyApi->getInventoryQuantitiesBySku($skus);
        } catch (\Throwable $e) {
            Log::warning('PlsInventorySyncService: 5Core Shopify fetch failed', ['error' => $e->getMessage()]);
            $live = [];
        }

        $local = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        foreach ($skus as $sku) {
            if ($this->resolve5CoreQty($live, $sku) !== null) {
                continue;
            }
            $qty = $this->resolve5CoreQty($local, $sku);
            if ($qty === null) {
                continue;
            }
            $live[strtoupper(trim($sku))] = $qty;
        }

        return $live;
    }

    /**
     * @param  array<string, int>  $map
     */
    protected function resolve5CoreQty(array $map, string $sku): ?int
    {
        return MarketplaceListingStockResolver::qtyFromMap($map, $sku);
    }

    /**
     * @return array{success: bool, message: string, qty?: int}
     */
    protected function pushSku(string $sku, int $qty): array
    {
        $variant = $this->catalogVariantForSku($sku);
        if (! $variant) {
            return ['success' => false, 'message' => 'Not listed on PLS'];
        }

        $variantId = preg_replace('/\D+/', '', (string) ($variant->shopify_variant_id ?? ''));
        if ($variantId === '') {
            return ['success' => false, 'message' => 'Missing PLS variant id'];
        }

        $variantRes = $this->request('GET', "/variants/{$variantId}.json");
        if (! $variantRes['ok']) {
            return ['success' => false, 'message' => $variantRes['message']];
        }

        $v = $variantRes['json']['variant'] ?? [];
        $inventoryItemId = isset($v['inventory_item_id']) ? (int) $v['inventory_item_id'] : 0;
        if ($inventoryItemId <= 0) {
            return ['success' => false, 'message' => 'No inventory_item_id on PLS variant'];
        }

        if (strtolower(trim((string) ($v['inventory_management'] ?? ''))) !== 'shopify') {
            $track = $this->request('PUT', '/inventory_items/'.$inventoryItemId.'.json', [
                'inventory_item' => [
                    'id' => $inventoryItemId,
                    'tracked' => true,
                ],
            ]);
            if (! $track['ok']) {
                return ['success' => false, 'message' => 'Could not enable inventory tracking: '.$track['message']];
            }
        }

        $levels = $this->fetchInventoryLevels($inventoryItemId);
        $primaryLoc = $this->pickLocationId($levels) ?? $this->primaryLocationId();
        if (! $primaryLoc) {
            return ['success' => false, 'message' => 'No PLS inventory location'];
        }

        if ($levels === []) {
            $this->request('POST', '/inventory_levels/connect.json', [
                'location_id' => $primaryLoc,
                'inventory_item_id' => $inventoryItemId,
            ]);
            $levels = $this->fetchInventoryLevels($inventoryItemId);
        }

        $set = $this->setLevel($inventoryItemId, $primaryLoc, $qty);
        if (! $set['ok']) {
            return ['success' => false, 'message' => $set['message']];
        }

        foreach ($levels as $level) {
            $loc = (int) ($level['location_id'] ?? 0);
            if ($loc <= 0 || $loc === $primaryLoc) {
                continue;
            }
            $zero = $this->setLevel($inventoryItemId, $loc, 0);
            if (! $zero['ok']) {
                return ['success' => false, 'message' => 'Could not zero extra PLS location '.$loc.': '.$zero['message']];
            }
        }

        $levels = $this->fetchInventoryLevels($inventoryItemId);
        foreach ($levels as $level) {
            $loc = (int) ($level['location_id'] ?? 0);
            $avail = (int) ($level['available'] ?? 0);
            if ($loc <= 0 || $loc === $primaryLoc || $avail === 0) {
                continue;
            }
            $this->setLevel($inventoryItemId, $loc, 0);
        }

        $primarySet = $this->setLevel($inventoryItemId, $primaryLoc, $qty);
        if (! $primarySet['ok']) {
            return ['success' => false, 'message' => $primarySet['message']];
        }

        $actual = $this->readVariantQuantity($variantId);
        if ($actual === null) {
            $actual = $qty;
        }
        if ($actual !== $qty) {
            return [
                'success' => false,
                'message' => 'PLS still reports '.$actual.' after set (wanted '.$qty.'). Check locations.',
            ];
        }

        if (Schema::hasTable('shopify_catalog_variants')) {
            DB::table('shopify_catalog_variants')
                ->where('store', 'pls')
                ->where('id', $variant->id)
                ->update([
                    'inventory_quantity' => $actual,
                    'updated_at' => now(),
                ]);
        }

        return ['success' => true, 'message' => 'Updated', 'qty' => $actual];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchInventoryLevels(int $inventoryItemId): array
    {
        $res = $this->request('GET', '/inventory_levels.json', [
            'inventory_item_ids' => $inventoryItemId,
        ]);
        if ($res['ok'] && is_array($res['json']['inventory_levels'] ?? null)) {
            return $res['json']['inventory_levels'];
        }

        $nested = $this->request('GET', '/inventory_items/'.$inventoryItemId.'/inventory_levels.json');
        if ($nested['ok'] && is_array($nested['json']['inventory_levels'] ?? null)) {
            return $nested['json']['inventory_levels'];
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     */
    protected function pickLocationId(array $levels): ?int
    {
        if ($levels === []) {
            return null;
        }
        usort($levels, static function ($a, $b) {
            return ((int) ($b['available'] ?? 0)) <=> ((int) ($a['available'] ?? 0));
        });
        $id = (int) ($levels[0]['location_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    protected function readVariantQuantity(string $variantId): ?int
    {
        $confirm = $this->request('GET', "/variants/{$variantId}.json");
        if ($confirm['ok'] && isset($confirm['json']['variant']['inventory_quantity'])) {
            return (int) $confirm['json']['variant']['inventory_quantity'];
        }

        return null;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function setLevel(int $inventoryItemId, int $locationId, int $qty): array
    {
        $set = $this->request('POST', '/inventory_levels/set.json', [
            'location_id' => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'available' => $qty,
        ]);
        if ($set['ok']) {
            return ['ok' => true, 'message' => 'ok'];
        }

        if (str_contains(strtolower($set['message']), 'not stocked')) {
            $this->request('POST', '/inventory_levels/connect.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
            ]);
            $set = $this->request('POST', '/inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => $qty,
            ]);
        }

        return ['ok' => $set['ok'], 'message' => $set['message']];
    }

    protected function catalogVariantForSku(string $sku): ?object
    {
        if (! Schema::hasTable('shopify_catalog_variants')) {
            return null;
        }

        $upper = strtoupper(trim($sku));
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

        $rows = DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->where(function ($q) use ($sku, $upper, $norm) {
                $q->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                if ($norm !== '' && $norm !== $upper) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(UPPER(TRIM(sku)), '-', ' '), '_', ' ') = ?",
                        [$norm]
                    );
                }
            })
            ->orderByDesc('id')
            ->get();

        foreach ($rows as $row) {
            if (strtoupper(trim((string) $row->sku)) === $upper) {
                return $row;
            }
        }
        foreach ($rows as $row) {
            if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) === $norm) {
                return $row;
            }
        }

        return $rows->first();
    }

    protected function primaryLocationId(): ?int
    {
        $cached = Cache::get(self::LOCATION_CACHE_KEY);
        if (is_numeric($cached) && (int) $cached > 0) {
            return (int) $cached;
        }

        $res = $this->request('GET', '/locations.json');
        if (! $res['ok']) {
            return null;
        }

        $locations = $res['json']['locations'] ?? [];
        $id = null;
        foreach ($locations as $loc) {
            if (! is_array($loc)) {
                continue;
            }
            if (($loc['active'] ?? true) === false) {
                continue;
            }
            $id = isset($loc['id']) ? (int) $loc['id'] : null;
            if ($id) {
                break;
            }
        }

        if ($id) {
            Cache::put(self::LOCATION_CACHE_KEY, $id, now()->addHours(6));
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, json: array, message: string, status: int}
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        $domain = $this->tokenService->getDomain();
        $token = $this->tokenService->getAccessToken();
        if (! $domain || ! $token) {
            return ['ok' => false, 'json' => [], 'message' => 'PLS credentials missing', 'status' => 0];
        }

        $path = '/'.ltrim($path, '/');
        $url = 'https://'.$domain.'/admin/api/'.self::API_VERSION.$path;
        $method = strtoupper($method);

        try {
            $response = null;
            for ($attempt = 1; $attempt <= 6; $attempt++) {
                $http = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->connectTimeout(20);

                if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
                    $http = $http->withoutVerifying();
                }

                if ($method === 'GET') {
                    $response = $http->get($url, $payload);
                } elseif ($method === 'PUT') {
                    $response = $http->put($url, $payload);
                } else {
                    $response = $http->send($method, $url, ['json' => $payload]);
                }

                if ($response->status() === 429) {
                    sleep(max(2, (int) ($response->header('Retry-After') ?: ($attempt * 2))));
                    continue;
                }
                if (in_array($response->status(), [401, 403], true) && $attempt === 1) {
                    $fresh = $this->tokenService->getAccessToken(true);
                    if (is_string($fresh) && $fresh !== '') {
                        $token = $fresh;
                        continue;
                    }
                }
                break;
            }
        } catch (\Throwable $e) {
            Log::warning('PlsInventorySyncService: request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'json' => [], 'message' => $e->getMessage(), 'status' => 0];
        }

        if (! $response) {
            return ['ok' => false, 'json' => [], 'message' => 'Empty PLS API response', 'status' => 0];
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        if (! $response->successful()) {
            $err = $json['errors'] ?? $response->body();
            $message = is_array($err) ? json_encode($err) : (string) $err;
            $message = trim($message) !== '' ? $message : ('HTTP '.$response->status());

            return ['ok' => false, 'json' => $json, 'message' => $message, 'status' => $response->status()];
        }

        return ['ok' => true, 'json' => $json, 'message' => 'ok', 'status' => $response->status()];
    }
}
