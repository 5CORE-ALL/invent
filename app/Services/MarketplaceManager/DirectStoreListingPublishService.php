<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bProduct;
use App\Services\Business5CoreB2bApiService;
use App\Services\DobaApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\ShopifyPlsTokenService;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Listing Manager create / update for PLS (Shopify), Business 5 Core B2B (store sync API) and Doba.
 * Each SKU is its own listing: existing listings are updated, missing ones are created.
 */
class DirectStoreListingPublishService
{
    private const PLS_API_VERSION = '2025-01';

    public static function channelFor(string $key): ?string
    {
        $key = strtolower(trim($key));
        if ($key === 'pls' || $key === 'prolightsounds') {
            return 'pls';
        }
        if (in_array($key, ['b5cb2b', 'business5coreb2b', 'business5core(b2b)'], true)) {
            return 'b5cb2b';
        }
        if ($key === 'doba') {
            return 'doba';
        }

        return null;
    }

    /**
     * Draft overrides apply only to `overrides['sku']`; sibling SKUs use Product Master data
     * and keep their current marketplace price on update.
     *
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $overrides
     * @return array{success: bool, message: string, goods_id?: string, skus?: list<string>}
     */
    public function publishSkus(string $channel, array $skus, array $overrides = []): array
    {
        $channel = self::channelFor($channel) ?? $channel;
        $skus = array_values(array_unique(array_filter(array_map(static fn ($s) => trim((string) $s), $skus))));
        if ($skus === []) {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $ok = [];
        $errors = [];
        $created = 0;
        $updated = 0;
        $lastId = null;
        foreach ($skus as $sku) {
            $item = $this->prepareItem($sku, $overrides);
            try {
                $result = match ($channel) {
                    'pls' => $this->publishPls($item),
                    'b5cb2b' => $this->publishB2b($item),
                    'doba' => $this->publishDoba($item),
                    default => ['success' => false, 'message' => 'Unsupported channel '.$channel.'.'],
                };
            } catch (\Throwable $e) {
                Log::warning('DirectStoreListingPublishService failed', ['channel' => $channel, 'sku' => $sku, 'error' => $e->getMessage()]);
                $result = ['success' => false, 'message' => $e->getMessage()];
            }

            if (! ($result['success'] ?? false)) {
                $errors[] = $sku.': '.trim((string) ($result['message'] ?? 'failed'));
                continue;
            }
            $ok[] = $sku;
            $lastId = $result['id'] ?? $lastId;
            ($result['created'] ?? false) ? $created++ : $updated++;
        }

        $label = ['pls' => 'PLS', 'b5cb2b' => 'Business 5 Core (B2B)', 'doba' => 'Doba'][$channel] ?? $channel;
        if ($ok === []) {
            return ['success' => false, 'message' => $label.': '.implode(' ', $errors)];
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = 'created '.$created;
        }
        if ($updated > 0) {
            $parts[] = 'updated '.$updated;
        }
        $message = $label.': '.implode(', ', $parts).' listing(s).';
        if ($errors !== []) {
            $message .= ' Failed: '.implode(' ', $errors);
        }

        return [
            'success' => true,
            'message' => $message,
            'goods_id' => $lastId !== null ? (string) $lastId : null,
            'skus' => $ok,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{sku: string, primary: bool, title: string, description: string, price: float|null, quantity: int|null, images: list<string>, upc: string, weight_lb: float|null}
     */
    private function prepareItem(string $sku, array $overrides): array
    {
        $primary = strcasecmp(trim((string) ($overrides['sku'] ?? '')), $sku) === 0;
        $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);

        $title = $primary ? trim((string) ($overrides['title'] ?? '')) : '';
        $title = $title !== '' ? $title : trim((string) ($hydrated['title'] ?? ''));

        $description = $primary ? trim((string) ($overrides['description'] ?? '')) : '';
        $description = $description !== '' ? $description : trim((string) ($hydrated['description'] ?? ''));

        $price = null;
        if ($primary && isset($overrides['price']) && is_numeric($overrides['price']) && (float) $overrides['price'] > 0) {
            $price = round((float) $overrides['price'], 2);
        }

        $images = $primary && is_array($overrides['images'] ?? null) ? $overrides['images'] : [];
        if ($images === []) {
            $images = is_array($hydrated['images'] ?? null) ? $hydrated['images'] : [];
        }
        $images = array_values(array_unique(array_filter(
            array_map(static fn ($u) => trim((string) $u), $images),
            static fn (string $u) => preg_match('#^https?://#i', $u) === 1
        )));

        $quantity = ListingManagerAmazonHydrator::shopifyQuantity($sku, true);
        if ($quantity === null && $primary && isset($overrides['quantity']) && is_numeric($overrides['quantity'])) {
            $quantity = (int) $overrides['quantity'];
        }

        $upc = $primary ? trim((string) ($overrides['upc'] ?? '')) : '';
        $upc = $upc !== '' ? $upc : trim((string) ($hydrated['upc'] ?? ''));

        $weight = null;
        $lb = (float) ($primary ? ($overrides['package_weight_lb'] ?? 0) : 0) ?: (float) ($hydrated['package_weight_lb'] ?? 0);
        $oz = (float) ($primary ? ($overrides['package_weight_oz'] ?? 0) : 0) ?: (float) ($hydrated['package_weight_oz'] ?? 0);
        if ($lb + $oz / 16 > 0) {
            $weight = round($lb + $oz / 16, 3);
        }

        return [
            'sku' => $sku,
            'primary' => $primary,
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'create_price' => $price ?? (isset($hydrated['price']) && (float) $hydrated['price'] > 0 ? round((float) $hydrated['price'], 2) : null),
            'quantity' => $quantity,
            'images' => array_slice($images, 0, 10),
            'upc' => $upc,
            'weight_lb' => $weight,
        ];
    }

    // ---------------------------------------------------------------- PLS (Shopify)

    /**
     * @param  array<string, mixed>  $item
     * @return array{success: bool, message: string, id?: string, created?: bool}
     */
    private function publishPls(array $item): array
    {
        $tokens = app(ShopifyPlsTokenService::class);
        $domain = $tokens->getDomain();
        $token = $tokens->getAccessToken();
        if (! $domain || ! $token) {
            return ['success' => false, 'message' => 'Shopify PLS credentials are not configured.'];
        }

        $sku = $item['sku'];
        $existing = $this->plsCatalogIds($sku)
            ?? app(ShopifyPLSApiService::class)->findProductBySkuViaGraphQL($domain, $token, $sku);

        if ($existing) {
            $productId = (string) $existing['product_id'];
            $variantId = preg_replace('/\D+/', '', (string) $existing['variant_id']);

            $product = ['id' => (int) $productId];
            if ($item['title'] !== '') {
                $product['title'] = $item['title'];
            }
            if ($item['description'] !== '') {
                $product['body_html'] = $item['description'];
            }
            if (count($product) > 1) {
                $res = $this->plsRequest('PUT', '/products/'.$productId.'.json', ['product' => $product]);
                if (! $res['ok']) {
                    return ['success' => false, 'message' => 'Product update failed: '.$res['message']];
                }
            }

            $variant = ['id' => (int) $variantId];
            if ($item['price'] !== null) {
                $variant['price'] = number_format((float) $item['price'], 2, '.', '');
            }
            if ($item['upc'] !== '') {
                $variant['barcode'] = $item['upc'];
            }
            if ($item['weight_lb'] !== null) {
                $variant['weight'] = $item['weight_lb'];
                $variant['weight_unit'] = 'lb';
            }
            if (count($variant) > 1) {
                $res = $this->plsRequest('PUT', '/variants/'.$variantId.'.json', ['variant' => $variant]);
                if (! $res['ok']) {
                    return ['success' => false, 'message' => 'Variant update failed: '.$res['message']];
                }
            }

            if ($item['primary'] && $item['images'] !== []) {
                $img = app(ShopifyPLSApiService::class)->updateListingImages($productId, $item['images'], 'replace');
                if (is_array($img) && ! ($img['success'] ?? true)) {
                    Log::warning('PLS listing images not updated', ['sku' => $sku, 'message' => $img['message'] ?? '']);
                }
            }

            $this->savePlsCatalog($productId, $variantId, $sku, $item);
            $this->pushPlsQty($sku);

            return ['success' => true, 'message' => 'Updated', 'id' => $productId, 'created' => false];
        }

        if ($item['title'] === '') {
            return ['success' => false, 'message' => 'Title is required to create a PLS listing.'];
        }
        if ($item['create_price'] === null) {
            return ['success' => false, 'message' => 'Price is required to create a PLS listing.'];
        }

        $variant = [
            'sku' => $sku,
            'price' => number_format((float) $item['create_price'], 2, '.', ''),
            'inventory_management' => 'shopify',
            'inventory_policy' => 'deny',
        ];
        if ($item['upc'] !== '') {
            $variant['barcode'] = $item['upc'];
        }
        if ($item['weight_lb'] !== null) {
            $variant['weight'] = $item['weight_lb'];
            $variant['weight_unit'] = 'lb';
        }

        $res = $this->plsRequest('POST', '/products.json', ['product' => [
            'title' => $item['title'],
            'body_html' => $item['description'],
            'vendor' => (string) (config('listing_manager.default_brand', '5 Core') ?: '5 Core'),
            'status' => 'active',
            'variants' => [$variant],
            'images' => array_map(static fn ($src) => ['src' => $src], $item['images']),
        ]]);
        if (! $res['ok']) {
            return ['success' => false, 'message' => 'Create failed: '.$res['message']];
        }

        $productId = (string) ($res['json']['product']['id'] ?? '');
        $variantId = (string) ($res['json']['product']['variants'][0]['id'] ?? '');
        if ($productId === '' || $variantId === '') {
            return ['success' => false, 'message' => 'Shopify PLS did not return a product id.'];
        }

        $this->savePlsCatalog($productId, $variantId, $sku, $item, true);
        $this->pushPlsQty($sku);

        return ['success' => true, 'message' => 'Created', 'id' => $productId, 'created' => true];
    }

    /**
     * @return array{product_id: string, variant_id: string}|null
     */
    private function plsCatalogIds(string $sku): ?array
    {
        if (! Schema::hasTable('shopify_catalog_variants')) {
            return null;
        }

        $row = DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->orderByDesc('id')
            ->first(['shopify_product_id', 'shopify_variant_id']);
        if (! $row || ! $row->shopify_product_id || ! $row->shopify_variant_id) {
            return null;
        }

        $check = $this->plsRequest('GET', '/variants/'.$row->shopify_variant_id.'.json');
        if (! $check['ok']) {
            return null;
        }

        return [
            'product_id' => (string) ($check['json']['variant']['product_id'] ?? $row->shopify_product_id),
            'variant_id' => (string) $row->shopify_variant_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function savePlsCatalog(string $productId, string $variantId, string $sku, array $item, bool $created = false): void
    {
        if (! Schema::hasTable('shopify_catalog_products') || ! Schema::hasTable('shopify_catalog_variants')) {
            return;
        }

        try {
            $now = now();
            $productPatch = ['updated_at' => $now, 'synced_at' => $now];
            if ($item['title'] !== '') {
                $productPatch['title'] = mb_substr($item['title'], 0, 255);
            }
            if ($created) {
                $productPatch['status'] = 'active';
            }
            $exists = DB::table('shopify_catalog_products')->where('store', 'pls')->where('shopify_id', $productId)->first(['id']);
            if ($exists) {
                DB::table('shopify_catalog_products')->where('id', $exists->id)->update($productPatch);
                $catalogProductId = (int) $exists->id;
            } else {
                $catalogProductId = (int) DB::table('shopify_catalog_products')->insertGetId($productPatch + [
                    'store' => 'pls',
                    'shopify_id' => $productId,
                    'status' => 'active',
                    'created_at' => $now,
                ]);
            }

            $variantPatch = [
                'shopify_catalog_product_id' => $catalogProductId,
                'shopify_product_id' => $productId,
                'sku' => $sku,
                'updated_at' => $now,
                'synced_at' => $now,
            ];
            $price = $item['price'] ?? ($created ? $item['create_price'] : null);
            if ($price !== null) {
                $variantPatch['price'] = $price;
            }
            $existsVariant = DB::table('shopify_catalog_variants')->where('store', 'pls')->where('shopify_variant_id', $variantId)->first(['id']);
            if ($existsVariant) {
                DB::table('shopify_catalog_variants')->where('id', $existsVariant->id)->update($variantPatch);
            } else {
                DB::table('shopify_catalog_variants')->insert($variantPatch + [
                    'store' => 'pls',
                    'shopify_variant_id' => $variantId,
                    'created_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('PLS catalog save after publish failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    private function pushPlsQty(string $sku): void
    {
        try {
            app(PlsInventorySyncService::class)->syncSkusFromShopify([$sku]);
        } catch (\Throwable $e) {
            Log::warning('PLS qty push after publish failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, json: array, message: string}
     */
    private function plsRequest(string $method, string $path, array $payload = []): array
    {
        $tokens = app(ShopifyPlsTokenService::class);
        $domain = $tokens->getDomain();
        $token = $tokens->getAccessToken();
        $url = 'https://'.$domain.'/admin/api/'.self::PLS_API_VERSION.'/'.ltrim($path, '/');

        $response = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $http = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->connectTimeout(20);

            $response = strtoupper($method) === 'GET'
                ? $http->get($url, $payload)
                : $http->send(strtoupper($method), $url, ['json' => $payload]);

            if ($response->status() === 429) {
                sleep(max(2, (int) ($response->header('Retry-After') ?: $attempt * 2)));
                continue;
            }
            if (in_array($response->status(), [401, 403], true) && $attempt === 1) {
                $fresh = $tokens->getAccessToken(true);
                if (is_string($fresh) && $fresh !== '' && $fresh !== $token) {
                    $token = $fresh;
                    continue;
                }
            }
            break;
        }

        $json = $response?->json();
        $json = is_array($json) ? $json : [];
        if (! $response || ! $response->successful()) {
            $err = $json['errors'] ?? ($response ? $response->body() : 'No response');
            $message = is_array($err) ? json_encode($err) : (string) $err;

            return ['ok' => false, 'json' => $json, 'message' => trim($message) !== '' ? $message : 'HTTP '.($response?->status() ?? 0)];
        }

        return ['ok' => true, 'json' => $json, 'message' => 'ok'];
    }

    // ---------------------------------------------------------------- Business 5 Core B2B

    /**
     * @param  array<string, mixed>  $item
     * @return array{success: bool, message: string, id?: string, created?: bool}
     */
    private function publishB2b(array $item): array
    {
        $api = app(Business5CoreB2bApiService::class);
        if (! $api->isConfigured()) {
            return ['success' => false, 'message' => 'Set BUSINESS5CORE_B2B_API_URL and BUSINESS5CORE_B2B_API_KEY.'];
        }

        $sku = $item['sku'];
        $local = Schema::hasTable('b5c_b2b_products')
            ? B5cB2bProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            : null;

        $payload = ['sku' => $sku];
        if ($item['title'] !== '') {
            $payload['name'] = $item['title'];
        }
        if ($item['description'] !== '') {
            $payload['description'] = $item['description'];
        }
        $price = $local ? $item['price'] : $item['create_price'];
        if ($price !== null) {
            $payload['price'] = $price;
        }
        if ($item['quantity'] !== null) {
            $payload['qty'] = max(0, (int) $item['quantity']);
            $payload['manage_stock'] = true;
        }
        if ($item['weight_lb'] !== null) {
            $payload['weight'] = $item['weight_lb'];
        }
        if ($item['images'] !== [] && (! $local || $item['primary'])) {
            $payload['image_urls'] = $item['images'];
        }
        if (! $local) {
            if (! isset($payload['name'])) {
                return ['success' => false, 'message' => 'Title is required to create a B2B listing.'];
            }
            if ($price === null) {
                return ['success' => false, 'message' => 'Price is required to create a B2B listing.'];
            }
            $payload['brand'] = (string) (config('listing_manager.default_brand', '5 Core') ?: '5 Core');
            $payload['is_active'] = true;
        }

        $res = $api->send('POST', '/api/listings', [], $payload);
        $status = (string) ($res['status'] ?? '');
        if (! in_array($status, ['created', 'updated'], true)) {
            return ['success' => false, 'message' => trim((string) ($res['message'] ?? 'B2B store rejected the listing.'))];
        }

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $id = (string) ($data['id'] ?? $local?->listing_id ?? '');
        if (Schema::hasTable('b5c_b2b_products')) {
            try {
                B5cB2bProduct::query()->updateOrCreate(
                    ['sku' => (string) ($data['sku'] ?? $sku)],
                    array_filter([
                        'listing_id' => $id !== '' ? $id : null,
                        'slug' => $data['slug'] ?? null,
                        'title' => $data['name'] ?? $payload['name'] ?? null,
                        'qty' => $data['qty'] ?? $payload['qty'] ?? null,
                        'price' => $data['price'] ?? $payload['price'] ?? null,
                        'special_price' => $data['special_price'] ?? null,
                        'in_stock' => $data['in_stock'] ?? null,
                        'is_active' => $data['is_active'] ?? null,
                        'payload' => $data !== [] ? $data : null,
                    ], static fn ($v) => $v !== null)
                );
            } catch (\Throwable $e) {
                Log::warning('B2B local listing save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
        }

        return ['success' => true, 'message' => ucfirst($status), 'id' => $id, 'created' => $status === 'created'];
    }

    // ---------------------------------------------------------------- Doba

    /**
     * Doba's OpenAPI has no create-goods endpoint, so only existing Doba items can be updated.
     *
     * @param  array<string, mixed>  $item
     * @return array{success: bool, message: string, id?: string, created?: bool}
     */
    private function publishDoba(array $item): array
    {
        $api = app(DobaApiService::class);
        if (! $api->isConfigured()) {
            return ['success' => false, 'message' => 'Doba API credentials are not configured.'];
        }

        $sku = $item['sku'];
        $itemNo = $api->resolveItemNo($sku);
        if (! $itemNo) {
            return [
                'success' => false,
                'message' => 'Not on Doba yet. Doba\'s API cannot create new items: add it once in seller.doba.com, sync Doba, then Save & Publish here to update it.',
            ];
        }

        $done = [];
        $errors = [];
        $record = static function (bool $ok, string $field, string $error = '') use (&$done, &$errors): void {
            if ($ok) {
                $done[] = $field;
            } else {
                $errors[] = $field.($error !== '' ? ' ('.$error.')' : '');
            }
        };

        if ($item['title'] !== '') {
            $record($api->updateTitle($itemNo, $item['title']), 'title');
        }
        if ($item['description'] !== '') {
            $res = $api->updateProductDescription($itemNo, $item['description']);
            $record((bool) ($res['success'] ?? false), 'description', (string) ($res['message'] ?? ''));
        }
        if ($item['primary'] && $item['images'] !== []) {
            $res = $api->updateImages($itemNo, $item['images'], 'replace');
            $record((bool) ($res['success'] ?? false), 'images', (string) ($res['message'] ?? ''));
        }
        if ($item['price'] !== null) {
            $res = $api->updateItemPrice($itemNo, $item['price']);
            $record(empty($res['errors']), 'price', is_string($res['errors'] ?? null) ? $res['errors'] : '');
        }
        try {
            $inv = app(DobaInventorySyncService::class)->syncSkusFromShopify([$sku], null, true);
            if ((int) ($inv['updated'] ?? 0) > 0) {
                $done[] = 'qty';
            }
        } catch (\Throwable $e) {
            $record(false, 'qty', $e->getMessage());
        }

        if ($done === []) {
            return ['success' => false, 'message' => 'Doba update failed: '.implode(', ', $errors ?: ['nothing to update'])];
        }

        return [
            'success' => true,
            'message' => 'Updated '.implode(', ', $done).($errors !== [] ? '; failed '.implode(', ', $errors) : ''),
            'id' => $itemNo,
            'created' => false,
        ];
    }
}
