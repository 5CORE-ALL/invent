<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TikTokShopService
{
    protected $clientKey;
    protected $clientSecret;
    protected $shopId;
    protected $apiBase;
    protected $authBase;
    protected $accessToken;
    protected $refreshToken;

    public function __construct()
    {
        $this->clientKey = config('services.tiktok.client_key');
        $this->clientSecret = config('services.tiktok.client_secret');
        $this->shopId = config('services.tiktok.shop_id');
        $this->apiBase = config('services.tiktok.api_base');
        $this->authBase = config('services.tiktok.auth_base');
        
        // Get tokens from cache first, then fallback to env
        $this->accessToken = Cache::get('tiktok_access_token') ?? env('TIKTOK_ACCESS_TOKEN');
        $this->refreshToken = Cache::get('tiktok_refresh_token') ?? env('TIKTOK_REFRESH_TOKEN');
    }

    /**
     * Generate OAuth authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        Cache::put('tiktok_oauth_state', $state, 600);

        $params = [
            'app_key' => $this->clientKey,
            'state' => $state,
        ];

        return $this->authBase . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $authCode): ?array
    {
        $timestamp = time();
        $path = '/api/v2/token/get';
        
        $params = [
            'app_key' => $this->clientKey,
            'app_secret' => $this->clientSecret,
            'auth_code' => $authCode,
            'grant_type' => 'authorized_code',
        ];

        $response = Http::get($this->authBase . $path, $params);

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']['access_token'])) {
                $this->accessToken = $data['data']['access_token'];
                $this->refreshToken = $data['data']['refresh_token'];
                
                // Cache tokens
                $expiresIn = $data['data']['access_token_expire_in'] ?? 86400;
                Cache::put('tiktok_access_token', $this->accessToken, $expiresIn - 300);
                Cache::put('tiktok_refresh_token', $this->refreshToken, 86400 * 30);
                
                return $data['data'];
            }
        }

        Log::error('TikTok getAccessToken failed', [
            'response' => $response->json(),
            'status' => $response->status()
        ]);

        return null;
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken(): ?array
    {
        if (!$this->refreshToken) {
            return null;
        }

        $path = '/api/v2/token/refresh';
        
        $params = [
            'app_key' => $this->clientKey,
            'app_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $response = Http::get($this->authBase . $path, $params);

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['data']['access_token'])) {
                $this->accessToken = $data['data']['access_token'];
                $this->refreshToken = $data['data']['refresh_token'];
                
                $expiresIn = $data['data']['access_token_expire_in'] ?? 86400;
                Cache::put('tiktok_access_token', $this->accessToken, $expiresIn - 300);
                Cache::put('tiktok_refresh_token', $this->refreshToken, 86400 * 30);
                
                return $data['data'];
            }
        }

        Log::error('TikTok refreshAccessToken failed', [
            'response' => $response->json()
        ]);

        return null;
    }

    /**
     * Generate signature for API request
     * TikTok Shop API signature: clientSecret + path + sorted_keyvalue_params + body + clientSecret, then SHA256
     */
    protected function generateSignature(string $path, array $params, string $body = ''): string
    {
        // Remove sign from params if exists (it shouldn't be in signature calculation)
        unset($params['sign']);
        
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Build string to sign: clientSecret + path + sorted_params + body + clientSecret
        $stringToSign = $this->clientSecret . $path;
        
        // Append sorted parameters (key + value)
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . (string)$value;
            }
        }
        
        // Append body if exists
        if (!empty($body)) {
            $stringToSign .= $body;
        }
        
        // Append clientSecret at the end
        $stringToSign .= $this->clientSecret;
        
        // Generate SHA256 hash (not HMAC, just hash)
        return hash('sha256', $stringToSign);
    }

    /**
     * Generate signature using alternative format (HMAC-SHA256)
     * This is a fallback if the standard format doesn't work
     */
    protected function generateSignatureAlternative(string $path, array $params, string $body = ''): string
    {
        // Remove sign from params
        unset($params['sign']);
        
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Build string to sign: path + sorted_params + body
        $stringToSign = $path;
        
        // Append sorted parameters (key + value)
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . (string)$value;
            }
        }
        
        // Append body if exists
        if (!empty($body)) {
            $stringToSign .= $body;
        }
        
        // Generate HMAC-SHA256 signature
        return hash_hmac('sha256', $stringToSign, $this->clientSecret);
    }

    /**
     * Make authenticated API request
     */
    protected function apiRequest(string $method, string $path, array $queryParams = [], array $body = []): ?array
    {
        if (!$this->accessToken) {
            // Try to refresh token
            if (!$this->refreshAccessToken()) {
                Log::error('TikTok API: No valid access token');
                return null;
            }
        }

        $timestamp = time();
        
        // Required parameters for all requests (must be in alphabetical order for signature)
        $params = [
            'access_token' => $this->accessToken,
            'app_key' => $this->clientKey,
            'shop_id' => $this->shopId,
            'timestamp' => (string)$timestamp,
        ];
        
        // Merge additional query params
        $params = array_merge($params, $queryParams);
        
        // Remove any null or empty values before signature calculation
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });

        // Generate signature BEFORE adding sign to params
        $bodyJson = !empty($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $sign = $this->generateSignature($path, $params, $bodyJson);
        
        // Log signature calculation for debugging
        Log::debug('TikTok Signature Calculation', [
            'path' => $path,
            'params_count' => count($params),
            'params_keys' => array_keys($params),
            'body_length' => strlen($bodyJson),
            'signature' => $sign
        ]);
        
        // Add sign to params AFTER calculation
        $params['sign'] = $sign;

        // Build URL with query parameters
        $url = $this->apiBase . $path . '?' . http_build_query($params);

        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];
            
            // For POST requests, send body as JSON
            if ($method === 'GET') {
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->get($url);
            } else {
                $response = Http::timeout(30)
                    ->withHeaders($headers)
                    ->post($url, $body);
            }

            $data = $response->json();
            
            // Log request for debugging (hide sensitive data)
            $logUrl = str_replace($this->accessToken, '***', $url);
            Log::debug('TikTok API Request', [
                'method' => $method,
                'path' => $path,
                'url' => $logUrl,
                'status' => $response->status(),
                'response_code' => $data['code'] ?? null,
                'response_message' => $data['message'] ?? null
            ]);
            
            // If signature error, log more details and try alternative signature format
            if (isset($data['code']) && ($data['code'] == 106001 || (isset($data['message']) && strpos($data['message'], 'sign') !== false))) {
                Log::error('TikTok Signature Error Details', [
                    'path' => $path,
                    'method' => $method,
                    'params_keys' => array_keys($params),
                    'signature_length' => strlen($sign),
                    'body_length' => strlen($bodyJson),
                    'response' => $data
                ]);
                
                // Try alternative signature format (HMAC-SHA256 instead of SHA256)
                $altSign = $this->generateSignatureAlternative($path, $params, $bodyJson);
                if ($altSign !== $sign) {
                    Log::info('Trying alternative signature format', ['alt_signature' => $altSign]);
                    $params['sign'] = $altSign;
                    $url = $this->apiBase . $path . '?' . http_build_query($params);
                    
                    if ($method === 'GET') {
                        $response = Http::timeout(30)->withHeaders($headers)->get($url);
                    } else {
                        $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                    }
                    $data = $response->json();
                    
                    if (!isset($data['code']) || $data['code'] != 106001) {
                        Log::info('Alternative signature format worked!');
                        return $data;
                    }
                }
            }

            // Check for token expiry
            if (isset($data['code']) && $data['code'] === 105001) {
                // Token expired, refresh and retry
                if ($this->refreshAccessToken()) {
                    return $this->apiRequest($method, $path, $queryParams, $body);
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('TikTok API request failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get orders from TikTok Shop
     */
    public function getOrders(int $startTime = null, int $endTime = null, int $pageSize = 100, string $cursor = ''): ?array
    {
        $path = '/api/orders/search';
        
        // Default to last 30 days if not specified
        if (!$startTime) {
            $startTime = Carbon::now()->subDays(30)->timestamp;
        }
        if (!$endTime) {
            $endTime = Carbon::now()->timestamp;
        }

        $body = [
            'create_time_ge' => $startTime,
            'create_time_lt' => $endTime,
            'page_size' => $pageSize,
        ];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get order details
     */
    public function getOrderDetail(array $orderIds): ?array
    {
        $path = '/api/orders/detail/query';
        
        $body = [
            'order_id_list' => $orderIds,
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get all orders with pagination
     */
    public function getAllOrders(int $days = 30): array
    {
        $allOrders = [];
        $startTime = Carbon::now()->subDays($days)->timestamp;
        $endTime = Carbon::now()->timestamp;
        $cursor = '';
        $hasMore = true;

        while ($hasMore) {
            $response = $this->getOrders($startTime, $endTime, 100, $cursor);

            if (!$response || !isset($response['data'])) {
                Log::error('TikTok getAllOrders failed', ['response' => $response]);
                break;
            }

            if (isset($response['data']['orders'])) {
                $allOrders = array_merge($allOrders, $response['data']['orders']);
            }

            // Check for more pages
            $hasMore = isset($response['data']['more']) && $response['data']['more'];
            $cursor = $response['data']['next_cursor'] ?? '';

            // Safety limit
            if (count($allOrders) > 10000) {
                break;
            }
        }

        return $allOrders;
    }

    /**
     * Get shop info
     */
    public function getShopInfo(): ?array
    {
        $path = '/api/shop/get_authorized_shop';
        return $this->apiRequest('GET', $path);
    }

    /**
     * Check if authenticated
     */
    public function isAuthenticated(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Set tokens manually (for testing or from database)
     */
    public function setTokens(string $accessToken, string $refreshToken = null): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        
        Cache::put('tiktok_access_token', $accessToken, 86400);
        if ($refreshToken) {
            Cache::put('tiktok_refresh_token', $refreshToken, 86400 * 30);
        }
    }

    /**
     * Get products list from TikTok Shop
     * 
     * @param int $pageSize Page size (default 20, max 50)
     * @param string $cursor Pagination cursor
     * @param int $status Product status (0=All, 1=Active, 2=Inactive)
     * @return array|null
     */
    public function getProducts(int $pageSize = 20, string $cursor = '', int $status = 0): ?array
    {
        // Try different possible endpoints
        $paths = [
            '/api/products/search',
            '/api/products/list',
            '/api/product/202309/products/search'
        ];

        $body = [
            'page_size' => min($pageSize, 50), // Max 50 per TikTok API
        ];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        if ($status > 0) {
            $body['product_status'] = $status;
        }

        // Try each endpoint until one works
        foreach ($paths as $path) {
            $response = $this->apiRequest('POST', $path, [], $body);
            if ($response && isset($response['data'])) {
                return $response;
            }
        }

        // If all failed, return the last response (might have error info)
        return $this->apiRequest('POST', $paths[0], [], $body);
    }

    /**
     * Get all products with pagination
     * 
     * @param int $status Product status filter
     * @return array
     */
    public function getAllProducts(int $status = 0): array
    {
        $allProducts = [];
        $cursor = '';
        $hasMore = true;
        $page = 1;

        while ($hasMore) {
            $response = $this->getProducts(50, $cursor, $status);

            if (!$response || !isset($response['data'])) {
                Log::error('TikTok getAllProducts failed', ['response' => $response, 'page' => $page]);
                break;
            }

            if (isset($response['data']['products'])) {
                $allProducts = array_merge($allProducts, $response['data']['products']);
            }

            // Check for more pages
            $hasMore = isset($response['data']['more']) && $response['data']['more'];
            $cursor = $response['data']['next_cursor'] ?? '';

            // Safety limit
            if (count($allProducts) > 10000) {
                Log::warning('TikTok getAllProducts: Reached safety limit of 10000 products');
                break;
            }

            $page++;
            
            // Small delay to respect rate limits
            usleep(200000); // 0.2 seconds
        }

        return $allProducts;
    }

    /**
     * Get product inventory/stock
     * 
     * @param array $productIds Product IDs to query
     * @return array|null
     */
    public function getProductInventory(array $productIds): ?array
    {
        // Try different possible endpoints
        $paths = [
            '/api/products/inventory/query',
            '/api/inventory/query',
            '/api/product/202309/inventory/query'
        ];

        $body = [
            'product_id_list' => array_slice($productIds, 0, 50), // Limit to 50 per request
        ];

        // Try each endpoint until one works
        foreach ($paths as $path) {
            $response = $this->apiRequest('POST', $path, [], $body);
            if ($response && isset($response['data'])) {
                return $response;
            }
        }

        // If all failed, return the last response
        return $this->apiRequest('POST', $paths[0], [], $body);
    }

    /**
     * Get product analytics/views data
     * 
     * @param int $startTime Start timestamp
     * @param int $endTime End timestamp
     * @param array $productIds Optional product IDs filter
     * @return array|null
     */
    public function getProductAnalytics(int $startTime = null, int $endTime = null, array $productIds = []): ?array
    {
        // Try different possible endpoints
        $paths = [
            '/api/analytics/products/query',
            '/api/analytics/query',
            '/api/product/202309/analytics/query'
        ];
        
        // Default to last 30 days if not specified
        if (!$startTime) {
            $startTime = Carbon::now()->subDays(30)->timestamp;
        }
        if (!$endTime) {
            $endTime = Carbon::now()->timestamp;
        }

        $body = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'dimensions' => ['product_id', 'sku'],
            'metrics' => ['product_views', 'views', 'product_clicks', 'product_orders'],
        ];

        if (!empty($productIds)) {
            $body['product_id_list'] = array_slice($productIds, 0, 50); // Limit to 50
        }

        // Try each endpoint until one works
        foreach ($paths as $path) {
            $response = $this->apiRequest('POST', $path, [], $body);
            if ($response && isset($response['data'])) {
                return $response;
            }
        }

        // If all failed, return the last response
        return $this->apiRequest('POST', $paths[0], [], $body);
    }

    /**
     * Get product details by product IDs
     * 
     * @param array $productIds Product IDs
     * @return array|null
     */
    public function getProductDetails(array $productIds): ?array
    {
        $path = '/api/products/details';
        
        $body = [
            'product_id_list' => $productIds,
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Sync all product data (price, stock, views) from TikTok API
     * This is a convenience method that fetches and returns structured data
     * 
     * @return array
     */
    public function syncAllProductData(): array
    {
        $result = [
            'products' => [],
            'inventory' => [],
            'analytics' => [],
            'errors' => []
        ];

        try {
            // 1. Get all products
            Log::info('TikTok: Fetching products...');
            $products = $this->getAllProducts(1); // Status 1 = Active products
            $result['products'] = $products;
            Log::info('TikTok: Fetched ' . count($products) . ' products');

            // 2. Get inventory for all products (in batches)
            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $chunks = array_chunk($productIds, 50); // TikTok API limit
                
                Log::info('TikTok: Fetching inventory for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                
                foreach ($chunks as $chunk) {
                    $inventory = $this->getProductInventory($chunk);
                    if ($inventory && isset($inventory['data']['inventory_list'])) {
                        $result['inventory'] = array_merge($result['inventory'], $inventory['data']['inventory_list']);
                    }
                    usleep(200000); // Rate limit delay
                }
                Log::info('TikTok: Fetched inventory for ' . count($result['inventory']) . ' products');
            }

            // 3. Get analytics/views (last 30 days)
            Log::info('TikTok: Fetching analytics/views...');
            $analytics = $this->getProductAnalytics();
            if ($analytics && isset($analytics['data']['analytics_list'])) {
                $result['analytics'] = $analytics['data']['analytics_list'];
            }
            Log::info('TikTok: Fetched analytics for ' . count($result['analytics']) . ' products');

        } catch (\Exception $e) {
            Log::error('TikTok syncAllProductData error: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }
}
