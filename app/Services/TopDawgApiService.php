<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Models\TopDawgProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;

class TopDawgApiService
{
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;

    protected string $baseUrl;

    protected string $token;

    public function __construct()
    {
        // config(key, default) only returns the default when the key is *missing*.
        // env('TOPDAWG_API_TOKEN') returns null when the var is absent, so the value
        // we get back here is null — not ''. Coerce explicitly so the strict
        // `string` property assignment below never blows up with a TypeError.
        $this->baseUrl = rtrim((string) (config('services.topdawg.base_url') ?? 'https://topdawg.com/supplier/api'), '/');
        $this->token = (string) (config('services.topdawg.token') ?? '');
    }

    /**
     * Throws a clear, actionable error when the API token is missing, instead of
     * letting the request fail later with a confusing 401/500 from TopDawg.
     */
    protected function assertConfigured(): void
    {
        if ($this->token === '') {
            throw new \RuntimeException(
                'TopDawg API token is not configured. Add TOPDAWG_API_TOKEN=<your-token> to .env '
                . '(and optionally TOPDAWG_API_BASE_URL), then run `php artisan config:clear`.'
            );
        }
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Fetch all products with pagination.
     * POST /SupplierProduct/list with per_page, page.
     * Loops through all pages and merges results.
     *
     * @param  callable|null  $onPage  Optional callback(page, lastPage, totalSoFar) for progress logging
     * @return array{data: array, total: int}
     */
    public function fetchProducts(?string $updatedSince = null, ?callable $onPage = null): array
    {
        $this->assertConfigured();

        $all = [];
        $page = 1;
        $perPage = 1000;

        do {
            $body = ['per_page' => $perPage, 'page' => $page];
            if ($updatedSince) {
                $body['updated_since'] = $updatedSince;
            }
            $url = $this->baseUrl . '/SupplierProduct/list';
            $response = Http::withHeaders($this->headers())
                ->timeout(60)
                ->post($url, $body);

            Log::debug('TopDawg API response', ['url' => $url, 'page' => $page, 'response' => $response->json()]);

            if (!$response->successful()) {
                Log::warning('TopDawgApiService: products request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            $items = $data['results'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            $all = array_merge($all, $items);

            $pagination = $data['pagination'] ?? [];
            $currentPage = (int) ($pagination['current_page'] ?? $page);
            $lastPage = (int) ($pagination['last_page'] ?? $currentPage);
            $totalFromApi = (int) ($pagination['total'] ?? count($all));

            if ($onPage !== null) {
                $onPage($currentPage, $lastPage, count($all));
            }

            // Stop when we've reached the last page or got no items
            if ($currentPage >= $lastPage || count($items) === 0) {
                break;
            }
            $page = $currentPage + 1;
        } while (true);

        return ['data' => $all, 'total' => count($all)];
    }

    /**
     * Push a price update for one SKU to TopDawg.
     *
     * Confirmed against the live TopDawg API via the `topdawg:test-push-price`
     * probe (see app/Console/Commands/TestTopDawgPushPrice.php):
     *
     *   POST  https://topdawg.com/supplier/api/SupplierProduct/update
     *   Body: { "product_code": "<SELLER_SKU>", "price": <float> }
     *
     *   → 200 { "message": "Product submitted successfully for review.", "code": 200 }
     *
     * IMPORTANT — TopDawg's price-push is asynchronous: a 200 OK means the
     * change was accepted into TopDawg's review queue, not that the price
     * is live on the storefront yet. Their reviewers approve / reject; the
     * approved price then propagates to listings. We have no API hook for
     * the approval — the caller should treat "200 OK" as "queued" and
     * communicate that to the user in the UI.
     *
     * Endpoint / shape / method are still arguments so the probe command can
     * sweep alternatives, but the defaults are now the discovered working
     * combo so callers (the /topdawg-pricing UI, future bulk jobs, etc.)
     * just need `pushPrice($sku, $price)`.
     *
     * Supported body shapes (one of):
     *   - 'pc_sku'        → { product_code: sku,  price }   ← DEFAULT (working)
     *   - 'pc_tdid'       → { product_code: tdid, price }
     *   - 'pc_array_sku'  → { products: [{ product_code: sku,  price }] }
     *   - 'pc_array_tdid' → { products: [{ product_code: tdid, price }] }
     *   - 'flat'          → { sku, price }                  (probe-only, returns 400)
     *   - 'flat_tdid'     → { tdid, price }                 (probe-only, returns 400)
     *   - 'items_array'   → { items:    [{ sku, price }] }  (probe-only, returns 400)
     *   - 'products'      → { products: [{ sku, price }] }  (probe-only, returns 400)
     *   - 'data'          → { data:     [{ sku, price }] }  (probe-only, returns 400)
     *   - 'id_price'      → { id: tdid, price }             (probe-only, returns 400)
     *
     * @return array{ok: bool, status: int, url: string, request: array, response: mixed}
     */
    public function pushPrice(
        string $sku,
        float $price,
        ?string $tdid = null,
        string $endpoint = '/SupplierProduct/update',
        string $bodyShape = 'pc_sku',
        string $method = 'POST',
    ): array {
        $this->assertConfigured();

        $body = $this->buildPushPriceBody($sku, $price, $tdid, $bodyShape);
        $url  = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $request = Http::withHeaders($this->headers())->timeout(45);
        $response = match (strtoupper($method)) {
            'PUT'   => $request->put($url, $body),
            'PATCH' => $request->patch($url, $body),
            default => $request->post($url, $body),
        };

        $payload = $response->json();
        if ($payload === null) {
            // Non-JSON body — keep the raw text so the probe can still display it.
            $payload = $response->body();
        }

        Log::info('TopDawg pushPrice', [
            'sku'        => $sku,
            'price'      => $price,
            'tdid'       => $tdid,
            'method'     => strtoupper($method),
            'url'        => $url,
            'body_shape' => $bodyShape,
            'request'    => $body,
            'status'     => $response->status(),
            'ok'         => $response->successful(),
        ]);

        return [
            'ok'       => $response->successful(),
            'status'   => $response->status(),
            'url'      => $url,
            'request'  => $body,
            'response' => $payload,
        ];
    }

    /**
     * Build the request body for a price push using one of the supported shapes.
     *
     * @return array<string, mixed>
     */
    public function buildPushPriceBody(string $sku, float $price, ?string $tdid, string $shape): array
    {
        return match ($shape) {
            'flat_tdid'     => ['tdid'     => $tdid ?? $sku, 'price' => $price],
            'items_array'   => ['items'    => [['sku' => $sku, 'price' => $price]]],
            'products'      => ['products' => [['sku' => $sku, 'price' => $price]]],
            'data'          => ['data'     => [['sku' => $sku, 'price' => $price]]],
            'id_price'      => ['id'       => $tdid ?? $sku, 'price' => $price],
            // product_code-based shapes — TopDawg's `POST /SupplierProduct/update`
            // validation says "The product code field is required.", so the API
            // keys on a `product_code` field rather than `sku` or `tdid`.
            'pc_sku'        => ['product_code' => $sku,                'price' => $price],
            'pc_tdid'       => ['product_code' => $tdid ?? $sku,       'price' => $price],
            'pc_array_sku'  => ['products' => [['product_code' => $sku,          'price' => $price]]],
            'pc_array_tdid' => ['products' => [['product_code' => $tdid ?? $sku, 'price' => $price]]],
            default         => ['sku'      => $sku, 'price' => $price],
        };
    }

    /**
     * Fetch all orders with pagination.
     * POST /SupplierOrder/list with per_page, page.
     *
     * @return array{data: array, total: int}
     */
    public function fetchOrders(?string $updatedSince = null): array
    {
        $this->assertConfigured();

        $all = [];
        $page = 1;
        $perPage = 100;

        do {
            $body = ['per_page' => $perPage, 'page' => $page];
            if ($updatedSince) {
                $body['updated_since'] = $updatedSince;
            }
            $url = $this->baseUrl . '/SupplierOrder/list';
            $response = Http::withHeaders($this->headers())
                ->timeout(60)
                ->post($url, $body);

            Log::debug('TopDawg API response', ['url' => $url, 'page' => $page, 'response' => $response->json()]);

            if (!$response->successful()) {
                Log::warning('TopDawgApiService: orders request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            $items = $data['orders'] ?? $data['results'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            $all = array_merge($all, $items);

            $pagination = $data['pagination'] ?? [];
            $currentPage = (int) ($pagination['current_page'] ?? $page);
            $lastPage = (int) ($pagination['last_page'] ?? $currentPage);

            if (count($items) < $perPage || $currentPage >= $lastPage) {
                break;
            }
            $page = $currentPage + 1;
        } while (true);

        return ['data' => $all, 'total' => count($all)];
    }

    /**
     * TopDawg `POST /SupplierProduct/update` keys on seller SKU as `product_code`
     * (e.g. "GSTOOL BLK"), not tdid — tdid returns 404 on update.
     */
    protected function resolveProductCode(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $upper = strtoupper($sku);
        $product = TopDawgProduct::query()
            ->where(function ($q) use ($sku, $upper) {
                $q->where('sku', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [$upper]);
            })
            ->first();
        if ($product) {
            $canonical = trim((string) ($product->sku ?? ''));
            if ($canonical !== '') {
                return $canonical;
            }
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $alts = array_values(array_unique(array_filter([
            $norm,
            str_replace('-', ' ', $sku),
            preg_replace('/\s+/', '-', $sku) ?: '',
        ])));
        if ($alts !== []) {
            $product = TopDawgProduct::query()
                ->where(function ($q) use ($alts) {
                    $q->whereIn('sku', $alts);
                    foreach ($alts as $alt) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper((string) $alt)]);
                    }
                })
                ->first();
            if ($product) {
                $canonical = trim((string) ($product->sku ?? ''));
                if ($canonical !== '') {
                    return $canonical;
                }
            }
        }

        return $sku;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{success: bool, message: string}
     */
    protected function pushSupplierProductFields(string $sku, array $fields): array
    {
        $this->assertConfigured();
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'Product code / SKU is required.'];
        }

        $resolved = $this->resolveProductCode($sku);
        $codes = [];
        foreach ([$resolved, $sku, ShopifySku::normalizeSkuForShopifyLookup($sku), str_replace('-', ' ', $sku)] as $code) {
            $code = trim((string) $code);
            if ($code !== '' && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        if ($codes === []) {
            return [
                'success' => false,
                'message' => 'TopDawg product_code not found for SKU (sync topdawg_products or topdawg_metrics first).',
            ];
        }

        $url = $this->baseUrl.'/SupplierProduct/update';
        $lastMessage = 'TopDawg product update failed.';
        foreach ($codes as $productCode) {
            $attempts = [
                array_merge(['product_code' => $productCode], $fields),
                array_merge(['sku' => $productCode], $fields),
            ];
            foreach ($attempts as $body) {
                $response = Http::withHeaders($this->headers())->timeout(45)->post($url, $body);
                if ($response->successful()) {
                    return ['success' => true, 'message' => 'TopDawg product update submitted for review.'];
                }
                $payload = $response->json();
                $lastMessage = is_array($payload)
                    ? (string) ($payload['message'] ?? json_encode($payload))
                    : (string) $response->body();
                Log::warning('TopDawgApiService: product update attempt failed', [
                    'sku' => $sku,
                    'product_code' => $productCode,
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    public function updateTitle(string $sku, string $title): array
    {
        return $this->pushSupplierProductFields($sku, ['title' => $title, 'product_title' => $title]);
    }

    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        return $this->pushSupplierProductFields($identifier, [
            'bullet_points' => $bulletPoints,
            'description_bullets' => $bulletPoints,
        ]);
    }

    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateDescription($identifier, $description);
    }

    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->pushSupplierProductFields($identifier, [
            'description' => $description,
            'long_description' => $description,
        ]);
    }

    /**
     * @param  list<string>  $images
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_values(array_filter(array_map('trim', $images), fn ($v) => $v !== ''));
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        return $this->pushSupplierProductFields($identifier, [
            'image_url' => $images[0],
            'main_image' => $images[0],
        ]);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 1);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'Product code / SKU and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $sku = trim($identifier);
        $primary = $videos[0];
        $attempts = [
            ['product_code' => $sku, 'video_url' => $primary, 'video' => $primary],
            ['product_code' => $sku, 'product_video_url' => $primary],
            ['sku' => $sku, 'video_url' => $primary],
        ];

        $lastMessage = 'TopDawg video update failed.';
        foreach ($attempts as $body) {
            try {
                $this->assertConfigured();
                $url = $this->baseUrl.'/SupplierProduct/update';
                $response = Http::withHeaders($this->headers())->timeout(45)->post($url, $body);
                if ($response->successful()) {
                    $this->saveVideoUrlsToMetricsRow('topdawg_metrics', $sku, $videos);

                    return [
                        'success' => true,
                        'message' => 'TopDawg product video submitted for review.',
                        'normalized_urls' => $videos,
                    ];
                }
                $payload = $response->json();
                $lastMessage = is_array($payload)
                    ? (string) ($payload['message'] ?? $response->body())
                    : (string) $response->body();
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    public function isConfigured(): bool
    {
        return trim($this->token) !== '';
    }

    /**
     * Resolve a single product payload for Marketplace Manager detail views.
     * Prefer local topdawg_products (already synced via link map / product fetch).
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function getProductInfo(string $productId): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Product id is required.'];
        }

        $row = TopDawgProduct::query()
            ->where(function ($q) use ($productId) {
                $q->where('topdawg_listing_id', $productId)
                    ->orWhere('sku', $productId)
                    ->orWhere('tdid', $productId);
            })
            ->orderByDesc('updated_at')
            ->first();

        if (! $row) {
            return ['success' => false, 'message' => 'TopDawg product not found locally. Run Sync link map first.'];
        }

        return [
            'success' => true,
            'data' => [
                'product_id' => (string) ($row->topdawg_listing_id ?: $row->sku),
                'sku' => (string) $row->sku,
                'title' => $row->product_title,
                'product_title' => $row->product_title,
                'product_name' => $row->product_title,
                'price' => $row->price,
                'inventory' => $row->remaining_inventory,
                'remaining_inventory' => $row->remaining_inventory,
                'status' => $row->listing_state,
                'image_src' => $row->image_src,
            ],
        ];
    }

    /**
     * Normalize a product info payload into SKU rows for MM detail/push helpers.
     *
     * @param  array<string, mixed>  $info
     * @return list<array{product_id: string, sku: string, product_name: ?string, price: mixed, inventory: mixed, stock: mixed}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $sku = trim((string) ($info['sku'] ?? $info['product_code'] ?? $info['seller_sku'] ?? ''));
        $pid = trim((string) ($info['product_id'] ?? $info['topdawg_listing_id'] ?? $info['tdid'] ?? $productId));
        if ($sku === '' && $pid !== '') {
            $sku = $pid;
        }
        if ($sku === '') {
            return [];
        }

        $qty = $info['inventory']
            ?? $info['remaining_inventory']
            ?? $info['qty_available']
            ?? $info['quantity']
            ?? null;

        return [[
            'product_id' => $pid !== '' ? $pid : $sku,
            'sku' => $sku,
            'product_name' => $productName ?: ($info['product_title'] ?? $info['product_name'] ?? $info['title'] ?? null),
            'price' => $info['price'] ?? $info['selling_price'] ?? null,
            'inventory' => $qty,
            'stock' => $qty,
        ]];
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Configure TOPDAWG_API_TOKEN in .env.',
            ];
        }

        try {
            $result = $this->fetchProducts();
            $count = count($result['data'] ?? []);

            return [
                'success' => true,
                'message' => "Connected. Product list returned {$count} item(s).",
                'sample_count' => $count,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Push inventory quantities via SupplierProduct/update (qty_available).
     *
     * @param  list<array{sku: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, updated_skus: list<string>, error_message?: string}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        $pushed = 0;
        $failed = 0;
        $errors = [];
        $updatedSkus = [];

        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            if ($sku === '') {
                $failed++;
                continue;
            }

            $result = $this->pushSupplierProductFields($sku, [
                'qty_available' => $qty,
                'quantity' => $qty,
                'remaining_inventory' => $qty,
            ]);

            if (! empty($result['success'])) {
                $pushed++;
                $updatedSkus[] = $sku;
            } else {
                $failed++;
                $errors[] = $sku.': '.($result['message'] ?? 'failed');
            }
        }

        return [
            'pushed' => $pushed,
            'failed' => $failed,
            'updated_skus' => $updatedSkus,
            'error_message' => $errors !== [] ? implode('; ', array_slice($errors, 0, 5)) : null,
        ];
    }
}
