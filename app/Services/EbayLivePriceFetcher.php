<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayLivePriceFetcher
{
    public function getApiKey(): ?string
    {
        $key = config('services.serpapi.key');

        return $key ?: '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
    }

    public function extractListingIdFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/\/itm\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Resolve the eBay listing ID to use for live price lookup.
     * Prefer the numeric ID from /itm/{id} in the product link over stored item_id (which may be epid).
     */
    public function resolveListingId(?string $productLink, ?string $storedItemId = null): ?string
    {
        $fromLink = $this->extractListingIdFromUrl($productLink);
        if ($fromLink) {
            return $fromLink;
        }

        if ($storedItemId && preg_match('/^\d+$/', (string) $storedItemId)) {
            return (string) $storedItemId;
        }

        return null;
    }

    /**
     * Fetch the current Buy-It-Now price for an eBay listing via SerpApi.
     *
     * @return array{listing_id: string, price: float, shipping_cost: float, total_price: float, title: ?string, link: ?string, image: ?string}|null
     */
    public function fetchByListingId(string $listingId): ?array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'ebay_product',
                'ebay_domain' => 'ebay.com',
                'product_id' => $listingId,
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('EbayLivePriceFetcher: SerpApi HTTP error', [
                    'listing_id' => $listingId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                return null;
            }

            $product = $data['product_results'] ?? $data;
            $price = $this->extractPrice($product);
            if ($price === null) {
                return null;
            }

            $shippingCost = $this->extractShippingCost($product);
            $link = $product['product_link'] ?? $product['link'] ?? "https://www.ebay.com/itm/{$listingId}";
            $image = $this->extractImage($product);

            return [
                'listing_id' => $listingId,
                'price' => round($price, 2),
                'shipping_cost' => round($shippingCost, 2),
                'total_price' => round($price + $shippingCost, 2),
                'title' => $product['title'] ?? null,
                'link' => $link,
                'image' => $image,
            ];
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: fetch failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractPrice(array $product): ?float
    {
        if (isset($product['buy']['buy_it_now']['price']['amount'])) {
            return (float) $product['buy']['buy_it_now']['price']['amount'];
        }

        if (isset($product['price']['value'])) {
            return (float) $product['price']['value'];
        }

        if (isset($product['price']['extracted'])) {
            return (float) $product['price']['extracted'];
        }

        if (!empty($product['price']) && is_string($product['price']) && preg_match('/[\d,.]+/', $product['price'], $matches)) {
            return (float) str_replace(',', '', $matches[0]);
        }

        return null;
    }

    private function extractShippingCost(array $product): float
    {
        $shipping = $product['shipping'] ?? null;
        if (!is_array($shipping)) {
            return 0.0;
        }

        if (!empty($shipping['options'][0]['free'])) {
            return 0.0;
        }

        if (isset($shipping['options'][0]['cost']['amount'])) {
            return (float) $shipping['options'][0]['cost']['amount'];
        }

        if (isset($shipping['cost']['value'])) {
            return (float) $shipping['cost']['value'];
        }

        return 0.0;
    }

  /**
   * Extract the best product image URL from SerpApi ebay_product response.
   */
    private function extractImage(array $product): ?string
    {
        if (!empty($product['thumbnail']) && is_string($product['thumbnail'])) {
            return $product['thumbnail'];
        }

        if (!empty($product['image']) && is_string($product['image'])) {
            return $product['image'];
        }

        if (!empty($product['images'][0]) && is_string($product['images'][0])) {
            return $product['images'][0];
        }

        if (empty($product['media']) || !is_array($product['media'])) {
            return null;
        }

        foreach ($product['media'] as $mediaItem) {
            if (($mediaItem['type'] ?? '') !== 'image') {
                continue;
            }

            $variants = $mediaItem['image'] ?? [];
            if (!is_array($variants) || empty($variants)) {
                continue;
            }

            $bestLink = null;
            $bestWidth = 0;

            foreach ($variants as $variant) {
                $link = $variant['link'] ?? null;
                if (!$link) {
                    continue;
                }

                $width = (int) ($variant['size']['width'] ?? 0);
                if ($width === 500) {
                    return $link;
                }

                if ($width > $bestWidth) {
                    $bestLink = $link;
                    $bestWidth = $width;
                }
            }

            if ($bestLink) {
                return $bestLink;
            }
        }

        return null;
    }
}
