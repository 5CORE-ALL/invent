<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StoreListingApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.store.url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    public function timeout(): int
    {
        $timeout = (int) config('services.store.timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    public function apiKey(): string
    {
        return trim((string) config('services.store.api_key'));
    }

    /**
     * @return array<string, string>
     */
    protected function headers(bool $withApiKey = false): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($withApiKey) {
            $apiKey = $this->apiKey();
            if ($apiKey !== '') {
                $headers['X-Api-Key'] = $apiKey;
                $headers['X-Listing-Api-Key'] = $apiKey;
                $headers['Authorization'] = 'Bearer '.$apiKey;
            }
        }
        $locale = trim((string) config('services.store.locale'));
        if ($locale !== '') {
            $headers['X-Locale'] = $locale;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $locale = trim((string) config('services.store.locale'));
        if ($locale !== '') {
            $query['locale'] = $query['locale'] ?? $locale;
        }

        return $this->send('GET', $path, $query, null, $this->apiKey() !== '');
    }

    /**
     * Write S PRC to the live listing special_price on business5core.com.
     *
     * @param  array<string, mixed>  $extra
     * @return array{success:bool,status:int,path:string,payload:array<string,mixed>,json:?array}
     */
    public function updateListingSpecialPrice(int $storeProductId, string $sku, float $specialPrice, array $extra = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BUSINESS5CORE_API_URL is not configured.');
        }
        if ($this->apiKey() === '') {
            throw new RuntimeException('BUSINESS5CORE_API_KEY is required to push prices.');
        }
        if ($storeProductId <= 0) {
            throw new RuntimeException('Store listing id is required to push price.');
        }

        $specialPrice = round($specialPrice, 2);
        $payload = [
            'sku' => $sku,
            'special_price' => $specialPrice,
            'selling_price' => $specialPrice,
            'special_price_type' => (string) ($extra['special_price_type'] ?? 'fixed'),
        ];
        if (! empty($extra['variant_id'])) {
            $payload['variant_id'] = (int) $extra['variant_id'];
        }
        if (! empty($extra['slug'])) {
            $payload['slug'] = (string) $extra['slug'];
        }

        $method = strtoupper(trim((string) config('services.store.price_update_method', 'PUT'))) ?: 'PUT';
        $template = (string) config('services.store.price_update_path', '/api/listings/prices/{id}');
        $attempts = [
            [$method, str_replace('{id}', (string) $storeProductId, $template)],
            ['PUT', '/api/listings/prices/'.$storeProductId],
            ['PATCH', '/api/listings/prices/'.$storeProductId],
            ['PUT', '/api/listings/'.$storeProductId],
            ['POST', '/api/listings/prices'],
        ];

        $seen = [];
        $last = null;
        foreach ($attempts as [$verb, $path]) {
            $key = $verb.' '.$path;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            try {
                $json = $this->send($verb, $path, [], $payload, true);
                return [
                    'success' => true,
                    'status' => 200,
                    'path' => $path,
                    'payload' => $payload,
                    'json' => $json,
                ];
            } catch (RuntimeException $e) {
                $last = $e;
                if (! str_contains($e->getMessage(), 'HTTP 404')
                    && ! str_contains($e->getMessage(), 'HTTP 405')
                    && ! str_contains($e->getMessage(), 'HTTP 501')) {
                    throw $e;
                }
            }
        }

        throw $last ?: new RuntimeException('Store price update failed.');
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $query = [], ?array $body = null, bool $withApiKey = false): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BUSINESS5CORE_API_URL is not configured.');
        }

        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $pending = Http::withoutVerifying()
            ->timeout($this->timeout())
            ->acceptJson()
            ->withHeaders($this->headers($withApiKey));

        $method = strtoupper($method);
        $response = match ($method) {
            'GET' => $pending->get($url, $query),
            'POST' => $pending->post($url, $body ?? []),
            'PATCH' => $pending->patch($url, $body ?? []),
            default => $pending->put($url, $body ?? []),
        };

        if (! $response->successful()) {
            $body = mb_substr($response->body(), 0, 500);
            Log::error('Store listing API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $body,
            ]);

            $json = $response->json();
            $storeMessage = is_array($json) ? trim((string) ($json['message'] ?? '')) : '';
            if ($response->status() === 401) {
                throw new RuntimeException(
                    'Store API request failed (HTTP 401)'
                    .($storeMessage !== '' ? ': '.$storeMessage : '.')
                    .' Set BUSINESS5CORE_API_KEY in .env to the same value as the store LISTING_API_KEY, then run php artisan config:clear.'
                );
            }
            throw new RuntimeException(
                'Store API request failed (HTTP '.$response->status().')'
                .($storeMessage !== '' ? ': '.$storeMessage : '.')
            );
        }

        $json = $response->json();
        if ($json === null || $json === []) {
            return is_array($json) ? $json : [];
        }
        if (! is_array($json)) {
            throw new RuntimeException('Store API returned invalid JSON.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPricePage(int $page = 1, int $perPage = 500, ?string $sku = null): array
    {
        $query = [
            'page' => max(1, $page),
            'perPage' => min(500, max(1, $perPage)),
        ];
        if ($sku !== null && trim($sku) !== '') {
            $query['sku'] = trim($sku);
        }

        return $this->get('/api/listings/prices', $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllPrices(?string $sku = null, ?callable $onPage = null): array
    {
        $items = [];
        $page = 1;
        $lastPage = 1;

        try {
            do {
                $payload = $this->fetchPricePage($page, 100, $sku);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }

                $lastPage = (int) ($payload['meta']['last_page'] ?? $page);
                if ($onPage) {
                    $onPage($page, $lastPage, count($data), $payload);
                }
                $page++;
            } while ($page <= $lastPage);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $items;
    }

    /**
     * Live catalog: GET /products (sku is often null).
     *
     * @return array<string, mixed>
     */
    public function fetchProductPage(int $page = 1, int $perPage = 100, ?string $sku = null): array
    {
        $query = [
            'page' => max(1, $page),
            'perPage' => min(100, max(1, $perPage)),
        ];
        if ($sku !== null && trim($sku) !== '') {
            $query['sku'] = trim($sku);
        }

        return $this->get('/products', $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllProducts(?string $sku = null, ?callable $onPage = null): array
    {
        $items = [];
        $page = 1;
        $lastPage = 1;

        try {
            do {
                $payload = $this->fetchProductPage($page, 100, $sku);
                [$data, $current, $lastPage] = $this->extractProductPage($payload, $page);
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
                if ($onPage) {
                    $onPage($current, $lastPage, count($data), $payload);
                }
                if ($current >= $lastPage) {
                    break;
                }
                $page = $current + 1;
            } while ($page <= $lastPage);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0:list<array<string, mixed>>,1:int,2:int}
     */
    protected function extractProductPage(array $payload, int $fallbackPage): array
    {
        $pager = is_array($payload['products'] ?? null) ? $payload['products'] : $payload;
        $data = is_array($pager['data'] ?? null) ? $pager['data'] : [];
        $current = (int) ($pager['current_page'] ?? $payload['meta']['current_page'] ?? $fallbackPage);
        $last = (int) ($pager['last_page'] ?? $payload['meta']['last_page'] ?? $current);

        return [$data, max(1, $current), max(1, $last)];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchListings(int $page = 1, int $perPage = 100, ?string $sku = null): array
    {
        $query = [
            'page' => max(1, $page),
            'perPage' => min(100, max(1, $perPage)),
        ];
        if ($sku !== null && trim($sku) !== '') {
            $query['sku'] = trim($sku);
        }

        return $this->get('/api/listings', $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllListings(?string $sku = null, ?callable $onPage = null): array
    {
        $items = [];
        $page = 1;
        $lastPage = 1;

        try {
            do {
                $payload = $this->fetchListings($page, 100, $sku);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }

                $lastPage = (int) ($payload['meta']['last_page'] ?? $page);
                if ($onPage) {
                    $onPage($page, $lastPage, count($data), $payload);
                }
                $page++;
            } while ($page <= $lastPage);
        } catch (RuntimeException $e) {
            if (! str_contains($e->getMessage(), 'HTTP 404')) {
                throw $e;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCatalog(): array
    {
        return $this->get('/api/listings/catalog');
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchListingBySlug(string $slug): array
    {
        return $this->get('/api/listings/'.ltrim($slug, '/'));
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, array<string, mixed>> slug => detail payload
     */
    public function fetchDetailsBySlugs(array $slugs, int $chunkSize = 15): array
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn ($slug) => trim((string) $slug),
            $slugs
        ), static fn ($slug) => $slug !== '')));

        $out = [];
        $headers = $this->headers(false);

        foreach (array_chunk($slugs, max(1, $chunkSize)) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $headers) {
                foreach ($chunk as $slug) {
                    $url = $this->baseUrl().'/api/listings/'.ltrim($slug, '/');
                    $query = [];
                    $locale = trim((string) config('services.store.locale'));
                    if ($locale !== '') {
                        $query['locale'] = $locale;
                    }
                    $pool->as($slug)
                        ->withoutVerifying()
                        ->timeout($this->timeout())
                        ->acceptJson()
                        ->withHeaders($headers)
                        ->get($url, $query);
                }
            });

            foreach ($chunk as $slug) {
                $response = $responses[$slug] ?? null;
                if (! $response || ! method_exists($response, 'successful') || ! $response->successful()) {
                    continue;
                }
                $json = $response->json();
                if (is_array($json)) {
                    $out[$slug] = $json;
                }
            }
        }

        return $out;
    }
}
