<?php

namespace App\Services;

use App\Models\FaireMetric;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Faire External API v2 client.
 *
 * Docs: https://developers.faire.com/docs
 *
 * Base: https://www.faire.com/external-api/v2  (use www — bare faire.com 301s strip POST bodies)
 * OAuth authorize: https://faire.com/oauth2/authorize
 * Token: POST https://www.faire.com/api/external-api-oauth2/token
 *
 * Auth modes:
 * - oauth: X-FAIRE-OAUTH-ACCESS-TOKEN + X-FAIRE-APP-CREDENTIALS (base64 appId:appSecret)
 * - apiKey: X-FAIRE-ACCESS-TOKEN only
 */
class FaireApiService
{
    protected string $baseUrl;

    protected string $authUrl;

    protected string $tokenUrl;

    protected int $timeout;

    protected int $connectTimeout;

    protected ?string $appId;

    protected ?string $appSecret;

    protected ?string $accessToken;

    protected ?string $refreshToken;

    protected ?string $bearerToken;

    /** @var list<string> */
    protected array $defaultScopes;

    public function __construct()
    {
        $this->appId = filled(config('services.faire.app_id')) ? (string) config('services.faire.app_id') : null;
        $this->appSecret = filled(config('services.faire.app_secret')) ? (string) config('services.faire.app_secret') : null;
        $this->accessToken = $this->firstFilled([
            config('services.faire.access_token'),
            config('services.faire.token'),
        ]);
        $this->refreshToken = filled(config('services.faire.refresh_token'))
            ? (string) config('services.faire.refresh_token')
            : null;
        $this->bearerToken = filled(config('services.faire.bearer_token'))
            ? (string) config('services.faire.bearer_token')
            : null;
        $this->baseUrl = rtrim((string) config('services.faire.base_url', 'https://www.faire.com/external-api/v2'), '/');
        $this->authUrl = rtrim((string) config('services.faire.auth_url', 'https://faire.com/oauth2/authorize'), '/');
        $this->tokenUrl = (string) config('services.faire.token_url', 'https://www.faire.com/api/external-api-oauth2/token');
        $this->timeout = (int) config('services.faire.http_timeout', 60);
        $this->connectTimeout = (int) config('services.faire.connect_timeout', 15);
        $this->defaultScopes = $this->normalizeScopes(config('services.faire.scopes', [
            'READ_PRODUCTS',
            'WRITE_PRODUCTS',
            'READ_ORDERS',
            'WRITE_ORDERS',
            'READ_INVENTORIES',
            'WRITE_INVENTORIES',
            'READ_SHIPMENTS',
            'WRITE_SHIPMENTS',
        ]));
    }

    public function isConfigured(): bool
    {
        return $this->getAccessToken() !== null
            || (filled($this->appId) && filled($this->appSecret));
    }

    public function hasAccessToken(): bool
    {
        return $this->getAccessToken() !== null;
    }

    public function usesOAuth(): bool
    {
        $mode = strtolower(trim((string) config('services.faire.auth_mode', 'api_key')));
        // Brand portal API keys must use X-FAIRE-ACCESS-TOKEN only.
        if (in_array($mode, ['api_key', 'apikey', 'access_token', 'token'], true)) {
            return false;
        }
        if ($mode === 'oauth') {
            return filled($this->appId) && filled($this->appSecret);
        }

        // Auto: prefer API-key header when a token is present without forcing OAuth headers.
        return false;
    }

    public function getSellerId(): ?string
    {
        $id = trim((string) config('services.faire.seller_id', ''));

        return $id !== '' ? $id : null;
    }

    public function getAccessToken(): ?string
    {
        $token = trim((string) ($this->accessToken ?: $this->bearerToken ?: ''));

        return $token !== '' ? $token : null;
    }

    public function redirectUrl(): string
    {
        $redirectUrl = trim((string) config('services.faire.redirect_url', ''));
        if ($redirectUrl !== '' && ! str_contains($redirectUrl, '://')) {
            $redirectUrl = 'http://'.$redirectUrl;
        }

        return $redirectUrl;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status?: int, blocked_by_cloudflare: bool, json: ?array, raw?: string, error: ?string}
     */
    public function getOrders(array $params = []): array
    {
        return $this->request('GET', '/orders', $params);
    }

    /**
     * @return array{ok: bool, status?: int, blocked_by_cloudflare: bool, json: ?array, raw?: string, error: ?string}
     */
    public function getOrder(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return [
                'ok' => false,
                'blocked_by_cloudflare' => false,
                'json' => null,
                'error' => 'Order ID is required.',
            ];
        }

        return $this->request('GET', '/orders/'.$orderId);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, status?: int, blocked_by_cloudflare: bool, json: ?array, raw?: string, error: ?string}
     */
    public function getProducts(array $params = []): array
    {
        return $this->request('GET', '/products', $params);
    }

    /**
     * POST /products
     *
     * @param  array<string, mixed>  $body
     * @return array{success: bool, message?: string, product_id?: string, data?: array<string, mixed>}
     */
    public function createProduct(array $body): array
    {
        $res = $this->request('POST', '/products', [], $body);
        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }
        if (empty($res['ok']) || ! is_array($res['json'])) {
            return ['success' => false, 'message' => $this->errorFromResponse($res)];
        }

        $product = $res['json']['product'] ?? $res['json'];
        $productId = trim((string) (is_array($product) ? ($product['id'] ?? '') : ''));

        return [
            'success' => $productId !== '',
            'message' => $productId !== '' ? 'Product created.' : $this->errorFromResponse($res),
            'product_id' => $productId,
            'data' => is_array($product) ? $product : [],
        ];
    }

    /**
     * POST /products/{id}/variants
     *
     * @param  array<string, mixed>  $body
     * @return array{success: bool, message?: string, variant_id?: string}
     */
    public function createVariant(string $productId, array $body): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Faire product id is required.'];
        }

        $res = $this->request('POST', '/products/'.$productId.'/variants', [], $body);
        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }
        if (empty($res['ok']) || ! is_array($res['json'])) {
            return ['success' => false, 'message' => $this->errorFromResponse($res)];
        }

        $variant = $res['json']['variant'] ?? $res['json']['product_variant'] ?? $res['json'];
        $variantId = trim((string) (is_array($variant) ? ($variant['id'] ?? '') : ''));

        return [
            'success' => true,
            'message' => 'Variant created.',
            'variant_id' => $variantId,
        ];
    }

    /**
     * @return array{success: bool, types?: list<array<string, mixed>>, message?: string}
     */
    public function getTaxonomyTypes(): array
    {
        foreach (['/products/taxonomy-types', '/taxonomy-types'] as $path) {
            $res = $this->request('GET', $path);
            if (empty($res['ok']) || ! is_array($res['json'])) {
                continue;
            }
            $types = $res['json']['taxonomy_types'] ?? $res['json']['types'] ?? $res['json'];
            if (! is_array($types)) {
                continue;
            }

            return ['success' => true, 'types' => array_values($types)];
        }

        return ['success' => false, 'types' => [], 'message' => 'Could not load Faire taxonomy types.'];
    }

    /**
     * @param  array{json?: ?array, error?: ?string, status?: int}  $res
     */
    protected function errorFromResponse(array $res): string
    {
        $json = is_array($res['json'] ?? null) ? $res['json'] : [];
        $message = $json['message'] ?? $json['error'] ?? $json['error_description'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }
        if (isset($json['errors']) && is_array($json['errors'])) {
            $first = reset($json['errors']);
            if (is_string($first) && $first !== '') {
                return $first;
            }
            if (is_array($first)) {
                $nested = $first['message'] ?? $first[0] ?? null;
                if (is_string($nested) && $nested !== '') {
                    return $nested;
                }
            }
        }

        return $res['error'] ?? ('Faire API request failed HTTP '.($res['status'] ?? 0));
    }

    /**
     * PATCH /product-inventory/by-skus
     * Body: { inventories: [{ sku, on_hand_quantity }] }
     *
     * @return array{success: bool, message: string}
     */
    public function updateInventoryBySku(string $sku, int $qty): array
    {
        $sku = trim($sku);
        $qty = max(0, $qty);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        return $this->updateInventoryBySkus([
            ['sku' => $sku, 'on_hand_quantity' => $qty],
        ]);
    }

    /**
     * GET /product-inventory/by-skus?sku=… (repeated)
     *
     * @param  list<string>  $skus
     * @return array<string, array{qty: int, product_variant_id: string, product_id: string}>
     */
    public function getInventoryBySkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ), static fn ($sku) => $sku !== '')));

        $out = [];
        foreach (array_chunk($skus, 40) as $chunk) {
            $res = $this->requestInventoryBySkuQuery($chunk);
            if (empty($res['ok']) || ! is_array($res['json'])) {
                continue;
            }
            foreach ($this->inventoriesFromJson($res['json']) as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $qty = $this->extractOnHandQuantity($row);
                if ($sku === '' || $qty === null) {
                    continue;
                }
                $out[$sku] = [
                    'qty' => $qty,
                    'product_variant_id' => trim((string) ($row['product_variant_id'] ?? $row['id'] ?? '')),
                    'product_id' => trim((string) ($row['product_id'] ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * Read on-hand qty (and variant ids) from GET /products/{id} when the
     * inventory-by-SKU endpoint omits a listing.
     *
     * @return array<string, array{qty: int|null, product_variant_id: string, product_id: string}>
     */
    public function getInventoryByProductId(string $productId): array
    {
        $info = $this->getProductInfo($productId);
        if (empty($info['success']) || ! is_array($info['data'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($this->extractSkuRowsFromProductInfo($info['data'], $productId) as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $out[$sku] = [
                'qty' => isset($row['inventory']) && $row['inventory'] !== null ? (int) $row['inventory'] : null,
                'product_variant_id' => trim((string) ($row['product_variant_id'] ?? '')),
                'product_id' => trim((string) ($row['product_id'] ?? $productId)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{sku: string, on_hand_quantity: int, product_variant_id?: string}>  $inventories
     * @return array{success: bool, message: string, status?: int|null}
     */
    public function updateInventoryBySkus(array $inventories): array
    {
        $rows = [];
        foreach ($inventories as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            $variantId = trim((string) ($row['product_variant_id'] ?? ''));
            if ($sku === '' && $variantId === '') {
                continue;
            }
            $entry = [
                'on_hand_quantity' => max(0, (int) ($row['on_hand_quantity'] ?? $row['quantity'] ?? 0)),
            ];
            if ($sku !== '') {
                $entry['sku'] = $sku;
            }
            if ($variantId !== '') {
                $entry['product_variant_id'] = $variantId;
            }
            $rows[] = $entry;
        }

        if ($rows === []) {
            return ['success' => false, 'message' => 'No inventory rows to update.'];
        }

        $allHaveVariant = collect($rows)->every(static fn ($r) => ! empty($r['product_variant_id']));
        if ($allHaveVariant) {
            $path = '/product-inventory/by-product-variant-ids';
            $payload = array_map(static fn ($r) => [
                'product_variant_id' => $r['product_variant_id'],
                'on_hand_quantity' => $r['on_hand_quantity'],
            ], $rows);
        } else {
            $path = '/product-inventory/by-skus';
            $payload = array_map(static function ($r) {
                $entry = [
                    'sku' => (string) ($r['sku'] ?? ''),
                    'on_hand_quantity' => $r['on_hand_quantity'],
                ];
                if ($entry['sku'] === '') {
                    unset($entry['sku']);
                }

                return $entry;
            }, $rows);
        }

        $res = $this->request('PATCH', $path, [], [
            'inventories' => $payload,
        ]);

        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare.', 'status' => $res['status'] ?? null];
        }

        if (! empty($res['ok'])) {
            $count = count($rows);

            return [
                'success' => true,
                'message' => $count === 1
                    ? 'Inventory '.$rows[0]['on_hand_quantity'].' pushed to Faire for SKU '.($rows[0]['sku'] ?? $rows[0]['product_variant_id'] ?? '').'.'
                    : "Inventory pushed to Faire for {$count} SKU(s).",
                'status' => $res['status'] ?? 200,
            ];
        }

        $status = (int) ($res['status'] ?? 0);
        $detail = trim((string) ($res['error'] ?? ''));
        if ($detail === '') {
            $detail = 'Inventory update failed HTTP '.$status;
        }

        Log::warning('Faire inventory PATCH failed', [
            'path' => $path,
            'status' => $status,
            'error' => $detail,
            'sku_count' => count($rows),
            'first_sku' => $rows[0]['sku'] ?? $rows[0]['product_variant_id'] ?? null,
        ]);

        return [
            'success' => false,
            'message' => $detail,
            'status' => $status !== 0 ? $status : null,
        ];
    }

    /**
     * POST /orders/{id}/shipments
     * Body: { shipments: [{ tracking_code, carrier, maker_cost: { amount_minor, currency }, items: [...] }] }
     *
     * @param  list<array{sku?: string, seller_part_number?: string, quantity?: int, order_item_id?: string, id?: string}>  $items
     * @return array{success: bool, message: string}
     */
    public function shipOrder(string $orderId, string $tracking, string $carrier, array $items, string $currency = 'USD'): array
    {
        $orderId = trim($orderId);
        $tracking = trim($tracking);
        $carrier = strtolower(trim($carrier) ?: 'other');
        $currency = strtoupper(trim($currency) ?: 'USD');

        if ($orderId === '' || $tracking === '') {
            return ['success' => false, 'message' => 'Order ID and tracking number are required.'];
        }

        $shipItems = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['seller_part_number'] ?? ''));
            $orderItemId = trim((string) ($item['order_item_id'] ?? $item['id'] ?? ''));
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            if (($sku === '' || in_array($sku, ['__order__', '__unknown__'], true)) && $orderItemId === '') {
                continue;
            }

            $row = ['quantity' => $qty];
            if ($orderItemId !== '') {
                $row['order_item'] = ['id' => $orderItemId];
            }
            if ($sku !== '' && ! in_array($sku, ['__order__', '__unknown__'], true)) {
                $row['sku'] = $sku;
            }
            $shipItems[] = $row;
        }

        if ($shipItems === []) {
            return ['success' => false, 'message' => 'No shippable line items found for this order.'];
        }

        $shipment = [
            'tracking_code' => $tracking,
            'carrier' => $carrier,
            // v2 money shape (maker_cost_cents is deprecated / often null)
            'maker_cost' => [
                'amount_minor' => 0,
                'currency' => $currency,
            ],
            'items' => $shipItems,
        ];

        $res = $this->request('POST', '/orders/'.$orderId.'/shipments', [], [
            'shipments' => [$shipment],
        ]);

        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare.'];
        }

        if (! empty($res['ok'])) {
            return ['success' => true, 'message' => 'Faire order marked shipped.'];
        }

        return [
            'success' => false,
            'message' => $res['error'] ?? ('Ship order failed HTTP '.($res['status'] ?? 0)),
        ];
    }

    /**
     * Exchange Faire OAuth authorization code for an access token.
     * Docs: POST https://www.faire.com/api/external-api-oauth2/token
     *
     * @param  list<string>  $scopes
     * @return array{success: bool, access_token?: string, refresh_token?: string|null, message: string, raw?: string}
     */
    public function exchangeAuthorizationCode(string $code, array $scopes = []): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['success' => false, 'message' => 'Authorization code is required.'];
        }

        if (! filled($this->appId) || ! filled($this->appSecret)) {
            return ['success' => false, 'message' => 'FAIRE_APP_ID and FAIRE_APP_SECRET are required for OAuth exchange.'];
        }

        $redirectUrl = $this->redirectUrl();
        if ($redirectUrl === '') {
            return ['success' => false, 'message' => 'FAIRE_REDIRECT_URL is required for OAuth exchange.'];
        }

        $scopeList = $scopes !== [] ? $this->normalizeScopes($scopes) : $this->defaultScopes;

        try {
            $payload = [
                'application_token' => $this->appId,
                'application_secret' => $this->appSecret,
                'redirect_url' => $redirectUrl,
                'grant_type' => 'AUTHORIZATION_CODE',
                'authorization_code' => $code,
            ];
            // Faire requires scope as a string[]; omit only when empty so app defaults apply.
            if ($scopeList !== []) {
                $payload['scope'] = $scopeList;
            }

            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($this->tokenUrl, $payload);

            return $this->normalizeTokenResponse($response, 'OAuth token exchange succeeded.');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string|null, message: string, raw?: string}
     */
    public function refreshAccessToken(?string $refreshToken = null): array
    {
        $refreshToken = trim((string) ($refreshToken ?: $this->refreshToken ?: ''));
        if ($refreshToken === '') {
            return ['success' => false, 'message' => 'FAIRE_REFRESH_TOKEN is required.'];
        }
        if (! filled($this->appId) || ! filled($this->appSecret)) {
            return ['success' => false, 'message' => 'FAIRE_APP_ID and FAIRE_APP_SECRET are required to refresh tokens.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($this->tokenUrl, [
                    'application_token' => $this->appId,
                    'application_secret' => $this->appSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'REFRESH_TOKEN',
                ]);

            return $this->normalizeTokenResponse($response, 'OAuth token refresh succeeded.');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build the Faire OAuth authorize URL.
     * Docs: https://faire.com/oauth2/authorize?applicationId=…&scope=…&state=…&redirectUrl=…
     *
     * @param  list<string>  $scopes
     */
    public function authorizationUrl(string $state, array $scopes = []): string
    {
        $redirectUrl = $this->redirectUrl();
        $scopeList = $scopes !== [] ? $this->normalizeScopes($scopes) : $this->defaultScopes;

        $query = [
            'applicationId' => $this->appId,
            'state' => $state,
            'redirectUrl' => $redirectUrl,
        ];
        // Faire splits scope on commas; each token must be a known enum.
        if ($scopeList !== []) {
            $query['scope'] = implode(',', $scopeList);
        }

        return $this->authUrl.'?'.http_build_query($query);
    }

    /**
     * @return array{success: bool, message: string, brand?: array<string, mixed>}
     */
    public function testConnection(): array
    {
        if (! $this->hasAccessToken()) {
            return ['success' => false, 'message' => 'Faire access token missing. Complete OAuth or set FAIRE_ACCESS_TOKEN.'];
        }

        if ($this->usesOAuth() && (! filled($this->appId) || ! filled($this->appSecret))) {
            return ['success' => false, 'message' => 'OAuth mode requires FAIRE_APP_ID and FAIRE_APP_SECRET with the access token.'];
        }

        $res = $this->request('GET', '/brands/profile');
        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare.'];
        }

        if (! empty($res['ok'])) {
            $json = is_array($res['json']) ? $res['json'] : [];
            $brand = $json['brand'] ?? $json;
            $name = is_array($brand)
                ? (string) ($brand['name'] ?? $brand['brand_name'] ?? $brand['id'] ?? $brand['brand_id'] ?? 'brand')
                : 'brand';

            return [
                'success' => true,
                'message' => 'Connected successfully to Faire External API v2 ('.$name.').',
                'brand' => is_array($brand) ? $brand : null,
            ];
        }

        // Fallback probe used by some brand tokens (orders limit min is 10).
        $orders = $this->request('GET', '/orders', ['limit' => 10, 'page' => 1]);
        if (! empty($orders['ok'])) {
            return ['success' => true, 'message' => 'Connected successfully to Faire External API v2 (orders).'];
        }

        return [
            'success' => false,
            'message' => $res['error'] ?? $orders['error'] ?? ('Connection test failed HTTP '.($res['status'] ?? 0)),
        ];
    }

    /**
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public function getProductInfo(string $productId): array
    {
        $productId = trim($productId);
        if ($productId === '') {
            return ['success' => false, 'message' => 'Empty product id'];
        }

        $res = $this->request('GET', '/products/'.$productId);
        if (! empty($res['blocked_by_cloudflare'])) {
            return ['success' => false, 'message' => 'Blocked by Cloudflare'];
        }

        if (empty($res['ok']) || ! is_array($res['json'])) {
            return ['success' => false, 'message' => $res['error'] ?? 'Product fetch failed'];
        }

        $data = $res['json']['product'] ?? $res['json'];

        return [
            'success' => true,
            'data' => is_array($data) ? $data : $res['json'],
        ];
    }

    /**
     * @param  array<string, mixed>  $info
     * @return list<array{sku: string, product_id: string, inventory?: int|null, price?: float|null, product_name?: ?string}>
     */
    public function extractSkuRowsFromProductInfo(array $info, string $productId, ?string $productName = null): array
    {
        $variants = $info['variants'] ?? $info['product_variants'] ?? [];
        if (! is_array($variants)) {
            $variants = [];
        }

        $rows = [];
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $sku = trim((string) ($variant['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $qty = $this->extractOnHandQuantity($variant);
            $priceCents = data_get($variant, 'wholesale_price.amount_minor')
                ?? data_get($variant, 'wholesale_price_cents')
                ?? data_get($variant, 'retail_price.amount_minor')
                ?? data_get($variant, 'retail_price_cents');
            $rows[] = [
                'sku' => $sku,
                'product_id' => (string) ($info['id'] ?? $productId),
                'product_variant_id' => trim((string) ($variant['id'] ?? $variant['product_variant_id'] ?? '')),
                'inventory' => is_numeric($qty) ? (int) $qty : null,
                'price' => is_numeric($priceCents) ? round(((float) $priceCents) / 100, 2) : null,
                'product_name' => $productName ?? ($info['name'] ?? null),
            ];
        }

        if ($rows === []) {
            $sku = trim((string) ($info['sku'] ?? $productId));
            $rows[] = [
                'sku' => $sku !== '' ? $sku : $productId,
                'product_id' => (string) ($info['id'] ?? $productId),
                'inventory' => null,
                'price' => null,
                'product_name' => $productName ?? ($info['name'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  mixed  $item
     * @return list<array{sku: string, product_id: string}>
     */
    public function extractSkuRowsFromListItem($item, bool $fetchDetail = false): array
    {
        if (! is_array($item)) {
            return [];
        }
        $sku = trim((string) ($item['sku'] ?? ''));
        $pid = trim((string) ($item['id'] ?? $item['product_id'] ?? $sku));
        if ($sku === '' && $pid === '') {
            return [];
        }
        if ($sku === '') {
            $sku = $pid;
        }

        return [['sku' => $sku, 'product_id' => $pid !== '' ? $pid : $sku]];
    }

    /**
     * MM adapter: bulk inventory push via PATCH /product-inventory/by-skus.
     * Chunks requests and retries failed SKUs one-by-one (optionally by variant id)
     * so one unknown SKU does not fail the whole batch.
     *
     * @param  list<array{seller_part_number?: string, sku?: string, quantity?: int, on_hand_quantity?: int, product_id?: string, product_variant_id?: string}>  $items
     * @return array{success: bool, pushed: int, failed: int, message?: string, error_message?: string, updated_skus?: list<string>}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        $inventories = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['seller_part_number'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $inventories[] = [
                'sku' => $sku,
                'on_hand_quantity' => max(0, (int) ($item['on_hand_quantity'] ?? $item['quantity'] ?? 0)),
                'product_id' => trim((string) ($item['product_id'] ?? '')),
                'product_variant_id' => trim((string) ($item['product_variant_id'] ?? '')),
            ];
        }

        if ($inventories === []) {
            return ['success' => false, 'pushed' => 0, 'failed' => 0, 'message' => 'No inventory rows.'];
        }

        $pushed = 0;
        $failed = 0;
        $updatedSkus = [];
        $errors = [];

        foreach (array_chunk($inventories, 10) as $chunk) {
            $result = $this->updateInventoryBySkus($chunk);
            if (! empty($result['success'])) {
                $pushed += count($chunk);
                foreach ($chunk as $row) {
                    $updatedSkus[] = $row['sku'];
                }
                continue;
            }

            foreach ($chunk as $row) {
                $one = $this->pushOneInventoryRow($row);
                if (! empty($one['success'])) {
                    $pushed++;
                    $updatedSkus[] = $row['sku'];
                    continue;
                }
                $failed++;
                $err = trim((string) ($one['message'] ?? 'Inventory update failed'));
                if ($err !== '' && count($errors) < 5) {
                    $errors[] = $row['sku'].': '.$err;
                }
            }
        }

        $ok = $pushed > 0;
        $message = $ok
            ? "Inventory pushed to Faire for {$pushed} SKU(s)."
            : ($errors[0] ?? 'Faire inventory update failed.');
        if ($failed > 0 && $errors !== []) {
            $message .= ' '.$failed.' failed ('.implode('; ', $errors).').';
        }

        return [
            'success' => $ok,
            'pushed' => $pushed,
            'failed' => $failed,
            'message' => $message,
            'error_message' => $ok ? null : $message,
            'updated_skus' => $updatedSkus,
        ];
    }

    /**
     * @param  array{sku: string, on_hand_quantity: int, product_id?: string, product_variant_id?: string}  $row
     * @return array{success: bool, message: string}
     */
    protected function pushOneInventoryRow(array $row): array
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $qty = max(0, (int) ($row['on_hand_quantity'] ?? 0));
        $variantId = trim((string) ($row['product_variant_id'] ?? ''));
        $productId = trim((string) ($row['product_id'] ?? ''));

        $bySku = $this->updateInventoryBySkus([
            ['sku' => $sku, 'on_hand_quantity' => $qty],
        ]);
        if (! empty($bySku['success'])) {
            return $bySku;
        }

        if ($variantId === '') {
            $live = $this->getInventoryBySkus([$sku]);
            $variantId = trim((string) ($live[$sku]['product_variant_id'] ?? ''));
        }
        if ($variantId === '' && $productId !== '') {
            $fromProduct = $this->getInventoryByProductId($productId);
            $variantId = trim((string) ($fromProduct[$sku]['product_variant_id'] ?? ''));
            if ($variantId === '') {
                $want = $this->normalizeFaireSku($sku);
                foreach ($fromProduct as $candSku => $row) {
                    if ($this->normalizeFaireSku((string) $candSku) === $want) {
                        $variantId = trim((string) ($row['product_variant_id'] ?? ''));
                        break;
                    }
                }
            }
        }
        if ($variantId === '') {
            return $bySku;
        }

        return $this->updateInventoryBySkus([
            [
                'sku' => $sku,
                'product_variant_id' => $variantId,
                'on_hand_quantity' => $qty,
            ],
        ]);
    }

    protected function findVariantIdForSku(string $productId, string $sku): string
    {
        $info = $this->getProductInfo($productId);
        if (empty($info['success']) || ! is_array($info['data'] ?? null)) {
            return '';
        }
        $variants = $info['data']['variants'] ?? $info['data']['product_variants'] ?? [];
        if (! is_array($variants)) {
            return '';
        }
        $want = $this->normalizeFaireSku($sku);
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            if ($this->normalizeFaireSku((string) ($variant['sku'] ?? '')) === $want) {
                return trim((string) ($variant['id'] ?? $variant['product_variant_id'] ?? ''));
            }
        }

        return '';
    }

    protected function normalizeFaireSku(string $sku): string
    {
        $sku = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $sku);
        $sku = strtoupper(preg_replace('/[-\x{2010}-\x{2015}_]+/u', ' ', $sku) ?? $sku);
        $sku = preg_replace('/\s+/u', ' ', trim($sku)) ?? '';

        return $sku;
    }

    /**
     * @param  list<string>  $skus
     * @return array{ok: bool, status?: int, blocked_by_cloudflare: bool, json: ?array, raw?: string, error: ?string}
     */
    protected function requestInventoryBySkuQuery(array $skus): array
    {
        $pairs = [];
        foreach ($skus as $sku) {
            $pairs[] = 'sku='.rawurlencode($sku);
        }
        $res = $this->request('GET', '/product-inventory/by-skus?'.implode('&', $pairs));
        if (! empty($res['ok'])) {
            return $res;
        }

        $pairs = [];
        foreach ($skus as $sku) {
            $pairs[] = 'skus='.rawurlencode($sku);
        }

        return $this->request('GET', '/product-inventory/by-skus?'.implode('&', $pairs));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    protected function inventoriesFromJson(array $json): array
    {
        $rows = $json['inventories'] ?? $json['inventory'] ?? $json['items'] ?? null;
        if (! is_array($rows) || $rows === []) {
            if (isset($json['sku']) || isset($json['on_hand_quantity']) || isset($json['product_variant_id'])) {
                return [$json];
            }

            return [];
        }
        if (array_is_list($rows)) {
            return $rows;
        }

        $out = [];
        foreach ($rows as $sku => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['sku'])) {
                $row['sku'] = is_string($sku) ? $sku : ($row['sku'] ?? '');
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractOnHandQuantity(array $row): ?int
    {
        $qty = data_get($row, 'on_hand_quantity')
            ?? data_get($row, 'available_quantity')
            ?? data_get($row, 'quantity')
            ?? data_get($row, 'inventory')
            ?? data_get($row, 'inventories.on_hand_quantity')
            ?? data_get($row, 'inventory.on_hand_quantity');

        return is_numeric($qty) ? max(0, (int) $qty) : null;
    }

    /**
     * Push wholesale prices via PATCH /products/{productId}/variants/{variantId}.
     *
     * @param  list<array{seller_part_number?: string, sku?: string, price?: float|int|string, product_id?: string}>  $items
     * @return array{success: bool, pushed: int, failed: int, message?: string, error_message?: string, results?: list<array<string, mixed>>}
     */
    public function updateItemPriceBulk(array $items, string $defaultCountry = 'USA'): array
    {
        $pushed = 0;
        $failed = 0;
        $results = [];
        $country = strtoupper(trim($defaultCountry)) ?: 'USA';

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? $item['seller_part_number'] ?? ''));
            $price = $item['price'] ?? null;
            $productId = trim((string) ($item['product_id'] ?? ''));
            if ($sku === '' || ! is_numeric($price) || (float) $price <= 0) {
                $failed++;
                $results[] = ['sku' => $sku, 'success' => false, 'message' => 'SKU and price > 0 required.'];
                continue;
            }

            $one = $this->updateSkuWholesalePrice($sku, (float) $price, $productId !== '' ? $productId : null, $country);
            if (! empty($one['success'])) {
                $pushed++;
            } else {
                $failed++;
            }
            $results[] = array_merge(['sku' => $sku], $one);
        }

        return [
            'success' => $failed === 0 && $pushed > 0,
            'pushed' => $pushed,
            'failed' => $failed,
            'message' => $pushed > 0
                ? "Pushed wholesale price for {$pushed} SKU(s)".($failed ? "; {$failed} failed" : '').'.'
                : 'No prices pushed.',
            'error_message' => $failed > 0 ? 'One or more Faire price pushes failed.' : null,
            'results' => $results,
        ];
    }

    /**
     * Update a single SKU wholesale price on Faire (External API v2 variant prices).
     *
     * @return array{success: bool, message: string, product_id?: string, variant_id?: string, status?: int, response?: mixed}
     */
    public function updateSkuWholesalePrice(string $sku, float $price, ?string $productId = null, string $country = 'USA'): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! ($price > 0)) {
            return ['success' => false, 'message' => 'Price must be greater than 0.'];
        }
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Faire API credentials missing.'];
        }

        $amountMinor = (int) round($price * 100);
        $country = strtoupper(trim($country)) ?: 'USA';

        // Resolve product/variant so we can keep existing retail_price (API requires it).
        $productId = trim((string) $productId);
        if ($productId === '') {
            try {
                if (Schema::hasTable('faire_metric')) {
                    $want = strtoupper(preg_replace('/\s+/u', ' ', trim(str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $sku))) ?? '');
                    $metric = FaireMetric::query()
                        ->whereNotNull('sku')
                        ->where('sku', '!=', '')
                        ->get()
                        ->first(function ($row) use ($want) {
                            $cand = strtoupper(preg_replace('/\s+/u', ' ', trim(str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', (string) $row->sku))) ?? '');

                            return $cand === $want;
                        });
                    if ($metric && filled($metric->product_id)) {
                        $productId = trim((string) $metric->product_id);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('FaireApiService: faire_metric lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
        }

        $variant = null;
        $variantId = '';
        if ($productId !== '') {
            $info = $this->getProductInfo($productId);
            if (! empty($info['success']) && is_array($info['data'] ?? null)) {
                $variants = $info['data']['variants'] ?? $info['data']['product_variants'] ?? [];
                if (! is_array($variants)) {
                    $variants = [];
                }
                $normalize = static function (string $v): string {
                    $v = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $v);

                    return strtoupper(preg_replace('/\s+/u', ' ', trim($v)) ?? '');
                };
                $want = $normalize($sku);
                foreach ($variants as $cand) {
                    if (! is_array($cand)) {
                        continue;
                    }
                    if ($normalize((string) ($cand['sku'] ?? '')) === $want) {
                        $variant = $cand;
                        break;
                    }
                }
                if ($variant) {
                    $variantId = trim((string) ($variant['id'] ?? $variant['product_variant_id'] ?? ''));
                }
            }
        }

        // Faire requires prices[0].retail_price — keep existing retail when possible, else 2× wholesale.
        $retailMinor = $this->extractVariantRetailMinor($variant);
        if (! ($retailMinor > 0)) {
            $retailMinor = max($amountMinor, (int) round($amountMinor * 2));
        }
        if ($retailMinor < $amountMinor) {
            $retailMinor = $amountMinor;
        }

        $wholesaleMoney = ['amount_minor' => $amountMinor, 'currency' => 'USD'];
        $retailMoney = ['amount_minor' => $retailMinor, 'currency' => 'USD'];
        $priceEntryBase = [
            'geo_constraint' => ['country' => $country],
            'wholesale_price' => $wholesaleMoney,
            'retail_price' => $retailMoney,
        ];

        $attempts = [
            [
                'path' => '/product-variant-prices/by-skus',
                'body' => [
                    'prices' => [array_merge(['sku' => $sku], $priceEntryBase)],
                ],
            ],
        ];
        if ($variantId !== '') {
            $attempts[] = [
                'path' => '/product-variant-prices/by-product-variant-ids',
                'body' => [
                    'prices' => [array_merge(['product_variant_id' => $variantId], $priceEntryBase)],
                ],
            ];
        }
        if ($productId !== '' && $variantId !== '') {
            $attempts[] = [
                'path' => '/products/'.$productId.'/variants/'.$variantId,
                'body' => [
                    'prices' => [$priceEntryBase],
                    'wholesale_price_cents' => $amountMinor,
                    'retail_price_cents' => $retailMinor,
                ],
            ];
        }

        if ($attempts === []) {
            return [
                'success' => false,
                'message' => 'Faire product/variant not found for SKU. Sync listings from Faire API first.',
            ];
        }

        $last = null;
        foreach ($attempts as $attempt) {
            $res = $this->request('PATCH', $attempt['path'], [], $attempt['body']);
            $last = $res;
            if (! empty($res['blocked_by_cloudflare'])) {
                return [
                    'success' => false,
                    'message' => 'Blocked by Cloudflare.',
                    'product_id' => $productId !== '' ? $productId : null,
                    'variant_id' => $variantId !== '' ? $variantId : null,
                ];
            }
            if (! empty($res['ok'])) {
                return [
                    'success' => true,
                    'message' => 'Wholesale price pushed to Faire.',
                    'product_id' => $productId !== '' ? $productId : null,
                    'variant_id' => $variantId !== '' ? $variantId : null,
                    'endpoint' => $attempt['path'],
                    'status' => $res['status'] ?? 200,
                    'response' => $res['json'] ?? null,
                ];
            }
        }

        return [
            'success' => false,
            'message' => $last['error'] ?? ('Faire price update failed HTTP '.($last['status'] ?? 0)),
            'product_id' => $productId !== '' ? $productId : null,
            'variant_id' => $variantId !== '' ? $variantId : null,
            'status' => $last['status'] ?? null,
            'response' => $last['json'] ?? $last['raw'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $variant
     */
    protected function extractVariantRetailMinor(?array $variant): int
    {
        if (! is_array($variant)) {
            return 0;
        }

        $direct = data_get($variant, 'retail_price.amount_minor')
            ?? data_get($variant, 'retail_price_cents');
        if (is_numeric($direct) && (float) $direct > 0) {
            return (int) round((float) $direct);
        }

        // prices[] may be geo-scoped
        $prices = $variant['prices'] ?? null;
        if (is_array($prices)) {
            foreach ($prices as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $minor = data_get($p, 'retail_price.amount_minor')
                    ?? data_get($p, 'retail_price_cents');
                if (is_numeric($minor) && (float) $minor > 0) {
                    return (int) round((float) $minor);
                }
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array{ok: bool, status?: int, blocked_by_cloudflare: bool, json: ?array, raw?: string, error: ?string}
     */
    protected function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl.'/'.ltrim($path, '/');
        $attempt = 0;
        $maxAttempts = 4;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $http = Http::withHeaders($this->authHeaders())
                    ->acceptJson()
                    ->timeout($this->timeout)
                    ->connectTimeout($this->connectTimeout);

                $options = [];
                if ($query !== []) {
                    $options['query'] = $query;
                }
                if ($body !== null) {
                    $options['json'] = $body;
                }

                $response = $http->send($method, $url, $options);

                if ($response->status() === 429 && $attempt < $maxAttempts) {
                    $retryAfter = (int) ($response->header('Retry-After') ?: 1);
                    usleep(min(max($retryAfter, 1), 10) * 1_000_000);

                    continue;
                }

                return $this->normalize($response, $method);
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    Log::error('Faire API request failed', ['url' => $url, 'error' => $e->getMessage()]);

                    return [
                        'ok' => false,
                        'status' => 0,
                        'blocked_by_cloudflare' => false,
                        'json' => null,
                        'raw' => '',
                        'error' => $e->getMessage(),
                    ];
                }
                usleep(min(500 * (2 ** ($attempt - 1)), 8000) * 1000);
            }
        }

        return [
            'ok' => false,
            'status' => 0,
            'blocked_by_cloudflare' => false,
            'json' => null,
            'raw' => '',
            'error' => 'Max retries exceeded.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $token = $this->getAccessToken();
        if ($token === null) {
            return $headers;
        }

        // OAuth app: both headers required together (developers.faire.com/docs).
        if ($this->usesOAuth()) {
            $headers['X-FAIRE-OAUTH-ACCESS-TOKEN'] = $token;
            $headers['X-FAIRE-APP-CREDENTIALS'] = base64_encode($this->appId.':'.$this->appSecret);

            return $headers;
        }

        // Single-brand API key mode.
        $headers['X-FAIRE-ACCESS-TOKEN'] = $token;

        return $headers;
    }

    /**
     * @return array{ok: bool, status: int, blocked_by_cloudflare: bool, json: ?array, raw: string, error: ?string}
     */
    protected function normalize(Response $response, string $method = 'GET'): array
    {
        $status = $response->status();
        $raw = $response->body();
        $json = null;

        $isCloudflare = $response->header('cf-mitigated') !== ''
            || (str_contains((string) $response->header('server'), 'cloudflare') && str_contains($raw, 'CAPTCHA'));

        if (! $isCloudflare) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        $error = null;
        if (! $response->successful()) {
            if (is_array($json)) {
                $error = (string) (
                    $json['message']
                    ?? $json['error_description']
                    ?? $json['error']
                    ?? (is_array($json['errors'] ?? null)
                        ? collect($json['errors'])->map(fn ($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e)->implode('; ')
                        : null)
                    ?? mb_substr($raw, 0, 300)
                );
            } else {
                $error = mb_substr($raw, 0, 300);
            }
        }

        $mutating = in_array(strtoupper($method), ['PATCH', 'PUT', 'POST', 'DELETE'], true);
        $emptyBody = trim((string) $raw) === '';

        return [
            'ok' => $response->successful() && ($json !== null || $status === 204 || ($mutating && $emptyBody)),
            'status' => $status,
            'blocked_by_cloudflare' => $isCloudflare,
            'json' => $json,
            'raw' => $raw,
            'error' => $error,
        ];
    }

    /**
     * @return array{success: bool, access_token?: string, refresh_token?: string|null, message: string, raw?: string}
     */
    protected function normalizeTokenResponse(Response $response, string $successMessage): array
    {
        $json = $response->json();
        if ($response->successful() && is_array($json) && ! empty($json['access_token'])) {
            $this->accessToken = (string) $json['access_token'];
            if (! empty($json['refresh_token'])) {
                $this->refreshToken = (string) $json['refresh_token'];
            }

            return [
                'success' => true,
                'access_token' => (string) $json['access_token'],
                'refresh_token' => isset($json['refresh_token']) ? (string) $json['refresh_token'] : null,
                'message' => $successMessage,
                'raw' => mb_substr($response->body(), 0, 500),
            ];
        }

        return [
            'success' => false,
            'message' => is_array($json)
                ? (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? json_encode($json))
                : ('HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300)),
            'raw' => mb_substr($response->body(), 0, 500),
        ];
    }

    /**
     * @param  mixed  $scopes
     * @return list<string>
     */
    protected function normalizeScopes($scopes): array
    {
        if (is_string($scopes)) {
            $scopes = preg_split('/[\s,]+/', $scopes) ?: [];
        }
        if (! is_array($scopes)) {
            return [];
        }

        $out = [];
        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope !== '') {
                $out[] = $scope;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<mixed>  $values
     */
    protected function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
