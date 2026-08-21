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

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('STORE_API_URL is not configured.');
        }

        $headers = ['Accept' => 'application/json'];
        $apiKey = trim((string) config('services.store.api_key'));
        if ($apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        $locale = trim((string) config('services.store.locale'));
        if ($locale !== '') {
            $headers['X-Locale'] = $locale;
            $query['locale'] = $query['locale'] ?? $locale;
        }

        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $response = Http::withoutVerifying()
            ->timeout(60)
            ->acceptJson()
            ->withHeaders($headers)
            ->get($url, $query);

        if (! $response->successful()) {
            Log::error('Store listing API request failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('Store API request failed (HTTP '.$response->status().').');
        }

        $json = $response->json();
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

        do {
            $payload = $this->fetchPricePage($page, 500, $sku);
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

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchListings(int $page = 1, int $perPage = 30): array
    {
        return $this->get('/api/listings', [
            'page' => max(1, $page),
            'perPage' => min(100, max(1, $perPage)),
        ]);
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
}
