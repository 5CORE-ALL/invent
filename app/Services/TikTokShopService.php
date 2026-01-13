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
    protected $signatureCallback = null;

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
     * TikTok Shop API signature format: app_secret + path + sorted_params + body + app_secret, then SHA256
     * Based on TikTok Shop API documentation: https://m.tiktok.shop/s/AIu6dbFhs2XW
     */
    protected function generateSignature(string $path, array $params, string $body = ''): string
    {
        // Remove sign from params if exists
        unset($params['sign']);
        
        // Sort parameters alphabetically by key (required by TikTok API)
        ksort($params);
        
        // TikTok Shop API signature format:
        // app_secret + path + sorted_params(key+value concatenated) + body + app_secret
        // Then calculate SHA256 hash
        
        $stringToSign = $this->clientSecret . $path;
        
        // Concatenate sorted parameters as key+value (no separators)
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                // Convert to string and ensure no extra encoding
                $stringToSign .= $key . (string)$value;
            }
        }
        
        // Append body if present (should already be minified JSON)
        if (!empty($body)) {
            $stringToSign .= $body;
        }
        
        // Append app_secret at the end
        $stringToSign .= $this->clientSecret;
        
        // Calculate SHA256 hash
        return hash('sha256', $stringToSign);
    }

    /**
     * Generate signature using alternative format (HMAC-SHA256 without clientSecret wrapper)
     */
    protected function generateSignatureAlternative(string $path, array $params, string $body = ''): string
    {
        // Remove sign from params
        unset($params['sign']);
        
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Build string: path + sorted_params + body
        $stringToSign = $path;
        
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . (string)$value;
            }
        }
        
        if (!empty($body)) {
            $stringToSign .= $body;
        }
        
        // Generate HMAC-SHA256 signature
        return hash_hmac('sha256', $stringToSign, $this->clientSecret);
    }
    
    /**
     * Generate signature format 3: params + body only
     */
    protected function generateSignatureFormat3(string $path, array $params, string $body = ''): string
    {
        unset($params['sign']);
        ksort($params);
        
        $stringToSign = '';
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $stringToSign .= $key . (string)$value;
            }
        }
        $stringToSign .= $body;
        
        return hash_hmac('sha256', $stringToSign, $this->clientSecret);
    }
    
    /**
     * Generate signature format 4: Sign by full URL (like signByUrl method)
     */
    protected function generateSignatureFormat4(string $fullUrl, string $body = ''): string
    {
        // Extract path and query string from URL
        $parsed = parse_url($fullUrl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        
        // Parse query string to array, remove sign
        parse_str($query, $params);
        unset($params['sign']);
        ksort($params);
        
        // Rebuild query string without sign
        $queryString = http_build_query($params);
        
        // String to sign: path + query + body
        $stringToSign = $path;
        if ($queryString) {
            $stringToSign .= '?' . $queryString;
        }
        $stringToSign .= $body;
        
        // HMAC-SHA256 with app_secret
        return hash_hmac('sha256', $stringToSign, $this->clientSecret);
    }
    
    /**
     * Generate signature format 5: app_secret + full_url + body + app_secret, then SHA256
     */
    protected function generateSignatureFormat5(string $fullUrl, string $body = ''): string
    {
        $parsed = parse_url($fullUrl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        
        parse_str($query, $params);
        unset($params['sign']);
        ksort($params);
        $queryString = http_build_query($params);
        
        $urlWithoutSign = $path;
        if ($queryString) {
            $urlWithoutSign .= '?' . $queryString;
        }
        
        $stringToSign = $this->clientSecret . $urlWithoutSign . $body . $this->clientSecret;
        return hash('sha256', $stringToSign);
    }
    
    /**
     * Generate signature from query string directly (Format 6)
     */
    protected function generateSignatureFromQueryString(string $path, string $queryString, string $body = ''): string
    {
        // Remove sign from query string if present
        $queryString = preg_replace('/(^|&)sign=[^&]*/', '', $queryString);
        $queryString = ltrim($queryString, '&');
        
        // String to sign: path + query + body
        $stringToSign = $path;
        if ($queryString) {
            $stringToSign .= '?' . $queryString;
        }
        $stringToSign .= $body;
        
        // HMAC-SHA256
        return hash_hmac('sha256', $stringToSign, $this->clientSecret);
    }
    
    /**
     * Generate signature with app_secret wrapper and query string (Format 7)
     */
    protected function generateSignatureFormat7(string $path, string $queryString, string $body = ''): string
    {
        $queryString = preg_replace('/(^|&)sign=[^&]*/', '', $queryString);
        $queryString = ltrim($queryString, '&');
        
        $urlPart = $path;
        if ($queryString) {
            $urlPart .= '?' . $queryString;
        }
        
        $stringToSign = $this->clientSecret . $urlPart . $body . $this->clientSecret;
        return hash('sha256', $stringToSign);
    }

    /**
     * Make authenticated API request
     */
    protected function apiRequest(string $method, string $path, array $queryParams = [], array $body = [], bool $includeShopId = true): ?array
    {
        if (!$this->accessToken) {
            // Try to refresh token
            if (!$this->refreshAccessToken()) {
                Log::error('TikTok API: No valid access token');
                return null;
            }
        }

        // Get current timestamp in milliseconds (TikTok API might expect milliseconds)
        // Also try seconds format
        $timestampMs = (int)(microtime(true) * 1000);
        $timestamp = time();
        
        // Required parameters for all requests (must be in alphabetical order for signature)
        // TikTok API might require specific format for shop_id and timestamp
        $params = [
            'access_token' => $this->accessToken,
            'app_key' => $this->clientKey,
            'timestamp' => (string)$timestamp, // Try seconds first
        ];
        
        // Some endpoints don't require shop_id (like get_authorized_shop)
        if ($includeShopId && !empty($this->shopId)) {
            $params['shop_id'] = (string)$this->shopId;
        }
        
        // Verify credentials are loaded (shop_id only required if includeShopId is true)
        if (empty($this->clientKey) || empty($this->clientSecret) || ($includeShopId && empty($this->shopId))) {
            Log::error('TikTok API: Missing credentials', [
                'has_client_key' => !empty($this->clientKey),
                'has_client_secret' => !empty($this->clientSecret),
                'has_shop_id' => !empty($this->shopId),
                'include_shop_id' => $includeShopId,
            ]);
            return ['code' => 999999, 'message' => 'Missing credentials', 'data' => null];
        }
        
        // Merge additional query params
        $params = array_merge($params, $queryParams);
        
        // Remove any null or empty values before signature calculation
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });

        // Generate signature BEFORE adding sign to params
        // TikTok API requires minified JSON (no spaces) for body in signature calculation
        $bodyJson = !empty($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        // Remove any whitespace from JSON body for signature calculation (TikTok API requirement)
        $bodyJsonForSign = !empty($bodyJson) ? preg_replace('/\s+/', '', $bodyJson) : '';
        $sign = $this->generateSignature($path, $params, $bodyJsonForSign);
        
        // Add sign to params AFTER calculation
        $params['sign'] = $sign;

        // Build URL with query parameters
        // Use the path as-is (caller should provide correct versioned path)
        // Note: http_build_query may URL encode which could affect signature
        // Try without encoding first for signature, then encode for URL
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $url = $this->apiBase . $path . '?' . $queryString;

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
            
            // Store response for external access
            $this->lastResponse = $data;
            $this->lastResponseCode = $data['code'] ?? null;
            
            // Log schema errors for debugging
            if (isset($data['code']) && $data['code'] == 40006) {
                Log::warning('TikTok API schema error (40006)', [
                    'path' => $path,
                    'method' => $method,
                    'url' => $url,
                    'response' => $data,
                ]);
            }
            
            
            // If signature error, try alternative signature formats
            if (isset($data['code']) && ($data['code'] == 106001 || (isset($data['message']) && strpos($data['message'], 'sign') !== false))) {
                // Ensure bodyJson is available for retry attempts (use minified version)
                if (!isset($bodyJsonForSign)) {
                    $bodyJsonForSign = !empty($body) ? preg_replace('/\s+/', '', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '';
                }
                
                // Try Format 1a: With URL-encoded parameter values
                $paramsForSign1a = [
                    'access_token' => $this->accessToken,
                    'app_key' => $this->clientKey,
                    'shop_id' => (string)$this->shopId,
                    'timestamp' => (string)$timestamp,
                ];
                $paramsForSign1a = array_merge($paramsForSign1a, $queryParams);
                $paramsForSign1a = array_filter($paramsForSign1a, function($value) {
                    return $value !== null && $value !== '';
                });
                ksort($paramsForSign1a);
                $stringToSign1a = $this->clientSecret . $path;
                foreach ($paramsForSign1a as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $stringToSign1a .= $key . rawurlencode((string)$value);
                    }
                }
                if (!empty($bodyJsonForSign)) {
                    $stringToSign1a .= $bodyJsonForSign;
                }
                $stringToSign1a .= $this->clientSecret;
                $altSign1a = hash('sha256', $stringToSign1a);
                $paramsForSign1a['sign'] = $altSign1a;
                $url = $this->apiBase . $path . '?' . http_build_query($paramsForSign1a);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 2: HMAC-SHA256 (path + params + body)
                $altSign2 = $this->generateSignatureAlternative($path, $params, $bodyJsonForSign ?? '');
                $params['sign'] = $altSign2;
                $url = $this->apiBase . $path . '?' . http_build_query($params);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 3: params + body only
                $altSign3 = $this->generateSignatureFormat3($path, $params, $bodyJsonForSign ?? '');
                $params['sign'] = $altSign3;
                $url = $this->apiBase . $path . '?' . http_build_query($params);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 4: Sign by full URL (signByUrl style)
                // Rebuild params without sign for Format 4
                $paramsForSign4 = [
                    'access_token' => $this->accessToken,
                    'app_key' => $this->clientKey,
                    'shop_id' => (string)$this->shopId,
                    'timestamp' => (string)$timestamp,
                ];
                $paramsForSign4 = array_merge($paramsForSign4, $queryParams);
                $paramsForSign4 = array_filter($paramsForSign4, function($value) {
                    return $value !== null && $value !== '';
                });
                $urlWithoutSign = $this->apiBase . $path . '?' . http_build_query($paramsForSign4);
                $altSign4 = $this->generateSignatureFormat4($urlWithoutSign, $bodyJsonForSign ?? '');
                $paramsForSign4['sign'] = $altSign4;
                $url = $this->apiBase . $path . '?' . http_build_query($paramsForSign4);
                $params = $paramsForSign4; // Update params for next attempts
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 5: app_secret + URL + body + app_secret
                // Rebuild params without sign for Format 5
                $paramsForSign5 = [
                    'access_token' => $this->accessToken,
                    'app_key' => $this->clientKey,
                    'shop_id' => (string)$this->shopId,
                    'timestamp' => (string)$timestamp,
                ];
                $paramsForSign5 = array_merge($paramsForSign5, $queryParams);
                $paramsForSign5 = array_filter($paramsForSign5, function($value) {
                    return $value !== null && $value !== '';
                });
                $urlWithoutSign = $this->apiBase . $path . '?' . http_build_query($paramsForSign5);
                $altSign5 = $this->generateSignatureFormat5($urlWithoutSign, $bodyJsonForSign ?? '');
                $paramsForSign5['sign'] = $altSign5;
                $url = $this->apiBase . $path . '?' . http_build_query($paramsForSign5);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 6: Sign from query string directly (HMAC)
                // Rebuild params without sign
                $paramsForSign6 = [
                    'access_token' => $this->accessToken,
                    'app_key' => $this->clientKey,
                    'shop_id' => (string)$this->shopId,
                    'timestamp' => (string)$timestamp,
                ];
                $paramsForSign6 = array_merge($paramsForSign6, $queryParams);
                $paramsForSign6 = array_filter($paramsForSign6, function($value) {
                    return $value !== null && $value !== '';
                });
                ksort($paramsForSign6);
                $queryStr = http_build_query($paramsForSign6, '', '&', PHP_QUERY_RFC3986);
                $altSign6 = $this->generateSignatureFromQueryString($path, $queryStr, $bodyJsonForSign ?? '');
                $paramsForSign6['sign'] = $altSign6;
                $url = $this->apiBase . $path . '?' . http_build_query($paramsForSign6);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // Try Format 7: app_secret + query string + body + app_secret
                $altSign7 = $this->generateSignatureFormat7($path, $queryStr, $bodyJsonForSign ?? '');
                $paramsForSign6['sign'] = $altSign7;
                $url = $this->apiBase . $path . '?' . http_build_query($paramsForSign6);
                
                if ($method === 'GET') {
                    $response = Http::timeout(30)->withHeaders($headers)->get($url);
                } else {
                    $response = Http::timeout(30)->withHeaders($headers)->post($url, $body);
                }
                $data = $response->json();
                
                if (!isset($data['code']) || ($data['code'] != 106001 && $data['code'] != 36009004)) {
                    return $data;
                }
                
                // All signature formats failed - log error but don't output to console
                Log::error('TikTok API signature validation failed', [
                    'path' => $path,
                    'response_code' => $data['code'] ?? null,
                    'response_message' => $data['message'] ?? null
                ]);
            }

            // Check for token expiry
            if (isset($data['code']) && $data['code'] === 105001) {
                // Token expired, refresh and retry
                if ($this->refreshAccessToken()) {
                    return $this->apiRequest($method, $path, $queryParams, $body, $includeShopId);
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
        // TikTok Shop API endpoint for getting authorized shop info
        $path = '/api/shop/get_authorized_shop';
        return $this->apiRequest('GET', $path, [], [], false);
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
     * Set callback for signature retry events (for command output)
     */
    public function setSignatureCallback(callable $callback): void
    {
        $this->signatureCallback = $callback;
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
        // TikTok Shop API endpoint for product search
        $path = '/api/product/202309/products/search';

        $body = [
            'page_size' => min($pageSize, 50), // Max 50 per TikTok API
        ];

        if ($cursor) {
            $body['cursor'] = $cursor;
        }

        if ($status > 0) {
            $body['product_status'] = $status;
        }

        return $this->apiRequest('POST', $path, [], $body);
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
        // TikTok Shop API endpoint for product inventory
        $path = '/api/product/202309/products/inventory/query';

        $body = [
            'product_id_list' => array_slice($productIds, 0, 50), // Limit to 50 per request
        ];

        return $this->apiRequest('POST', $path, [], $body);
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
        // TikTok Shop API endpoint for product analytics
        $path = '/api/analytics/202309/analytics/products/query';
        
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

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Get product details by product IDs
     * 
     * @param array $productIds Product IDs
     * @return array|null
     */
    public function getProductDetails(array $productIds): ?array
    {
        // TikTok Shop API endpoint for product details
        $path = '/api/product/202309/products/details';
        
        $body = [
            'product_id_list' => array_slice($productIds, 0, 50), // Limit to 50
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }
    
    /**
     * Get product reviews/ratings
     * 
     * @param array $productIds Product IDs to query
     * @return array|null
     */
    public function getProductReviews(array $productIds): ?array
    {
        // TikTok Shop API endpoint for product reviews
        $path = '/api/product/202309/products/reviews/query';
        
        $body = [
            'product_id_list' => array_slice($productIds, 0, 50), // Limit to 50
        ];

        return $this->apiRequest('POST', $path, [], $body);
    }

    /**
     * Sync all product data (price, stock, views, reviews) from TikTok API
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
            'reviews' => [],
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
            
            // 4. Get reviews/ratings for all products (in batches)
            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $chunks = array_chunk($productIds, 50); // TikTok API limit
                
                Log::info('TikTok: Fetching reviews for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                
                foreach ($chunks as $chunk) {
                    $reviews = $this->getProductReviews($chunk);
                    if ($reviews && isset($reviews['data']['reviews'])) {
                        $result['reviews'] = array_merge($result['reviews'], $reviews['data']['reviews']);
                    } elseif ($reviews && isset($reviews['data']['review_list'])) {
                        $result['reviews'] = array_merge($result['reviews'], $reviews['data']['review_list']);
                    }
                    usleep(200000); // Rate limit delay
                }
                Log::info('TikTok: Fetched reviews for ' . count($result['reviews']) . ' products');
            }

        } catch (\Exception $e) {
            Log::error('TikTok syncAllProductData error: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }
}
