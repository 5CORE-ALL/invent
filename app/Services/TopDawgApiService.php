<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TopDawgApiService
{
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
}
