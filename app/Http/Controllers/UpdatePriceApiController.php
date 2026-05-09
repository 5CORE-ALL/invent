<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateEbaySPriceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UpdatePriceApiController extends Controller
{
    //update price in shopify by variant id
    public static function updateShopifyVariantPrice($variantId, $newPrice, $store = 'b2c')
    {
        try {
            // Shopify rate limiting (2 calls/sec) via default cache store. On production, a missing or
            // unwritable storage/framework/cache/data tree causes file cache to throw — do not fail the
            // price update; fall back to the per-request delay below + HTTP 429 retries.
            $rateLimitKey = 'shopify_api_rate_limit';
            try {
                $now = now();
                $windowStart = $now->copy()->startOfSecond();

                $recentCalls = Cache::get($rateLimitKey, []);
                $recentCalls = array_filter($recentCalls, function ($timestamp) use ($windowStart) {
                    return $timestamp >= $windowStart->timestamp;
                });

                if (count($recentCalls) >= 2) {
                    $waitTime = 1000000 - ($now->micro);
                    usleep($waitTime + 100000);
                    $recentCalls = [];
                }

                $recentCalls[] = now()->timestamp;
                Cache::put($rateLimitKey, $recentCalls, 2);
            } catch (\Throwable $e) {
                Log::warning('Shopify price update: rate-limit cache unavailable, continuing without coordinated throttle', [
                    'error' => $e->getMessage(),
                    'hint' => 'Ensure storage/framework/cache/data exists and is writable by the web user, or set CACHE_DRIVER=redis (or database).',
                ]);
            }

            Log::info('Shopify price update started', [
                'variant_id' => $variantId,
                'new_price' => $newPrice,
                'store' => $store
            ]);

            // Determine which store credentials to use
            if ($store === 'pls' || $store === 'prolightsounds') {
                $storeUrl = "https://" . config('services.prolightsounds_shopify.store_url');
                $accessToken = config('services.prolightsounds_shopify.password');
                $storeName = 'ProLightSounds';
            } else {
                // Default to B2C store
                $storeUrl = "https://" . config('services.shopify.store_url');
                $accessToken = config('services.shopify.password');
                $storeName = 'Shopify B2C';
            }

            $apiVersion = "2025-01";

            if (!$storeUrl || !$accessToken) {
                Log::error("$storeName credentials missing", [
                    'store_url' => $storeUrl ? 'present' : 'missing',
                    'access_token' => $accessToken ? 'present' : 'missing'
                ]);
                return [
                    "status" => "error",
                    "message" => "$storeName credentials not configured"
                ];
            }

            $url = "{$storeUrl}/admin/api/{$apiVersion}/variants/{$variantId}.json";

            // Ensure price is formatted as string with exactly 2 decimal places
            // This prevents float precision issues and ensures exact price is sent
            $priceFormatted = number_format((float)$newPrice, 2, '.', '');
            
            $payload = [
                "variant" => [
                    "id" => $variantId,
                    "price" => $priceFormatted
                ]
            ];

            Log::info("Sending $storeName API request", [
                'url' => $url,
                'payload' => $payload,
                'price_original' => $newPrice,
                'price_formatted' => $priceFormatted
            ]);

            // API enforces ~2 calls/sec; small gap before first attempt reduces immediate 429 when
            // multiple features fire back-to-back (Amazon tab + CVR + other jobs).
            usleep(600000);

            $response = null;
            $statusCode = 0;
            $responseBody = null;
            $maxAttempts = 4;
            $baseDelayMs = 700; // Shopify allows low burst; back off quickly on 429.

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders([
                    "X-Shopify-Access-Token" => $accessToken,
                    "Content-Type" => "application/json",
                ])->put($url, $payload);

                $statusCode = $response->status();
                $responseBody = $response->json();

                Log::info("$storeName API response received", [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                if ($statusCode !== 429) {
                    break;
                }

                if ($attempt < $maxAttempts) {
                    $retryAfterSec = (int) ($response->header('Retry-After') ?? 0);
                    $sleepMs = $retryAfterSec > 0 ? ($retryAfterSec * 1000) : ($baseDelayMs * $attempt);
                    Log::warning("$storeName API rate limited (429), retrying", [
                        'attempt' => $attempt,
                        'sleep_ms' => $sleepMs,
                    ]);
                    usleep($sleepMs * 1000);
                }
            }

            if ($response->successful()) {
                // CRITICAL: Verify the price was actually updated in the response
                $updatedPrice = null;
                if (isset($responseBody['variant']['price'])) {
                    $updatedPrice = (float) $responseBody['variant']['price'];
                }
                
                // Verify the price matches exactly what we sent (no tolerance for rounding)
                // Format both prices to 2 decimals for exact comparison
                $sentPrice = number_format((float)$newPrice, 2, '.', '');
                $receivedPrice = number_format($updatedPrice, 2, '.', '');
                $priceMatches = $updatedPrice && ($sentPrice === $receivedPrice);
                
                if (!$priceMatches) {
                    Log::error("$storeName API returned success but price mismatch detected", [
                        'variant_id' => $variantId,
                        'expected_price' => $newPrice,
                        'expected_price_formatted' => $sentPrice,
                        'actual_price_in_response' => $updatedPrice,
                        'actual_price_formatted' => $receivedPrice,
                        'response' => $responseBody
                    ]);
                    return [
                        "status" => "error",
                        "message" => "Price update verification failed - price mismatch in API response",
                        "expected_price" => $newPrice,
                        "actual_price" => $updatedPrice
                    ];
                }
                
                Log::info("$storeName price updated and verified successfully", [
                    'variant_id' => $variantId,
                    'new_price' => $newPrice,
                    'verified_price' => $updatedPrice
                ]);
                return [
                    "status" => "success",
                    "data" => $responseBody,
                    "verified_price" => $updatedPrice
                ];
            } else {
                $errorMessage = 'API returned error';
                if (isset($responseBody['errors'])) {
                    $errorMessage = is_array($responseBody['errors']) 
                        ? json_encode($responseBody['errors']) 
                        : $responseBody['errors'];
                } elseif (isset($responseBody['error'])) {
                    $errorMessage = $responseBody['error'];
                }

                Log::error("$storeName API returned error", [
                    'variant_id' => $variantId,
                    'status_code' => $statusCode,
                    'error' => $errorMessage,
                    'response' => $responseBody
                ]);

                return [
                    "status" => "error",
                    "code" => $statusCode,
                    "message" => $errorMessage
                ];
            }

        } catch (\Exception $e) {
            $storeName = ($store === 'pls' || $store === 'prolightsounds') ? 'ProLightSounds' : 'Shopify B2C';
            Log::error("$storeName price update exception", [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                "status" => "error",
                "message" => "Exception: " . $e->getMessage()
            ];
        }
    }


    

}
