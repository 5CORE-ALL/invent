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
    protected $accessToken;
    protected $refreshToken;
    protected $lastResponse = null;
    protected $lastResponseCode = null;

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
            
            if ($outputCallback) {
                $outputCallback('info', 'Using TikTok Shop PHP library (same as ship_hub)');
                $outputCallback('info', 'Calling: Authorization->getAuthorizedShop()');
            }
            
            $response = $this->client->Authorization->getAuthorizedShop();
            
            $this->lastResponse = $response;
            
            if ($outputCallback) {
                $outputCallback('info', 'Response received: ' . json_encode($response, JSON_PRETTY_PRINT));
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
     * Get products list using the library
     */
    public function getProducts(int $pageSize = 20, string $cursor = '', int $status = 0, $outputCallback = null): ?array
    {
        try {
            if (!$this->accessToken) {
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Build request body with required page_size
            // Library method likely expects body as single parameter (like inventorySearch)
            $body = [
                'page_size' => min($pageSize, 50),
            ];

            if ($cursor) {
                $body['cursor'] = $cursor;
            }

            if ($status > 0) {
                $body['product_status'] = $status;
            }

            // Call searchProducts with body as single parameter (consistent with inventorySearch)
            $response = $this->client->Product->searchProducts($body);
            $this->lastResponse = $response;
            
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            // Token expired - refresh and retry
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                $response = $this->client->Product->searchProducts($body);
                $this->lastResponse = $response;
                return $response;
            }
            Log::error('TikTok getProducts failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
            if ($outputCallback) {
                $outputCallback('error', 'Error getting products: ' . $e->getMessage());
            }
            Log::error('TikTok getProducts failed', ['error' => $e->getMessage()]);
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
            $response = $this->getProducts(50, $cursor, $status);

            if (!$response) {
                Log::error('TikTok getAllProducts failed', ['page' => $page]);
                break;
            }

            // Library returns data in different format
            $products = $response['data']['products'] ?? $response['products'] ?? [];
            if (!empty($products)) {
                $allProducts = array_merge($allProducts, $products);
            }

            $hasMore = isset($response['data']['more']) && $response['data']['more'];
            $cursor = $response['data']['next_cursor'] ?? $response['data']['cursor'] ?? $response['next_cursor'] ?? '';

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
     * Get product inventory using the library
     */
    public function getProductInventory(array $productIds): ?array
    {
        try {
            if (!$this->accessToken) {
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Use inventorySearch method from library
            $response = $this->client->Product->inventorySearch([
                'product_id_list' => array_slice($productIds, 0, 50),
            ]);
            $this->lastResponse = $response;
            
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                return $this->client->Product->inventorySearch([
                    'product_id_list' => array_slice($productIds, 0, 50),
                ]);
            }
            Log::error('TikTok getProductInventory failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
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

            // Use getShopProductPerformanceList for product analytics
            $response = $this->client->Analytics->getShopProductPerformanceList($params);
            $this->lastResponse = $response;
            
            return $response;
        } catch (\EcomPHP\TiktokShop\Errors\TokenException $e) {
            if ($this->refreshAccessToken()) {
                $this->client->setAccessToken($this->accessToken);
                return $this->client->Analytics->getShopProductPerformanceList($params);
            }
            Log::error('TikTok getProductAnalytics failed', ['error' => $e->getMessage()]);
            return null;
        } catch (\Exception $e) {
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
                return null;
            }
            
            $this->client->setAccessToken($this->accessToken);
            
            // Try to get reviews from product details (if available)
            // TikTok API might include reviews in product details
            $reviews = [];
            foreach (array_slice($productIds, 0, 50) as $productId) {
                $product = $this->client->Product->getProduct($productId);
                if (isset($product['data']['reviews']) || isset($product['reviews'])) {
                    $reviews[] = [
                        'product_id' => $productId,
                        'reviews' => $product['data']['reviews'] ?? $product['reviews'] ?? [],
                    ];
                }
                usleep(100000); // Rate limit
            }
            
            $this->lastResponse = ['reviews' => $reviews];
            return ['reviews' => $reviews];
        } catch (\Exception $e) {
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
                    if ($inventory) {
                        $inventoryList = $inventory['data']['inventory_list'] ?? $inventory['inventory_list'] ?? [];
                        $result['inventory'] = array_merge($result['inventory'], $inventoryList);
                    }
                    usleep(200000);
                }
                Log::info('TikTok: Fetched inventory for ' . count($result['inventory']) . ' products');
            }

            Log::info('TikTok: Fetching analytics/views...');
            $analytics = $this->getProductAnalytics();
            if ($analytics) {
                $result['analytics'] = $analytics['data']['product_performance_list'] ?? $analytics['product_performance_list'] ?? $analytics['data'] ?? [];
            }
            Log::info('TikTok: Fetched analytics for ' . count($result['analytics']) . ' products');
            
            if (!empty($products)) {
                $productIds = array_column($products, 'id');
                $chunks = array_chunk($productIds, 50);
                
                Log::info('TikTok: Fetching reviews for ' . count($productIds) . ' products in ' . count($chunks) . ' batches...');
                
                foreach ($chunks as $chunk) {
                    $reviews = $this->getProductReviews($chunk);
                    if ($reviews) {
                        $reviewList = $reviews['reviews'] ?? $reviews['data']['reviews'] ?? $reviews['review_list'] ?? [];
                        $result['reviews'] = array_merge($result['reviews'], $reviewList);
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
