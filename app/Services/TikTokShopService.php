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
    protected $lastResponse = null;
    protected $lastResponseCode = null;

    public function __construct()
    {
        $this->clientKey = config('services.tiktok.client_key');
        $this->clientSecret = config('services.tiktok.client_secret');
        $this->shopId = config('services.tiktok.shop_id');
        $this->apiBase = rtrim(config('services.tiktok.api_base'), '/');
        $this->authBase = rtrim(config('services.tiktok.auth_base'), '/');
        
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
     * TikTok Shop API signature format per documentation: https://m.tiktok.shop/s/AIu6dbFhs2XW
     * Format: app_secret + path + sorted_params(key+value) + body + app_secret, then SHA256
     */
    protected function generateSignature(string $path, array $params, string $body = ''): string
    {
        unset($params['sign']);
        ksort($params);
        
        $stringToSign = $this->clientSecret . $path;
        
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . (string)$value;
            }
        }
        
        if (!empty($body)) {
            $stringToSign .= $body;
        }
        
        $stringToSign .= $this->clientSecret;
        
        $signature = hash('sha256', $stringToSign);
        
        return $signature;
    }

    /**
     * Make authenticated API request - EXACT format from working orders endpoint
     */
    protected function apiRequest(string $method, string $path, array $queryParams = [], array $body = [], bool $includeShopId = true, $outputCallback = null): ?array
    {
        if (!$this->accessToken) {
            if (!$this->refreshAccessToken()) {
                Log::error('TikTok API: No valid access token');
                return null;
            }
        }

        $timestamp = time();
        
        $params = [
            'access_token' => $this->accessToken,
            'app_key' => $this->clientKey,
            'timestamp' => (string)$timestamp,
        ];
        
        if ($includeShopId && !empty($this->shopId)) {
            $params['shop_id'] = (string)$this->shopId;
        }
        
        if (empty($this->clientKey) || empty($this->clientSecret) || ($includeShopId && empty($this->shopId))) {
            Log::error('TikTok API: Missing credentials');
            return ['code' => 999999, 'message' => 'Missing credentials', 'data' => null];
        }
        
        $params = array_merge($params, $queryParams);
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });

        // For signature: body must be minified JSON (no spaces, no newlines)
        $bodyJson = !empty($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $bodyJsonForSign = !empty($bodyJson) ? preg_replace('/\s+/', '', $bodyJson) : '';
        
        // Generate signature BEFORE adding sign to params
        $sign = $this->generateSignature($path, $params, $bodyJsonForSign);
        
        // Add sign to params AFTER calculation
        $params['sign'] = $sign;
        
        // Build query string - ensure proper encoding
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $this->apiBase . $path . '?' . $queryString;
        
        // DEBUG OUTPUT
        if ($outputCallback) {
            $outputCallback('info', '');
            $outputCallback('info', '=== TikTok API Request Debug ===');
            $outputCallback('info', 'Method: ' . $method);
            $outputCallback('info', 'Path: ' . $path);
            $outputCallback('info', 'Params (before sign): ' . json_encode($params, JSON_PRETTY_PRINT));
            $outputCallback('info', 'Body JSON: ' . ($bodyJson ?: '(empty)'));
            $outputCallback('info', 'Body for signature: ' . ($bodyJsonForSign ?: '(empty)'));
            
            // Show signature calculation
            $debugParams = $params;
            unset($debugParams['sign']);
            ksort($debugParams);
            $stringToSign = $this->clientSecret . $path;
            foreach ($debugParams as $key => $value) {
                if ($value !== null && $value !== '') {
                    $stringToSign .= $key . (string)$value;
                }
            }
            if (!empty($bodyJsonForSign)) {
                $stringToSign .= $bodyJsonForSign;
            }
            $stringToSign .= $this->clientSecret;
            
            $outputCallback('info', 'String to sign (length: ' . strlen($stringToSign) . '): ' . substr($stringToSign, 0, 200) . '...');
            $outputCallback('info', 'Generated Signature: ' . $sign);
            $outputCallback('info', 'Client Secret (first 10 chars): ' . substr($this->clientSecret, 0, 10) . '...');
            $outputCallback('info', 'Full URL: ' . $url);
            $outputCallback('info', '===============================');
        }

        try {
            $headers = ['Content-Type' => 'application/json'];
            
            if ($method === 'GET') {
                $response = Http::timeout(30)->withHeaders($headers)->get($url);
            } else {
                $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
            }

            $data = $response->json();
            $this->lastResponse = $data;
            $this->lastResponseCode = $data['code'] ?? null;
            
            if ($outputCallback && isset($data['code']) && $data['code'] != 0) {
                $outputCallback('error', 'API Response Code: ' . ($data['code'] ?? 'unknown'));
                $outputCallback('error', 'API Response Message: ' . ($data['message'] ?? 'No message'));
            }

            if (isset($data['code']) && $data['code'] === 105001) {
                if ($this->refreshAccessToken()) {
                    return $this->apiRequest($method, $path, $queryParams, $body, $includeShopId, $outputCallback);
                }
            }

            return $data;
        } catch (\Exception $e) {
            if ($outputCallback) {
                $outputCallback('error', 'Exception: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Get orders from TikTok Shop - WORKING ENDPOINT
     */
    public function getOrders(int $startTime = null, int $endTime = null, int $pageSize = 100, string $cursor = ''): ?array
    {
        $path = '/api/orders/search';
        
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
     * Get shop info - using same pattern as orders
     */
    public function getShopInfo($outputCallback = null): ?array
    {
        $path = '/api/shop/get_authorized_shop';
        return $this->apiRequest('GET', $path, [], [], false, $outputCallback);
    }

    /**
     * Get products list - using EXACT same pattern as orders
     */
    public function getProducts(int $pageSize = 20, string $cursor = '', int $status = 0, $outputCallback = null): ?array
    {
        $path = '/api/products/search';

        $body = [
            'page_size' => min($pageSize, 50),
        ];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        if ($status > 0) {
            $body['product_status'] = $status;
        }

        return $this->apiRequest('POST', $path, [], $body, true, $outputCallback);
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
            $response = $this->getProducts(50, $cursor, $status);

            if (!$response || !isset($response['data'])) {
                Log::error('TikTok getAllProducts failed', ['response' => $response, 'page' => $page]);
                break;
            }

            if (isset($response['data']['products'])) {
                $allProducts = array_merge($allProducts, $response['data']['products']);
            }

            $hasMore = isset($response['data']['more']) && $response['data']['more'];
            $cursor = $response['data']['next_cursor'] ?? '';

            if (count($allProducts) > 10000) {
                Log::warning('TikTok getAllProducts: Reached safety limit of 10000 products');
                break;
            }

            $page++;
            usleep(200000);
        }

        return $allProducts;
    }

    /**
     * Get product inventory - using EXACT same pattern as orders
     */
    public function getProductInventory(array $productIds): ?array
    {
        $path = '/api/products/inventory/query';

        $body = [
            'product_id_list' => array_slice($productIds, 0, 50),
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get product analytics - using EXACT same pattern as orders
     */
    public function getProductAnalytics(int $startTime = null, int $endTime = null, array $productIds = []): ?array
    {
        $path = '/api/analytics/products/query';
        
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
            $body['product_id_list'] = array_slice($productIds, 0, 50);
        }

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get product details - using EXACT same pattern as orders
     */
    public function getProductDetails(array $productIds): ?array
    {
        $path = '/api/products/details';
        
        $body = [
            'product_id_list' => array_slice($productIds, 0, 50),
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get product reviews - using EXACT same pattern as orders
     */
    public function getProductReviews(array $productIds): ?array
    {
        $path = '/api/products/reviews/query';
        
        $body = [
            'product_id_list' => array_slice($productIds, 0, 50),
        ];

        return $this->apiRequest('POST', $path, [], $body);
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
            Log::info('TikTok: Fetching products...');
            $products = $this->getAllProducts(1);
            $result['products'] = $products;
            Log::info('TikTok: Fetched ' . count($products) . ' products');

            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $chunks = array_chunk($productIds, 50);
                
                Log::info('TikTok: Fetching inventory for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                
                foreach ($chunks as $chunk) {
                    $inventory = $this->getProductInventory($chunk);
                    if ($inventory && isset($inventory['data']['inventory_list'])) {
                        $result['inventory'] = array_merge($result['inventory'], $inventory['data']['inventory_list']);
                    }
                    usleep(200000);
                }
                Log::info('TikTok: Fetched inventory for ' . count($result['inventory']) . ' products');
            }

            Log::info('TikTok: Fetching analytics/views...');
            $analytics = $this->getProductAnalytics();
            if ($analytics && isset($analytics['data']['analytics_list'])) {
                $result['analytics'] = $analytics['data']['analytics_list'];
            }
            Log::info('TikTok: Fetched analytics for ' . count($result['analytics']) . ' products');
            
            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $chunks = array_chunk($productIds, 50);
                
                Log::info('TikTok: Fetching reviews for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                
                foreach ($chunks as $chunk) {
                    $reviews = $this->getProductReviews($chunk);
                    if ($reviews && isset($reviews['data']['reviews'])) {
                        $result['reviews'] = array_merge($result['reviews'], $reviews['data']['reviews']);
                    } elseif ($reviews && isset($reviews['data']['review_list'])) {
                        $result['reviews'] = array_merge($result['reviews'], $reviews['data']['review_list']);
                    }
                    usleep(200000);
                }
                Log::info('TikTok: Fetched reviews for ' . count($result['reviews']) . ' products');
            }

        } catch (\Exception $e) {
            Log::error('TikTok syncAllProductData error: ' . $e->getMessage());
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
    }
    
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }
    
    public function getLastResponseCode(): ?int
    {
        return $this->lastResponseCode;
    }
}
