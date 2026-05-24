<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleLivePriceFetcher
{
    public function getApiKey(): ?string
    {
        $key = config('services.serpapi.key');

        return $key ?: '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
    }

    /**
     * Full Google Shopping search: paginated results + optional immersive seller expansion.
     *
     * @param  array{
     *     max_pages?: int,
     *     expand_sellers?: bool,
     *     max_immersive_products?: int,
     *     max_store_pages?: int,
     *     sort_by_price?: bool,
     * }  $options
     * @return array<int, array<string, mixed>>
     */
    public function searchShopping(string $query, int $limit = 40, array $options = []): array
    {
        $options = array_merge([
            'max_pages' => max(1, (int) ceil($limit / 40)),
            'expand_sellers' => true,
            'expand_multiple_only' => true,
            'max_immersive_products' => 12,
            'max_store_pages' => 1,
            'parallel_batch_size' => 8,
            'sort_by_price' => true,
        ], $options);

        $apiKey = $this->getApiKey();
        if (!$apiKey || trim($query) === '') {
            return [];
        }

        try {
            $rawItems = $this->fetchShoppingPages($query, $apiKey, (int) $options['max_pages']);
            $parsed = [];

            foreach ($rawItems as $index => $item) {
                $row = $this->parseShoppingItem($item, $index + 1);
                if ($row) {
                    $parsed[] = $row;
                }
            }

            if ($options['expand_sellers']) {
                $parsed = $this->expandImmersiveSellers(
                    $parsed,
                    $rawItems,
                    $apiKey,
                    (int) $options['max_immersive_products'],
                    (int) $options['max_store_pages'],
                    (bool) $options['expand_multiple_only'],
                    (int) $options['parallel_batch_size']
                );
            }

            $parsed = $this->dedupeResults($parsed);

            if ($options['sort_by_price']) {
                usort($parsed, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));
            }

            if ($limit > 0 && count($parsed) > $limit) {
                $parsed = array_slice($parsed, 0, $limit);
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('GoogleLivePriceFetcher: search failed', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchByProductId(string $productId, ?string $source = null, ?string $searchQuery = null): ?array
    {
        if ($searchQuery) {
            foreach ($this->searchShopping($searchQuery, 80, [
                'max_pages' => 1,
                'expand_sellers' => false,
                'sort_by_price' => false,
            ]) as $item) {
                if (($item['product_id'] ?? null) !== $productId) {
                    continue;
                }
                if ($source && strcasecmp((string) ($item['source'] ?? ''), $source) !== 0) {
                    continue;
                }

                return $item;
            }
        }

        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'google_product',
                'product_id' => $productId,
                'google_domain' => 'google.com',
                'gl' => 'us',
                'hl' => 'en',
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                return null;
            }

            $product = $data['product_results'] ?? null;
            if (!$product || !is_array($product)) {
                return null;
            }

            $price = $this->extractPrice($product);
            if ($price === null) {
                return null;
            }

            return [
                'product_id' => $productId,
                'source' => $source ?: ($product['source'] ?? ($product['seller'] ?? null)),
                'price' => round($price, 2),
                'title' => $product['title'] ?? null,
                'link' => $product['link'] ?? ($product['product_link'] ?? null),
                'image' => $this->extractImage($product),
                'rating' => isset($product['rating']) && is_numeric($product['rating']) ? (float) $product['rating'] : null,
                'reviews' => isset($product['reviews']) && is_numeric($product['reviews']) ? (int) $product['reviews'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GoogleLivePriceFetcher: product fetch failed', [
                'product_id' => $productId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchShoppingPages(string $query, string $apiKey, int $maxPages): array
    {
        $allItems = [];
        $url = 'https://serpapi.com/search';
        $params = [
            'engine' => 'google_shopping',
            'q' => $query,
            'google_domain' => 'google.com',
            'gl' => 'us',
            'hl' => 'en',
            'num' => 100,
            'api_key' => $apiKey,
        ];

        for ($page = 0; $page < $maxPages; $page++) {
            $response = Http::timeout(45)->get($url, $params);
            if (!$response->successful()) {
                break;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                break;
            }

            $allItems = array_merge($allItems, $this->collectShoppingArrays($data));

            if (empty($data['serpapi_pagination']['next'])) {
                break;
            }

            parse_str(parse_url($data['serpapi_pagination']['next'], PHP_URL_QUERY) ?: '', $params);
            $params['api_key'] = $apiKey;
            $url = 'https://serpapi.com/search.json';
        }

        return $allItems;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectShoppingArrays(array $data): array
    {
        $items = [];

        foreach ([
            'shopping_results',
            'inline_shopping_results',
            'featured_shopping_results',
            'related_shopping_results',
        ] as $key) {
            foreach ($data[$key] ?? [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        foreach ($data['categorized_shopping_results'] ?? [] as $category) {
            if (!is_array($category)) {
                continue;
            }
            foreach ($category['shopping_results'] ?? [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Expand headline offers into all sellers shown on Google product page (Walmart, Amazon, eBay, etc.).
     *
     * @param  array<int, array<string, mixed>>  $parsed
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function expandImmersiveSellers(
        array $parsed,
        array $rawItems,
        string $apiKey,
        int $maxProducts,
        int $maxStorePages,
        bool $multipleSourcesOnly = true,
        int $parallelBatchSize = 8
    ): array {
        $expanded = $parsed;
        $candidates = [];
        $seenTokens = [];

        foreach ($rawItems as $item) {
            $token = $item['immersive_product_page_token'] ?? null;
            if (!$token || isset($seenTokens[$token])) {
                continue;
            }

            $hasMultipleSources = !empty($item['multiple_sources']);
            if ($multipleSourcesOnly && !$hasMultipleSources) {
                continue;
            }

            $seenTokens[$token] = true;
            $parent = $this->parseShoppingItem($item, (int) ($item['position'] ?? 0));
            if (!$parent) {
                continue;
            }

            $candidates[] = [
                'token' => $token,
                'parent' => $parent,
                'multiple_sources' => $hasMultipleSources,
                'position' => (int) ($item['position'] ?? 9999),
            ];
        }

        usort($candidates, function ($a, $b) {
            if ($a['multiple_sources'] !== $b['multiple_sources']) {
                return $b['multiple_sources'] <=> $a['multiple_sources'];
            }

            return $a['position'] <=> $b['position'];
        });

        $candidates = array_slice($candidates, 0, max(0, $maxProducts));
        if ($candidates === []) {
            return $expanded;
        }

        foreach (array_chunk($candidates, max(1, $parallelBatchSize)) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $apiKey) {
                foreach ($chunk as $index => $candidate) {
                    $pool->as((string) $index)->timeout(30)->get('https://serpapi.com/search', [
                        'engine' => 'google_immersive_product',
                        'page_token' => $candidate['token'],
                        'api_key' => $apiKey,
                    ]);
                }
            });

            foreach ($chunk as $index => $candidate) {
                $response = $responses[(string) $index] ?? null;
                if (!$response || !$response->successful()) {
                    continue;
                }

                $productResults = $response->json()['product_results'] ?? [];
                foreach ($productResults['stores'] ?? [] as $store) {
                    $storeRow = $this->parseImmersiveStore($store, $candidate['parent']);
                    if ($storeRow) {
                        $expanded[] = $storeRow;
                    }
                }

                if ($maxStorePages > 1 && !empty($productResults['stores_next_page_token'])) {
                    foreach ($this->fetchImmersiveStorePages(
                        $candidate['token'],
                        $productResults['stores_next_page_token'],
                        $apiKey,
                        $maxStorePages - 1
                    ) as $store) {
                        $storeRow = $this->parseImmersiveStore($store, $candidate['parent']);
                        if ($storeRow) {
                            $expanded[] = $storeRow;
                        }
                    }
                }
            }
        }

        return $expanded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchImmersiveStorePages(
        string $pageToken,
        string $nextPageToken,
        string $apiKey,
        int $remainingPages
    ): array {
        $stores = [];
        $cursor = $nextPageToken;

        for ($page = 0; $page < $remainingPages && $cursor; $page++) {
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'google_immersive_product',
                'page_token' => $pageToken,
                'next_page_token' => $cursor,
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                break;
            }

            $productResults = $response->json()['product_results'] ?? [];
            foreach ($productResults['stores'] ?? [] as $store) {
                if (is_array($store)) {
                    $stores[] = $store;
                }
            }

            $cursor = $productResults['stores_next_page_token'] ?? null;
        }

        return $stores;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchImmersiveStores(string $pageToken, string $apiKey, int $maxStorePages): array
    {
        $stores = [];
        $nextPageToken = null;

        for ($page = 0; $page < $maxStorePages; $page++) {
            $params = [
                'engine' => 'google_immersive_product',
                'page_token' => $pageToken,
                'api_key' => $apiKey,
            ];

            if ($nextPageToken) {
                $params['next_page_token'] = $nextPageToken;
            }

            $response = Http::timeout(45)->get('https://serpapi.com/search', $params);
            if (!$response->successful()) {
                break;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                break;
            }

            $productResults = $data['product_results'] ?? [];
            foreach ($productResults['stores'] ?? [] as $store) {
                if (is_array($store)) {
                    $stores[] = $store;
                }
            }

            $nextPageToken = $productResults['stores_next_page_token'] ?? null;
            if (!$nextPageToken) {
                break;
            }
        }

        return $stores;
    }

    /**
     * @param  array<string, mixed>  $store
     * @param  array<string, mixed>  $parent
     * @return array<string, mixed>|null
     */
    private function parseImmersiveStore(array $store, array $parent): ?array
    {
        $price = null;
        if (isset($store['extracted_total']) && is_numeric($store['extracted_total'])) {
            $price = (float) $store['extracted_total'];
        } elseif (isset($store['extracted_price']) && is_numeric($store['extracted_price'])) {
            $price = (float) $store['extracted_price'];
        } else {
            $price = $this->extractPrice($store);
        }

        if ($price === null) {
            return null;
        }

        $source = trim((string) ($store['name'] ?? $store['source'] ?? ''));
        if ($source === '') {
            return null;
        }

        return [
            'product_id' => (string) $parent['product_id'],
            'source' => $source,
            'price' => round($price, 2),
            'title' => $store['title'] ?? $parent['title'] ?? null,
            'link' => $store['link'] ?? ($store['direct_link'] ?? $parent['link'] ?? null),
            'image' => $parent['image'] ?? null,
            'rating' => $parent['rating'] ?? null,
            'reviews' => $parent['reviews'] ?? null,
            'position' => $parent['position'] ?? null,
            'from_immersive' => true,
        ];
    }

  /**
   * @return array<string, mixed>|null
   */
    private function parseShoppingItem(array $item, int $position): ?array
    {
        $productId = $item['product_id'] ?? null;
        $link = $item['link'] ?? ($item['product_link'] ?? ($item['tracking_link'] ?? null));

        if (!$productId && $link) {
            $productId = 'link_' . substr(md5((string) $link), 0, 16);
        }

        if (!$productId) {
            return null;
        }

        $price = $this->extractPrice($item);
        if ($price === null) {
            return null;
        }

        return [
            'product_id' => (string) $productId,
            'source' => $item['source'] ?? ($item['seller'] ?? null),
            'price' => round($price, 2),
            'title' => $item['title'] ?? null,
            'link' => $link,
            'image' => $this->extractImage($item),
            'rating' => isset($item['rating']) && is_numeric($item['rating']) ? (float) $item['rating'] : null,
            'reviews' => isset($item['reviews']) && is_numeric($item['reviews']) ? (int) $item['reviews'] : null,
            'position' => $item['position'] ?? $position,
            'from_immersive' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function dedupeResults(array $results): array
    {
        $unique = [];

        foreach ($results as $row) {
            $source = strtolower(trim((string) ($row['source'] ?? '')));
            $key = ($row['product_id'] ?? '') . '|' . $source;
            if (!isset($unique[$key]) || ($row['price'] ?? PHP_FLOAT_MAX) < ($unique[$key]['price'] ?? PHP_FLOAT_MAX)) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }

    private function extractPrice(array $item): ?float
    {
        if (isset($item['extracted_total']) && is_numeric($item['extracted_total'])) {
            return (float) $item['extracted_total'];
        }

        if (isset($item['extracted_price']) && is_numeric($item['extracted_price'])) {
            return (float) $item['extracted_price'];
        }

        if (isset($item['price']['value'])) {
            return (float) $item['price']['value'];
        }

        if (!empty($item['price']) && is_string($item['price']) && preg_match('/[\d,.]+/', $item['price'], $matches)) {
            return (float) str_replace(',', '', $matches[0]);
        }

        return null;
    }

    private function extractImage(array $item): ?string
    {
        if (!empty($item['thumbnail']) && is_string($item['thumbnail'])) {
            return $item['thumbnail'];
        }

        if (!empty($item['thumbnails'][0]) && is_string($item['thumbnails'][0])) {
            return $item['thumbnails'][0];
        }

        if (!empty($item['image']) && is_string($item['image'])) {
            return $item['image'];
        }

        if (!empty($item['serpapi_thumbnail']) && is_string($item['serpapi_thumbnail'])) {
            return $item['serpapi_thumbnail'];
        }

        return null;
    }
}
