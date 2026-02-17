<?php

namespace App\Services;

use App\Models\ProductStockMapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductMaster;

class ShopifyApiService
{
    public function __construct()
    {
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
        $this->shopifyStoreUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $this->shopifyStoreUrlName = env('SHOPIFY_STORE');
        $this->shopifyAccessToken = env('SHOPIFY_PASSWORD');
    }

    public function getInventory()
    {

        
        $inventoryData = [];
        $parentVariants = [];
        $pageInfo = null;
        $hasMore = true;
        $pageCount = 0;
        $totalProducts = 0;
        $totalVariants = 0;
 $validSkus = ProductMaster::query()
    ->selectRaw('DISTINCT TRIM(sku) as sku')
    ->whereNotNull('sku')
    ->whereNull('deleted_at')
    ->whereRaw("TRIM(sku) != ''")
    ->whereRaw("LOWER(sku) NOT LIKE '%parent%'")
    ->orderBy('sku')
    ->pluck('sku')
    ->map(fn($sku) => trim($sku))
    ->filter()
    ->unique()
    ->values()
    ->toArray();
   $validSkuLookup = array_flip($validSkus);
        while ($hasMore) {
            $pageCount++;
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,variants,image,images',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);

            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->retry(3, 500)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                Log::error("Failed to fetch products (Page {$pageCount}): ".$response->body());
                break;
            }
            
            $products = $response->json()['products'] ?? [];
            $productCount = count($products);
            $totalProducts += $productCount;

            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    $totalVariants++;

                    $sku = $variant['sku'] ?? '';
                    $isParent = count($product['variants']) > 1 ? 1 : 0;
                    $imageUrl = $this->sanitizeImageUrl(
                        $product['image']['src'] ?? (! empty($product['images']) ? $product['images'][0]['src'] : null),
                        $sku
                    );

                    // Ensure SKU is properly formatted but preserve original case
                    $sku = trim((string) $sku);
                    
                    // Skip empty SKUs or SKUs containing 'PARENT'
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }

                    $inventoryData[$sku] = [
                        'variant_id' => $variant['id'],
                        'product_id' => $product['id'],
                        'inventory' => (int) ($variant['inventory_quantity'] ?? 0),
                        'product_title' => $product['title'] ?? '',
                        'sku' => $sku,
                        'variant_title' => $variant['title'] ?? '',
                        'inventory_item_id' => $variant['inventory_item_id'],
                        'on_hand' => (int) ($variant['old_inventory_quantity'] ?? 0),
                        'available_to_sell' => (int) ($variant['inventory_quantity'] ?? 0),
                        'price' => $variant['price'],
                        'image_src' => $imageUrl,
                        'is_parent' => $isParent,
                    ];
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;

            if ($hasMore) {
                Log::info('Waiting 0.5s before next page...');
                usleep(500000);
            }
        }


        foreach ($inventoryData as $sku => $data) {
            // Check if SKU exists in our valid SKUs list
            if (!isset($validSkuLookup[$sku])) {
                Log::info("Skipping SKU not in ProductMaster or contains 'PARENT': $sku");
                continue;
            }

            // Ensure inventory values are integers
            $inventory = (int) $data['inventory'];

            ProductStockMapping::updateOrCreate(
                ['sku' => $sku],  // Use exact SKU from ProductMaster
                [
                    'image' => $data['image_src'],
                    'inventory_shopify' => $inventory,
                    'inventory_amazon' => 'Not Listed',
                    'inventory_walmart' => 'Not Listed',
                    'inventory_reverb' => 'Not Listed',
                    'inventory_shein' => 'Not Listed',
                    'inventory_doba' => 'Not Listed',
                    'inventory_temu' => 'Not Listed',
                    'inventory_macy' => 'Not Listed',
                    'inventory_ebay1' => 'Not Listed',
                    'inventory_ebay2' => 'Not Listed',
                    'inventory_ebay3' => 'Not Listed',
                    'inventory_bestbuy' => 'Not Listed',
                    'tiendamia' => 'Not Listed',
                ]
            );
        }
        return $inventoryData;
    }

    protected function sanitizeImageUrl(?string $url,$sku): ?string
    {                     
        if (empty($url)) {return null;}      
        // Remove line breaks and spaces
        $cleanUrl = trim(preg_replace('/\s+/', '', $url));
        // Remove ?v= query string (Shopify versioning param)
        $cleanUrl = strtok($cleanUrl, '?');
        return $cleanUrl;
    }
      
    protected function getNextPageInfo($response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    parse_str(parse_url($matches[1], PHP_URL_QUERY), $query);
                    return $query['page_info'] ?? null;
                }
            }
        }
        return null;
    }

    /**
     * Build map of SKU => { title, description, image_src } from Shopify products (for Reverb sync product list).
     */
    public function getProductDetailsBySkuMap(array $limitToSkus = []): array
    {
        $map = [];
        $pageInfo = null;
        $hasMore = true;
        $limitToLookup = $limitToSkus ? array_flip(array_map('trim', $limitToSkus)) : null;

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,title,body_html,vendor,product_type,variants,image,images',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);
            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                break;
            }

            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                $imageUrl = $this->sanitizeImageUrl(
                    $product['image']['src'] ?? (! empty($product['images']) ? $product['images'][0]['src'] : null),
                    ''
                );
                $title = $product['title'] ?? '';
                $description = isset($product['body_html']) ? strip_tags($product['body_html']) : '';
                $description = strlen($description) > 500 ? substr($description, 0, 497) . '...' : $description;
                $brand = $product['vendor'] ?? '';
                $productType = $product['product_type'] ?? '';

                foreach ($product['variants'] ?? [] as $variant) {
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    if ($limitToLookup !== null && ! isset($limitToLookup[$sku])) {
                        continue;
                    }
                    $variantTitle = trim((string) ($variant['title'] ?? ''));
                    $model = ($variantTitle !== '' && $variantTitle !== 'Default Title') ? $variantTitle : $productType;
                    $map[$sku] = [
                        'title' => $title,
                        'description' => $description,
                        'image_src' => $imageUrl,
                        'upc' => $variant['barcode'] ?? '',
                        'brand' => $brand,
                        'model' => $model,
                    ];
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(300000);
            }
        }

        return $map;
    }

    /**
     * Return map SKU => inventory quantity from Shopify (no DB writes). Used for Reverb inventory sync.
     */
    public function getInventoryQuantitiesBySku(array $limitToSkus = []): array
    {
        $map = [];
        $pageInfo = null;
        $hasMore = true;
        $limitToLookup = $limitToSkus ? array_flip(array_map('trim', $limitToSkus)) : null;

        while ($hasMore) {
            $queryParams = [
                'limit' => 250,
                'fields' => 'id,variants',
            ];
            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $request = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->shopifyAccessToken,
                'Content-Type' => 'application/json',
            ]);
            if (env('FILESYSTEM_DRIVER') === 'local') {
                $request = $request->withoutVerifying();
            }

            $response = $request->timeout(120)->get(
                "https://{$this->shopifyStoreUrl}/admin/api/2025-01/products.json",
                $queryParams
            );

            if (! $response->successful()) {
                break;
            }

            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    $sku = trim((string) ($variant['sku'] ?? ''));
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    if ($limitToLookup !== null && ! isset($limitToLookup[$sku])) {
                        continue;
                    }
                    $map[$sku] = (int) ($variant['inventory_quantity'] ?? 0);
                }
            }

            $pageInfo = $this->getNextPageInfo($response);
            $hasMore = (bool) $pageInfo;
            if ($hasMore) {
                usleep(300000);
            }
        }

        return $map;
    }
}
