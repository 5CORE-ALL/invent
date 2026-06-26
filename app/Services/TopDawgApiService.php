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
