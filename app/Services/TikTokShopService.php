<?php

namespace App\Services;

use EcomPHP\TiktokShop\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
     * Get product reviews using the library
     * Note: TikTok API may not have a direct reviews endpoint - reviews might be in product details
     */
    public function getProductReviews(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                $this->output('error', 'getProductReviews: No access token available');
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            $this->output('info', 'getProductReviews: Attempting to fetch reviews for ' . count($productIds) . ' products');
            $this->output('warn', 'Note: TikTok API may not have a direct reviews endpoint - checking product details');
            
            // Try to get reviews from product details (if available)
            // TikTok API might include reviews in product details
            $reviews = [];
            $processed = 0;
            foreach (array_slice($productIds, 0, 50) as $productId) {
                try {
                    $product = $this->client->Product->getProduct($productId);
                    $processed++;
                    
                    if (isset($product['data']['reviews']) || isset($product['reviews'])) {
                        $reviews[] = [
                            'product_id' => $productId,
                            'reviews' => $product['data']['reviews'] ?? $product['reviews'] ?? [],
                        ];
                    }
                } catch (\Exception $e) {
                    $this->output('warn', "getProductReviews: Failed to get product {$productId}: " . $e->getMessage());
                }
                usleep(100000); // Rate limit
            }
            
            $this->output('info', "getProductReviews: Processed {$processed} products, found reviews for " . count($reviews) . " products");
            
            $this->lastResponse = ['reviews' => $reviews];
            return ['reviews' => $reviews];
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

            if (!empty($products)) {
                // Extract product IDs and ensure they're strings (TikTok API requires string IDs)
                $productIds = array_map(function($product) {
                    return (string)($product['id'] ?? $product['product_id'] ?? '');
                }, array_filter($products, function($product) {
                    return !empty($product['id'] ?? $product['product_id'] ?? null);
                }));
                
                if (empty($productIds)) {
                    $this->output('warn', '⚠ No valid product IDs found in products');
                } else {
                    $chunks = array_chunk($productIds, 50);
                    
                    $this->output('info', 'Step 2: Fetching inventory for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                    $batchNum = 1;
                    
                    foreach ($chunks as $chunk) {
                        $this->output('info', "  Batch {$batchNum}/" . count($chunks) . ": Fetching inventory for " . count($chunk) . " products (IDs: " . substr(implode(', ', $chunk), 0, 50) . "...)");
                        $inventory = $this->getProductInventory($chunk);
                        
                        if ($inventory) {
                            $inventoryList = $inventory['data']['inventory_list'] ?? $inventory['inventory_list'] ?? [];
                            if (!empty($inventoryList)) {
                                $result['inventory'] = array_merge($result['inventory'], $inventoryList);
                                $this->output('info', "  ✓ Batch {$batchNum}: Got " . count($inventoryList) . " inventory records");
                            } else {
                                $this->output('warn', "  ⚠ Batch {$batchNum}: No inventory_list in response. Response keys: " . implode(', ', array_keys($inventory)));
                            }
                        } else {
                            $this->output('error', "  ✗ Batch {$batchNum}: getProductInventory returned null");
                        }
                        $batchNum++;
                        usleep(200000);
                    }
                    $this->output('info', '✓ Completed inventory fetch: ' . count($result['inventory']) . ' total records');
                }
            } else {
                $this->output('warn', '⚠ No products to fetch inventory for');
            }

            $this->output('info', 'Step 3: Fetching analytics/views...');
            $this->output('warn', '⚠ Note: Analytics API requires API version 202405+. Skipping if unavailable.');
            try {
                $analytics = $this->getProductAnalytics();
                if ($analytics) {
                    $result['analytics'] = $analytics['data']['product_performance_list'] ?? $analytics['product_performance_list'] ?? $analytics['data'] ?? [];
                    if (!empty($result['analytics'])) {
                        $this->output('info', '✓ Fetched analytics for ' . count($result['analytics']) . ' products');
                    } else {
                        $this->output('warn', '⚠ Analytics response received but no product_performance_list found. Response keys: ' . implode(', ', array_keys($analytics)));
                    }
                } else {
                    $this->output('warn', '⚠ Analytics not available (API version requirement or endpoint issue). Skipping...');
                }
            } catch (\Exception $e) {
                $this->output('warn', '⚠ Analytics fetch failed: ' . $e->getMessage() . '. Skipping...');
            }
            
            if (!empty($products)) {
                // Extract product IDs and ensure they're strings
                $productIds = array_map(function($product) {
                    return (string)($product['id'] ?? $product['product_id'] ?? '');
                }, array_filter($products, function($product) {
                    return !empty($product['id'] ?? $product['product_id'] ?? null);
                }));
                
                if (!empty($productIds)) {
                    $chunks = array_chunk($productIds, 50);
                    
                    $this->output('info', 'Step 4: Fetching reviews for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                    $batchNum = 1;
                    
                    foreach ($chunks as $chunk) {
                        $this->output('info', "  Batch {$batchNum}/" . count($chunks) . ": Fetching reviews for " . count($chunk) . " products...");
                        $reviews = $this->getProductReviews($chunk);
                    
                    if ($reviews) {
                        $reviewList = $reviews['reviews'] ?? $reviews['data']['reviews'] ?? $reviews['review_list'] ?? $reviews['data'] ?? [];
                        if (!empty($reviewList)) {
                            $result['reviews'] = array_merge($result['reviews'], $reviewList);
                            $this->output('info', "  ✓ Batch {$batchNum}: Got " . count($reviewList) . " reviews");
                        } else {
                            $this->output('warn', "  ⚠ Batch {$batchNum}: No reviews in response. Response keys: " . implode(', ', array_keys($reviews)));
                        }
                    } else {
                        $this->output('error', "  ✗ Batch {$batchNum}: getProductReviews returned null");
                    }
                        $batchNum++;
                        usleep(200000);
                    }
                    $this->output('info', '✓ Completed reviews fetch: ' . count($result['reviews']) . ' total reviews');
                } else {
                    $this->output('warn', '⚠ No valid product IDs found for reviews');
                }
            } else {
                $this->output('warn', '⚠ No products to fetch reviews for');
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
