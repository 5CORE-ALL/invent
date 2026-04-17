<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\AmazonListingRaw;
use App\Models\ProductStockMapping;
use App\Services\Support\AplusContentDocumentParser;
use App\Services\Support\DescriptionWithImagesFormatter;

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
        $this->clientId = config('services.amazon_sp.client_id');
        $this->clientSecret = config('services.amazon_sp.client_secret');
        $this->refreshToken = config('services.amazon_sp.refresh_token');
        $this->region = config('services.amazon_sp.region');
        $this->marketplaceId = config('services.amazon_sp.marketplace_id');
        $this->awsAccessKey = config('services.amazon_sp.aws_access_key');
        $this->awsSecretKey = config('services.amazon_sp.aws_secret_key');
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
            'refresh_token' => config('services.amazon_sp.refresh_token'),
            'client_id' => config('services.amazon_sp.client_id'),
            'client_secret' => config('services.amazon_sp.client_secret'),
        ]);
        return $res['access_token'] ?? null;
    }

    /**
     * Normalize seller SKU for Listings Items API (NBSP / unicode spaces → ASCII space, collapse runs).
     */
    private function normalizeListingsSellerSku(string $sku): string
    {
        $s = trim(str_replace("\xc2\xa0", ' ', $sku));
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/\p{Z}+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    public function updateAmazonPriceUS($sku, $price, $maxRetries = 3)
    {
        // Validate inputs
        $sku = $this->normalizeListingsSellerSku(trim((string) $sku));
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

        $sellerId = config('services.amazon_sp.seller_id');
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
                        sleep(0.5); // Wait before retry
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
                if (!is_array($responseData)) {
                    $responseData = [];
                }

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

                $patchFailure = $this->listingsPatchFailureFromResponseBody($responseData);
                if ($patchFailure !== null) {
                    Log::error("Amazon Price Update: listing patch not accepted (Attempt {$attempt}/{$maxRetries})", [
                        'original_sku' => $sku,
                        'amazon_sku' => $amazonSku,
                        'price' => $price,
                        'response' => $responseData,
                    ]);
                    return $patchFailure;
                }

                $confirmFailure = $this->confirmPriceAfterListingsPatch($amazonSku, $price, $accessToken);
                if ($confirmFailure !== null) {
                    $lastError = $confirmFailure;
                    Log::warning("Amazon Price Update: post-patch verification did not succeed (Attempt {$attempt}/{$maxRetries})", [
                        'original_sku' => $sku,
                        'amazon_sku' => $amazonSku,
                        'price' => $price,
                        'patch_response' => $responseData,
                        'errors' => $confirmFailure['errors'] ?? $confirmFailure,
                    ]);
                    if ($attempt < $maxRetries) {
                        sleep(2);
                        continue;
                    }
                    Log::error('Amazon Price Update: post-patch verification failed after all attempts', [
                        'original_sku' => $sku,
                        'amazon_sku' => $amazonSku,
                        'price' => $price,
                    ]);
                    return $confirmFailure;
                }

                Log::info("Amazon Price Update: SUCCESS and verified (Attempt {$attempt}/{$maxRetries})", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku,
                    "price" => $price,
                    "response" => $responseData
                ]);

                return !empty($responseData) ? $responseData : ['success' => true, 'status' => 'ACCEPTED'];
                
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
     * Update product title (item_name) on Amazon via Listings Items API PATCH.
     * Returns ['success' => true] or ['errors' => [['code' => ..., 'message' => ...]]].
     */
    public function updateAmazonTitle($sku, $title, $maxRetries = 2)
    {
        $sku = trim($sku);
        $title = trim($title);
        if (empty($sku)) {
            Log::error('Amazon Title Update: Empty SKU provided');
            return ['errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]];
        }
        if (strlen($title) === 0) {
            Log::error('Amazon Title Update: Empty title provided', ['sku' => $sku]);
            return ['errors' => [['code' => 'InvalidInput', 'message' => 'Title cannot be empty.']]];
        }

        $sellerId = config('services.amazon_sp.seller_id');
        if (empty($sellerId)) {
            Log::error('Amazon Title Update: Seller ID not configured');
            return ['errors' => [['code' => 'ConfigurationError', 'message' => 'Amazon Seller ID is not configured.']]];
        }

        $amazonSku = null;
        $productType = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $forceRefresh = $attempt > 1;
                $accessToken = $this->getAccessToken($forceRefresh);
                if (empty($accessToken)) {
                    $lastError = ['errors' => [['code' => 'AuthenticationError', 'message' => 'Failed to get Amazon access token.']]];
                    if ($attempt < $maxRetries) {
                        sleep(1);
                        continue;
                    }
                    return $lastError;
                }

                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        Log::error('Amazon Title Update: SKU not found in Amazon', ['sku' => $sku]);
                        return ['errors' => [['code' => 'InvalidInput', 'message' => 'SKU not found in Amazon.']]];
                    }
                }

                if ($productType === null) {
                    $productType = $this->getAmazonProductType($sku, $amazonSku, $accessToken);
                    if (empty($productType)) {
                        Log::error('Amazon Title Update: Product type not found', ['sku' => $sku]);
                        return ['errors' => [['code' => 'InvalidInput', 'message' => 'Product type not found for SKU.']]];
                    }
                }

                $encodedSku = rawurlencode($amazonSku);
                $endpoint = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

                $body = [
                    'productType' => $productType,
                    'patches' => [
                        [
                            'op' => 'replace',
                            'path' => '/attributes/item_name',
                            'value' => [
                                [
                                    'value' => $title,
                                    'language_tag' => 'en_US',
                                ],
                            ],
                        ],
                    ],
                ];

                Log::info('Amazon Title Update Request', [
                    'original_sku' => $sku,
                    'amazon_sku' => $amazonSku,
                    'productType' => $productType,
                    'title_length' => strlen($title),
                ]);

                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(30)->patch($endpoint, $body);

                $responseData = $response->json();

                if ($response->failed()) {
                    Log::error('Amazon Title Update Failed', [
                        'sku' => $sku,
                        'status' => $response->status(),
                        'response' => $responseData,
                    ]);
                    $lastError = $responseData ?: [
                        'errors' => [['code' => 'RequestFailed', 'message' => 'HTTP ' . $response->status()]],
                    ];
                    if (in_array($response->status(), [401, 403, 500, 502, 503]) && $attempt < $maxRetries) {
                        sleep(1);
                        continue;
                    }
                    return $lastError;
                }

                if (isset($responseData['errors']) && ! empty($responseData['errors'])) {
                    Log::error('Amazon Title Update: API returned errors', ['sku' => $sku, 'errors' => $responseData['errors']]);
                    return $responseData;
                }

                Log::info('Amazon Title Update: SUCCESS', ['sku' => $sku, 'amazon_sku' => $amazonSku]);
                return ['success' => true];
            } catch (\Exception $e) {
                Log::error('Amazon Title Update Exception', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                $lastError = [
                    'errors' => [['code' => 'Exception', 'message' => $e->getMessage()]],
                ];
                if ($attempt < $maxRetries) {
                    sleep(1);
                    continue;
                }
                return $lastError;
            }
        }

        return $lastError ?: ['errors' => [['code' => 'UpdateFailed', 'message' => 'Failed to update title.']]];
    }

    /**
     * Listings Items PATCH returns HTTP 200 with status/issues; treat non-ACCEPTED and ERROR issues as failure.
     *
     * @param  array<string,mixed>  $body
     * @return array{errors: array<int, array{code?: string, message?: string}>}|null  Error payload or null if OK
     */
    private function listingsPatchFailureFromResponseBody(array $body): ?array
    {
        $errors = [];

        if (isset($body['status']) && is_string($body['status']) && strtoupper($body['status']) !== 'ACCEPTED') {
            $errors[] = [
                'code' => 'ListingsPatchNotAccepted',
                'message' => 'Amazon did not accept the price update (status: ' . $body['status'] . ').',
            ];
        }

        $issues = $body['issues'] ?? [];
        if (is_array($issues)) {
            foreach ($issues as $issue) {
                if (!is_array($issue)) {
                    continue;
                }
                $sev = strtoupper((string) ($issue['severity'] ?? ''));
                if ($sev !== 'ERROR') {
                    continue;
                }
                $errors[] = [
                    'code' => (string) ($issue['code'] ?? 'ListingIssue'),
                    'message' => trim((string) ($issue['message'] ?? 'Amazon reported an error for the listing update.')),
                ];
            }
        }

        if (empty($errors)) {
            return null;
        }

        return ['errors' => $errors];
    }

    /**
     * Read back listing price after PATCH (eventual consistency).
     *
     * @return array{errors: array<int, array{code?: string, message?: string}>}|null  Null when price matches
     */
    private function confirmPriceAfterListingsPatch(string $amazonSku, float $price, string $accessToken): ?array
    {
        $anyFalse = false;
        $attempts = 6;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                usleep(1500000);
            } else {
                usleep(1000000);
            }

            $v = $this->verifyPriceUpdate($amazonSku, $price, $accessToken);
            if ($v === true) {
                return null;
            }
            if ($v === false) {
                $anyFalse = true;
            }
        }

        if ($anyFalse) {
            return [
                'errors' => [[
                    'code' => 'PriceVerificationFailed',
                    'message' => 'Amazon did not show the new price after the update. Check Seller Central or retry.',
                ]],
            ];
        }

        return [
            'errors' => [[
                'code' => 'PriceVerificationInconclusive',
                'message' => 'Could not read the listing price back from Amazon to confirm the update. Check Seller Central or retry in a minute.',
            ]],
        ];
    }

    /**
     * Extract "your" price from getListingsItem JSON (multiple shapes / includedData sets).
     *
     * @param  array<string,mixed>  $data
     */
    private function extractListingsItemYourPrice(array $data): ?float
    {
        $fromSchedule = function ($v): ?float {
            if ($v === null) {
                return null;
            }
            if (is_array($v) && isset($v[0]['schedule'][0]['value_with_tax'])) {
                return (float) $v[0]['schedule'][0]['value_with_tax'];
            }
            if (is_array($v) && isset($v[0]['value_with_tax'])) {
                return (float) $v[0]['value_with_tax'];
            }

            return null;
        };

        $fromOffer = function (array $o) use ($fromSchedule): ?float {
            foreach (['ourPrice', 'our_price'] as $k) {
                if (!isset($o[$k])) {
                    continue;
                }
                $p = $fromSchedule($o[$k]);
                if ($p !== null) {
                    return $p;
                }
            }

            return null;
        };

        $offers = $data['offers'] ?? [];
        if (isset($offers[0]) && is_array($offers[0])) {
            $p = $fromOffer($offers[0]);
            if ($p !== null) {
                return $p;
            }
        }

        if (isset($data['summaries'][0]['offers']) && is_array($data['summaries'][0]['offers'])) {
            foreach ($data['summaries'][0]['offers'] as $offer) {
                if (is_array($offer) && ($p = $fromOffer($offer)) !== null) {
                    return $p;
                }
            }
        }

        $attrs = $data['attributes'] ?? [];
        $purchasable = $attrs['purchasable_offer'] ?? null;
        if (is_array($purchasable) && isset($purchasable[0]) && is_array($purchasable[0])) {
            $po = $purchasable[0];
            if (isset($po['our_price'])) {
                $p = $fromSchedule($po['our_price']);
                if ($p !== null) {
                    return $p;
                }
            }
        }

        return null;
    }

    /**
     * Verify that the price was actually updated on Amazon
     * Returns: true if verified, false if price doesn't match, null if unable to verify
     */
    private function verifyPriceUpdate($amazonSku, $expectedPrice, $accessToken = null)
    {
        try {
            $sellerId = config('services.amazon_sp.seller_id');
            if (empty($sellerId)) {
                Log::warning("Price verification: Seller ID not configured");
                return null;
            }

            $marketplaceId = $this->marketplaceId ?? config('services.amazon_sp.marketplace_id') ?: 'ATVPDKIKX0DER';
            
            // Get fresh token if not provided
            if (empty($accessToken)) {
                $accessToken = $this->getAccessToken();
                if (empty($accessToken)) {
                    Log::warning("Price verification: Could not get access token");
                    return null;
                }
            }
            
            $encodedSku = rawurlencode($amazonSku);
            $url = $this->endpoint . '/listings/2021-08-01/items/' . $sellerId . '/' . $encodedSku
                . '?marketplaceIds=' . rawurlencode((string) $marketplaceId)
                . '&includedData=' . rawurlencode('summaries,attributes,offers');
            
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get($url);
            
            if ($response->failed()) {
                Log::warning("Price verification: Failed to fetch data", [
                    'sku' => $amazonSku,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
            
            $data = $response->json();
            if (!is_array($data)) {
                return null;
            }

            $currentPrice = $this->extractListingsItemYourPrice($data);
            
            if ($currentPrice === null) {
                Log::warning("Could not extract price from verification response", [
                    'sku' => $amazonSku,
                    'has_summaries' => isset($data['summaries']),
                    'has_attributes' => isset($data['attributes']),
                    'has_offers' => isset($data['offers']),
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
            $sellerId = config('services.amazon_sp.seller_id');
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
            $marketplaceId = $this->marketplaceId ?? config('services.amazon_sp.marketplace_id') ?: 'ATVPDKIKX0DER';
            $url = $this->endpoint . '/listings/2021-08-01/items/' . $sellerId . '/' . $encodedSku
                . '?marketplaceIds=' . rawurlencode((string) $marketplaceId)
                . '&includedData=' . rawurlencode('summaries,attributes,offers');
            
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get($url);
            
            if ($response->failed()) {
                return null;
            }
            
            $data = $response->json();
            if (!is_array($data)) {
                return null;
            }

            return $this->extractListingsItemYourPrice($data);
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
        $sku = $this->normalizeListingsSellerSku(trim((string) $sku));
        if (empty($sku)) {
            return null;
        }

        $sellerId = config('services.amazon_sp.seller_id');
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

        $collapsed = preg_replace('/\s+/u', ' ', $sku);
        $noSpaces = str_replace([' ', "\xc2\xa0"], '', $sku);

        // Generate case + spacing variations (Seller Central MSKU may omit spaces, e.g. "CS042W" vs "CS 04 2W")
        $variations = [
            $sku,
            $collapsed,
            $noSpaces,
            strtoupper($sku),
            strtoupper($collapsed),
            strtoupper($noSpaces),
            strtolower($sku),
            strtolower($noSpaces),
            ucfirst(strtolower($sku)),
            ucwords(strtolower($sku)),
        ];

        $variations = array_values(array_unique(array_filter($variations, function ($v) {
            return $v !== null && $v !== '';
        })));

        $marketplaceId = rawurlencode((string) ($this->marketplaceId ?: config('services.amazon_sp.marketplace_id') ?: 'ATVPDKIKX0DER'));
        $included = rawurlencode('summaries,attributes,productTypes');

        foreach ($variations as $skuVariation) {
            try {
                $encodedSku = rawurlencode($skuVariation);
                $url = $this->endpoint . '/listings/2021-08-01/items/' . $sellerId . '/' . $encodedSku
                    . '?marketplaceIds=' . $marketplaceId . '&includedData=' . $included;

                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get($url);

                // If successful (200), return this SKU format
                if ($response->successful()) {
                    $data = $response->json();
                    if (! is_array($data)) {
                        $data = [];
                    }
                    if (! empty($data['errors'])) {
                        continue;
                    }
                    $hasSummaries = ! empty($data['summaries']);
                    $hasAttributes = ! empty($data['attributes']);
                    $hasProductTypes = ! empty($data['productTypes']);
                    $hasSkuField = isset($data['sku']) && (string) $data['sku'] !== '';
                    if ($hasSummaries || $hasAttributes || $hasProductTypes || $hasSkuField) {
                        Log::info('Found matching SKU format in Amazon', [
                            'original_sku' => $sku,
                            'amazon_sku' => $skuVariation,
                            'has_summaries' => $hasSummaries,
                            'has_attributes' => $hasAttributes,
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

            $sellerId = config('services.amazon_sp.seller_id');
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

        $marketplaceId = config('services.amazon_sp.marketplace_id');

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

    /**
     * Insert a batch with up to 3 retries on failure.
     */
    private function insertBatchWithRetry(array $batch): int
    {
        $count = count($batch);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                AmazonListingRaw::insert($batch);
                return $count;
            } catch (\Throwable $e) {
                Log::warning('Amazon Listings Report: Batch insert failed', [
                    'attempt' => $attempt,
                    'count' => $count,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < 3) {
                    sleep(2);
                } else {
                    throw $e;
                }
            }
        }
        return 0;
    }

    /**
     * Request GET_MERCHANT_LISTINGS_ALL_DATA report, download, parse and store all rows in amazon_listings_raw.
     * Uses SP-API credentials from config (services.amazon_sp).
     * Reports API returns a single document. Includes retry logic, download verification, and comprehensive logging.
     * Returns ['success' => true, 'count' => N] or ['success' => false, 'message' => '...'].
     */
    public function fetchAndStoreListingsReport(): array
    {
        try {
            set_time_limit(3600); // Allow up to 1 hour for polling + download + parse
            Log::info('Amazon Listings Report: Starting import', [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'marketplace_id' => config('services.amazon_sp.marketplace_id'),
            ]);

            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return ['success' => false, 'message' => 'Could not obtain Amazon API access token. Check SPAPI credentials in .env.'];
            }

            $marketplaceId = config('services.amazon_sp.marketplace_id');
            if (empty($marketplaceId)) {
                return ['success' => false, 'message' => 'SPAPI_MARKETPLACE_ID is not set in .env.'];
            }

            // Step 1: Request the report
            $reportRequestUrl = 'https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports';
            $reportPayload = [
                'reportType' => 'GET_MERCHANT_LISTINGS_ALL_DATA',
                'marketplaceIds' => [$marketplaceId],
            ];
            Log::info('Amazon Listings Report: Requesting report', [
                'url' => $reportRequestUrl,
                'payload' => $reportPayload,
            ]);

            $response = Http::withoutVerifying()->timeout(30)->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post($reportRequestUrl, $reportPayload);

            $body = $response->json();
            $reportId = $body['reportId'] ?? null;
            if (!$reportId) {
                Log::error('Amazon Listings Report: Failed to request report', [
                    'status' => $response->status(),
                    'response' => $body,
                ]);
                return ['success' => false, 'message' => 'Failed to request report: ' . ($body['errors'][0]['message'] ?? $response->body())];
            }

            Log::info('Amazon Listings Report: Report requested', ['report_id' => $reportId]);

            // Step 2: Poll until report is DONE (max 60 minutes)
            $maxWait = 60;
            $waited = 0;
            $pollCount = 0;
            $statusUrl = "https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports/{$reportId}";
            do {
                sleep(15);
                $waited += 15;
                $pollCount++;
                Log::info('Amazon Listings Report: Polling status', [
                    'poll_count' => $pollCount,
                    'waited_seconds' => $waited,
                    'url' => $statusUrl,
                ]);

                $statusResponse = Http::withoutVerifying()->timeout(30)->withHeaders([
                    'x-amz-access-token' => $accessToken,
                ])->get($statusUrl);

                $status = $statusResponse->json();
                $processingStatus = $status['processingStatus'] ?? 'UNKNOWN';

                Log::info('Amazon Listings Report: Poll response', [
                    'processing_status' => $processingStatus,
                    'response_keys' => array_keys($status),
                ]);

                if ($processingStatus === 'CANCELLED' || $processingStatus === 'FATAL') {
                    Log::error('Amazon Listings Report: Report failed', ['status' => $processingStatus, 'response' => $status]);
                    return ['success' => false, 'message' => 'Report processing status: ' . $processingStatus];
                }
                if ($waited >= $maxWait * 60) {
                    Log::error('Amazon Listings Report: Poll timeout', ['waited_seconds' => $waited]);
                    return ['success' => false, 'message' => 'Report timed out waiting for DONE.'];
                }
            } while ($processingStatus !== 'DONE');

            $documentId = $status['reportDocumentId'] ?? null;
            if (!$documentId) {
                Log::error('Amazon Listings Report: Document ID missing', ['status_response' => $status]);
                return ['success' => false, 'message' => 'Report document ID missing.'];
            }

            Log::info('Amazon Listings Report: Report DONE', ['document_id' => $documentId]);

            // Step 3: Get document URL
            $docUrl = "https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/documents/{$documentId}";
            $docResponse = Http::withoutVerifying()->timeout(30)->withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->get($docUrl);

            $doc = $docResponse->json();
            $url = $doc['url'] ?? null;
            $compression = $doc['compressionAlgorithm'] ?? 'GZIP';

            Log::info('Amazon Listings Report: Document metadata', [
                'compression' => $compression,
                'url_present' => !empty($url),
            ]);

            if (!$url) {
                Log::error('Amazon Listings Report: Document URL missing', ['doc' => $doc]);
                return ['success' => false, 'message' => 'Report document URL not found.'];
            }

            // Step 4: Download document with retry (critical - partial download causes missing rows)
            $maxRetries = 3;
            $rawContent = null;
            $expectedBytes = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                Log::info('Amazon Listings Report: Downloading document', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'url_length' => strlen($url),
                ]);

                $downloadResponse = Http::timeout(180)->withHeaders(['Accept' => '*/*'])->get($url);
                $rawContent = $downloadResponse->body();
                $bytesReceived = strlen($rawContent);
                $contentLength = $downloadResponse->header('Content-Length');
                if ($contentLength !== null && is_numeric($contentLength)) {
                    $expectedBytes = (int) $contentLength;
                }

                Log::info('Amazon Listings Report: Download result', [
                    'attempt' => $attempt,
                    'bytes_received' => $bytesReceived,
                    'expected_bytes' => $expectedBytes,
                    'status' => $downloadResponse->status(),
                    'complete' => $expectedBytes === null ? 'unknown' : ($bytesReceived >= $expectedBytes ? 'yes' : 'no'),
                ]);

                // Verify download completeness when Content-Length is available
                if ($expectedBytes !== null && $bytesReceived < $expectedBytes) {
                    Log::warning('Amazon Listings Report: Possible truncated download', [
                        'bytes_received' => $bytesReceived,
                        'expected' => $expectedBytes,
                        'short_by' => $expectedBytes - $bytesReceived,
                    ]);
                    if ($attempt < $maxRetries) {
                        sleep(5);
                        continue;
                    }
                    return ['success' => false, 'message' => 'Report download truncated: received ' . $bytesReceived . ' bytes, expected ' . $expectedBytes];
                }

                if ($downloadResponse->successful() && $bytesReceived > 0) {
                    break;
                }
                if ($attempt < $maxRetries) {
                    sleep(5);
                } else {
                    return ['success' => false, 'message' => 'Failed to download report after ' . $maxRetries . ' attempts. Bytes: ' . $bytesReceived];
                }
            }

            $csv = strtoupper($compression) === 'GZIP' ? gzdecode($rawContent) : $rawContent;
            if ($csv === false || $csv === '') {
                Log::error('Amazon Listings Report: GZIP decode failed', [
                    'compression' => $compression,
                    'raw_size' => strlen($rawContent ?? ''),
                ]);
                return ['success' => false, 'message' => 'Failed to decode report content (GZIP).'];
            }

            $csvLength = strlen($csv);
            Log::info('Amazon Listings Report: Content decoded', ['csv_bytes' => $csvLength]);

            $lines = explode("\n", trim($csv));
            $lineCount = count($lines);
            Log::info('Amazon Listings Report: Lines parsed', [
                'total_lines' => $lineCount,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            if (empty($lines)) {
                return ['success' => false, 'message' => 'Report has no content.'];
            }
            $headers = array_map('trim', explode("\t", array_shift($lines)));
            $headerCount = count($headers);
            $reportImportedAt = now()->toDateTimeString();

            Log::info('Amazon Listings Report: Headers', [
                'count' => $headerCount,
                'sample' => array_slice($headers, 0, 5),
            ]);

            // Replace existing data with this report snapshot
            AmazonListingRaw::query()->delete();

            $inserted = 0;
            $batch = [];
            $batchSize = 200;
            $skippedInvalid = 0;
            $lineIndex = 0;
            foreach ($lines as $line) {
                $lineIndex++;
                $line = trim($line);
                if ($line === '') continue;
                $row = str_getcsv($line, "\t");
                if (count($row) < count($headers)) {
                    $skippedInvalid++;
                    continue;
                }
                $data = array_combine($headers, $row);
                $sellerSku = isset($data['seller-sku']) ? preg_replace('/[^\x20-\x7E]/', '', trim($data['seller-sku'])) : null;
                $asin1 = isset($data['asin1']) ? trim($data['asin1']) : null;

                // Map report columns to our system fields (report uses hyphenated names)
                $listPrice = isset($data['list-price']) && is_numeric($data['list-price'])
                    ? (float) $data['list-price']
                    : (isset($data['list_price']) && is_numeric($data['list_price']) ? (float) $data['list_price'] : null);
                $minAdvertisedPrice = isset($data['minimum-advertised-price']) && is_numeric($data['minimum-advertised-price'])
                    ? (float) $data['minimum-advertised-price']
                    : (isset($data['minimum_advertised_price']) && is_numeric($data['minimum_advertised_price']) ? (float) $data['minimum_advertised_price'] : null);

                // Preserve all image columns in raw_data (report may have image-url, image-url-1..9)
                $imageKeys = ['image-url'];
                for ($i = 1; $i <= 9; $i++) {
                    $imageKeys[] = 'image-url-' . $i;
                }
                foreach ($imageKeys as $imgKey) {
                    if (isset($data[$imgKey]) && is_string($data[$imgKey]) && trim($data[$imgKey]) !== '') {
                        $data[$imgKey] = trim($data[$imgKey]);
                    }
                }

                $rawJson = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($rawJson === false || json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Amazon Listings Report: json_encode failed for row', [
                        'json_error' => json_last_error_msg(),
                        'seller_sku' => $sellerSku,
                        'line_index' => $lineIndex,
                    ]);
                    $skippedInvalid++;
                    continue;
                }

                $rowData = [
                    'report_imported_at' => $reportImportedAt,
                    'seller_sku' => $sellerSku,
                    'asin1' => $asin1,
                    'raw_data' => $rawJson,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
                $tableName = (new AmazonListingRaw)->getTable();

                // Thumbnail from report: image-url or first of image-url-1..9
                $thumbnailUrl = null;
                if (! empty(trim((string) ($data['image-url'] ?? '')))) {
                    $thumbnailUrl = trim($data['image-url']);
                } else {
                    for ($i = 1; $i <= 9; $i++) {
                        $v = $data['image-url-' . $i] ?? '';
                        if (is_string($v) && trim($v) !== '') {
                            $thumbnailUrl = trim($v);
                            break;
                        }
                    }
                }
                if (Schema::hasColumn($tableName, 'thumbnail_image') && $thumbnailUrl !== null) {
                    $rowData['thumbnail_image'] = $thumbnailUrl;
                    if ($inserted < 3) {
                        Log::debug('Amazon Listings Report: thumbnail from report', [
                            'seller_sku' => $sellerSku,
                            'thumbnail_image' => substr($thumbnailUrl, 0, 80) . '...',
                        ]);
                    }
                }

                if (Schema::hasColumn($tableName, 'list_price') && $listPrice !== null) {
                    $rowData['list_price'] = $listPrice;
                }
                if (Schema::hasColumn($tableName, 'minimum_advertised_price') && $minAdvertisedPrice !== null) {
                    $rowData['minimum_advertised_price'] = $minAdvertisedPrice;
                }
                $condType = $data['condition-type'] ?? $data['condition_type'] ?? null;
                if (Schema::hasColumn($tableName, 'condition_type') && $condType !== null && $condType !== '') {
                    $rowData['condition_type'] = trim((string) $condType);
                    $rowData['condition_type_display'] = self::mapConditionType($rowData['condition_type']);
                }
                $batch[] = $rowData;
                if (count($batch) >= $batchSize) {
                    $inserted += $this->insertBatchWithRetry($batch);
                    $batch = [];
                    if ($inserted % 1000 === 0 && $inserted > 0) {
                        Log::info('Amazon Listings Report: Progress', [
                            'inserted' => $inserted,
                            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                        ]);
                    }
                }
            }
            if (!empty($batch)) {
                $inserted += $this->insertBatchWithRetry($batch);
            }

            if ($skippedInvalid > 0) {
                Log::info('Amazon Listings Report: Skipped invalid rows', ['count' => $skippedInvalid]);
            }
            Log::info('Amazon Listings Report: Import complete', [
                'total_inserted' => $inserted,
                'skipped_invalid' => $skippedInvalid,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
            return ['success' => true, 'count' => $inserted];
        } catch (\Throwable $e) {
            Log::error('Amazon Listings Report: Exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Field mapping: Amazon API/UI field names → our amazon_listings_raw columns.
     */
    private static function amazonFieldMapping(): array
    {
        return [
            'item_name' => 'item_name',
            'product_title' => 'item_name',
            'brand' => 'brand',
            'bullet_point' => 'bullet_point',
            'product_description' => 'product_description',
            'model_number' => 'model_number',
            'manufacturer' => 'manufacturer',
            'part_number' => 'part_number',
            'product_id' => 'product_id',
            'color' => 'color',
            'material' => 'material',
            'style' => 'style',
            'item_dimensions' => 'item_dimensions',
            'exterior_finish' => 'exterior_finish',
            'voltage' => 'voltage',
            'noise_level' => 'noise_level',
            'country_of_origin' => 'country_of_origin',
            'warranty_description' => 'warranty_description',
            'included_components' => 'included_components',
            'number_of_items' => 'number_of_items',
            'assembly_required' => 'assembly_required',
            'handling_time' => 'handling_time',
            'minimum_advertised_price' => 'minimum_advertised_price',
            'list_price' => 'list_price',
            'merchant_shipping_group' => 'merchant_shipping_group',
            'item_type_keyword' => 'item_type_keyword',
            'generic_keyword' => 'generic_keyword',
        ];
    }

    /**
     * Get Catalog Item details by ASIN for product attributes enrichment.
     * Uses Catalog Items API v2022-04-01.
     * @param array{catalog_raw?: array} $context Optional - stores raw response for debug
     */
    public function getCatalogItemByAsin(string $asin, array &$context = []): ?array
    {
        try {
            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                Log::warning('Catalog API: No access token');
                return null;
            }
            $marketplaceId = config('services.amazon_sp.marketplace_id') ?: $this->marketplaceId;
            if (empty($marketplaceId)) {
                Log::warning('Catalog API: No marketplace_id configured');
                return null;
            }
            $asinEncoded = rawurlencode($asin);
            $url = 'https://sellingpartnerapi-na.amazon.com/catalog/2022-04-01/items/' . $asinEncoded
                . '?marketplaceIds=' . $marketplaceId
                . '&includedData=attributes,dimensions,identifiers,productTypes,summaries';

            Log::info('Catalog API: Calling getCatalogItem', ['asin' => $asin, 'url' => $url]);

            $response = Http::withoutVerifying()->timeout(30)->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            $body = $response->json();
            $status = $response->status();

            if ($response->failed()) {
                Log::warning('Catalog API: Request failed', [
                    'asin' => $asin,
                    'status' => $status,
                    'body' => $body,
                ]);
                return null;
            }

            $context['catalog_raw'] = $body;
            Log::info('Catalog API: Success', [
                'asin' => $asin,
                'has_summaries' => ! empty($body['summaries']),
                'has_attributes' => ! empty($body['attributes']),
                'has_dimensions' => ! empty($body['dimensions']),
                'has_identifiers' => ! empty($body['identifiers']),
            ]);

            return $body;
        } catch (\Throwable $e) {
            Log::warning('Catalog API: Exception', ['asin' => $asin, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract product attributes from Catalog API v2022-04-01 response.
     * Maps: summaries[0], dimensions[0], identifiers[0], attributes.
     */
    public function extractCatalogAttributes(array $catalogData): array
    {
        $out = [];
        $attributes = $catalogData['attributes'] ?? [];
        $summaries = $catalogData['summaries'] ?? [];
        $dimensions = $catalogData['dimensions'] ?? [];
        $identifiers = $catalogData['identifiers'] ?? [];
        $productTypes = $catalogData['productTypes'] ?? [];
        $summary = $summaries[0] ?? [];
        $dim = $dimensions[0] ?? [];
        // Handle dimensions as object (length/width/height/weight)
        if (is_array($dim) && isset($dim['item'])) {
            $dim = $dim['item'] ?? $dim;
        }

        $extractAttrValue = function ($val) {
            if (is_string($val)) {
                return trim($val) === '' ? null : trim($val);
            }
            if (is_array($val) && isset($val[0]['value'])) {
                $v = $val[0]['value'] ?? null;
                return is_string($v) ? trim($v) : (is_array($v) ? json_encode($v) : null);
            }
            if (is_array($val) && isset($val[0]['marketplace_id'])) {
                return $val[0]['value'] ?? null;
            }
            return null;
        };

        // summaries[0] mapping - API may use brandName or brand, itemName, etc.
        $summaryMap = [
            'brand' => 'brand',
            'brandName' => 'brand',
            'color' => 'color',
            'size' => 'size',
            'style' => 'style',
            'modelNumber' => 'model_number',
            'modelName' => 'model_name',
            'partNumber' => 'part_number',
            'manufacturer' => 'manufacturer',
            'packageQuantity' => 'number_of_items',
            'itemName' => 'item_name',
        ];
        foreach ($summaryMap as $apiKey => $ourKey) {
            $v = $summary[$apiKey] ?? null;
            if ($v !== null && $v !== '') {
                if ($ourKey === 'number_of_items' && is_numeric($v)) {
                    $out[$ourKey] = (int) $v;
                } elseif ($ourKey === 'model_name' || $ourKey === 'item_name' || $ourKey === 'brand') {
                    $out[$ourKey] = is_string($v) ? trim($v) : (string) $v;
                } else {
                    $out[$ourKey] = is_string($v) ? trim($v) : (string) $v;
                }
            }
        }
        if (empty($out['item_name']) && ! empty($summary['itemName'])) {
            $out['item_name'] = is_string($summary['itemName']) ? trim($summary['itemName']) : (string) $summary['itemName'];
        }

        // dimensions[0] → item_dimensions - may be object with length/width/height/weight
        if (! empty($dim)) {
            if (is_string($dim)) {
                $out['item_dimensions'] = trim($dim);
            } elseif (is_array($dim)) {
                $out['item_dimensions'] = json_encode($dim, JSON_UNESCAPED_UNICODE);
            }
        }
        if (empty($out['item_dimensions']) && ! empty($dimensions)) {
            $first = $dimensions[0] ?? null;
            if (is_array($first)) {
                $out['item_dimensions'] = json_encode($first, JSON_UNESCAPED_UNICODE);
            }
        }

        // identifiers[0].identifiers → external_product_id (UPC/EAN)
        $idRow = $identifiers[0] ?? null;
        if (is_array($idRow)) {
            $idents = $idRow['identifiers'] ?? $idRow;
            if (! is_array($idents)) {
                $idents = [$idRow];
            }
            foreach ($idents as $id) {
                if (! is_array($id)) {
                    continue;
                }
                $type = strtoupper((string) ($id['identifierType'] ?? $id['type'] ?? ''));
                $val = $id['identifier'] ?? $id['value'] ?? null;
                if ($val && in_array($type, ['UPC', 'EAN'], true)) {
                    $out['external_product_id'] = is_string($val) ? trim($val) : (string) $val;
                    break;
                }
            }
        }

        // product_description: attributes.product_description[0].value or summaries[0].description
        $prodDesc = $attributes['product_description'] ?? null;
        if ($prodDesc !== null) {
            $extracted = $extractAttrValue($prodDesc);
            if (is_string($extracted) && trim($extracted) !== '') {
                $out['product_description'] = trim($extracted);
            }
        }
        if (empty($out['product_description']) && ! empty($summary['description'])) {
            $d = $summary['description'];
            $out['product_description'] = is_string($d) ? trim($d) : (string) $d;
        }

        // attributes mapping
        $attrKeys = [
            'country_of_origin' => 'country_of_origin',
            'finish_type' => 'exterior_finish',
            'assembly_required' => 'assembly_required',
            'voltage' => 'voltage',
            'generic_keyword' => 'generic_keyword',
            'material' => 'material',
            'model_number' => 'model_number',
            'manufacturer' => 'manufacturer',
            'part_number' => 'part_number',
            'included_components' => 'included_components',
            'item_type_keyword' => 'item_type_keyword',
        ];
        foreach ($attrKeys as $apiKey => $ourKey) {
            $val = $attributes[$apiKey] ?? null;
            if ($val !== null) {
                $extracted = $extractAttrValue($val);
                if ($extracted !== null) {
                    if ($ourKey === 'assembly_required') {
                        $out[$ourKey] = in_array(strtolower((string) $extracted), ['yes', 'true', '1'], true);
                    } elseif ($ourKey === 'number_of_items' && is_numeric($extracted)) {
                        $out[$ourKey] = (int) $extracted;
                    } else {
                        $out[$ourKey] = $extracted;
                    }
                }
            }
        }

        // productTypes for item_type_keyword and product_type
        if (! empty($productTypes[0]['productType'])) {
            $pt = trim((string) $productTypes[0]['productType']);
            $out['item_type_keyword'] = $out['item_type_keyword'] ?? $pt;
            $out['product_type'] = $out['product_type'] ?? $pt;
        }

        // model_name (from summary or attributes)
        if (! empty($summary['modelName'])) {
            $out['model_name'] = is_string($summary['modelName']) ? trim($summary['modelName']) : (string) $summary['modelName'];
        }

        return $out;
    }

    /**
     * Get full Listings Item details from Listings Items API v2021-08-01.
     * Include: attributes, offers, fulfillmentAvailability, productTypes.
     * Maps: quantity, handling_time, your_price, minimum_advertised_price, list_price,
     * merchant_shipping_group, warranty_description, product_description, bullet_point, item_name.
     */
    public function getListingsItemFullDetails(string $sellerSku): array
    {
        $empty = [
            'item_name' => null,
            'product_description' => null,
            'bullet_point' => [],
            'quantity' => null,
            'handling_time' => null,
            'your_price' => null,
            'minimum_advertised_price' => null,
            'list_price' => null,
            'merchant_shipping_group' => null,
            'warranty_description' => null,
            'product_type' => null,
            'condition_type' => null,
            'images' => [],
        ];

        try {
            $accessToken = $this->getAccessToken();
            $sellerId = config('services.amazon_sp.seller_id');
            $marketplaceId = $this->marketplaceId ?? config('services.amazon_sp.marketplace_id');
            if (empty($sellerId) || empty($marketplaceId)) {
                return $empty;
            }

            $skuEncoded = rawurlencode($sellerSku);
            $url = $this->endpoint . '/listings/2021-08-01/items/' . $sellerId . '/' . $skuEncoded
                . '?marketplaceIds=' . $marketplaceId
                . '&includedData=attributes,offers,fulfillmentAvailability,productTypes';

            $response = Http::withoutVerifying()->timeout(30)->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            $body = $response->json();
            if ($response->status() !== 200) {
                return $empty;
            }

            $attrs = $body['attributes'] ?? [];
            $offers = $body['offers'] ?? [];
            $fulfillmentAvail = $body['fulfillmentAvailability'] ?? [];
            $productTypes = $body['productTypes'] ?? [];
            $summaries = $body['summaries'] ?? [];

            $extractAttr = function ($v) {
                if (is_array($v) && isset($v[0]['value'])) {
                    return is_string($v[0]['value']) ? trim($v[0]['value']) : (is_numeric($v[0]['value']) ? $v[0]['value'] : null);
                }
                return is_string($v) && trim($v) !== '' ? trim($v) : (is_numeric($v) ? $v : null);
            };

            $out = $empty;

            // fulfillmentAvailability[0]
            $fa = $fulfillmentAvail[0] ?? [];
            if (isset($fa['quantity']) && is_numeric($fa['quantity'])) {
                $out['quantity'] = (int) $fa['quantity'];
            }
            if (isset($fa['handlingTime']['value']) && is_numeric($fa['handlingTime']['value'])) {
                $out['handling_time'] = (int) $fa['handlingTime']['value'];
            } elseif (isset($fa['handlingTime']) && is_numeric($fa['handlingTime'])) {
                $out['handling_time'] = (int) $fa['handlingTime'];
            }

            // Price extraction from multiple possible paths
            $getPriceFromSchedule = function ($v): ?float {
                if ($v === null) {
                    return null;
                }
                if (is_array($v) && isset($v[0]['schedule'][0]['value_with_tax'])) {
                    return (float) $v[0]['schedule'][0]['value_with_tax'];
                }
                if (is_array($v) && isset($v[0]['value_with_tax'])) {
                    return (float) $v[0]['value_with_tax'];
                }
                if (is_numeric($v)) {
                    return (float) $v;
                }
                return null;
            };
            $getPriceFromOffer = function (array $o, string $key) use ($getPriceFromSchedule): ?float {
                $v = $o[$key] ?? null;
                return $getPriceFromSchedule($v);
            };

            // Path 1: offers[0] or summaries[0].offers[0]
            $offer = $offers[0] ?? ($summaries[0]['offers'][0] ?? []);
            if (($p = $getPriceFromOffer($offer, 'ourPrice')) !== null) {
                $out['your_price'] = $p;
            }
            if (($p = $getPriceFromOffer($offer, 'minimumSellerAllowedPrice')) !== null) {
                $out['minimum_advertised_price'] = $p;
            }
            if (($p = $getPriceFromOffer($offer, 'listPrice')) !== null) {
                $out['list_price'] = $p;
            }

            // Path 2: attributes.purchasable_offer[0].our_price / list_price / minimum_advertised_price
            $purchasable = $attrs['purchasable_offer'] ?? null;
            if (is_array($purchasable) && isset($purchasable[0])) {
                $po = $purchasable[0];
                if ($out['your_price'] === null && ($p = $getPriceFromSchedule($po['our_price'] ?? null)) !== null) {
                    $out['your_price'] = $p;
                }
                if ($out['list_price'] === null && ($p = $getPriceFromSchedule($po['list_price'] ?? null)) !== null) {
                    $out['list_price'] = $p;
                }
                if ($out['minimum_advertised_price'] === null && ($p = $getPriceFromSchedule($po['minimum_advertised_price'] ?? $po['minimumSellerAllowedPrice'] ?? null)) !== null) {
                    $out['minimum_advertised_price'] = $p;
                }
            }

            // Path 3: summaries[0].offers[0].ourPrice (already tried above; try summaries.ourPrice directly)
            if ($out['your_price'] === null && ! empty($summaries[0]['offers'][0]['ourPrice'])) {
                $p = $getPriceFromSchedule($summaries[0]['offers'][0]['ourPrice']);
                if ($p !== null) {
                    $out['your_price'] = $p;
                }
            }

            // attributes including condition_type (API may return numeric code e.g. 11 = New)
            $condAttr = $attrs['condition_type'] ?? $attrs['condition_note_condition'] ?? null;
            if ($condAttr !== null) {
                $ex = $extractAttr($condAttr);
                if ($ex !== null) {
                    $out['condition_type'] = is_string($ex) ? trim($ex) : (string) $ex;
                }
            }
            foreach (['item_name', 'product_description', 'merchant_shipping_group', 'warranty_description'] as $k) {
                $v = $attrs[$k] ?? null;
                if ($v !== null) {
                    $ex = $extractAttr($v);
                    if ($ex !== null) {
                        $out[$k] = $ex;
                    }
                }
            }

            // bullet_point (features tab → bullet points)
            $bp = $attrs['bullet_point'] ?? $attrs['bullet_points'] ?? $attrs['feature_bullets'] ?? null;
            if (is_array($bp)) {
                $bullets = [];
                foreach ($bp as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $bullets[] = trim($item);
                    } elseif (is_array($item) && isset($item['value']) && is_string($item['value']) && trim($item['value']) !== '') {
                        $bullets[] = trim($item['value']);
                    }
                }
                if (! empty($bullets)) {
                    $out['bullet_point'] = $bullets;
                }
            }

            // productTypes
            if (! empty($productTypes[0]['productType'])) {
                $out['product_type'] = trim((string) $productTypes[0]['productType']);
            }

            // summaries fallback for item_name
            if (empty($out['item_name']) && ! empty($summaries[0]['itemName'])) {
                $out['item_name'] = trim((string) $summaries[0]['itemName']);
            }

            $filled = array_filter($out, fn ($v) => $v !== null && $v !== '' && (! is_array($v) || ! empty($v)));
            Log::debug('Listings Item full details: extracted', [
                'sku' => $sellerSku,
                'field_count' => count($filled),
                'your_price' => $out['your_price'] ?? null,
                'list_price' => $out['list_price'] ?? null,
                'minimum_advertised_price' => $out['minimum_advertised_price'] ?? null,
                'handling_time' => $out['handling_time'] ?? null,
            ]);

            return $out;
        } catch (\Throwable $e) {
            Log::debug('Listings Item full details: Exception', ['sku' => $sellerSku, 'error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Map Amazon condition type code to display value.
     */
    public static function mapConditionType(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        $map = [
            '11' => 'New',
            '10' => 'Refurbished',
            '9' => 'Not Used',
            '1' => 'Used - Like New',
            '2' => 'Used - Very Good',
            '3' => 'Used - Good',
            '4' => 'Used - Acceptable',
        ];

        return $map[trim((string) $code)] ?? $code;
    }

    /**
     * Enrich an AmazonListingRaw record with Catalog API and Listings Items API data.
     * Merges all 26 required fields. Catalog API: 1/sec rate limit. Listings API: 5/sec.
     * @param array{warnings?: array, api_response?: array} $context Optional context for logging
     */
    public function enrichListingData(string $asin, string $sellerSku, array &$context = []): array
    {
        $updates = [];
        $maxRetries = 3;

        // 1. Catalog API (rate limit 1/sec) - MUST be called first for brand, color, material, dimensions, etc.
        sleep(1); // Rate limit: 1 req/sec before first Catalog call
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $catalogData = $this->getCatalogItemByAsin($asin, $context);
            if ($catalogData !== null) {
                $catalogAttrs = $this->extractCatalogAttributes($catalogData);
                foreach ($catalogAttrs as $k => $v) {
                    if ($v !== null && $v !== '' && (! is_array($v) || ! empty($v))) {
                        $updates[$k] = $v;
                    }
                }
                break;
            }
            if ($attempt < $maxRetries) {
                sleep(1);
            } else {
                $context['warnings'] = ($context['warnings'] ?? []);
                $context['warnings'][] = "Catalog API failed for ASIN {$asin} after {$maxRetries} attempts";
            }
        }

        sleep(1); // Catalog API rate limit: 1 req/sec

        // 2. Listings Items API (rate limit 5/sec, use 200ms delay)
        usleep(200000);
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $listingsData = $this->getListingsItemFullDetails($sellerSku);
            $listingsFields = [
                'item_name', 'product_description', 'bullet_point', 'quantity', 'handling_time',
                'your_price', 'minimum_advertised_price', 'list_price', 'merchant_shipping_group',
                'warranty_description', 'product_type', 'condition_type',
            ];
            $merged = false;
            foreach ($listingsFields as $k) {
                $v = $listingsData[$k] ?? null;
                if ($v !== null && $v !== '' && (! is_array($v) || ! empty($v))) {
                    $updates[$k] = $v;
                    if ($k === 'condition_type') {
                        $updates['condition_type_display'] = self::mapConditionType(is_string($v) ? $v : (string) $v);
                    }
                    $merged = true;
                }
            }
            if ($merged || $attempt === $maxRetries) {
                break;
            }
            usleep(500000); // 500ms before retry
        }

        // Ensure bullet_point is array for JSON casting
        if (isset($updates['bullet_point']) && ! is_array($updates['bullet_point'])) {
            $bp = $updates['bullet_point'];
            $updates['bullet_point'] = is_string($bp) ? (json_decode($bp, true) ?? [$bp]) : (array) $bp;
        }

        // 3. Fetch images via Listings Item Media and add thumbnail + image-url-1..9 for raw_data
        usleep(200000);
        $media = $this->getListingsItemMedia($sellerSku);
        if (! empty($media['images']) && is_array($media['images'])) {
            $images = array_values($media['images']);
            $first = $images[0] ?? null;
            if (is_string($first) && $first !== '') {
                $updates['thumbnail_image'] = $first;
            } elseif (is_array($first) && ! empty($first['url'])) {
                $updates['thumbnail_image'] = $first['url'];
            }
            $imageUrlsForRaw = [];
            foreach ($images as $idx => $url) {
                $u = is_string($url) ? $url : (is_array($url) ? ($url['url'] ?? $url['locator'] ?? null) : null);
                if (is_string($u) && $u !== '') {
                    if ($idx === 0) {
                        $imageUrlsForRaw['image-url'] = $u;
                    } elseif ($idx >= 1 && $idx <= 9) {
                        $imageUrlsForRaw['image-url-' . $idx] = $u;
                    }
                }
            }
            if (empty($imageUrlsForRaw['image-url']) && ! empty($images[0])) {
                $first = $images[0];
                $imageUrlsForRaw['image-url'] = is_string($first) ? $first : ($first['url'] ?? $first['locator'] ?? '');
            }
            $updates['_image_urls_for_raw_data'] = $imageUrlsForRaw;
            $context['image_count'] = count($images);
            Log::debug('Amazon enrichment: images for raw_data', [
                'sku' => $sellerSku,
                'count' => count($imageUrlsForRaw),
                'thumbnail_set' => isset($updates['thumbnail_image']),
            ]);
        }

        $context['updates_count'] = count($updates);
        Log::debug('Amazon enrichment: merged updates', [
            'sku' => $sellerSku,
            'field_count' => count($updates),
            'has_your_price' => isset($updates['your_price']),
            'has_product_description' => isset($updates['product_description']),
            'has_thumbnail' => isset($updates['thumbnail_image']),
        ]);

        return $updates;
    }

    /**
     * Enrich a single SKU and return updates. For testing (e.g. 3501 USB).
     */
    public function enrichSingleSku(string $sellerSku, bool $debug = true): array
    {
        $listing = AmazonListingRaw::where('seller_sku', $sellerSku)->first()
            ?? AmazonListingRaw::where('seller_sku', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $sellerSku) . '%')->first();
        if (! $listing) {
            return ['error' => "SKU '{$sellerSku}' not found in amazon_listings_raw. Run report import first."];
        }
        $asin = $listing->asin1;
        if (empty($asin)) {
            return ['error' => "ASIN empty for SKU '{$sellerSku}'."];
        }

        $context = [];
        $updates = $this->enrichListingData($asin, $listing->seller_sku, $context);

        if ($debug) {
            Log::info('AmazonSpApiService: enrichSingleSku before save', [
                'sku' => $listing->seller_sku,
                'asin' => $asin,
                'updates_keys' => array_keys($updates),
                'updates_count' => count($updates),
                'bullet_point_type' => isset($updates['bullet_point']) ? gettype($updates['bullet_point']) : null,
            ]);
        }

        // Merge image URLs into raw_data (image-url, image-url-1..9)
        $imageUrlsForRaw = $updates['_image_urls_for_raw_data'] ?? null;
        unset($updates['_image_urls_for_raw_data']);
        if ($imageUrlsForRaw !== null && is_array($imageUrlsForRaw)) {
            $rawData = $listing->raw_data;
            if (! is_array($rawData)) {
                $rawData = is_string($rawData) ? (json_decode($rawData, true) ?? []) : [];
            }
            foreach ($imageUrlsForRaw as $k => $v) {
                if (is_string($v) && $v !== '') {
                    $rawData[$k] = $v;
                }
            }
            $updates['raw_data'] = $rawData;
        }

        // Filter to fillable columns only for correct persistence
        $fillable = (new AmazonListingRaw)->getFillable();
        $filtered = [];
        foreach ($updates as $k => $v) {
            if (in_array($k, $fillable, true)) {
                $filtered[$k] = $v;
            }
        }
        $filteredCount = count($filtered);

        if (! empty($updates)) {
            if ($filteredCount < count($updates)) {
                $dropped = array_diff_key($updates, $filtered);
                Log::warning('AmazonSpApiService: enrichSingleSku - dropped non-fillable keys', [
                    'dropped' => array_keys($dropped),
                ]);
            }
            $listing->update($filtered);
            if ($debug) {
                $listing->refresh();
                Log::info('AmazonSpApiService: enrichSingleSku after save', [
                    'sku' => $listing->seller_sku,
                    'fields_saved' => $filteredCount,
                    'bullet_point_saved' => $listing->bullet_point,
                    'condition_type' => $listing->condition_type ?? 'N/A',
                    'condition_type_display' => $listing->condition_type_display ?? 'N/A',
                ]);
            }
        }

        return [
            'success' => true,
            'sku' => $listing->seller_sku,
            'asin' => $asin,
            'updates_count' => $filteredCount,
            'warnings' => $context['warnings'] ?? [],
        ];
    }

    /**
     * GET_MERCHANT_LISTINGS_ALL_DATA is known to return empty image-url. This method fetches
     * listing item details (images, videos, bullet points) via the Listings Items API when available.
     * Video extraction only uses explicit video attribute names (product_video, video, etc.);
     * media_location is not treated as a video key (it is used for images). If Listings Items API
     * does not return videos for your SKUs, consider: Catalog Items API (/catalog/2022-04-01/items/{asin}),
     * A+ Content API, or storing video URLs in a separate table for manual maintenance.
     * Returns:
     * [
     *     'success' => true,
     *     'images' => [...],
     *     'videos' => [
     *         ['url' => '...', 'thumbnail' => '...', 'duration' => ''],
     *     ],
     *     'bullet_points' => [...],
     * ].
     */
    public function getListingsItemMedia(string $sellerSku): array
    {
        $empty = ['images' => [], 'videos' => [], 'bullet_points' => []];

        try {
            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                return ['success' => false, 'message' => 'Could not obtain access token.'] + $empty;
            }

            $sellerId = config('services.amazon_sp.seller_id');
            if (empty($sellerId)) {
                return ['success' => false, 'message' => 'AMAZON_SELLER_ID is not set in .env.'] + $empty;
            }

            $marketplaceId = $this->marketplaceId ?? config('services.amazon_sp.marketplace_id');
            if (empty($marketplaceId)) {
                return ['success' => false, 'message' => 'SPAPI_MARKETPLACE_ID is not set.'] + $empty;
            }

            $skuEncoded = rawurlencode($sellerSku);
            // IMPORTANT: use includedData=attributes so that we always get the rich
            // attributes block (images, videos, bullet points) rather than summaries only.
            $url = $this->endpoint . '/listings/2021-08-01/items/' . $sellerId . '/' . $skuEncoded
                . '?marketplaceIds=' . $marketplaceId . '&includedData=attributes';

            $response = Http::withoutVerifying()->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            $body = $response->json();

            if ($response->status() !== 200) {
                Log::warning('Amazon Listings Item: API error', ['status' => $response->status(), 'body' => $body]);

                return ['success' => false, 'message' => $body['errors'][0]['message'] ?? 'Listings Item API error.'] + $empty;
            }

            $attributes = isset($body['attributes']) && is_array($body['attributes']) ? $body['attributes'] : [];

            Log::info('Amazon Listings Item: attributes keys', [
                'sku' => $sellerSku,
                'attribute_keys' => array_keys($attributes),
            ]);

            $this->logAplusContentIfPresent($body, $attributes, $sellerSku);

            // Prefer explicit extraction from the attributes block, then fall back to the
            // more generic scanners as a safety net.
            $imagesFromAttributes = $this->extractImagesFromAttributes($attributes);
            $genericImages = $this->extractImageUrlsFromListingsItemResponse($body);
            $images = array_values(array_unique(array_merge($imagesFromAttributes, $genericImages)));

            Log::info('Amazon Listings Item: image sequence', [
                'sku' => $sellerSku,
                'count' => count($images),
                'order' => array_map(fn ($u) => substr($u, -40), $images),
            ]);

            $videosFromAttributes = $this->extractVideosFromAttributes($attributes, $images);
            $genericVideos = $this->extractVideoUrlsFromListingsItemResponse($body, $images);
            $videos = $this->mergeVideoLists($videosFromAttributes, $genericVideos);

            $bulletPoints = $this->extractBulletPointsFromListingsItemResponse($body);

            return ['success' => true, 'images' => $images, 'videos' => $videos, 'bullet_points' => $bulletPoints];
        } catch (\Throwable $e) {
            Log::warning('Amazon Listings Item: Exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()] + $empty;
        }
    }

    /**
     * Fetch and return the first image URL for a given seller SKU using the Listings Items API.
     * Returns the URL string or null when not available.
     */
    public function syncThumbnailForSku(string $sellerSku): ?string
    {
        $media = $this->getListingsItemMedia($sellerSku);

        if (! is_array($media) || empty($media['success'])) {
            return null;
        }

        $images = $media['images'] ?? [];
        if (! is_array($images) || empty($images)) {
            return null;
        }

        $first = $images[0] ?? null;
        if (is_string($first) && $first !== '') {
            return $first;
        }
        if (is_array($first)) {
            $url = $first['url'] ?? $first['locator'] ?? $first['media_location'] ?? null;
            return is_string($url) && $url !== '' ? $url : null;
        }

        return null;
    }

    /**
     * Check and log if A+ Content (Enhanced Brand Content) is present in the API response.
     * A+ content keys may include: a_plus_content, aplus_content, aplus, brand_content,
     * enhanced_content, rich_content, etc.
     */
    private function logAplusContentIfPresent(array $body, array $attributes, string $sku): void
    {
        $aplusPattern = '#a_?plus|aplus|brand_content|enhanced_content|rich_content|brand_story#i';
        $found = [];
        foreach (array_keys($attributes) as $k) {
            if (preg_match($aplusPattern, (string) $k)) {
                $found[$k] = is_array($attributes[$k]) ? 'array(' . count($attributes[$k]) . ')' : gettype($attributes[$k]);
            }
        }
        array_walk_recursive($body, function ($v, $k) use (&$found, $aplusPattern) {
            if (is_string($k) && preg_match($aplusPattern, $k)) {
                $found[$k] = is_string($v) ? substr($v, 0, 100) : gettype($v);
            }
        });
        if (count($found) > 0) {
            Log::info('Amazon Listings Item: A+ Content keys found', [
                'sku' => $sku,
                'aplus_keys' => $found,
            ]);
        } else {
            Log::debug('Amazon Listings Item: no A+ Content keys in response', ['sku' => $sku]);
        }
    }

    /**
     * Log only keys that explicitly indicate video (not generic "media" which is used for images).
     * Avoids treating media_location / media as video and causing false positives.
     */
    private function logVideoRelatedKeys(array $data, string $sku): void
    {
        $explicitVideoKeyPattern = '#\bvideo\b|video_|_video|product_video|video_url|video_locator|video_content|video_metadata|main_product_video#i';
        $videoKeys = [];
        array_walk_recursive($data, function ($v, $k) use (&$videoKeys, $explicitVideoKeyPattern) {
            if (is_string($k) && preg_match($explicitVideoKeyPattern, $k)) {
                $videoKeys[$k] = is_string($v) ? substr($v, 0, 80) : gettype($v);
            }
        });
        if (count($videoKeys) > 0) {
            Log::debug('Amazon Listings Item: explicit video-related keys in response', ['sku' => $sku, 'video_keys' => $videoKeys]);
        }
    }

    /**
     * Recursively find attribute keys that indicate video data (for debugging).
     * Only keys that explicitly contain "video", not generic "media".
     */
    private function logNestedVideoKeysRecursive(array $data, string $sku, array $path = []): void
    {
        $explicitVideoKeys = [
            'product_video', 'videos', 'video', 'external_product_video', 'video_locator',
            'video_url', 'video_metadata', 'video_content', 'main_product_video_locator',
        ];
        foreach ($data as $k => $v) {
            $keyLower = is_string($k) ? strtolower($k) : '';
            $currentPath = array_merge($path, [$k]);
            if (in_array($keyLower, $explicitVideoKeys, true)) {
                Log::debug('Amazon Listings Item: nested video attribute path', [
                    'sku' => $sku,
                    'path' => implode('.', $currentPath),
                    'type' => is_array($v) ? 'array' : gettype($v),
                    'sample' => is_string($v) ? substr($v, 0, 100) : (is_array($v) ? 'count=' . count($v) : null),
                ]);
            }
            if (is_array($v) && count($currentPath) < 5) {
                $this->logNestedVideoKeysRecursive($v, $sku, $currentPath);
            }
        }
    }

    /** Return true if URL looks like an image (by extension). */
    private function urlLooksLikeImage(string $url): bool
    {
        return (bool) preg_match('#\.(jpe?g|png|gif|webp)(\?|$)#i', $url);
    }

    private function extractBulletPointsFromListingsItemResponse(array $data): array
    {
        $bullets = [];
        $keysToTry = ['bullet_points', 'feature_bullets', 'bullet_point', 'features', 'product_description', 'item_description', 'key_product_features', 'item_notes', 'generic_keyword'];
        foreach ($keysToTry as $key) {
            $val = $this->getNestedValue($data, $key);
            if ($val === null) {
                continue;
            }
            if (is_array($val)) {
                foreach ($val as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $bullets[] = trim($item);
                    }
                    if (is_array($item) && isset($item['value'])) {
                        $v = $item['value'];
                        if (is_string($v) && trim($v) !== '') {
                            $bullets[] = trim($v);
                        }
                    }
                }
                if (count($bullets) > 0) {
                    break;
                }
            }
            if (is_string($val) && trim($val) !== '') {
                $split = preg_split('/\n|\r\n|•|\*|(?<=[.!?])\s+/', $val);
                foreach ($split as $line) {
                    $line = trim($line, " \t\n\r\0\x0B•*-\t");
                    if ($line !== '') {
                        $bullets[] = $line;
                    }
                }
                if (count($bullets) > 0) {
                    break;
                }
            }
        }
        if (count($bullets) === 0) {
            array_walk_recursive($data, function ($v) use (&$bullets) {
                if (is_string($v) && strlen($v) > 20 && strlen($v) < 500 && preg_match('/\b(feature|includes|comes with)\b/i', $v)) {
                    $bullets[] = trim($v);
                }
            });
            $bullets = array_unique(array_slice($bullets, 0, 20));
        }

        return array_values(array_unique($bullets));
    }

    private function getNestedValue(array $data, string $key): mixed
    {
        $keyLower = strtolower($key);
        foreach ($data as $k => $v) {
            if (strtolower((string) $k) === $keyLower) {
                return $v;
            }
            if (is_array($v)) {
                $found = $this->getNestedValue($v, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Extract image URLs from the Listings Items API attributes block.
     * Focuses on:
     *  - attributes.main_product_image
     *  - attributes.other_product_image
     *  - attributes.product_image
     */
    private function extractImagesFromAttributes(array $attributes): array
    {
        if (empty($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $k => $v) {
            $normalized[strtolower((string) $k)] = $v;
        }

        $imageAttrKeys = [
            'main_product_image_locator',
            'other_product_image_locator_1',
            'other_product_image_locator_2',
            'other_product_image_locator_3',
            'other_product_image_locator_4',
            'other_product_image_locator_5',
            'other_product_image_locator_6',
            'other_product_image_locator_7',
            'other_product_image_locator_8',
            'main_product_image',
            'other_product_image',
            'product_image',
            'images',
            'product_images',
            'other_product_images',
            'main_image',
            'variant_images',
            'fulfillment_images',
        ];

        $urls = [];
        $seen = [];

        foreach ($imageAttrKeys as $attrKey) {
            if (! isset($normalized[$attrKey]) || ! is_array($normalized[$attrKey])) {
                continue;
            }

            foreach ($normalized[$attrKey] as $entry) {
                $value = is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $entry;

                // Sometimes value itself is another wrapper with "value" inside.
                if (is_array($value) && array_key_exists('value', $value) && (is_string($value['value']) || is_array($value['value']))) {
                    $value = $value['value'];
                }

                $candidates = [];

                if (is_string($value)) {
                    $candidates[] = $value;
                } elseif (is_array($value)) {
                    foreach (['media_location', 'locator', 'url', 'image_url'] as $key) {
                        if (! empty($value[$key]) && is_string($value[$key])) {
                            $candidates[] = $value[$key];
                        }
                    }
                }

                foreach ($candidates as $rawUrl) {
                    $url = trim($rawUrl);
                    if ($url === '' || isset($seen[$url])) {
                        continue;
                    }
                    if (! preg_match('#^https?://#i', $url)) {
                        continue;
                    }
                    $seen[$url] = true;
                    $urls[] = $url;
                }
            }
        }

        return array_values($urls);
    }

    /**
     * Extract video metadata (url, thumbnail, duration) from the Listings Items API
     * attributes block. Only considers explicit video attribute names (not media_location).
     * Filters out URLs that are clearly images or already in image list to avoid false positives.
     */
    private function extractVideosFromAttributes(array $attributes, array $imageUrls): array
    {
        if (empty($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $k => $v) {
            $normalized[strtolower((string) $k)] = $v;
        }

        $videoAttrKeys = [
            'product_video',
            'videos',
            'video',
            'external_product_video',
            'video_locator',
            'video_url',
            'video_metadata',
            'video_content',
            'main_product_video_locator',
        ];

        $videos = [];
        $seen = [];
        $firstImage = $imageUrls[0] ?? '';
        $imageSet = array_flip(array_map('strval', $imageUrls));

        $collectEntries = function ($val) use (&$collectEntries): array {
            if (! is_array($val)) {
                return [];
            }
            $entries = [];
            foreach ($val as $item) {
                if (is_array($item) && (isset($item['value']) || isset($item['url']) || isset($item['locator']) || isset($item['media_location']))) {
                    $entries[] = $item;
                } elseif (is_string($item) && preg_match('#^https?://#', $item)) {
                    $entries[] = ['value' => $item];
                } elseif (is_array($item)) {
                    $nested = $collectEntries($item);
                    $entries = array_merge($entries, $nested);
                }
            }
            return $entries;
        };

        $hasVideoAttributes = false;

        foreach ($videoAttrKeys as $attrKey) {
            if (! isset($normalized[$attrKey])) {
                continue;
            }
            $val = $normalized[$attrKey];
            $hasVideoAttributes = true;

            $entries = is_array($val) ? $collectEntries($val) : [];
            if (empty($entries) && is_array($val) && ! isset($val[0])) {
                $entries = [$val];
            }

            foreach ($entries as $entry) {
                $parsed = $this->parseVideoAttributeEntry($entry);
                $url = $parsed['url'] ?? null;

                if (! is_string($url)) {
                    continue;
                }

                $url = trim($url);
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }
                if (! preg_match('#^https?://#i', $url)) {
                    continue;
                }
                if ($this->urlLooksLikeImage($url) || isset($imageSet[$url])) {
                    continue;
                }

                $seen[$url] = true;

                $thumbnail = $parsed['thumbnail'] ?? '';
                if (! is_string($thumbnail) || trim($thumbnail) === '') {
                    $thumbnail = $firstImage;
                }

                $duration = (string) ($parsed['duration'] ?? '');

                $videos[] = [
                    'url' => $url,
                    'thumbnail' => $thumbnail,
                    'duration' => $duration,
                ];
            }
        }

        return $videos;
    }

    /**
     * Parse a single product_video-style attribute entry into a normalized
     * ['url' => ..., 'thumbnail' => ..., 'duration' => ...] array.
     *
     * Handles shapes like:
     *  - ['value' => 'https://...mp4']
     *  - ['value' => ['locator' => 'https://...mp4', 'thumbnail' => 'https://...jpg']]
     *  - ['locator' => 'https://...mp4']
     */
    private function parseVideoAttributeEntry(mixed $entry): array
    {
        $url = null;
        $thumbnail = '';
        $duration = '';

        $value = is_array($entry) && array_key_exists('value', $entry) ? $entry['value'] : $entry;

        // Sometimes there's a nested "value" wrapper as well.
        if (is_array($value) && array_key_exists('value', $value) && (is_string($value['value']) || is_array($value['value']))) {
            $inner = $value['value'];
            if (is_string($inner)) {
                $value = $inner;
            } elseif (is_array($inner)) {
                $value = array_merge($value, $inner);
            }
        }

        if (is_string($value)) {
            $url = $value;
        } elseif (is_array($value)) {
            foreach (['locator', 'url', 'media_location', 'asset_url'] as $key) {
                if (! empty($value[$key]) && is_string($value[$key])) {
                    $url = $value[$key];
                    break;
                }
            }

            foreach (['thumbnail', 'thumbnail_url', 'thumbnailUrl', 'preview_image', 'preview_image_location', 'previewImageLocation'] as $tKey) {
                if (! empty($value[$tKey]) && is_string($value[$tKey])) {
                    $thumbnail = $value[$tKey];
                    break;
                }
            }

            foreach (['duration', 'durationSeconds', 'duration_seconds'] as $dKey) {
                if (isset($value[$dKey]) && $value[$dKey] !== '' && $value[$dKey] !== null) {
                    $duration = (string) $value[$dKey];
                    break;
                }
            }
        }

        if ($url === null && is_array($entry)) {
            foreach (['url', 'locator', 'media_location'] as $key) {
                if (! empty($entry[$key]) && is_string($entry[$key])) {
                    $url = $entry[$key];
                    break;
                }
            }
        }

        return [
            'url' => $url,
            'thumbnail' => $thumbnail,
            'duration' => $duration,
        ];
    }

    /**
     * Merge two video lists while de-duplicating by URL and ensuring the
     * structure ['url' => ..., 'thumbnail' => ..., 'duration' => ''].
     */
    private function mergeVideoLists(array $primary, array $fallback): array
    {
        $merged = [];
        $seen = [];

        $add = function (array $video) use (&$merged, &$seen) {
            $url = isset($video['url']) ? trim((string) $video['url']) : '';
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;

            $merged[] = [
                'url' => $url,
                'thumbnail' => isset($video['thumbnail']) ? (string) $video['thumbnail'] : '',
                'duration' => isset($video['duration']) ? (string) $video['duration'] : '',
            ];
        };

        foreach ($primary as $v) {
            $add($v);
        }
        foreach ($fallback as $v) {
            $add($v);
        }

        return $merged;
    }

    private function extractImageUrlsFromListingsItemResponse(array $data): array
    {
        $urls = [];
        $seen = [];
        $re = '/^https?:\/\/[^\s"\']+\.(jpe?g|png|gif|webp)(\?[^\s"\']*)?$/i';
        array_walk_recursive($data, function ($v) use (&$urls, &$seen, $re) {
            if (!is_string($v) || isset($seen[$v])) {
                return;
            }
            $v = trim($v);
            if ($v === '' || strlen($v) < 20) {
                return;
            }
            if (preg_match($re, $v) || (preg_match('#^https?://#', $v) && preg_match('#(image|img|photo|media)#i', $v))) {
                $seen[$v] = true;
                $urls[] = $v;
            }
        });
        return array_values(array_unique($urls));
    }

    /**
     * Extract video URLs from full response only from explicit video keys.
     * Excludes image URLs (by extension and by membership in imageUrls) to avoid
     * treating media_location image URLs as videos.
     */
    private function extractVideoUrlsFromListingsItemResponse(array $data, array $imageUrls): array
    {
        $videos = [];
        $seen = [];
        $firstImage = $imageUrls[0] ?? '';
        $imageSet = array_flip(array_map('strval', $imageUrls));
        $videoUrlPattern = '#(youtube\.com|youtu\.be|vimeo\.com|\.mp4|product-video)#i';
        $addVideo = function (string $url, string $thumb = '', string $duration = '') use (&$videos, &$seen, $firstImage, $imageSet) {
            $url = trim($url);
            if ($url === '' || strlen($url) < 15 || isset($seen[$url])) {
                return;
            }
            if (! preg_match('#^https?://#', $url)) {
                return;
            }
            if (preg_match('#\.(jpe?g|png|gif|webp)(\?|$)#i', $url)) {
                return;
            }
            if (isset($imageSet[$url])) {
                return;
            }
            $seen[$url] = true;
            $videos[] = [
                'url' => $url,
                'thumbnail' => $thumb ?: $firstImage,
                'duration' => $duration,
            ];
        };
        $explicitVideoKeys = ['video_urls', 'product_videos', 'videos', 'video', 'main_video', 'video_url', 'product_video'];
        foreach ($explicitVideoKeys as $key) {
            $val = $this->getNestedValue($data, $key);
            if ($val === null) {
                continue;
            }
            if (is_string($val)) {
                $addVideo($val);
                continue;
            }
            if (is_array($val)) {
                foreach ($val as $item) {
                    if (is_string($item)) {
                        $addVideo($item);
                    }
                    if (is_array($item)) {
                        $url = $item['url'] ?? $item['video_url'] ?? $item['src'] ?? null;
                        if (is_string($url)) {
                            $addVideo(
                                $url,
                                $item['thumbnail'] ?? $item['thumb'] ?? '',
                                (string) ($item['duration'] ?? '')
                            );
                        }
                    }
                }
            }
        }
        array_walk_recursive($data, function ($v, $k) use (&$videos, &$seen, $firstImage, $videoUrlPattern, $addVideo) {
            if (! is_string($v)) {
                return;
            }
            $v = trim($v);
            if ($v === '' || strlen($v) < 15 || isset($seen[$v])) {
                return;
            }
            if (! preg_match('#^https?://#', $v)) {
                return;
            }
            $key = is_string($k) ? strtolower($k) : '';
            $isVideoKey = str_contains($key, 'video') && ! str_contains($key, 'media_location');
            $looksLikeVideoUrl = (bool) preg_match($videoUrlPattern, $v);
            if ($isVideoKey || $looksLikeVideoUrl) {
                $addVideo($v);
            }
        });
        return $videos;
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

    /**
     * Prefer seller SKU from amazon_metrics when identifier is ASIN/FNSKU or alternate column.
     */
    private function resolveAmazonSellerSkuForBullets(string $identifier): string
    {
        $id = trim($identifier);
        if ($id === '' || ! Schema::hasTable('amazon_metrics')) {
            return $id;
        }

        $row = DB::table('amazon_metrics')
            ->where(function ($q) use ($id) {
                $q->where('sku', $id)
                    ->orWhere('sku', strtoupper($id))
                    ->orWhere('sku', strtolower($id));
            })
            ->first();

        if (! $row) {
            $cols = Schema::getColumnListing('amazon_metrics');
            foreach (['asin', 'fnsku', 'seller_sku', 'amazon_sku'] as $col) {
                if (! in_array($col, $cols, true)) {
                    continue;
                }
                $row = DB::table('amazon_metrics')->where($col, $id)->first();
                if ($row) {
                    break;
                }
            }
        }

        if ($row && ! empty($row->sku)) {
            return trim((string) $row->sku);
        }

        return $id;
    }

    /**
     * GET current listing attributes (for image patch path discovery).
     *
     * @return array<string, mixed>
     */
    private function getListingItemAttributes(string $sellerSku, string $accessToken): array
    {
        $sellerId = config('services.amazon_sp.seller_id');
        $marketplaceId = (string) config('services.amazon_sp.marketplace_id', 'ATVPDKIKX0DER');
        if (empty($sellerId)) {
            return [];
        }

        $encoded = rawurlencode($sellerSku);
        $url = $this->endpoint.'/listings/2021-08-01/items/'.$sellerId.'/'.$encoded
            .'?marketplaceIds='.$marketplaceId.'&includedData=attributes';

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(45)->get($url);

            if (! $response->successful()) {
                return [];
            }

            $body = $response->json();

            return isset($body['attributes']) && is_array($body['attributes']) ? $body['attributes'] : [];
        } catch (\Throwable $e) {
            Log::warning('Amazon getListingItemAttributes failed', ['sku' => $sellerSku, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Case-insensitive check for an attribute key on the listing.
     */
    private function listingHasAttributeKey(array $attributes, string $key): bool
    {
        $k = strtolower($key);
        foreach (array_keys($attributes) as $name) {
            if (strtolower((string) $name) === $k) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build ordered patch strategies for image updates. Paths must match Product Type Definitions
     * (see Listings Items API + Product Type Definitions API). Common US: main_product_image_locator + other_product_image_locator_1..8.
     *
     * @param  list<string>  $urls
     * @return list<array{label: string, patches: list<array<string, mixed>>}>
     */
    private function buildAmazonListingImagePatchStrategies(array $urls, string $marketplaceId, array $attributes): array
    {
        $urls = array_values(array_slice($urls, 0, 9));
        $strategies = [];

        $locatorValueSnake = function (string $url) use ($marketplaceId): array {
            return [
                [
                    'marketplace_id' => $marketplaceId,
                    'media_location' => $url,
                ],
            ];
        };

        $locatorValueCamel = function (string $url) use ($marketplaceId): array {
            return [
                [
                    'marketplaceId' => $marketplaceId,
                    'mediaLocation' => $url,
                ],
            ];
        };

        // 1) Standard locator model (fixes "invalid path" when main_product_image is not in schema)
        $patchesLocatorSnake = [
            [
                'op' => 'replace',
                'path' => '/attributes/main_product_image_locator',
                'value' => $locatorValueSnake($urls[0]),
            ],
        ];
        foreach (array_slice($urls, 1) as $i => $url) {
            $n = $i + 1;
            if ($n > 8) {
                break;
            }
            $patchesLocatorSnake[] = [
                'op' => 'replace',
                'path' => '/attributes/other_product_image_locator_'.$n,
                'value' => $locatorValueSnake($url),
            ];
        }
        $strategies[] = ['label' => 'main_product_image_locator + other_product_image_locator_1..N (marketplace_id + media_location)', 'patches' => $patchesLocatorSnake];

        // 2) Same paths, camelCase keys (some schemas / SDK examples)
        $patchesLocatorCamel = [
            [
                'op' => 'replace',
                'path' => '/attributes/main_product_image_locator',
                'value' => $locatorValueCamel($urls[0]),
            ],
        ];
        foreach (array_slice($urls, 1) as $i => $url) {
            $n = $i + 1;
            if ($n > 8) {
                break;
            }
            $patchesLocatorCamel[] = [
                'op' => 'replace',
                'path' => '/attributes/other_product_image_locator_'.$n,
                'value' => $locatorValueCamel($url),
            ];
        }
        $strategies[] = ['label' => 'main_product_image_locator + other_product_image_locator_1..N (marketplaceId + mediaLocation)', 'patches' => $patchesLocatorCamel];

        // 3) Legacy / alternate: main_product_image + other_product_image (array of locator rows)
        $rowSnake = function (string $url) use ($marketplaceId): array {
            return [
                'marketplace_id' => $marketplaceId,
                'media_location' => $url,
            ];
        };
        $mainOther = [
            [
                'op' => 'replace',
                'path' => '/attributes/main_product_image',
                'value' => [$rowSnake($urls[0])],
            ],
        ];
        $rest = array_slice($urls, 1);
        if ($rest !== []) {
            $mainOther[] = [
                'op' => 'replace',
                'path' => '/attributes/other_product_image',
                'value' => array_map($rowSnake, $rest),
            ];
        }
        $strategies[] = ['label' => 'main_product_image + other_product_image', 'patches' => $mainOther];

        // 4) Some product types expose /attributes/images (nested main.link + order)
        $imagesNested = [];
        foreach ($urls as $i => $url) {
            $imagesNested[] = [
                'main' => [
                    'link' => $url,
                    'order' => $i + 1,
                ],
            ];
        }
        $strategies[] = ['label' => '/attributes/images (main.link + order)', 'patches' => [
            [
                'op' => 'replace',
                'path' => '/attributes/images',
                'value' => $imagesNested,
            ],
        ]];

        // 5) fulfillment_images (reported in some seller docs)
        $strategies[] = ['label' => '/attributes/fulfillment_images (marketplace_id + media_location)', 'patches' => [
            [
                'op' => 'replace',
                'path' => '/attributes/fulfillment_images',
                'value' => array_map($rowSnake, $urls),
            ],
        ]];

        // If we know which keys exist on the listing, try those first
        $preferred = [];
        $restStrategies = [];
        if ($this->listingHasAttributeKey($attributes, 'main_product_image_locator')) {
            $preferred[] = $strategies[0];
            $preferred[] = $strategies[1];
        }
        if ($this->listingHasAttributeKey($attributes, 'main_product_image') && ! $this->listingHasAttributeKey($attributes, 'main_product_image_locator')) {
            $preferred[] = $strategies[2];
        }
        if ($this->listingHasAttributeKey($attributes, 'images')) {
            $preferred[] = $strategies[3];
        }
        if ($this->listingHasAttributeKey($attributes, 'fulfillment_images')) {
            $preferred[] = $strategies[4];
        }

        $seen = [];
        $ordered = [];
        foreach (array_merge($preferred, $strategies) as $s) {
            $h = md5(json_encode($s['patches']));
            if (isset($seen[$h])) {
                continue;
            }
            $seen[$h] = true;
            $ordered[] = $s;
        }

        return $ordered;
    }

    /**
     * PATCH listing image attributes (main + other product images) via Listings Items API.
     *
     * @param  list<string>  $imageUrls  Public HTTPS URLs (JPEG/PNG per Amazon rules).
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $sku = $this->resolveAmazonSellerSkuForBullets($identifier);
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 9);
        if ($sku === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or ASIN from amazon_metrics) and at least one image URL are required.'];
        }

        foreach ($urls as $u) {
            if (! preg_match('#^https://#i', $u)) {
                return ['success' => false, 'message' => 'Amazon requires publicly reachable HTTPS image URLs.'];
            }
        }

        $sellerId = config('services.amazon_sp.seller_id');
        $marketplaceId = (string) config('services.amazon_sp.marketplace_id', 'ATVPDKIKX0DER');
        if (empty($sellerId)) {
            return ['success' => false, 'message' => 'Amazon Seller ID is not configured.'];
        }

        $amazonSku = null;
        $productType = null;
        $lastError = null;
        $triedLabels = [];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $accessToken = $this->getAccessToken($attempt > 1);
                if (empty($accessToken)) {
                    return ['success' => false, 'message' => 'Failed to get Amazon access token.'];
                }

                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        return ['success' => false, 'message' => 'SKU not found in Amazon.'];
                    }
                }

                if ($productType === null) {
                    $productType = $this->getAmazonProductType($sku, $amazonSku, $accessToken);
                    if (empty($productType)) {
                        return ['success' => false, 'message' => 'Product type not found for SKU.'];
                    }
                }

                $attributes = $this->getListingItemAttributes($amazonSku, $accessToken);
                $strategies = $this->buildAmazonListingImagePatchStrategies($urls, $marketplaceId, $attributes);

                $encodedSku = rawurlencode($amazonSku);
                $endpoint = $this->endpoint.'/listings/2021-08-01/items/'.$sellerId.'/'.$encodedSku.'?marketplaceIds='.$marketplaceId;

                $response = null;
                foreach ($strategies as $strategy) {
                    $triedLabels[] = $strategy['label'];
                    $body = [
                        'productType' => $productType,
                        'patches' => $strategy['patches'],
                    ];

                    $response = Http::withHeaders([
                        'x-amz-access-token' => $accessToken,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->timeout(60)->patch($endpoint, $body);

                    $responseData = $response->json() ?? [];

                    if ($response->successful() && empty($responseData['errors'])) {
                        Log::info('Amazon listing images updated', [
                            'sku' => $sku,
                            'amazon_sku' => $amazonSku,
                            'count' => count($urls),
                            'strategy' => $strategy['label'],
                            'attribute_keys_sample' => array_slice(array_keys($attributes), 0, 25),
                        ]);

                        return ['success' => true, 'message' => 'Amazon listing images updated ('.$strategy['label'].').'];
                    }

                    $lastError = $responseData['errors'][0]['message'] ?? $response->body();
                    if (is_array($lastError)) {
                        $lastError = json_encode($lastError);
                    }
                    $lastError = (string) $lastError;

                    Log::warning('Amazon image patch strategy failed', [
                        'sku' => $sku,
                        'strategy' => $strategy['label'],
                        'status' => $response->status(),
                        'message' => substr($lastError, 0, 500),
                    ]);
                }

                if (isset($response) && in_array($response->status(), [401, 403, 500, 502, 503], true) && $attempt < 2) {
                    sleep(1);
                    continue;
                }

                $summary = 'None of the image patch strategies succeeded. Tried: '.implode('; ', array_unique($triedLabels)).'. ';
                $summary .= 'Last error: '.$lastError.'. ';
                $summary .= 'Use Product Type Definitions API for product type "'.$productType.'" to confirm image attribute names for your marketplace.';

                return ['success' => false, 'message' => $summary];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt >= 2) {
                    return ['success' => false, 'message' => $lastError];
                }
            }
        }

        return ['success' => false, 'message' => (string) $lastError];
    }

    /**
     * Image Master compatibility method: push images then persist image_urls in amazon_metrics.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public function updateImages(string $identifier, array $images): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 9);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $res = $this->updateListingImages($identifier, $images);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $sku = $this->resolveAmazonSellerSkuForBullets($identifier);
        $saved = $this->saveImageUrlsToAmazonMetrics($sku, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Amazon listing images updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * Fetch A+ style description/images from Amazon listing attributes.
     *
     * @return array{
     *   success: bool,
     *   message?: string,
     *   description?: string,
     *   images?: array<int, string>,
     *   aplus_content?: string,
     *   data?: array<string, mixed>
     * }
     */
    public function fetchAplusContent(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $sku = $this->resolveAmazonSellerSkuForBullets($identifier);
        if ($sku === '') {
            return ['success' => false, 'message' => 'Could not resolve SKU.'];
        }

        Log::info('Amazon fetchAplusContent start', ['identifier' => $identifier, 'sku' => $sku]);

        $amazonSku = null;
        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $accessToken = $this->getAccessToken($attempt > 1);
                if (empty($accessToken)) {
                    return ['success' => false, 'message' => 'Failed to get Amazon access token.'];
                }

                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        return $this->fetchAplusFallbackFromDb($sku, 'SKU not found in Amazon.');
                    }
                }

                $attributes = $this->getListingItemAttributes($amazonSku, $accessToken);
                $aplusHtml = $this->extractDescriptionHtmlFromAttributes($attributes);
                $images = array_slice($this->extractImagesFromAttributes($attributes), 0, 12);
                $description = trim(strip_tags($aplusHtml));

                if ($aplusHtml === '' && $images === []) {
                    Log::warning('Amazon fetchAplusContent empty listing attributes', [
                        'sku' => $sku,
                        'amazon_sku' => $amazonSku,
                    ]);

                    return $this->fetchAplusFallbackFromDb($sku, 'No A+ content found for this SKU');
                }

                $this->saveAplusToProductMaster($sku, $aplusHtml, $images);

                Log::info('Amazon fetchAplusContent success', [
                    'sku' => $sku,
                    'amazon_sku' => $amazonSku,
                    'images_count' => count($images),
                    'html_len' => mb_strlen($aplusHtml),
                ]);

                return [
                    'success' => true,
                    'description' => $description,
                    'images' => $images,
                    'aplus_content' => $aplusHtml,
                    'data' => [
                        'sku' => $sku,
                        'amazon_sku' => $amazonSku,
                        'description_plain' => $description,
                        'description_html' => $aplusHtml,
                        'images' => $images,
                    ],
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('Amazon fetchAplusContent attempt failed', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'error' => $lastError,
                ]);
                if ($attempt < 2) {
                    sleep(1);
                }
            }
        }

        Log::error('Amazon fetchAplusContent failed', ['sku' => $sku, 'error' => $lastError]);

        return $this->fetchAplusFallbackFromDb($sku, (string) ($lastError ?: 'No A+ content found for this SKU'));
    }

    /**
     * @return array{success: bool, message: string, description: string, images: array<int, string>, aplus_content: string, data: array<string, mixed>}
     */
    private function fetchAplusFallbackFromDb(string $sku, string $message): array
    {
        $pm = null;
        if (Schema::hasTable('product_master') && Schema::hasColumn('product_master', 'sku')) {
            $pm = DB::table('product_master')
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
        }

        $fallbackDescription = trim((string) ($pm->description_1500 ?? $pm->product_description ?? ''));
        $fallbackHtml = trim((string) ($pm->amazon_aplus_content ?? ''));
        $fallbackImages = [];

        if (isset($pm->amazon_aplus_images) && is_string($pm->amazon_aplus_images) && trim($pm->amazon_aplus_images) !== '') {
            $decoded = json_decode($pm->amazon_aplus_images, true);
            if (is_array($decoded)) {
                $fallbackImages = array_values(array_filter($decoded, fn ($v) => is_string($v) && trim($v) !== ''));
            }
        }
        if ($fallbackImages === []) {
            $fallbackImages = array_slice($this->extractProductMasterImageUrls($pm), 0, 12);
        }

        return [
            'success' => false,
            'message' => $message,
            'description' => $fallbackDescription,
            'images' => $fallbackImages,
            'aplus_content' => $fallbackHtml,
            'data' => [
                'sku' => $sku,
                'description_plain' => $fallbackDescription,
                'description_html' => $fallbackHtml,
                'images' => $fallbackImages,
            ],
        ];
    }

    private function saveAplusToProductMaster(string $sku, string $html, array $images): void
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'sku')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('product_master', 'amazon_aplus_content')) {
            $update['amazon_aplus_content'] = $html;
        }
        if (Schema::hasColumn('product_master', 'amazon_aplus_images')) {
            $encoded = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $update['amazon_aplus_images'] = $encoded;
            }
        }
        if ($update === []) {
            return;
        }

        DB::table('product_master')
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->update($update);
    }

    /**
     * @param  object|null  $pm
     * @return array<int, string>
     */
    private function extractProductMasterImageUrls(?object $pm): array
    {
        if (! $pm) {
            return [];
        }
        $fields = ['image_path', 'main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7', 'image8', 'image9', 'image10', 'image11', 'image12'];
        $urls = [];
        $seen = [];
        foreach ($fields as $f) {
            $raw = trim((string) ($pm->{$f} ?? ''));
            if ($raw === '') {
                continue;
            }
            if (str_starts_with($raw, '//')) {
                $raw = 'https:'.$raw;
            } elseif (! preg_match('#^https?://#i', $raw)) {
                $base = rtrim((string) config('app.url', ''), '/');
                if ($base !== '') {
                    $raw = $base.'/'.ltrim($raw, '/');
                }
            }
            if ($raw !== '' && ! isset($seen[$raw])) {
                $seen[$raw] = true;
                $urls[] = $raw;
            }
        }

        return $urls;
    }

    private function extractDescriptionHtmlFromAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $normalized = [];
        foreach ($attributes as $k => $v) {
            $normalized[strtolower((string) $k)] = $v;
        }

        $keys = ['product_description', 'description', 'long_description', 'item_description'];
        foreach ($keys as $key) {
            $val = $normalized[$key] ?? null;
            if ($val === null) {
                continue;
            }
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
            if (is_array($val)) {
                foreach ($val as $entry) {
                    $candidate = is_array($entry) ? ($entry['value'] ?? '') : $entry;
                    if (is_string($candidate) && trim($candidate) !== '') {
                        return trim($candidate);
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToAmazonMetrics(string $sku, array $images): bool
    {
        try {
            if (! Schema::hasTable('amazon_metrics') || ! Schema::hasColumn('amazon_metrics', 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('amazon_metrics', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('amazon_metrics', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('amazon_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('amazon_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('amazon_metrics', 'created_at')) {
                DB::table('amazon_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Amazon image_urls save failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * PATCH listing bullet_point attributes (SP-API Listings Items). Full text per line; no truncation.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $sku = $this->resolveAmazonSellerSkuForBullets($identifier);
        $bulletPoints = trim($bulletPoints);
        if ($sku === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or ASIN from amazon_metrics) and bullet points are required.'];
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletPoints))));
        if ($lines === []) {
            return ['success' => false, 'message' => 'No bullet lines found.'];
        }

        $value = [];
        foreach ($lines as $line) {
            $value[] = [
                'value' => $line,
                'language_tag' => 'en_US',
            ];
        }

        $sellerId = config('services.amazon_sp.seller_id');
        if (empty($sellerId)) {
            return ['success' => false, 'message' => 'Amazon Seller ID is not configured.'];
        }

        $amazonSku = null;
        $productType = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $accessToken = $this->getAccessToken($attempt > 1);
                if (empty($accessToken)) {
                    return ['success' => false, 'message' => 'Failed to get Amazon access token.'];
                }

                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        return ['success' => false, 'message' => 'SKU not found in Amazon.'];
                    }
                }

                if ($productType === null) {
                    $productType = $this->getAmazonProductType($sku, $amazonSku, $accessToken);
                    if (empty($productType)) {
                        return ['success' => false, 'message' => 'Product type not found for SKU.'];
                    }
                }

                $encodedSku = rawurlencode($amazonSku);
                $endpoint = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

                $body = [
                    'productType' => $productType,
                    'patches' => [
                        [
                            'op' => 'replace',
                            'path' => '/attributes/bullet_point',
                            'value' => $value,
                        ],
                    ],
                ];

                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(45)->patch($endpoint, $body);

                $responseData = $response->json();

                if ($response->failed()) {
                    $lastError = $responseData['errors'][0]['message'] ?? $response->body();
                    if (in_array($response->status(), [401, 403, 500, 502, 503], true) && $attempt < 2) {
                        sleep(1);
                        continue;
                    }

                    return ['success' => false, 'message' => is_string($lastError) ? $lastError : json_encode($responseData)];
                }

                if (isset($responseData['errors']) && ! empty($responseData['errors'])) {
                    return ['success' => false, 'message' => json_encode($responseData['errors'])];
                }

                Log::info('Amazon bullet points updated', ['sku' => $sku, 'amazon_sku' => $amazonSku]);

                return ['success' => true, 'message' => 'Amazon bullet points updated.'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt >= 2) {
                    return ['success' => false, 'message' => $lastError];
                }
            }
        }

        return ['success' => false, 'message' => (string) $lastError];
    }

    /**
     * Rich listing update: HTML description (Listings Items API) plus gallery images when URLs are provided.
     * True Brand A+ Content Documents use the separate A+ Content API (see updateAplusContent); many sellers
     * still surface copy and images via product_description + main/other image locators.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, image_warning?: string}
     */
    public function updateAplusContent(string $identifier, string $description, array $imageUrls = []): array
    {
        $descResult = $this->updateDescription($identifier, $description, $imageUrls);
        if (! ($descResult['success'] ?? false)) {
            return $descResult;
        }

        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 9);
        if ($urls === []) {
            return $descResult;
        }

        $imgResult = $this->updateListingImages($identifier, $urls);
        if (! ($imgResult['success'] ?? false)) {
            return [
                'success' => true,
                'message' => ($descResult['message'] ?? 'Amazon product description updated.').' Listing images: '.($imgResult['message'] ?? 'failed'),
                'image_warning' => (string) ($imgResult['message'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'message' => 'Amazon product description and listing images updated (listing attributes; use Brand A+ Content API for published A+ modules).',
        ];
    }

    /**
     * PATCH listing `product_description` (SP-API Listings Items API). Retries token / transient errors like other listing patches.
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU (or ASIN from amazon_metrics) and description are required.'];
        }

        $sku = $this->resolveAmazonSellerSkuForBullets($identifier);
        $description = trim($description);
        if ($sku === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU (or ASIN from amazon_metrics) and description are required.'];
        }

        $descriptionWithImages = DescriptionWithImagesFormatter::buildHtmlWithImages(
            $description,
            $identifier,
            $sku,
            'Product Image',
            9,
            $imageUrls
        )['html'];

        $value = [[
            'value' => $descriptionWithImages,
            'language_tag' => 'en_US',
        ]];

        $sellerId = config('services.amazon_sp.seller_id');
        if (empty($sellerId)) {
            return ['success' => false, 'message' => 'Amazon Seller ID is not configured.'];
        }

        $amazonSku = null;
        $productType = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $accessToken = $this->getAccessToken($attempt > 1);
                if (empty($accessToken)) {
                    return ['success' => false, 'message' => 'Failed to get Amazon access token.'];
                }

                if ($amazonSku === null) {
                    $amazonSku = $this->findAmazonSkuFormat($sku, $accessToken);
                    if (empty($amazonSku)) {
                        return ['success' => false, 'message' => 'SKU not found in Amazon.'];
                    }
                }

                if ($productType === null) {
                    $productType = $this->getAmazonProductType($sku, $amazonSku, $accessToken);
                    if (empty($productType)) {
                        return ['success' => false, 'message' => 'Product type not found for SKU.'];
                    }
                }

                $encodedSku = rawurlencode($amazonSku);
                $endpoint = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$encodedSku}?marketplaceIds=ATVPDKIKX0DER";

                $body = [
                    'productType' => $productType,
                    'patches' => [
                        [
                            'op' => 'replace',
                            'path' => '/attributes/product_description',
                            'value' => $value,
                        ],
                    ],
                ];

                $response = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(45)->patch($endpoint, $body);

                $responseData = $response->json();

                if ($response->failed()) {
                    $lastError = $responseData['errors'][0]['message'] ?? $response->body();
                    if (in_array($response->status(), [401, 403, 500, 502, 503], true) && $attempt < 2) {
                        sleep(1);
                        continue;
                    }

                    return ['success' => false, 'message' => is_string($lastError) ? $lastError : json_encode($responseData)];
                }

                if (isset($responseData['errors']) && ! empty($responseData['errors'])) {
                    return ['success' => false, 'message' => json_encode($responseData['errors'])];
                }

                Log::info('Amazon product description updated', ['sku' => $sku, 'amazon_sku' => $amazonSku]);

                return ['success' => true, 'message' => 'Amazon product description updated.'];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt >= 2) {
                    return ['success' => false, 'message' => $lastError];
                }
            }
        }

        return ['success' => false, 'message' => (string) $lastError];
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->updateDescription($identifier, $description, $imageUrls);
    }

    /**
     * Description Master 2.0: fetch Brand A+ Content via A+ Content API (searchContentPublishRecords + getContentDocument),
     * map modules to DM2 fields, then optionally enrich from Listings Item / legacy fetchAplusContent for gaps.
     *
     * @return array{
     *   success: bool,
     *   message?: string,
     *   partial?: bool,
     *   source?: string,
     *   data?: array<string, mixed>
     * }
     */
    public function fetchAplusContentForSku(string $identifier): array
    {
        $sku = $this->resolveAmazonSellerSkuForBullets(trim($identifier));
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $marketplaceId = (string) config('services.amazon_sp.marketplace_id', 'ATVPDKIKX0DER');
        $dm2 = AplusContentDocumentParser::parseToDm2([]);
        $source = [];

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return ['success' => false, 'message' => 'Failed to get Amazon access token.'];
        }

        $asin = $this->resolveAsinForSku($sku);
        if ($asin !== null) {
            $refKey = $this->searchContentPublishRecords($asin, $marketplaceId, $accessToken);
            if ($refKey !== null && $refKey !== '') {
                $docJson = $this->getContentDocument($refKey, $marketplaceId, $accessToken);
                if (is_array($docJson) && $docJson !== []) {
                    $dm2 = AplusContentDocumentParser::parseToDm2($docJson);
                    $source[] = 'aplus_content_api';
                }
            }
        }

        $partialBeforeMerge = $this->dm2PayloadMostlyEmpty($dm2);
        $legacy = $this->fetchAplusContent($sku);
        $dm2 = $this->mergeDm2WithLegacyFetch($dm2, $legacy);

        if ($this->dm2PayloadMostlyEmpty($dm2)) {
            return [
                'success' => false,
                'message' => 'No A+ content could be loaded for this SKU. Ensure ASIN is in amazon_metrics and Brand Registry A+ is published.',
                'partial' => true,
                'source' => $source,
                'data' => $this->normalizeDm2Payload($dm2),
            ];
        }

        return [
            'success' => true,
            'message' => 'Amazon content loaded.',
            'partial' => $partialBeforeMerge,
            'source' => array_values(array_unique(array_merge($source, ['listings_attributes_fallback']))),
            'data' => $this->normalizeDm2Payload($dm2),
        ];
    }

    /**
     * @param  array<string, mixed>  $dm2
     */
    private function dm2PayloadMostlyEmpty(array $dm2): bool
    {
        $hasText = trim((string) ($dm2['description_v2_bullets'] ?? '')) !== ''
            || trim((string) ($dm2['description_v2_description'] ?? '')) !== ''
            || trim((string) ($dm2['description_v2_package'] ?? '')) !== ''
            || trim((string) ($dm2['description_v2_brand'] ?? '')) !== '';
        $imgs = $dm2['description_v2_images'] ?? [];
        $hasImg = is_array($imgs) && array_filter($imgs, fn ($u) => is_string($u) && trim($u) !== '') !== [];
        $feats = $dm2['description_v2_features'] ?? [];
        $hasFeat = false;
        if (is_array($feats)) {
            foreach ($feats as $f) {
                if (is_array($f) && (trim((string) ($f['title'] ?? '')) !== '' || trim((string) ($f['body'] ?? '')) !== '')) {
                    $hasFeat = true;
                    break;
                }
            }
        }
        $specs = $dm2['description_v2_specifications'] ?? [];

        return ! $hasText && ! $hasImg && ! $hasFeat && (! is_array($specs) || $specs === []);
    }

    /**
     * @param  array<string, mixed>  $dm2
     * @param  array<string, mixed>  $legacy  Output shape from fetchAplusContent
     * @return array<string, mixed>
     */
    private function mergeDm2WithLegacyFetch(array $dm2, array $legacy): array
    {
        $data = (array) ($legacy['data'] ?? []);
        $plain = trim((string) ($data['description_plain'] ?? ''));
        $html = trim((string) ($data['description_html'] ?? ''));
        $images = isset($data['images']) && is_array($data['images']) ? array_values(array_filter($data['images'], fn ($u) => is_string($u) && trim($u) !== '')) : [];

        if (trim((string) ($dm2['description_v2_description'] ?? '')) === '' && $plain !== '') {
            $dm2['description_v2_description'] = $plain;
        } elseif (trim((string) ($dm2['description_v2_description'] ?? '')) === '' && $html !== '') {
            $dm2['description_v2_description'] = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ((! isset($dm2['description_v2_images']) || ! is_array($dm2['description_v2_images']) || $dm2['description_v2_images'] === []) && $images !== []) {
            $dm2['description_v2_images'] = array_slice($images, 0, 12);
        }

        if (trim((string) ($dm2['description_v2_bullets'] ?? '')) === '' && $html !== '') {
            $dm2['description_v2_bullets'] = $this->extractBulletLinesFromHtml($html);
        }

        return $dm2;
    }

    private function extractBulletLinesFromHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $lis = $dom->getElementsByTagName('li');
        $lines = [];
        foreach ($lis as $li) {
            $t = trim(preg_replace('/\s+/u', ' ', $li->textContent ?? '') ?? '');
            if ($t !== '' && count($lines) < 5) {
                $lines[] = $t;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $dm2
     * @return array<string, mixed>
     */
    private function normalizeDm2Payload(array $dm2): array
    {
        $images = $dm2['description_v2_images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_values(array_pad(array_slice(array_values(array_filter(array_map('trim', $images), fn ($u) => $u !== '')), 0, 12), 12, ''));

        $features = $dm2['description_v2_features'] ?? [];
        if (! is_array($features)) {
            $features = [];
        }
        while (count($features) < 4) {
            $features[] = ['title' => '', 'body' => ''];
        }
        $features = array_slice($features, 0, 4);

        $specs = $dm2['description_v2_specifications'] ?? [];
        if (! is_array($specs)) {
            $specs = [];
        }

        return [
            'description_v2_bullets' => (string) ($dm2['description_v2_bullets'] ?? ''),
            'description_v2_description' => (string) ($dm2['description_v2_description'] ?? ''),
            'description_v2_images' => $images,
            'description_v2_features' => $features,
            'description_v2_specifications' => $specs,
            'description_v2_package' => (string) ($dm2['description_v2_package'] ?? ''),
            'description_v2_brand' => (string) ($dm2['description_v2_brand'] ?? ''),
            'modules_seen' => isset($dm2['modules_seen']) && is_array($dm2['modules_seen']) ? $dm2['modules_seen'] : [],
        ];
    }

    private function resolveAsinForSku(string $sku): ?string
    {
        if ($sku === '' || ! Schema::hasTable('amazon_metrics')) {
            return null;
        }

        $row = DB::table('amazon_metrics')
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)->orWhere('sku', strtoupper($sku))->orWhere('sku', strtolower($sku));
            })
            ->first();

        if (! $row) {
            return null;
        }

        $cols = Schema::hasColumn('amazon_metrics', 'asin') ? ['asin'] : [];
        if (Schema::hasColumn('amazon_metrics', 'asin1')) {
            $cols[] = 'asin1';
        }
        foreach ($cols as $c) {
            $a = trim((string) ($row->{$c} ?? ''));
            if ($a !== '' && preg_match('/^B[0-9A-Z]{9}$/i', $a)) {
                return strtoupper($a);
            }
        }

        return null;
    }

    private function searchContentPublishRecords(string $asin, string $marketplaceId, string $accessToken): ?string
    {
        $url = $this->endpoint.'/aplus/2020-11-01/contentPublishRecords'
            .'?marketplaceId='.rawurlencode($marketplaceId)
            .'&asin='.rawurlencode($asin);

        $response = Http::withoutVerifying()->withHeaders([
            'x-amz-access-token' => $accessToken,
            'Accept' => 'application/json',
        ])->timeout(60)->connectTimeout(25)->get($url);

        if ($response->status() === 429) {
            sleep(2);
            $response = Http::withoutVerifying()->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Accept' => 'application/json',
            ])->timeout(60)->connectTimeout(25)->get($url);
        }

        if (! $response->successful()) {
            Log::info('Amazon searchContentPublishRecords non-success', [
                'asin' => $asin,
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 400),
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $records = $json['publishRecordList'] ?? $json['publishRecords'] ?? $json['records'] ?? [];
        if (! is_array($records) || $records === []) {
            return null;
        }

        foreach ($records as $rec) {
            if (! is_array($rec)) {
                continue;
            }
            $key = $rec['contentReferenceKey'] ?? null;
            if (is_string($key) && trim($key) !== '') {
                return trim($key);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getContentDocument(string $contentReferenceKey, string $marketplaceId, string $accessToken): ?array
    {
        $path = rawurlencode($contentReferenceKey);
        $url = $this->endpoint.'/aplus/2020-11-01/contentDocuments/'.$path
            .'?marketplaceId='.rawurlencode($marketplaceId)
            .'&includedDataSet=CONTENTS';

        $response = Http::withoutVerifying()->withHeaders([
            'x-amz-access-token' => $accessToken,
            'Accept' => 'application/json',
        ])->timeout(60)->connectTimeout(25)->get($url);

        if ($response->status() === 429) {
            sleep(4);
            $response = Http::withoutVerifying()->withHeaders([
                'x-amz-access-token' => $accessToken,
                'Accept' => 'application/json',
            ])->timeout(60)->connectTimeout(25)->get($url);
        }

        if (! $response->successful()) {
            Log::warning('Amazon getContentDocument failed', [
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
