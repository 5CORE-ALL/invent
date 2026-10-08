<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bProduct;
use App\Models\ShopifyB2BDataView;
use App\Services\Business5CoreB2bApiService;
use App\Services\DobaApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\ShopifyPlsTokenService;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\LmpStdPrice;
use Illuminate\Support\Facades\Cache;
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
        if ($ok !== []) {
            try {
                ListingChannelCounts::refreshChannelOnMissingListingPage($channel);
            } catch (\Throwable $e) {
                Log::warning('Missing Listing refresh after publish failed', ['channel' => $channel, 'error' => $e->getMessage()]);
            }
        }
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
        $descriptionFromDraft = $description !== '';
        $description = $description !== '' ? $description : trim((string) ($hydrated['description'] ?? ''));

        $price = LmpStdPrice::forSku($sku);

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

        $dims = [];
        foreach (['length', 'width', 'height'] as $axis) {
            $v = (float) ($primary ? ($overrides['package_'.$axis] ?? 0) : 0) ?: (float) ($hydrated['package_'.$axis] ?? 0);
            $dims[$axis] = $v > 0 ? round($v, 2) : null;
        }

        return [
            'sku' => $sku,
            'primary' => $primary,
            'title' => $title,
            'description' => $description,
            'description_from_draft' => $descriptionFromDraft,
            'length_in' => $dims['length'],
            'width_in' => $dims['width'],
            'height_in' => $dims['height'],
            'price' => $price,
            'create_price' => $price,
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
            return ['success' => false, 'message' => 'Set Std Prc on LMP Overall before creating a PLS listing.'];
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
     * S PRC saved on /shopify-b2b-pricing (shopifyb2b_data_view.value.SPRICE) — the B2B price of record.
     */
    private function b2bPricingSprice(string $sku): ?float
    {
        if (! Schema::hasTable('shopifyb2b_data_view')) {
            return null;
        }
        $row = ShopifyB2BDataView::query()->where('sku', $sku)->first()
            ?? ShopifyB2BDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (! $row) {
            return null;
        }
        $value = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
        $sprice = $value['SPRICE'] ?? null;

        return is_numeric($sprice) && (float) $sprice > 0 ? round((float) $sprice, 2) : null;
    }

    /**
     * Shopify 5 Core (main store) product for a SKU from the synced catalog.
     *
     * @return array{product_type: string, body_html: string}|null
     */
    private function shopifyMainProduct(string $sku): ?array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return null;
        }
        try {
            $row = DB::table('shopify_catalog_variants as v')
                ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                ->where('v.store', 'main')
                ->whereRaw('UPPER(TRIM(v.sku)) = ?', [strtoupper(trim($sku))])
                ->orderByDesc('p.synced_at')
                ->first(['p.product_type', 'p.body_html']);
        } catch (\Throwable) {
            return null;
        }

        return $row ? ['product_type' => trim((string) $row->product_type), 'body_html' => trim((string) $row->body_html)] : null;
    }

    /**
     * Brands and flattened categories from the B2B store (GET /api/listings/catalog).
     *
     * @return array{brands: list<array{id: int, slug: string, name: string}>, categories: list<array{id: int, slug: string, name: string}>}
     */
    private function b2bCatalog(): array
    {
        return Cache::remember('b5cb2b_catalog_v1', now()->addHours(6), function () {
            $res = app(Business5CoreB2bApiService::class)->get('/api/listings/catalog');
            $flat = [];
            $walk = function (array $nodes) use (&$walk, &$flat): void {
                foreach ($nodes as $node) {
                    if (! is_array($node) || ! isset($node['id'])) {
                        continue;
                    }
                    $flat[] = ['id' => (int) $node['id'], 'slug' => (string) ($node['slug'] ?? ''), 'name' => (string) ($node['name'] ?? '')];
                    foreach (['children', 'items', 'subcategories'] as $key) {
                        if (is_array($node[$key] ?? null)) {
                            $walk($node[$key]);
                        }
                    }
                }
            };
            $walk(is_array($res['categories'] ?? null) ? $res['categories'] : []);
            $brands = [];
            foreach ((array) ($res['brands'] ?? []) as $b) {
                if (is_array($b) && isset($b['id'])) {
                    $brands[] = ['id' => (int) $b['id'], 'slug' => (string) ($b['slug'] ?? ''), 'name' => (string) ($b['name'] ?? '')];
                }
            }

            return ['brands' => $brands, 'categories' => $flat];
        });
    }

    private static function b2bKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    private function b2bBrandId(): ?int
    {
        try {
            foreach ($this->b2bCatalog()['brands'] as $brand) {
                if (self::b2bKey($brand['name']) === '5core' || self::b2bKey($brand['slug']) === '5core') {
                    return $brand['id'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('B2B catalog fetch failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Store category matching the Shopify 5 Core product type (exact, then singular/plural, then contains).
     */
    private function b2bCategoryId(string $productType): ?int
    {
        $want = self::b2bKey($productType);
        if ($want === '') {
            return null;
        }
        try {
            $categories = $this->b2bCatalog()['categories'];
        } catch (\Throwable $e) {
            Log::warning('B2B catalog fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
        $singular = static fn (string $k): string => preg_replace('/(es|s)$/', '', $k) ?? $k;
        foreach ([
            static fn (string $k): bool => $k === $want,
            static fn (string $k): bool => $singular($k) === $singular($want),
            static fn (string $k): bool => strlen($k) >= 4 && (str_contains($want, $k) || str_contains($k, $want)),
        ] as $match) {
            foreach ($categories as $category) {
                foreach ([self::b2bKey($category['name']), self::b2bKey($category['slug'])] as $key) {
                    if ($key !== '' && $match($key)) {
                        return $category['id'];
                    }
                }
            }
        }

        return null;
    }

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

        $shopify = $this->shopifyMainProduct($sku);

        $payload = ['sku' => $sku];
        if ($item['title'] !== '') {
            $payload['name'] = $item['title'];
        }
        $description = $item['description_from_draft'] ? $item['description'] : '';
        if ($description === '') {
            $description = trim(ListingManagerAmazonHydrator::descriptionMaster($sku));
        }
        if ($description === '') {
            $description = trim((string) ($shopify['body_html'] ?? ''));
        }
        if ($description === '') {
            $description = trim(ListingManagerAmazonHydrator::shopifyDescription($sku));
        }
        if ($description === '') {
            $description = $item['description'];
        }
        if ($description !== '') {
            $payload['description'] = $description;
        }
        foreach (['length', 'width', 'height'] as $axis) {
            if ($item[$axis.'_in'] !== null) {
                $payload[$axis] = $item[$axis.'_in'];
            }
        }
        $brandId = $this->b2bBrandId();
        if ($brandId !== null) {
            $payload['brand_id'] = $brandId;
        }
        $categoryId = $this->b2bCategoryId((string) ($shopify['product_type'] ?? ''));
        if ($categoryId !== null) {
            $payload['categories'] = [$categoryId];
        }
        $price = $item['price'] ?? $item['create_price'];
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
                return ['success' => false, 'message' => 'Set Std Prc on LMP Overall before creating a B2B listing for '.$sku.'.'];
            }
            if (! isset($payload['brand_id'])) {
                $payload['brand'] = '5 CORE';
            }
            $payload['is_active'] = true;
        }

        return $this->sendB2bListing($sku, $payload, $local);
    }

    /**
     * Update fields of an existing Business 5 Core B2B listing (upsert by SKU) and refresh the local mirror.
     *
     * @param  array<string, mixed>  $fields  name|description|price|image_urls|bullet_points
     * @return array{success: bool, message: string, id?: string, created?: bool, data?: array<string, mixed>}
     */
    public function updateB2bListing(string $sku, array $fields): array
    {
        $api = app(Business5CoreB2bApiService::class);
        if (! $api->isConfigured()) {
            return ['success' => false, 'message' => 'Set BUSINESS5CORE_B2B_API_URL and BUSINESS5CORE_B2B_API_KEY.'];
        }
        $sku = trim($sku);
        $local = Schema::hasTable('b5c_b2b_products')
            ? B5cB2bProduct::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            : null;

        return $this->sendB2bListing($sku, ['sku' => $sku] + $fields, $local);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, id?: string, created?: bool, data?: array<string, mixed>}
     */
    private function sendB2bListing(string $sku, array $payload, ?B5cB2bProduct $local): array
    {
        $api = app(Business5CoreB2bApiService::class);
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

        return ['success' => true, 'message' => ucfirst($status), 'id' => $id, 'created' => $status === 'created', 'data' => $data];
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
