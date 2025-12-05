<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductStockMapping;

class AmazonSpApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;

    public function __construct()
    {
        $this->clientId = env('SPAPI_CLIENT_ID');
        $this->clientSecret = env('SPAPI_CLIENT_SECRET');
        $this->refreshToken = env('SPAPI_REFRESH_TOKEN');
        $this->region = env('SPAPI_REGION', 'us-east-1');
        $this->marketplaceId = env('SPAPI_MARKETPLACE_ID');
        $this->awsAccessKey = env('AWS_ACCESS_KEY_ID');
        $this->awsSecretKey = env('AWS_SECRET_ACCESS_KEY');
        $this->endpoint = 'https://sellingpartnerapi-na.amazon.com';
    }
    
    /**
     * Force refresh access token by clearing cache and getting new one
     */
    public function forceRefreshAccessToken()
    {
        Cache::forget('amazon_spapi_access_token');
        Cache::forget('amazon_spapi_access_token_data');
        return $this->getAccessToken(true);
    }
    public function getAccessToken($forceRefresh = false)
    {
        // Use cache to prevent multiple simultaneous token requests
        $cacheKey = 'amazon_spapi_access_token';
        
        // **IMPROVED: Check if token is about to expire (add metadata to cache)**
        if (!$forceRefresh) {
            try {
                $cachedData = Cache::get($cacheKey . '_data');
                
                // Check if we have cached token with timestamp
                if ($cachedData && is_array($cachedData)) {
                    $token = $cachedData['token'] ?? null;
                    $timestamp = $cachedData['timestamp'] ?? null;
                    
                    if ($token && $timestamp) {
                        $age = now()->diffInMinutes($timestamp);
                        
                        // Only use cached token if less than 40 minutes old (20-min safety buffer before 60-min expiration)
                        if ($age < 40) {
                            Log::debug('Amazon SPAPI: Using cached token', ['age_minutes' => $age]);
                            return $token;
                        } else {
                            Log::info('Amazon SPAPI: Cached token too old, refreshing', ['age_minutes' => $age]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // If cache fails, log and continue without cache
                Log::warning('Amazon SPAPI: Cache read failed, continuing without cache', [
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::info('Amazon SPAPI: Force refresh requested, clearing cache');
        }
        
        // Use a lock to prevent multiple simultaneous token refresh requests
        $lockKey = 'amazon_spapi_token_refresh_lock';
        $lock = null;
        
        try {
            $lock = Cache::lock($lockKey, 10); // 10 second lock
            
            // Try to acquire lock, but don't wait if it fails
            if (!$lock->get()) {
                Log::warning('Amazon SPAPI: Could not acquire lock, proceeding without lock');
            }
        } catch (\Exception $e) {
            // Lock might not be supported on all cache drivers (e.g., file cache)
            Log::warning('Amazon SPAPI: Lock not available, proceeding without lock', [
                'error' => $e->getMessage()
            ]);
        }
        
        try {
            // Double-check cache after acquiring lock (another process might have refreshed it)
            if (!$forceRefresh) {
                try {
                    $cachedData = Cache::get($cacheKey . '_data');
                    if ($cachedData && is_array($cachedData)) {
                        $token = $cachedData['token'] ?? null;
                        $timestamp = $cachedData['timestamp'] ?? null;
                        
                        if ($token && $timestamp && now()->diffInMinutes($timestamp) < 40) {
                            if ($lock) {
                                $lock->release();
                            }
                            Log::debug('Amazon SPAPI: Another process refreshed token, using it');
                            return $token;
                        }
                    }
                } catch (\Exception $e) {
                    // Cache read failed, continue
                }
            }
            
            Log::info('Amazon SPAPI: Requesting new access token from Amazon');
            $client = new Client();
            $response = $client->post('https://api.amazon.com/auth/o2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'timeout' => 15, // Increased timeout
                'http_errors' => false // Don't throw exceptions on HTTP errors
            ]);

            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);
            
            if ($statusCode !== 200 || !isset($data['access_token'])) {
                $errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
                Log::error('Amazon SPAPI: Failed to get access token', [
                    'status_code' => $statusCode,
                    'response' => $data,
                    'error' => $errorMsg
                ]);
                throw new \Exception('Failed to get access token from Amazon API: ' . $errorMsg);
            }
            
            $accessToken = $data['access_token'];
            
            // **IMPROVED: Cache token with timestamp for better expiry tracking**
            try {
                $tokenData = [
                    'token' => $accessToken,
                    'timestamp' => now()
                ];
                Cache::put($cacheKey . '_data', $tokenData, now()->addMinutes(55));
                // Also keep simple token cache for backward compatibility
                Cache::put($cacheKey, $accessToken, now()->addMinutes(55));
                Log::info('Amazon SPAPI: New access token obtained and cached with timestamp');
            } catch (\Exception $e) {
                Log::warning('Amazon SPAPI: Failed to cache token, but token obtained', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return $accessToken;
        } catch (\Exception $e) {
            Log::error('Amazon SPAPI: Exception getting access token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (\Exception $e) {
                    // Ignore lock release errors
                }
            }
        }
    }

    private function getAccessTokenV1()
    {
        $res = Http::withoutVerifying()->asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => env('SPAPI_REFRESH_TOKEN'),
            'client_id' => env('SPAPI_CLIENT_ID'),
            'client_secret' => env('SPAPI_CLIENT_SECRET'),
        ]);
        return $res['access_token'] ?? null;
    }

    public function updateAmazonPriceUS($sku, $price, $maxRetries = 3)
    {
        // Validate inputs
        $sku = trim($sku);
        if (empty($sku)) {
            Log::error("Amazon Price Update: Empty SKU provided");
            return [
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'SKU is required and cannot be empty.'
                ]]
            ];
        }

        // Validate and format price
        $price = is_numeric($price) ? (float) $price : null;
        if ($price === null || $price <= 0) {
            Log::error("Amazon Price Update: Invalid price", ['sku' => $sku, 'price' => $price]);
            return [
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price must be a valid number greater than 0.'
                ]]
            ];
        }

        // Round price to 2 decimal places (Amazon requirement)
        $price = round($price, 2);

        $sellerId = env('AMAZON_SELLER_ID');
        if (empty($sellerId)) {
            Log::error("Amazon Price Update: Seller ID not configured");
            return [
                'errors' => [[
                    'code' => 'ConfigurationError',
                    'message' => 'Amazon Seller ID is not configured.'
                ]]
            ];
        }

        $amazonSku = null;
        $productType = null;
        $lastError = null;
        
        // **CRITICAL FIX: Retry loop with fresh token strategy**
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // **IMPORTANT: Check token age and force refresh if old**
                $tokenData = Cache::get('amazon_spapi_access_token_data');
                $forceRefresh = false;
                
                if ($attempt > 1) {
                    // Always force refresh on retry attempts
                    $forceRefresh = true;
                    Log::info("Amazon Price Update: Forcing fresh token on retry (Attempt {$attempt})", ['sku' => $sku]);
                } else if ($tokenData && is_array($tokenData) && isset($tokenData['timestamp'])) {
                    // On first attempt, check if token is older than 40 minutes
                    $tokenAge = now()->diffInMinutes($tokenData['timestamp']);
                    if ($tokenAge > 40) {
                        $forceRefresh = true;
                        Log::info("Amazon Price Update: Token is old ({$tokenAge} min), forcing refresh", ['sku' => $sku]);
                    }
                } else {
                    // No token metadata, force refresh to be safe
                    $forceRefresh = true;
                    Log::info("Amazon Price Update: No token metadata, forcing refresh", ['sku' => $sku]);
                }
                
                $accessToken = $this->getAccessToken($forceRefresh);
                
                if (empty($accessToken)) {
                    Log::error("Amazon Price Update: Failed to get access token", ['sku' => $sku, 'attempt' => $attempt]);
                    $lastError = [
                        'errors' => [[
                            'code' => 'AuthenticationError',
                            'message' => 'Failed to authenticate with Amazon API.'
                        ]]
                    ];
                    
                    if ($attempt < $maxRetries) {
                        sleep(1); // Wait before retry
                        continue;
                    }
                    return $lastError;
                }

                // Find the correct SKU format in Amazon (only on first attempt)
                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        Log::error("Amazon Price Update: SKU not found in Amazon", ['sku' => $sku]);
                        return [
                            'errors' => [[
                                'code' => 'InvalidInput',
                                'message' => 'SKU not found in Amazon. Please ensure the SKU exists in your Amazon listings.'
                            ]]
                        ];
                    }
                }
                
                // Get product type (only on first attempt)
                if ($productType === null) {
                    $productType = $this->getAmazonProductType($sku, $amazonSku, $accessToken);
                    if (empty($productType)) {
                        Log::error("Amazon Price Update: Product type not found", [
                            'sku' => $sku,
                            'amazon_sku' => $amazonSku,
                            'attempt' => $attempt
                        ]);
                        return [
                            'errors' => [[
                                'code' => 'InvalidInput',
                                'message' => 'Product type not found for SKU. Please ensure the SKU exists in Amazon.'
                            ]]
                        ];
                    }
                }

                // Use the correct Amazon SKU format for the API call
                $encodedSku = rawurlencode($amazonSku);
                $endpoint = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

                // Build request body with proper structure
                $body = [
                    "productType" => $productType,
                    "patches" => [[
                        "op" => "replace",
                        "path" => "/attributes/purchasable_offer",
                        "value" => [[
                            "marketplaceId" => "ATVPDKIKX0DER",
                            "currency" => "USD",
                            "our_price" => [
                                [
                                    "schedule" => [
                                        [
                                            "value_with_tax" => $price
                                        ]
                                    ]
                                ]
                            ]
                        ]]
                    ]]
                ];

                // Log request for debugging
                Log::info("Amazon Price Update Request (Attempt {$attempt}/{$maxRetries})", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku,
                    "price" => $price,
                    "productType" => $productType,
                    "token_fresh" => true
                ]);

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'content-type' => 'application/json',
                        'accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->patch($endpoint, $body);

                $responseData = $response->json();

                // Check for errors in response
                if ($response->failed()) {
                    Log::error("Amazon Price Update Failed (Attempt {$attempt}/{$maxRetries})", [
                        "original_sku" => $sku,
                        "amazon_sku" => $amazonSku,
                        "price" => $price,
                        "status" => $response->status(),
                        "response" => $responseData
                    ]);

                    $lastError = $responseData ?: [
                        'errors' => [[
                            'code' => 'RequestFailed',
                            'message' => 'Failed to update price on Amazon. HTTP Status: ' . $response->status()
                        ]]
                    ];
                    
                    // Retry on auth errors (401, 403) or server errors (5xx)
                    if ($response->status() === 401 || $response->status() === 403 || $response->status() >= 500) {
                        if ($attempt < $maxRetries) {
                            Log::info("Retrying due to status {$response->status()}...");
                            sleep(1);
                            continue;
                        }
                    }
                    
                    // For other errors (4xx), return immediately
                    return $lastError;
                }

                // Response successful - check if it contains errors in data
                if (isset($responseData['errors']) && !empty($responseData['errors'])) {
                    Log::error("Amazon Price Update: API returned errors (Attempt {$attempt}/{$maxRetries})", [
                        "sku" => $sku,
                        "errors" => $responseData['errors']
                    ]);
                    
                    $lastError = $responseData;
                    
                    // Check if it's an auth-related error
                    $hasAuthError = false;
                    foreach ($responseData['errors'] as $error) {
                        $errorMsg = $error['message'] ?? '';
                        if (stripos($errorMsg, 'authentication') !== false || 
                            stripos($errorMsg, 'unauthorized') !== false ||
                            stripos($errorMsg, 'invalid_client') !== false) {
                            $hasAuthError = true;
                            break;
                        }
                    }
                    
                    if ($hasAuthError && $attempt < $maxRetries) {
                        Log::info("Auth error detected in response, retrying...");
                        sleep(1);
                        continue;
                    }
                    
                    // Return error
                    return $responseData;
                }

                // Success - log and return
                Log::info("Amazon Price Update: SUCCESS (Attempt {$attempt}/{$maxRetries})", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku,
                    "price" => $price,
                    "response" => $responseData
                ]);
                
                return $responseData ?: ['success' => true];
                
            } catch (\Exception $e) {
                Log::error("Amazon Price Update Exception (Attempt {$attempt}/{$maxRetries})", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku ?: 'not_found',
                    "price" => $price,
                    "error" => $e->getMessage(),
                    "trace" => $e->getTraceAsString()
                ]);

                $lastError = [
                    'errors' => [[
                        'code' => 'Exception',
                        'message' => 'An error occurred while updating price: ' . $e->getMessage()
                    ]]
                ];
                
                // Retry on exceptions
                if ($attempt < $maxRetries) {
                    sleep(1);
                    continue;
                }
            }
        }
        
        // All retries failed - return last error
        return $lastError ?: [
            'errors' => [[
                'code' => 'UpdateFailed',
                'message' => 'Failed to update price after ' . $maxRetries . ' attempts.'
            ]]
        ];
    }
    
    /**
     * Verify that the price was actually updated on Amazon
     * Returns: true if verified, false if price doesn't match, null if unable to verify
     */
    private function verifyPriceUpdate($amazonSku, $expectedPrice, $accessToken = null)
    {
        try {
            $sellerId = env('AMAZON_SELLER_ID');
            if (empty($sellerId)) {
                Log::warning("Price verification: Seller ID not configured");
                return null;
            }
            
            // Get fresh token if not provided
            if (empty($accessToken)) {
                $accessToken = $this->getAccessToken();
                if (empty($accessToken)) {
                    Log::warning("Price verification: Could not get access token");
                    return null;
                }
            }
            
            $encodedSku = rawurlencode($amazonSku);
            $url = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";
            
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url);
            
            if ($response->failed()) {
                Log::warning("Price verification: Failed to fetch data", [
                    'sku' => $amazonSku,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
            
            $data = $response->json();
            
            // Extract current price from response - try multiple paths
            $currentPrice = null;
            
            // Path 1: summaries > offers > ourPrice
            if (isset($data['summaries'][0]['offers'])) {
                foreach ($data['summaries'][0]['offers'] as $offer) {
                    if (isset($offer['ourPrice'][0]['schedule'][0]['value_with_tax'])) {
                        $currentPrice = (float) $offer['ourPrice'][0]['schedule'][0]['value_with_tax'];
                        break;
                    }
                }
            }
            
            // Path 2: attributes > purchasable_offer
            if ($currentPrice === null && isset($data['attributes']['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax'])) {
                $currentPrice = (float) $data['attributes']['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax'];
            }
            
            if ($currentPrice === null) {
                Log::warning("Could not extract price from verification response", [
                    'sku' => $amazonSku,
                    'has_summaries' => isset($data['summaries']),
                    'has_attributes' => isset($data['attributes']),
                    'response_keys' => array_keys($data)
                ]);
                return null;
            }
            
            // Compare prices (allow 0.02 difference for rounding)
            $priceDiff = abs($currentPrice - $expectedPrice);
            $verified = $priceDiff < 0.02;
            
            Log::info("Price verification result", [
                'sku' => $amazonSku,
                'expected_price' => $expectedPrice,
                'current_price' => $currentPrice,
                'difference' => $priceDiff,
                'verified' => $verified ? 'YES' : 'NO'
            ]);
            
            return $verified;
            
        } catch (\Exception $e) {
            Log::warning("Price verification exception", [
                'sku' => $amazonSku,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get current price from Amazon for a SKU
     * Returns: price as float, or null if not found
     */
    public function getCurrentAmazonPrice($sku)
    {
        try {
            $sellerId = env('AMAZON_SELLER_ID');
            if (empty($sellerId)) {
                return null;
            }
            
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                return null;
            }
            
            $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
            if (empty($amazonSku)) {
                return null;
            }
            
            $encodedSku = rawurlencode($amazonSku);
            $url = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";
            
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url);
            
            if ($response->failed()) {
                return null;
            }
            
            $data = $response->json();
            
            // Extract current price
            if (isset($data['summaries'][0]['offers'])) {
                foreach ($data['summaries'][0]['offers'] as $offer) {
                    if (isset($offer['ourPrice'][0]['schedule'][0]['value_with_tax'])) {
                        return (float) $offer['ourPrice'][0]['schedule'][0]['value_with_tax'];
                    }
                }
            }
            
            if (isset($data['attributes']['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax'])) {
                return (float) $data['attributes']['purchasable_offer'][0]['our_price'][0]['schedule'][0]['value_with_tax'];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Error getting current Amazon price", [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find the correct SKU format in Amazon by trying different case variations
     * Returns the SKU format that works with Amazon API, or null if not found
     */
    private function findAmazonSkuFormat($sku, $accessToken = null)
    {
        $sku = trim($sku);
        if (empty($sku)) {
            return null;
        }

        $sellerId = env('AMAZON_SELLER_ID');
        if (empty($sellerId)) {
            return null;
        }

        // Use provided token or get new one
        if (empty($accessToken)) {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                return null;
            }
        }

        // Generate case variations to try
        $variations = [
            $sku, // Original
            strtoupper($sku), // All uppercase
            strtolower($sku), // All lowercase
            ucfirst(strtolower($sku)), // First letter uppercase
            ucwords(strtolower($sku)), // Title case (each word capitalized)
        ];

        // Remove duplicates while preserving order
        $variations = array_values(array_unique($variations));

        foreach ($variations as $skuVariation) {
            try {
                $encodedSku = rawurlencode($skuVariation);
                $url = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($url);

                // If successful (200), return this SKU format
                if ($response->successful()) {
                    $data = $response->json();
                    // Check if we got valid data
                    if (isset($data['summaries']) && !empty($data['summaries'])) {
                        Log::info("Found matching SKU format in Amazon", [
                            'original_sku' => $sku,
                            'amazon_sku' => $skuVariation
                        ]);
                        return $skuVariation;
                    }
                }

                // If 404, try next variation
                // If other error (like 400 InvalidInput), also try next variation
                if ($response->status() === 404 || $response->status() === 400) {
                    continue;
                }

                // For other errors, log and continue
                if ($response->failed()) {
                    Log::debug("SKU variation failed", [
                        'sku_variation' => $skuVariation,
                        'status' => $response->status()
                    ]);
                    continue;
                }
            } catch (\Exception $e) {
                // Continue to next variation on exception
                Log::debug("Exception trying SKU variation", [
                    'sku_variation' => $skuVariation,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // None of the variations worked
        Log::warning("Could not find matching SKU format in Amazon", [
            'original_sku' => $sku,
            'tried_variations' => $variations
        ]);
        return null;
    }

    public function getAmazonProductType($sku, $amazonSku = null, $accessToken = null)
    {
        try {
            $sku = trim($sku);
            if (empty($sku)) {
                Log::warning("getAmazonProductType: Empty SKU provided");
                return null;
            }

            $sellerId = env('AMAZON_SELLER_ID');
            if (empty($sellerId)) {
                Log::warning("getAmazonProductType: Seller ID not configured");
                return null;
            }

            // Use provided token or get new one
            if (empty($accessToken)) {
                $accessToken = $this->getAccessToken();
                if (empty($accessToken)) {
                    Log::warning("getAmazonProductType: Failed to get access token", ['sku' => $sku]);
                    return null;
                }
            }

            // Use provided Amazon SKU or find it
            if (empty($amazonSku)) {
                $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                if (empty($amazonSku)) {
                    Log::warning("getAmazonProductType: Could not find SKU in Amazon", ['sku' => $sku]);
                    return null;
                }
            }

            // Use the correct SKU format to get product type
            $encodedSku = rawurlencode($amazonSku);
            $url = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url);

            if ($response->failed()) {
                Log::warning("getAmazonProductType: Failed to get product type", [
                    'sku' => $sku,
                    'amazon_sku' => $amazonSku,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }

            $data = $response->json();
            $productType = $data['summaries'][0]['productType'] ?? null;

            if (empty($productType)) {
                Log::warning("getAmazonProductType: Product type not found in response", [
                    'sku' => $sku,
                    'amazon_sku' => $amazonSku,
                    'response' => $data
                ]);
            }

            return $productType;
        } catch (\Exception $e) {
            Log::error("getAmazonProductType: Exception", [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

 public function getinventory()
{
   $accessToken = $this->getAccessTokenV1();
        info('Access Token', [$accessToken]);

        $marketplaceId = env('SPAPI_MARKETPLACE_ID');

        // Step 1: Request the report
        $response = Http::withoutVerifying()->withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->post('https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports', [
            'reportType' => 'GET_MERCHANT_LISTINGS_ALL_DATA',
            'marketplaceIds' => [$marketplaceId],
        ]);

        Log::error('Report Request Response: ' . $response->body());
        $reportId = $response['reportId'] ?? null;
        if (!$reportId) {
            Log::error('Failed to request report.');
            return;
        }

        // Step 2: Wait for report generation
        do {
            sleep(15);
            $status = Http::withoutVerifying()->withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->get("https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports/{$reportId}");
            $processingStatus = $status['processingStatus'] ?? 'UNKNOWN';
            Log::info("Waiting... Status: $processingStatus");
        } while ($processingStatus !== 'DONE');

        $documentId = $status['reportDocumentId'];
        $doc = Http::withoutVerifying()->withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->get("https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/documents/{$documentId}");

        $url = $doc['url'] ?? null;
        $compression = $doc['compressionAlgorithm'] ?? 'GZIP';


        if (!$url) {
            Log::error('Document URL not found.');
            return;
        }

        // Step 3: Download and parse the data
            $csv = file_get_contents($url);
            $csv = strtoupper($compression) === 'GZIP' ? gzdecode($csv) : $csv;
        if (!$csv) {
            Log::error('Failed to decode report content.');
            return;
        }


        $lines = explode("\n", $csv);
        $headers = explode("\t", array_shift($lines));

        foreach ($lines as $line) {
            $row = str_getcsv($line, "\t");
            if (count($row) < count($headers)) continue;

            $data = array_combine($headers, $row);

            // Fulfillment channel filter
            // if (($data['fulfillment-channel'] ?? '') !== 'DEFAULT') continue;

            $asin = $data['asin1'] ?? null;
            $sku = isset($data['seller-sku']) ? preg_replace('/[^\x20-\x7E]/', '', trim($data['seller-sku'])) : null;
            $price = isset($data['price']) && is_numeric($data['price']) ? $data['price'] : null;
            $quantity = isset($data['quantity']) && is_numeric($data['quantity']) ? $data['quantity'] : null;
            ProductStockMapping::where('sku', $sku)->update([
                    'inventory_amazon' => $quantity,
                ]);
        }
    }

    public function getFbaShipments($status = null, $marketplaceId = null, $lastUpdatedAfter = null, $lastUpdatedBefore = null)
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Could not obtain access token');
            }

            $url = $this->endpoint . '/fba/inbound/v0/shipments';

            // Build query parameters
            $queryParams = ['QueryType' => 'SHIPMENT'];
            
            // Add marketplace if specified
            if ($marketplaceId) {
                $queryParams['MarketplaceId'] = $marketplaceId;
            }
            
            // Add date filters if specified
            if ($lastUpdatedAfter) {
                $queryParams['LastUpdatedAfter'] = $lastUpdatedAfter;
            }
            if ($lastUpdatedBefore) {
                $queryParams['LastUpdatedBefore'] = $lastUpdatedBefore;
            }
            
            // Add shipment status list - Amazon API expects comma-separated values
            if ($status) {
                $statuses = is_array($status) ? $status : [$status];
                $queryParams['ShipmentStatusList'] = implode(',', $statuses);
            } else {
                // Default statuses to get all shipments
                $queryParams['ShipmentStatusList'] = 'WORKING,SHIPPED,IN_TRANSIT,DELIVERED,CHECKED_IN,RECEIVING,CLOSED,CANCELLED,DELETED,ERROR';
            }

            $allShipments = [];
            $nextToken = null;
            $pageCount = 0;
            
            // Paginate through all results
            do {
                $pageCount++;
                if ($nextToken) {
                    $queryParams['NextToken'] = $nextToken;
                }
                
                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(120) // 120 second timeout (Amazon API is slow)
                ->retry(2, 1000) // Retry twice with 1 second delay
                ->get($url, $queryParams);

                if ($response->failed()) {
                    Log::error('FBA Shipments API failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'url' => $url,
                        'params' => $queryParams
                    ]);
                    throw new \Exception('API request failed: ' . $response->status() . ' - ' . $response->body());
                }
                
                $data = $response->json();
                
                // Add shipments from this page
                if (isset($data['payload']['ShipmentData'])) {
                    $shipmentsInPage = count($data['payload']['ShipmentData']);
                    $allShipments = array_merge($allShipments, $data['payload']['ShipmentData']);
                    Log::info("FBA Shipments API - Page {$pageCount}: fetched {$shipmentsInPage} shipments, total so far: " . count($allShipments));
                }
                
                // Get next token for pagination
                $nextToken = $data['payload']['NextToken'] ?? null;
                
                if ($nextToken) {
                    Log::info("FBA Shipments API - NextToken received, fetching next page...");
                }
                
                // Rate limiting - small delay between requests
                if ($nextToken) {
                    usleep(500000); // 0.5 second delay
                }
                
            } while ($nextToken);
            
            Log::info("FBA Shipments API - Completed. Total pages: {$pageCount}, Total shipments: " . count($allShipments));
            
            // Return all shipments in standard format
            return [
                'payload' => [
                    'ShipmentData' => $allShipments
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching FBA shipments', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch FBA shipments with streaming/callback approach
     * Calls the callback function after each page is fetched
     * This allows processing data incrementally instead of waiting for all pages
     * 
     * CRITICAL FIX: Added QueryType parameter based on Amazon SP-API documentation
     * - QueryType=SHIPMENT: Use when filtering by ShipmentStatusList/ShipmentIdList WITHOUT dates
     * - QueryType=DATE_RANGE: Use when filtering by LastUpdatedAfter/LastUpdatedBefore WITH dates
     * - QueryType=NEXT_TOKEN: Automatically used when NextToken is present
     */
    public function getFbaShipmentsStreaming($status = null, $marketplaceId = null, $lastUpdatedAfter = null, $lastUpdatedBefore = null, $callback = null)
    {
        try {
            $url = $this->endpoint . '/fba/inbound/v0/shipments';

            // Build query parameters based on documentation requirements
            $queryParams = [];
            
            // Default marketplace to US if not specified
            if (!$marketplaceId) {
                $marketplaceId = 'ATVPDKIKX0DER'; // Amazon US marketplace
            }
            $queryParams['MarketplaceId'] = $marketplaceId;
            
            // CRITICAL FIX: Use proper QueryType based on filter type
            // According to Amazon SP-API docs, QueryType determines which parameters are valid
            if ($lastUpdatedAfter || $lastUpdatedBefore) {
                // Use DATE_RANGE query type when filtering by dates
                $queryParams['QueryType'] = 'DATE_RANGE';
                
                if ($lastUpdatedAfter) {
                    $queryParams['LastUpdatedAfter'] = $lastUpdatedAfter;
                }
                
                if ($lastUpdatedBefore) {
                    $queryParams['LastUpdatedBefore'] = $lastUpdatedBefore;
                }
                
                // When using DATE_RANGE, we can optionally filter by status
                if ($status) {
                    $statuses = is_array($status) ? $status : [$status];
                    $queryParams['ShipmentStatusList'] = implode(',', $statuses);
                }
            } else {
                // Use SHIPMENT query type when filtering by status without dates
                $queryParams['QueryType'] = 'SHIPMENT';
                
                if ($status) {
                    $statuses = is_array($status) ? $status : [$status];
                    $queryParams['ShipmentStatusList'] = implode(',', $statuses);
                } else {
                    // Default to all statuses if none specified
                    $queryParams['ShipmentStatusList'] = 'WORKING,SHIPPED,IN_TRANSIT,DELIVERED,CHECKED_IN,RECEIVING,CLOSED,CANCELLED,DELETED,ERROR';
                }
            }

            $nextToken = null;
            $pageCount = 0;
            $totalShipments = 0;
            
            // Paginate and process each page immediately
            do {
                $pageCount++;
                
                // Get fresh access token for each page to avoid expiration
                $accessToken = $this->getAccessToken();
                if (!$accessToken) {
                    throw new \Exception('Could not obtain access token for page ' . $pageCount);
                }
                
                if ($nextToken) {
                    $queryParams['NextToken'] = $nextToken;
                    // When using NextToken, QueryType must be NEXT_TOKEN
                    $queryParams['QueryType'] = 'NEXT_TOKEN';
                }
                
                // DEBUG: Log exact API call details
                Log::info("FBA API Call - Page {$pageCount}", [
                    'url' => $url,
                    'query_params' => $queryParams,
                    'has_next_token' => !empty($nextToken)
                ]);
                
                try {
                    $response = Http::withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(120)
                    ->retry(2, 1000)
                    ->get($url, $queryParams);

                    if ($response->failed()) {
                        $errorBody = $response->body();
                        Log::error('FBA Shipments API failed', [
                            'page' => $pageCount,
                            'status' => $response->status(),
                            'body' => $errorBody,
                        ]);
                        
                        // If 403 Unauthorized, try to get fresh token and retry once
                        if ($response->status() === 403) {
                            Log::warning("Got 403 on page {$pageCount}, clearing token cache and retrying...");
                            Cache::forget('amazon_spapi_access_token');
                            $accessToken = $this->getAccessToken();
                            
                            $response = Http::withHeaders([
                                'x-amz-access-token' => $accessToken,
                                'Content-Type' => 'application/json',
                            ])
                            ->timeout(120)
                            ->get($url, $queryParams);
                            
                            if ($response->failed()) {
                                throw new \Exception('API request failed after token refresh: ' . $response->status() . ' - ' . $errorBody);
                            }
                        } else {
                            throw new \Exception('API request failed: ' . $response->status() . ' - ' . $errorBody);
                        }
                    }
                    
                    $data = $response->json();
                    
                    // Process this page immediately via callback
                    if (isset($data['payload']['ShipmentData']) && is_callable($callback)) {
                        $shipments = $data['payload']['ShipmentData'];
                        $shipmentsInPage = count($shipments);
                        $totalShipments += $shipmentsInPage;
                        
                        // Log first 3 shipment IDs from this page for debugging
                        $shipmentIds = array_slice(array_map(function($s) {
                            return $s['ShipmentId'] ?? 'unknown';
                        }, $shipments), 0, 3);
                        
                        Log::info("FBA Streaming - Page {$pageCount}: fetched {$shipmentsInPage} shipments, calling callback...", [
                            'sample_ids' => implode(', ', $shipmentIds)
                        ]);
                        
                        // Call the callback to process this page
                        try {
                            $callback($shipments);
                            Log::info("FBA Streaming - Page {$pageCount}: callback completed successfully");
                        } catch (\Exception $callbackError) {
                            Log::error("FBA Streaming - Page {$pageCount}: callback error", [
                                'error' => $callbackError->getMessage()
                            ]);
                            // Continue to next page even if callback fails
                        }
                    }
                    
                    $nextToken = $data['payload']['NextToken'] ?? null;
                    
                    if ($nextToken) {
                        usleep(500000); // 0.5 second delay between requests
                    }
                    
                } catch (\Exception $pageError) {
                    Log::error("FBA Streaming - Page {$pageCount}: error", [
                        'error' => $pageError->getMessage(),
                        'total_shipments_processed' => $totalShipments
                    ]);
                    
                    // Don't throw - just break the loop and return what we've processed so far
                    Log::warning("Stopping pagination due to error. Processed {$pageCount} pages with {$totalShipments} total shipments.");
                    break;
                }
                
            } while ($nextToken);
            
            Log::info("FBA Streaming - Completed. Total pages: {$pageCount}, Total shipments: {$totalShipments}");
            
        } catch (\Exception $e) {
            Log::error('Error in FBA shipments streaming', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFbaShipmentItems($shipmentId)
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Could not obtain access token');
            }

            $url = $this->endpoint . "/fba/inbound/v0/shipments/{$shipmentId}/items";

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->failed()) {
                Log::error('FBA Shipment Items API failed', [
                    'shipment_id' => $shipmentId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('API request failed: ' . $response->status());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error fetching FBA shipment items', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
