<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonLivePriceFetcher
{
    public function getApiKey(): ?string
    {
        $key = config('services.serpapi.key');

        return $key ?: null;
    }

    public function resolveAmazonDomain(?string $marketplace = null): string
    {
        $marketplace = strtolower(trim((string) $marketplace));

        return match ($marketplace) {
            'amazon.co.uk', 'amazon_uk', 'amazon-uk' => 'amazon.co.uk',
            'amazon.ca', 'amazon_ca', 'amazon-ca' => 'amazon.ca',
            'amazon.de', 'amazon_de', 'amazon-de' => 'amazon.de',
            default => 'amazon.com',
        };
    }

    public function extractAsinFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/\/(?:dp|gp\/product|product)\/([A-Z0-9]{10})/i', $url, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    public function resolveAsin(?string $productLink, ?string $storedAsin = null): ?string
    {
        $fromLink = $this->extractAsinFromUrl($productLink);
        if ($fromLink) {
            return $fromLink;
        }

        $storedAsin = strtoupper(trim((string) $storedAsin));
        if ($storedAsin !== '' && preg_match('/^[A-Z0-9]{10}$/', $storedAsin)) {
            return $storedAsin;
        }

        return null;
    }

    /**
     * @return array{
     *     asin: string,
     *     price: float,
     *     title: ?string,
     *     link: ?string,
     *     image: ?string,
     *     rating: ?float,
     *     reviews: ?int,
     *     extracted_old_price: ?float,
     *     delivery: ?array,
     *     seller_name: ?string,
     *     stock: ?string,
     *     stock_quantity: ?int
     * }|null
     */
    public function fetchByAsin(string $asin, ?string $marketplace = null): ?array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return null;
        }

        $asin = strtoupper(trim($asin));
        $amazonDomain = $this->resolveAmazonDomain($marketplace);

        try {
            $response = Http::timeout(15)->get('https://serpapi.com/search', [
                'engine' => 'amazon_product',
                'amazon_domain' => $amazonDomain,
                'asin' => $asin,
                'api_key' => $apiKey,
            ]);

            return $this->parseProductResponse($response, $asin, $amazonDomain);
        } catch (\Throwable $e) {
            Log::warning('AmazonLivePriceFetcher: fetch failed', [
                'asin' => $asin,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch many ASINs in parallel (Http::pool), keyed by ASIN.
     * Duplicate ASINs are requested once. Use for LMP Pull so N competitors
     * finish in ~1–2 SerpApi round-trips instead of N sequential calls.
     *
     * @param  array<int, array{asin: string, marketplace?: string|null}>  $requests
     * @return array<string, array|null>
     */
    public function fetchManyByAsin(array $requests, int $parallelBatchSize = 8): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey || $requests === []) {
            return [];
        }

        // Dedupe by ASIN (first marketplace wins)
        $unique = [];
        foreach ($requests as $req) {
            $asin = strtoupper(trim((string) ($req['asin'] ?? '')));
            if ($asin === '' || isset($unique[$asin])) {
                continue;
            }
            $unique[$asin] = [
                'asin' => $asin,
                'marketplace' => $req['marketplace'] ?? null,
                'domain' => $this->resolveAmazonDomain($req['marketplace'] ?? null),
            ];
        }

        $results = [];
        $chunks = array_chunk(array_values($unique), max(1, $parallelBatchSize));

        foreach ($chunks as $chunk) {
            try {
                $responses = Http::pool(function ($pool) use ($chunk, $apiKey) {
                    foreach ($chunk as $item) {
                        $pool->as($item['asin'])->timeout(15)->get('https://serpapi.com/search', [
                            'engine' => 'amazon_product',
                            'amazon_domain' => $item['domain'],
                            'asin' => $item['asin'],
                            'api_key' => $apiKey,
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('AmazonLivePriceFetcher: pool fetch failed', [
                    'message' => $e->getMessage(),
                    'asins' => array_column($chunk, 'asin'),
                ]);
                foreach ($chunk as $item) {
                    $results[$item['asin']] = null;
                }
                continue;
            }

            foreach ($chunk as $item) {
                $asin = $item['asin'];
                $response = $responses[$asin] ?? null;
                if (!$response) {
                    $results[$asin] = null;
                    continue;
                }
                try {
                    $results[$asin] = $this->parseProductResponse($response, $asin, $item['domain']);
                } catch (\Throwable $e) {
                    Log::warning('AmazonLivePriceFetcher: parse failed', [
                        'asin' => $asin,
                        'message' => $e->getMessage(),
                    ]);
                    $results[$asin] = null;
                }
            }
        }

        return $results;
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array{
     *     asin: string,
     *     price: float,
     *     title: ?string,
     *     link: ?string,
     *     image: ?string,
     *     rating: ?float,
     *     reviews: ?int,
     *     extracted_old_price: ?float,
     *     delivery: ?array,
     *     seller_name: ?string,
     *     stock: ?string,
     *     stock_quantity: ?int
     * }|null
     */
    private function parseProductResponse($response, string $asin, string $amazonDomain): ?array
    {
        if (!$response->successful()) {
            Log::warning('AmazonLivePriceFetcher: SerpApi HTTP error', [
                'asin' => $asin,
                'status' => method_exists($response, 'status') ? $response->status() : null,
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! empty($data['error'])) {
            return null;
        }

        $product = $data['product_results'] ?? null;
        if (!$product || ! is_array($product)) {
            return null;
        }

        $price = $this->extractPrice($product);
        if ($price === null) {
            return null;
        }

        $title = $product['title'] ?? null;
        $link = $product['link'] ?? "https://www.{$amazonDomain}/dp/{$asin}";
        $image = $this->extractImage($product);
        $stockInfo = $this->extractStock($data, $product);

        return [
            'asin' => $asin,
            'price' => round($price, 2),
            'title' => $title,
            'link' => $link,
            'image' => $image,
            'rating' => isset($product['rating']) && is_numeric($product['rating']) ? (float) $product['rating'] : null,
            'reviews' => isset($product['reviews']) && is_numeric($product['reviews']) ? (int) $product['reviews'] : null,
            'extracted_old_price' => $this->extractOldPrice($data, $product),
            'delivery' => isset($product['delivery']) && is_array($product['delivery'])
                ? array_values(array_filter(array_map('strval', $product['delivery'])))
                : null,
            'seller_name' => $this->extractSellerFromTitle($title),
            'stock' => $stockInfo['stock'],
            'stock_quantity' => $stockInfo['stock_quantity'],
        ];
    }

    /**
     * SerpApi amazon_product exposes stock as text, e.g. "In Stock" or
     * "Only 3 left in stock - order soon." Also check purchase_options.buy_new.
     *
     * @return array{stock: ?string, stock_quantity: ?int}
     */
    private function extractStock(array $data, array $product): array
    {
        $candidates = [];

        if (! empty($product['stock']) && is_string($product['stock'])) {
            $candidates[] = $product['stock'];
        }

        $buyNew = $data['purchase_options']['buy_new'] ?? null;
        if (is_array($buyNew) && ! empty($buyNew['stock']) && is_string($buyNew['stock'])) {
            $candidates[] = $buyNew['stock'];
        }

        // Some responses nest stock under offers[0]
        if (! empty($product['offers']) && is_array($product['offers'])) {
            foreach ($product['offers'] as $offer) {
                if (is_array($offer) && ! empty($offer['stock']) && is_string($offer['stock'])) {
                    $candidates[] = $offer['stock'];
                    break;
                }
            }
        }

        $stock = null;
        foreach ($candidates as $text) {
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text !== '') {
                $stock = $text;
                break;
            }
        }

        if ($stock === null) {
            return ['stock' => null, 'stock_quantity' => null];
        }

        $qty = null;
        if (preg_match('/only\s+(\d+)\s+left/i', $stock, $m)) {
            $qty = (int) $m[1];
        } elseif (preg_match('/(\d+)\s+left\s+in\s+stock/i', $stock, $m)) {
            $qty = (int) $m[1];
        } elseif (preg_match('/\bout\s+of\s+stock\b/i', $stock)) {
            $qty = 0;
        }

        return [
            'stock' => mb_substr($stock, 0, 255),
            'stock_quantity' => $qty,
        ];
    }

    private function extractPrice(array $product): ?float
    {
        if (isset($product['extracted_price']) && is_numeric($product['extracted_price'])) {
            return (float) $product['extracted_price'];
        }

        if (isset($product['price']['value'])) {
            return (float) $product['price']['value'];
        }

        if (!empty($product['price']) && is_string($product['price']) && preg_match('/[\d,.]+/', $product['price'], $matches)) {
            return (float) str_replace(',', '', $matches[0]);
        }

        return null;
    }

    private function extractImage(array $product): ?string
    {
        if (!empty($product['thumbnail']) && is_string($product['thumbnail'])) {
            return $product['thumbnail'];
        }

        if (!empty($product['thumbnails'][0]) && is_string($product['thumbnails'][0])) {
            return $product['thumbnails'][0];
        }

        if (!empty($product['image']) && is_string($product['image'])) {
            return $product['image'];
        }

        return null;
    }

    private function extractOldPrice(array $data, array $product): ?float
    {
        if (isset($product['extracted_old_price']) && is_numeric($product['extracted_old_price'])) {
            return (float) $product['extracted_old_price'];
        }

        if (!empty($product['old_price']) && is_string($product['old_price']) && preg_match('/[\d,.]+/', $product['old_price'], $matches)) {
            return (float) str_replace(',', '', $matches[0]);
        }

        $buyNew = $data['purchase_options']['buy_new'] ?? null;
        if (is_array($buyNew)) {
            if (isset($buyNew['extracted_old_price']) && is_numeric($buyNew['extracted_old_price'])) {
                return (float) $buyNew['extracted_old_price'];
            }
            if (!empty($buyNew['old_price']) && is_string($buyNew['old_price']) && preg_match('/[\d,.]+/', $buyNew['old_price'], $matches)) {
                return (float) str_replace(',', '', $matches[0]);
            }
        }

        return null;
    }

    private function extractSellerFromTitle(?string $title): ?string
    {
        if ($title === null || trim($title) === '') {
            return null;
        }

        $title = trim($title);
        $patterns = [
            '/\s+by\s+([^\-|(]+)$/i',
            '/\s+-\s+([^\-|(]+)$/u',
            '/\s*[|]\s*([^\-|(]+)$/u',
            '/\s*\(\s*([^)]+)\)\s*$/u',
            '/Sold\s+by\s+([^\.\-|(]+)/i',
            '/from\s+([^\.\-|(]+?)(?:\s*[\.\-|]|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                $seller = trim(preg_replace('/\s+/', ' ', $matches[1]));
                if (strlen($seller) >= 2 && strlen($seller) <= 255 && !preg_match('/^\d+$/', $seller)) {
                    return $seller;
                }
            }
        }

        return null;
    }
}
