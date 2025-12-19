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
        
        // Get tokens from cache/database
        $this->accessToken = Cache::get('tiktok_access_token');
        $this->refreshToken = Cache::get('tiktok_refresh_token');
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
     */
    protected function generateSignature(string $path, array $params, string $body = ''): string
    {
        // Sort parameters alphabetically
        ksort($params);
        
        // Build string to sign
        $stringToSign = $this->clientSecret . $path;
        
        foreach ($params as $key => $value) {
            $stringToSign .= $key . $value;
        }
        
        $stringToSign .= $body . $this->clientSecret;
        
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
        
        // Required parameters for all requests
        $params = array_merge([
            'app_key' => $this->clientKey,
            'timestamp' => $timestamp,
            'shop_id' => $this->shopId,
        ], $queryParams);

        // Generate signature
        $bodyJson = !empty($body) ? json_encode($body) : '';
        $params['sign'] = $this->generateSignature($path, $params, $bodyJson);
        $params['access_token'] = $this->accessToken;

        $url = $this->apiBase . $path . '?' . http_build_query($params);

        try {
            if ($method === 'GET') {
                $response = Http::timeout(30)->get($url);
            } else {
                $response = Http::timeout(30)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, $body);
            }

            $data = $response->json();

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
}
