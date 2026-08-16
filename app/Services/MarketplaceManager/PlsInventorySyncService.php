<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use App\Services\ShopifyCatalogSyncService;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Push B2C Shopify qty onto the PLS Shopify store (Admin inventory_levels/set).
 */
class PlsInventorySyncService
{
    private const LOCATION_CACHE_KEY = 'mm.pls.primary_location_id';

    private const API_VERSION = '2025-01';

    public function __construct(
        protected ShopifyPlsTokenService $tokenService
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

        $shopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        if ($shopifyQty === []) {
            $shopifyQty = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($skus);
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

            $qty = MarketplaceListingStockResolver::qtyFromMap($shopifyQty, $sku);
            if ($qty === null) {
                $skipped++;
                $errorSamples['No B2C Shopify qty'] = ($errorSamples['No B2C Shopify qty'] ?? 0) + 1;
                continue;
            }

            $result = $this->pushSku($sku, max(0, (int) $qty));
            if ($result['success']) {
                $updated++;
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '') {
                    $overlay[$norm] = (int) ($result['qty'] ?? $qty);
                }
            } else {
                $failed++;
                $msg = $result['message'] ?? 'Push failed';
                $errorSamples[$msg] = ($errorSamples[$msg] ?? 0) + 1;
                Log::warning('PlsInventorySyncService: push failed', [
                    'sku' => $sku,
                    'qty' => $qty,
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
        $classified = $catalog->classifyLinkedInventoryMatch($linkedSkus, $mpStock) ?? [];
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
        $inventoryItemId = $v['inventory_item_id'] ?? null;
        if (! $inventoryItemId) {
            return ['success' => false, 'message' => 'No inventory_item_id on PLS variant'];
        }

        if (strtolower(trim((string) ($v['inventory_management'] ?? ''))) !== 'shopify') {
            $track = $this->request('PUT', '/inventory_items/'.$inventoryItemId.'.json', [
                'inventory_item' => [
                    'id' => (int) $inventoryItemId,
                    'tracked' => true,
                ],
            ]);
            if (! $track['ok']) {
                return ['success' => false, 'message' => 'Could not enable inventory tracking: '.$track['message']];
            }
        }

        $levelsRes = $this->request('GET', '/inventory_items/'.$inventoryItemId.'/inventory_levels.json');
        $levels = ($levelsRes['ok'] && is_array($levelsRes['json']['inventory_levels'] ?? null))
            ? $levelsRes['json']['inventory_levels']
            : [];
        if ($levels === []) {
            $fallback = $this->request('GET', '/inventory_levels.json', [
                'inventory_item_ids' => $inventoryItemId,
            ]);
            if ($fallback['ok'] && is_array($fallback['json']['inventory_levels'] ?? null)) {
                $levels = $fallback['json']['inventory_levels'];
            }
        }

        if ($levels === []) {
            $locationId = $this->primaryLocationId();
            if (! $locationId) {
                return ['success' => false, 'message' => 'No PLS inventory location'];
            }
            $this->request('POST', '/inventory_levels/connect.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
            ]);
            $set = $this->setLevel((int) $inventoryItemId, $locationId, $qty);
            if (! $set['ok']) {
                return ['success' => false, 'message' => $set['message']];
            }
        } else {
            usort($levels, static function ($a, $b) {
                return ((int) ($b['available'] ?? 0)) <=> ((int) ($a['available'] ?? 0));
            });
            $primaryLoc = (int) ($levels[0]['location_id'] ?? 0);
            if ($primaryLoc <= 0) {
                return ['success' => false, 'message' => 'PLS inventory level has no location'];
            }
            $set = $this->setLevel((int) $inventoryItemId, $primaryLoc, $qty);
            if (! $set['ok']) {
                return ['success' => false, 'message' => $set['message']];
            }
            foreach (array_slice($levels, 1) as $level) {
                $loc = (int) ($level['location_id'] ?? 0);
                if ($loc <= 0 || (int) ($level['available'] ?? 0) === 0) {
                    continue;
                }
                $this->setLevel((int) $inventoryItemId, $loc, 0);
            }
        }

        $confirm = $this->request('GET', "/variants/{$variantId}.json");
        $actual = $qty;
        if ($confirm['ok'] && isset($confirm['json']['variant']['inventory_quantity'])) {
            $actual = (int) $confirm['json']['variant']['inventory_quantity'];
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

        return DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->where(function ($q) use ($sku, $upper, $norm) {
                $q->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
                if ($norm !== '' && $norm !== $upper) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
                }
            })
            ->orderByDesc('id')
            ->first();
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
