<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonLivePriceFetcher
{
    public function getApiKey(): ?string
    {
        $key = config('services.serpapi.key');

        return $key ?: '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
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
     *     seller_name: ?string
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
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'amazon_product',
                'amazon_domain' => $amazonDomain,
                'asin' => $asin,
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('AmazonLivePriceFetcher: SerpApi HTTP error', [
                    'asin' => $asin,
                    'status' => $response->status(),
                ]);

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

            $title = $product['title'] ?? null;
            $link = $product['link'] ?? "https://www.{$amazonDomain}/dp/{$asin}";
            $image = $this->extractImage($product);

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
            ];
        } catch (\Throwable $e) {
            Log::warning('AmazonLivePriceFetcher: fetch failed', [
                'asin' => $asin,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
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
