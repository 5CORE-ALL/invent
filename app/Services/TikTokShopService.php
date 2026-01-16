<?php

namespace App\Services;

use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TikTokShopService
{
    protected $client;
    protected $clientKey;
    protected $clientSecret;
    protected $shopId;
    protected $shopCipher = null;
    protected $accessToken;
    protected $refreshToken;
    protected $lastResponse = null;
    protected $lastResponseCode = null;
    protected $outputCallback = null;

    public function __construct()
    {
        $this->clientKey = config('services.tiktok.client_key');
        $this->clientSecret = config('services.tiktok.client_secret');
        $this->shopId = config('services.tiktok.shop_id');
        
        // Get tokens from cache first, then fallback to env
        $this->accessToken = Cache::get('tiktok_access_token') ?? env('TIKTOK_ACCESS_TOKEN');
        $this->refreshToken = Cache::get('tiktok_refresh_token') ?? env('TIKTOK_REFRESH_TOKEN');
        
        // Initialize the TikTok Shop client library (same as ship_hub)
        $this->client = new Client($this->clientKey, $this->clientSecret);
        
        if ($this->accessToken) {
            $this->client->setAccessToken($this->accessToken);
        }
    }

    /**
     * Get shop info using the library
     */
    public function getShopInfo($outputCallback = null): ?array
    {
        try {
            if (!$this->accessToken) {
                if ($outputCallback) $outputCallback('error', 'No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            
            $response = $this->client->Authorization->getAuthorizedShop();
            
            $this->lastResponse = $response;
            
            // Extract shop_cipher from response and set it on the client for subsequent API calls
            if (isset($response['shops']) && is_array($response['shops']) && !empty($response['shops'])) {
                $shop = $response['shops'][0];
                if (isset($shop['cipher'])) {
                    $this->shopCipher = $shop['cipher'];
                    // Set shop cipher on the client - required for all product/inventory API calls
                    $this->client->setShopCipher($this->shopCipher);
                }
            }
            
            
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            // Token expired - try to refresh
            if ($outputCallback) {
                $outputCallback('info', 'Token expired, attempting to refresh...');
            }
            
            if ($this->refreshAccessToken()) {
                // Retry with new token
                $this->client->setAccessToken($this->accessToken);
                try {
                    $response = $this->client->Authorization->getAuthorizedShop();
                    $this->lastResponse = $response;
                    
                    // Extract shop cipher from response and set it on client
                    if (isset($response['shops']) && !empty($response['shops'][0]['cipher'])) {
                        $shopCipher = $response['shops'][0]['cipher'];
                        $this->client->setShopCipher($shopCipher);
                    }
                    
                    if ($outputCallback) {
                        $outputCallback('info', '✓ Token refreshed and request succeeded');
                    }
                    return $response;
                } catch (\Exception $retryException) {
                    if ($outputCallback) {
                        $outputCallback('error', 'Retry after refresh failed: ' . $retryException->getMessage());
                    }
                }
            } else {
                if ($outputCallback) {
                    $outputCallback('error', 'Failed to refresh token');
                }
            }
            
            if ($outputCallback) {
                $outputCallback('error', 'Error: ' . $e->getMessage());
            }
            Log::error('TikTok getShopInfo failed', ['error' => $e->getMessage()]);
            return ['code' => 999999, 'message' => $e->getMessage(), 'data' => null];
        } catch (\Exception $e) {
            if ($outputCallback) {
                $outputCallback('error', 'Error: ' . $e->getMessage());
                $outputCallback('error', 'Error Class: ' . get_class($e));
            }
            Log::error('TikTok getShopInfo failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return ['code' => 999999, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Set output callback for console output
     */
    public function setOutputCallback(callable $callback): void
    {
        $this->outputCallback = $callback;
    }

    /**
     * Output message using callback or log
     */
    protected function output(string $type, string $message): void
    {
        if (is_callable($this->outputCallback)) {
            call_user_func($this->outputCallback, $type, $message);
        } else {
            // Fallback to Log if no callback is set
            if ($type === 'info') Log::info($message);
            elseif ($type === 'error') Log::error($message);
            elseif ($type === 'warn') Log::warning($message);
            else Log::debug($message);
        }
    }

    /**
     * Get products list using the library
     */
    public function getProducts(int $pageSize = 20, string $cursor = '', int $status = 0, $outputCallback = null): ?array
    {
        $callback = $outputCallback ?? $this->outputCallback;
        
        try {
            if (!$this->accessToken) {
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'error', 'No access token available');
                }
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Build request body with required page_size
            $body = [
                'page_size' => min($pageSize, 50),
            ];

            if ($cursor) {
                $body['cursor'] = $cursor;
            }

            if ($status > 0) {
                $body['product_status'] = $status;
            }

            // Ensure shop cipher is set on client (required for all product API calls)
            if (!$this->shopCipher) {
                // Try to get shop info to extract cipher
                $shopInfo = $this->getShopInfo();
                if ($shopInfo && isset($shopInfo['shops'][0]['cipher'])) {
                    $this->shopCipher = $shopInfo['shops'][0]['cipher'];
                    $this->client->setShopCipher($this->shopCipher);
                }
            } else {
                // Make sure it's set on client even if we already have it
                $this->client->setShopCipher($this->shopCipher);
            }

            // Shop cipher is already set on the client, so we don't pass it in query params
            $queryParams = [];


            // Use searchProducts - shop_cipher is already set on the client
            $response = $this->client->Product->searchProducts([], $body);
            
            $this->lastResponse = $response;
            
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'info', 'Response received. Keys: ' . implode(', ', array_keys($response)));
                if (isset($response['code'])) {
                    call_user_func($callback, 'info', 'Response code: ' . $response['code']);
                }
            }
            
            // Check for error in response
            if (isset($response['code']) && $response['code'] != 0) {
                $errorMsg = 'API Error Code: ' . $response['code'] . ', Message: ' . ($response['message'] ?? 'No message');
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'error', $errorMsg);
                    call_user_func($callback, 'error', 'Full response: ' . json_encode($response, JSON_PRETTY_PRINT));
                }
            }
            
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'info', 'Token expired, attempting to refresh...');
            }
            // Token expired - refresh and retry
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                // Rebuild query params and body for retry
                $retryQueryParams = [];
                if ($this->shopCipher) {
                    $retryQueryParams['shop_cipher'] = $this->shopCipher;
                }
                $retryBody = $body;
                
                // Retry with same parameters
                try {
                    $response = $this->client->Product->searchProducts($retryQueryParams, $retryBody);
                } catch (\Exception $e2) {
                    if ($this->shopCipher) {
                        $retryBody['shop_cipher'] = $this->shopCipher;
                        $response = $this->client->Product->searchProducts($retryBody);
                    } else {
                        throw $e2;
                    }
                }
                $this->lastResponse = $response;
                if ($callback && is_callable($callback)) {
                    call_user_func($callback, 'info', 'Token refreshed, retry successful');
                }
                return $response;
            }
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'error', 'Failed to refresh token: ' . $e->getMessage());
            }
            return null;
        } catch (\Exception $e) {
            if ($callback && is_callable($callback)) {
                call_user_func($callback, 'error', 'Exception getting products: ' . $e->getMessage());
                call_user_func($callback, 'error', 'Exception class: ' . get_class($e));
            }
            return null;
        }
    }

    /**
     * Get all products with pagination
     */
    public function getAllProducts(int $status = 0): array
    {
        $allProducts = [];
        $cursor = '';
        $hasMore = true;
        $page = 1;

            while ($hasMore) {

            $response = $this->getProducts(50, $cursor, $status, $this->outputCallback);

            if (!$response) {
                if ($this->outputCallback && is_callable($this->outputCallback)) {
                    call_user_func($this->outputCallback, 'error', "Page {$page}: No response received");
                }
                break;
            }


            // Check for error in response
            if (isset($response['code']) && $response['code'] != 0) {
                break;
            }

            // Library returns data in different format
            $products = $response['data']['products'] ?? $response['products'] ?? $response['data'] ?? [];
            
            // Handle case where products might be directly in response
            if (empty($products) && isset($response['data']) && is_array($response['data'])) {
                // Try to find products array in nested structure
                foreach ($response['data'] as $key => $value) {
                    if (is_array($value) && isset($value[0]) && (isset($value[0]['id']) || isset($value[0]['product_id']))) {
                        $products = $value;
                        break;
                    }
                }
            }

            if (!empty($products)) {
                $allProducts = array_merge($allProducts, $products);
            }

            $hasMore = isset($response['data']['more']) && $response['data']['more'];
            $cursor = $response['data']['next_cursor'] ?? $response['data']['cursor'] ?? $response['next_cursor'] ?? '';

            // If no cursor and no more flag, assume no more pages
            if (empty($cursor) && !$hasMore) {
                break;
            }

            if (count($allProducts) > 10000) {
                break;
            }

            $page++;
            usleep(200000);
        }


        return $allProducts;
    }

    /**
     * Get product inventory using the library
     */
    public function getProductInventory(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                $this->output('error', 'getProductInventory: No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Use inventorySearch method from library
            // Ensure all product IDs are strings
            $productIdList = array_map('strval', array_slice($productIds, 0, 50));
            $this->output('info', 'Calling Product->inventorySearch() with ' . count($productIdList) . ' product IDs');
            $this->output('info', 'Sample product IDs: ' . implode(', ', array_slice($productIdList, 0, 3)));
            $response = $this->client->Product->inventorySearch([
                'product_id_list' => $productIdList,
            ]);
            $this->lastResponse = $response;
            
            if (isset($response['code']) && $response['code'] != 0) {
                $this->output('error', 'getProductInventory API error: Code ' . $response['code'] . ', Message: ' . ($response['message'] ?? 'No message'));
                return null;
            }
            
            $this->output('info', 'getProductInventory: Response received. Keys: ' . implode(', ', array_keys($response)));
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->output('info', 'getProductInventory: Token expired, refreshing...');
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                $productIdList = array_map('strval', array_slice($productIds, 0, 50));
                $response = $this->client->Product->inventorySearch([
                    'product_id_list' => $productIdList,
                ]);
                $this->lastResponse = $response;
                return $response;
            }
            $this->output('error', 'getProductInventory: Failed to refresh token - ' . $e->getMessage());
            Log::error('TikTok getProductInventory failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            $this->output('error', 'getProductInventory Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductInventory failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get all product inventory - extract from product data (skus array)
     * TikTok product data includes SKU-level inventory information
     */
    public function getAllProductInventory(array $products): array
    {
        $allInventory = [];
        
        if (empty($products)) {
            $this->output('warn', 'getAllProductInventory: No products provided');
            return $allInventory;
        }
        
        $this->output('info', 'getAllProductInventory: Extracting inventory from ' . count($products) . ' products');
        
        // Extract inventory from product data (skus array contains inventory info)
        foreach ($products as $product) {
            $productId = $product['id'] ?? $product['product_id'] ?? null;
            if (!$productId) {
                continue;
            }
            
            // Check if product has skus array with inventory data
            $skus = $product['skus'] ?? [];
            
            if (!empty($skus) && is_array($skus)) {
                // Sum up inventory from all SKUs for this product
                $totalStock = 0;
                foreach ($skus as $sku) {
                    // Try different fields for stock quantity
                    $stock = $sku['available_stock'] 
                        ?? $sku['stock'] 
                        ?? $sku['inventory_quantity'] 
                        ?? $sku['quantity'] 
                        ?? $sku['inventory']['available_stock'] 
                        ?? 0;
                    $totalStock += (int)$stock;
                }
                
                // Create inventory record for this product
                if ($totalStock > 0 || isset($skus[0])) {
                    $allInventory[] = [
                        'product_id' => (string)$productId,
                        'available_stock' => $totalStock,
                        'stock' => $totalStock,
                    ];
                }
            } else {
                // Try to get inventory from product-level fields
                $stock = $product['available_stock'] 
                    ?? $product['stock'] 
                    ?? $product['inventory_quantity'] 
                    ?? $product['inventory']['available_stock'] 
                    ?? 0;
                
                if ($stock > 0) {
                    $allInventory[] = [
                        'product_id' => (string)$productId,
                        'available_stock' => (int)$stock,
                        'stock' => (int)$stock,
                    ];
                }
            }
        }
        
        $this->output('info', 'getAllProductInventory: Extracted inventory for ' . count($allInventory) . ' products');
        return $allInventory;
    }

    /**
     * Get product analytics using the library
     */
    public function getProductAnalytics(int $startTime = null, int $endTime = null, array $productIds = []): ?array
    {
        try {
            if (!$this->accessToken) {
                $this->output('error', 'getProductAnalytics: No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            if (!$startTime) {
                $startTime = Carbon::now()->subDays(30)->timestamp;
            }
            if (!$endTime) {
                $endTime = Carbon::now()->timestamp;
            }

            $params = [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];

            if (!empty($productIds)) {
                $params['product_id_list'] = array_slice($productIds, 0, 50);
            }

            $this->output('info', 'Calling Analytics->getShopProductPerformanceList() with params: ' . json_encode($params));
            // Use getShopProductPerformanceList for product analytics
            $response = $this->client->Analytics->getShopProductPerformanceList($params);
            $this->lastResponse = $response;
            
            if (isset($response['code']) && $response['code'] != 0) {
                $this->output('error', 'getProductAnalytics API error: Code ' . $response['code'] . ', Message: ' . ($response['message'] ?? 'No message'));
                return null;
            }
            
            $this->output('info', 'getProductAnalytics: Response received. Keys: ' . implode(', ', array_keys($response)));
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            $this->output('info', 'getProductAnalytics: Token expired, refreshing...');
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                $response = $this->client->Analytics->getShopProductPerformanceList($params);
                $this->lastResponse = $response;
                return $response;
            }
            $this->output('error', 'getProductAnalytics: Failed to refresh token - ' . $e->getMessage());
            Log::error('TikTok getProductAnalytics failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            $this->output('error', 'getProductAnalytics Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductAnalytics failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get product details using the library
     */
    public function getProductDetails(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Get first product ID (library method takes single ID)
            if (empty($productIds)) {
                return null;
            }
            
            $productId = $productIds[0];
            $response = $this->client->Product->getProduct($productId);
            $this->lastResponse = $response;
            
            return $response;
        } catch (\Exception $e) {
            Log::error('TikTok getProductDetails failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get product views - try multiple methods to fetch view data
     */
    public function getProductViews(array $products): ?array
    {
        try {
            if (empty($products)) {
                $this->output('warn', 'getProductViews: No products provided');
                return null;
            }
            
            $this->output('info', 'getProductViews: Attempting to fetch view data for ' . count($products) . ' products...');
            
            // Debug: Log first product structure to see what fields are available
            if (!empty($products[0])) {
                $sampleProduct = $products[0];
                $sampleKeys = array_keys($sampleProduct);
                $this->output('info', 'Sample product keys: ' . implode(', ', array_slice($sampleKeys, 0, 20)) . (count($sampleKeys) > 20 ? '...' : ''));
                Log::debug('TikTok product sample structure', [
                    'keys' => $sampleKeys,
                    'has_data' => isset($sampleProduct['data']),
                    'has_metrics' => isset($sampleProduct['metrics']),
                    'has_performance' => isset($sampleProduct['performance']),
                ]);
            }
            
            $viewsData = [];
            $productIds = [];
            
            // First, try to extract views from product data directly
            foreach ($products as $product) {
                $productId = $product['id'] ?? $product['product_id'] ?? null;
                if (!$productId) continue;
                
                $productIds[] = (string)$productId;
                
                // Try to extract views from various possible fields in product data
                $views = $product['product_views'] 
                    ?? $product['views'] 
                    ?? $product['total_views']
                    ?? $product['view_count']
                    ?? $product['page_views']
                    ?? $product['data']['product_views'] 
                    ?? $product['data']['views']
                    ?? $product['data']['total_views']
                    ?? $product['metrics']['product_views'] 
                    ?? $product['metrics']['views']
                    ?? $product['performance']['product_views']
                    ?? $product['performance']['views']
                    ?? $product['analytics']['product_views']
                    ?? $product['analytics']['views']
                    ?? $product['statistics']['product_views']
                    ?? $product['statistics']['views'] ?? null;
                
                // Also try to get SKU for matching
                $sku = $product['seller_sku'] 
                    ?? $product['sku'] 
                    ?? ($product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null) ?? null;
                
                if ($views !== null) {
                    $viewsData[] = [
                        'product_id' => (string)$productId,
                        'sku' => $sku,
                        'views' => (int)$views,
                        'product_views' => (int)$views,
                    ];
                }
            }
            
            $this->output('info', 'Extracted ' . count($viewsData) . ' products with views from product list data');
            
            // If we didn't find views in product data, try fetching detailed product info
            if (empty($viewsData) && !empty($productIds)) {
                $this->output('info', 'Views not found in product list, trying to fetch detailed product data...');
                $viewsData = $this->fetchViewsFromProductDetails($productIds, $products);
            }
            
            // If still no views, try direct API call to analytics endpoint
            if (empty($viewsData) && !empty($productIds)) {
                // Ensure shopCipher is set
                if (!$this->shopCipher) {
                    $this->output('info', 'Getting shop info to retrieve shop cipher...');
                    $shopInfo = $this->getShopInfo();
                    if ($shopInfo && isset($shopInfo['shops'][0]['cipher'])) {
                        $this->shopCipher = $shopInfo['shops'][0]['cipher'];
                        $this->output('info', '✓ Shop cipher retrieved');
                    } else {
                        $this->output('warn', 'Could not retrieve shop cipher from shop info');
                    }
                }
                
                if ($this->shopCipher) {
                    $this->output('info', 'Trying direct API call to analytics endpoint with ' . count($productIds) . ' product IDs...');
                    $viewsData = $this->fetchViewsDirectApi($productIds, $products);
                } else {
                    $this->output('warn', 'Shop cipher not available, skipping direct API call');
                }
            }
            
            $this->output('info', "getProductViews: Found view data for " . count($viewsData) . " products");
            
            $this->lastResponse = ['analytics' => $viewsData];
            return ['analytics' => $viewsData];
        } catch (\Exception $e) {
            $this->output('error', 'getProductViews Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductViews failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Fetch views by getting detailed product information
     */
    protected function fetchViewsFromProductDetails(array $productIds, array $products): array
    {
        $viewsData = [];
        $productMap = [];
        
        // Create a map of product_id to product data for SKU lookup
        foreach ($products as $product) {
            $productId = $product['id'] ?? $product['product_id'] ?? null;
            if ($productId) {
                $productMap[(string)$productId] = $product;
            }
        }
        
        // Try fetching detailed product info in batches
        $batches = array_chunk($productIds, 10); // Smaller batches for detailed calls
        foreach ($batches as $batchIndex => $batch) {
            $this->output('info', "Fetching detailed product data for batch " . ($batchIndex + 1) . " (" . count($batch) . " products)...");
            
            foreach ($batch as $productId) {
                try {
                    $productDetails = $this->getProductDetails([$productId]);
                    if ($productDetails) {
                        // Try to extract views from detailed product response
                        $productData = $productDetails['data']['product'] ?? $productDetails['product'] ?? $productDetails['data'] ?? $productDetails;
                        
                        $views = $productData['product_views'] 
                            ?? $productData['views'] 
                            ?? $productData['total_views']
                            ?? $productData['view_count']
                            ?? $productData['page_views']
                            ?? $productData['metrics']['product_views'] 
                            ?? $productData['metrics']['views']
                            ?? $productData['performance']['product_views']
                            ?? $productData['performance']['views'] ?? null;
                        
                        if ($views !== null) {
                            $product = $productMap[$productId] ?? [];
                            $sku = $product['seller_sku'] 
                                ?? $product['sku'] 
                                ?? ($product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null) ?? null;
                            
                            $viewsData[] = [
                                'product_id' => $productId,
                                'sku' => $sku,
                                'views' => (int)$views,
                                'product_views' => (int)$views,
                            ];
                        }
                    }
                    usleep(100000); // 0.1 second delay between calls
                } catch (\Exception $e) {
                    // Continue with next product
                    continue;
                }
            }
        }
        
        return $viewsData;
    }
    
    /**
     * Try direct API call to analytics endpoint with different API versions
     */
    protected function fetchViewsDirectApi(array $productIds, array $products): array
    {
        $viewsData = [];
        
        if (!$this->accessToken || !$this->shopCipher) {
            return $viewsData;
        }
        
        try {
            // Try making direct HTTP call to TikTok API analytics endpoint
            // TikTok API base URL - try both US and global endpoints
            $baseUrls = [
                'https://open-api.tiktokglobalshop.com',
                'https://open-api-us.tiktokglobalshop.com',
            ];
            
            $batches = array_chunk($productIds, 50);
            $startTime = Carbon::now()->subDays(30)->timestamp;
            $endTime = Carbon::now()->timestamp;
            
            // First, try using the library's Analytics method (might work with proper error handling)
            $this->output('info', 'Attempting to use library Analytics method...');
            try {
                $analyticsResponse = $this->getProductAnalytics($startTime, $endTime, array_slice($productIds, 0, 50));
                if ($analyticsResponse && !isset($analyticsResponse['code'])) {
                    $performanceList = $analyticsResponse['data']['product_performance_list'] 
                        ?? $analyticsResponse['data']['products'] 
                        ?? $analyticsResponse['product_performance_list']
                        ?? $analyticsResponse['products']
                        ?? $analyticsResponse['data'] ?? [];
                    
                    if (!empty($performanceList) && is_array($performanceList)) {
                        foreach ($performanceList as $item) {
                            $productId = $item['product_id'] ?? $item['id'] ?? null;
                            $views = $item['product_views'] 
                                ?? $item['views'] 
                                ?? $item['total_views']
                                ?? $item['view_count']
                                ?? $item['page_views']
                                ?? $item['metrics']['product_views']
                                ?? $item['metrics']['views'] ?? null;
                            
                            if ($productId && $views !== null) {
                                $product = collect($products)->first(function($p) use ($productId) {
                                    return ($p['id'] ?? $p['product_id'] ?? null) == $productId;
                                });
                                
                                $sku = $product['seller_sku'] 
                                    ?? $product['sku'] 
                                    ?? ($product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null) ?? null;
                                
                                $viewsData[] = [
                                    'product_id' => (string)$productId,
                                    'sku' => $sku,
                                    'views' => (int)$views,
                                    'product_views' => (int)$views,
                                ];
                            }
                        }
                        
                        if (!empty($viewsData)) {
                            $this->output('info', "✓ Successfully fetched views using library Analytics method");
                            return $viewsData;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->output('info', 'Library Analytics method failed: ' . $e->getMessage());
            }
            
            // If library method failed, try direct HTTP calls
            $this->output('info', 'Trying direct HTTP calls to TikTok API...');
            $batches = array_chunk($productIds, 50);
            
            foreach ($batches as $batchIndex => $batch) {
                $this->output('info', "Trying direct API call for batch " . ($batchIndex + 1) . " (" . count($batch) . " products)...");
                
                // Try different API versions (newest first)
                $apiVersions = ['202405', '202401', '202312', '202309'];
                
                foreach ($baseUrls as $baseUrl) {
                    foreach ($apiVersions as $apiVersion) {
                        try {
                            // Try different endpoint formats
                            $endpoints = [
                                "/analytics/{$apiVersion}/products/query",
                                "/analytics/{$apiVersion}/products/performance",
                                "/analytics/{$apiVersion}/shop/products/performance",
                            ];
                            
                            foreach ($endpoints as $endpoint) {
                                $url = $baseUrl . $endpoint;
                                $this->output('info', "  Trying: {$url}");
                                
                                $body = [
                                    'shop_cipher' => $this->shopCipher,
                                    'start_time' => $startTime,
                                    'end_time' => $endTime,
                                    'product_id_list' => $batch,
                                ];
                                
                                $response = Http::timeout(30)->withHeaders([
                                    'Authorization' => 'Bearer ' . $this->accessToken,
                                    'Content-Type' => 'application/json',
                                ])->post($url, $body);
                                
                                $status = $response->status();
                                $this->output('info', "  Response status: {$status}");
                                
                                if ($response->successful()) {
                                    $data = $response->json();
                                    
                                    // Log response structure for debugging
                                    if (isset($data['code'])) {
                                        $this->output('info', "  API Response code: {$data['code']}, message: " . ($data['message'] ?? 'N/A'));
                                    }
                                    
                                    // Check various response structures
                                    $performanceList = $data['data']['product_performance_list'] 
                                        ?? $data['data']['products'] 
                                        ?? $data['product_performance_list']
                                        ?? $data['products']
                                        ?? $data['data'] ?? [];
                                    
                                    if (!empty($performanceList) && is_array($performanceList)) {
                                        $this->output('info', "  Found " . count($performanceList) . " performance records");
                                        foreach ($performanceList as $item) {
                                            $productId = $item['product_id'] ?? $item['id'] ?? null;
                                            $views = $item['product_views'] 
                                                ?? $item['views'] 
                                                ?? $item['total_views']
                                                ?? $item['view_count']
                                                ?? $item['page_views']
                                                ?? $item['metrics']['product_views']
                                                ?? $item['metrics']['views'] ?? null;
                                            
                                            if ($productId && $views !== null) {
                                                // Find SKU from products array
                                                $product = collect($products)->first(function($p) use ($productId) {
                                                    return ($p['id'] ?? $p['product_id'] ?? null) == $productId;
                                                });
                                                
                                                $sku = $product['seller_sku'] 
                                                    ?? $product['sku'] 
                                                    ?? ($product['skus'][0]['seller_sku'] ?? $product['skus'][0]['sku'] ?? null) ?? null;
                                                
                                                $viewsData[] = [
                                                    'product_id' => (string)$productId,
                                                    'sku' => $sku,
                                                    'views' => (int)$views,
                                                    'product_views' => (int)$views,
                                                ];
                                            }
                                        }
                                        
                                        if (!empty($viewsData)) {
                                            $this->output('info', "✓ Successfully fetched views using {$baseUrl}{$endpoint}");
                                            return $viewsData; // Success, return immediately
                                        }
                                    }
                                } elseif ($status == 403) {
                                    $this->output('info', "  Access forbidden (403) - API version {$apiVersion} may not be available");
                                } elseif ($status == 400) {
                                    $errorData = $response->json();
                                    $this->output('info', "  Bad request (400): " . ($errorData['message'] ?? 'Unknown error'));
                                }
                            }
                        } catch (\Exception $e) {
                            $this->output('info', "  Exception: " . $e->getMessage());
                            // Try next endpoint/version
                            continue;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->output('warn', 'Direct API call failed: ' . $e->getMessage());
            Log::error('TikTok direct API call failed', ['error' => $e->getMessage()]);
        }
        
        return $viewsData;
    }

    /**
     * Get product reviews/ratings - extract from product data since TikTok doesn't expose individual reviews
     * TikTok product data includes review_count and shop_rating/rating fields
     */
    public function getProductReviews(array $products): ?array
    {
        try {
            if (empty($products)) {
                $this->output('warn', 'getProductReviews: No products provided');
                return null;
            }
            
            $this->output('info', 'getProductReviews: Extracting review data from ' . count($products) . ' products');
            
            // Debug: Log first product structure to see what fields are available
            if (!empty($products[0])) {
                $sampleProduct = $products[0];
                $this->output('info', 'Sample product keys: ' . implode(', ', array_keys($sampleProduct)));
                if (isset($sampleProduct['data']) && is_array($sampleProduct['data'])) {
                    $this->output('info', 'Sample product data keys: ' . implode(', ', array_keys($sampleProduct['data'])));
                }
            }
            
            $reviewsData = [];
            foreach ($products as $product) {
                $productId = $product['id'] ?? $product['product_id'] ?? null;
                if (!$productId) continue;
                
                // Extract review count and rating from product data - try various field names
                $reviewCount = $product['review_count'] 
                    ?? $product['reviews_count'] 
                    ?? $product['total_reviews']
                    ?? $product['reviews']
                    ?? $product['data']['review_count'] 
                    ?? $product['data']['reviews_count']
                    ?? $product['data']['total_reviews']
                    ?? $product['rating_info']['review_count'] ?? 0;
                
                $rating = $product['shop_rating'] 
                    ?? $product['rating'] 
                    ?? $product['average_rating']
                    ?? $product['avg_rating']
                    ?? $product['data']['shop_rating'] 
                    ?? $product['data']['rating']
                    ?? $product['rating_info']['rating']
                    ?? $product['rating_info']['average_rating'] ?? null;
                
                // Always include product with review data (even if 0/null) - let the processing method decide
                $reviewsData[] = [
                    'product_id' => (string)$productId,
                    'review_count' => (int)$reviewCount,
                    'rating' => $rating !== null ? (float)$rating : null,
                ];
            }
            
            $this->output('info', "getProductReviews: Extracted review data for " . count($reviewsData) . " products");
            
            $this->lastResponse = ['reviews' => $reviewsData];
            return ['reviews' => $reviewsData];
        } catch (\Exception $e) {
            $this->output('error', 'getProductReviews Exception: ' . $e->getMessage() . ' (Class: ' . get_class($e) . ')');
            Log::error('TikTok getProductReviews failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sync all product data
     */
    public function syncAllProductData(): array
    {
        $result = [
            'products' => [],
            'inventory' => [],
            'analytics' => [],
            'reviews' => [],
            'errors' => []
        ];

        try {
            $this->output('info', 'Starting syncAllProductData...');
            
            $this->output('info', 'Step 1: Fetching products...');
            $products = $this->getAllProducts(1);
            $result['products'] = $products;
            $this->output('info', '✓ Fetched ' . count($products) . ' products');

            // Fetch inventory for products
            $this->output('info', 'Step 2: Fetching inventory data...');
            $inventoryData = $this->getAllProductInventory($products);
            $result['inventory'] = $inventoryData;
            if (!empty($inventoryData)) {
                $this->output('info', '✓ Fetched inventory for ' . count($inventoryData) . ' products');
            } else {
                $this->output('warn', '⚠ No inventory data retrieved');
            }
            
            // Analytics/Views: Try to extract from product data
            // Note: Analytics API requires version 202405+ which isn't available in current library
            $this->output('info', 'Step 3: Extracting view data from product data...');
            $viewsData = $this->getProductViews($products);
            if ($viewsData && !empty($viewsData['analytics'])) {
                $result['analytics'] = $viewsData['analytics'];
                $this->output('info', '✓ Extracted view data for ' . count($viewsData['analytics']) . ' products');
            } else {
                // Only show info message, not a warning, since this is expected
                $this->output('info', 'ℹ View data not found in product response. (Analytics API requires v202405+ which is not available)');
                $result['analytics'] = [];
            }
            
            // Reviews: Extract review_count and rating from product data (TikTok provides aggregated stats, not individual reviews)
            $this->output('info', 'Step 4: Extracting review data from products...');
            $reviews = $this->getProductReviews($products);
            if ($reviews && !empty($reviews['reviews'])) {
                $result['reviews'] = $reviews['reviews'];
                $this->output('info', '✓ Extracted review data for ' . count($reviews['reviews']) . ' products');
            } else {
                $this->output('warn', '⚠ No review data found in products');
                $result['reviews'] = [];
            }

        } catch (\Exception $e) {
            $errorMsg = 'TikTok syncAllProductData error: ' . $e->getMessage();
            $this->output('error', '✗ Exception: ' . $errorMsg);
            Log::error($errorMsg, ['trace' => $e->getTraceAsString()]);
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->accessToken);
    }
    
    public function setTokens(string $accessToken, string $refreshToken = null): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        
        Cache::put('tiktok_access_token', $accessToken, 86400);
        if ($refreshToken) {
            Cache::put('tiktok_refresh_token', $refreshToken, 86400 * 30);
        }
        
        if ($this->client) {
            $this->client->setAccessToken($accessToken);
        }
    }
    
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
    
    public function getLastResponseCode(): ?int
    {
        return $this->lastResponseCode;
    }
    
    public function getAuthorizationUrl(): string
    {
        $auth = $this->client->auth();
        $state = bin2hex(random_bytes(16));
        Cache::put('tiktok_oauth_state', $state, 600);
        return $auth->createAuthRequest($state, true);
    }
    
    public function refreshAccessToken(): ?array
    {
        if (!$this->refreshToken) {
            return null;
        }

        try {
            $auth = $this->client->auth();
            $newToken = $auth->refreshNewToken($this->refreshToken);
            
            if (isset($newToken['access_token'])) {
                $this->accessToken = $newToken['access_token'];
                $this->refreshToken = $newToken['refresh_token'] ?? $this->refreshToken;
                
                $expiresIn = $newToken['expire_in'] ?? 86400;
                Cache::put('tiktok_access_token', $this->accessToken, $expiresIn - 300);
                Cache::put('tiktok_refresh_token', $this->refreshToken, 86400 * 30);
                
                $this->client->setAccessToken($this->accessToken);
                
                return $newToken;
            }
        } catch (\Exception $e) {
            Log::error('TikTok refreshAccessToken failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
