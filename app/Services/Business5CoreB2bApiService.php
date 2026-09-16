<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Business 5 Core (B2B) Laravel store sync API (docs/sync-api.md).
 * Auth: X-Api-Key
 */
class Business5CoreB2bApiService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.b5cb2b.url'), '/');
    }

    public function apiKey(): string
    {
        return trim((string) config('services.b5cb2b.api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    public function timeout(): int
    {
        $timeout = (int) config('services.b5cb2b.timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->get('/api');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function fetchListings(int $page = 1, int $perPage = 100, array $query = []): array
    {
        return $this->get('/api/listings', array_merge($query, [
            'page' => max(1, $page),
            'perPage' => min(500, max(1, $perPage)),
        ]));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function fetchAllListings(array $query = [], ?callable $onPage = null): array
    {
        return $this->paginate('/api/listings', $query, $onPage);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function fetchInventory(int $page = 1, int $perPage = 100, array $query = []): array
    {
        return $this->get('/api/inventory', array_merge($query, [
            'page' => max(1, $page),
            'perPage' => min(200, max(1, $perPage)),
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllInventoryRows(array $query = []): array
    {
        return $this->paginate('/api/inventory', $query, null, 100, 200);
    }

    /**
     * @param  list<array{sku?: string, id?: int, qty: int, in_stock?: bool, manage_stock?: bool}>  $items
     * @return array<string, mixed>
     */
    public function pushInventory(array $items): array
    {
        if (count($items) === 1) {
            return $this->send('POST', '/api/inventory', [], $items[0]);
        }

        return $this->send('POST', '/api/inventory', [], ['items' => array_values($items)]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function fetchOrders(int $page = 1, int $perPage = 50, array $query = []): array
    {
        return $this->get('/api/orders', array_merge($query, [
            'page' => max(1, $page),
            'perPage' => min(200, max(1, $perPage)),
        ]));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function fetchAllOrders(array $query = [], ?callable $onPage = null): array
    {
        return $this->paginate('/api/orders', $query, $onPage, 50, 200);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchOrder(int $id): array
    {
        $json = $this->get('/api/orders/'.$id);

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateOrder(int $id, array $payload): array
    {
        return $this->send('PATCH', '/api/orders/'.$id, [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function paginate(string $path, array $query, ?callable $onPage, int $perPage = 100, int $maxPerPage = 500): array
    {
        $items = [];
        $page = 1;
        $lastPage = 1;
        do {
            $payload = $this->get($path, array_merge($query, [
                'page' => $page,
                'perPage' => min($maxPerPage, max(1, $perPage)),
            ]));
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            foreach ($data as $row) {
                if (is_array($row)) {
                    $items[] = $row;
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
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query, null);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('BUSINESS5CORE_B2B_API_URL and BUSINESS5CORE_B2B_API_KEY must be set.');
        }

        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $pending = Http::withoutVerifying()
            ->timeout($this->timeout())
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Api-Key' => $this->apiKey(),
            ]);

        $method = strtoupper($method);
        $response = match ($method) {
            'GET' => $pending->get($url, $query),
            'POST' => $pending->post($url, $body ?? []),
            'PATCH' => $pending->patch($url, $body ?? []),
            default => $pending->put($url, $body ?? []),
        };

        if (! $response->successful()) {
            $json = $response->json();
            $message = is_array($json) ? trim((string) ($json['message'] ?? '')) : '';
            Log::warning('B5C B2B API failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
            ]);
            throw new RuntimeException(
                'Business 5 Core B2B API failed (HTTP '.$response->status().')'
                .($message !== '' ? ': '.$message : '.')
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
