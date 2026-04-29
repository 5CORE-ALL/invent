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
    public static function updateShopifyVariantPrice($variantId, $newPrice)
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
                'new_price' => $newPrice
            ]);

            $storeUrl = "https://" . config('services.shopify.store_url');
            $apiVersion = "2025-01";
            $accessToken = config('services.shopify.password');

            if (!$storeUrl || !$accessToken) {
                Log::error('Shopify credentials missing', [
                    'store_url' => $storeUrl ? 'present' : 'missing',
                    'access_token' => $accessToken ? 'present' : 'missing'
                ]);
                return [
                    "status" => "error",
                    "message" => "Shopify credentials not configured"
                ];
            }

            $url = "{$storeUrl}/admin/api/{$apiVersion}/variants/{$variantId}.json";

            $payload = [
                "variant" => [
                    "id" => $variantId,
                    "price" => $newPrice
                ]
            ];

            Log::info('Sending Shopify API request', [
                'url' => $url,
                'payload' => $payload
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

                Log::info('Shopify API response received', [
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
                    Log::warning('Shopify API rate limited (429), retrying', [
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
                
                // Verify the price matches what we sent (with small tolerance for rounding)
                $priceMatches = $updatedPrice && abs($updatedPrice - (float)$newPrice) < 0.01;
                
                if (!$priceMatches) {
                    Log::error('Shopify API returned success but price mismatch detected', [
                        'variant_id' => $variantId,
                        'expected_price' => $newPrice,
                        'actual_price_in_response' => $updatedPrice,
                        'response' => $responseBody
                    ]);
                    return [
                        "status" => "error",
                        "message" => "Price update verification failed - price mismatch in API response",
                        "expected_price" => $newPrice,
                        "actual_price" => $updatedPrice
                    ];
                }
                
                Log::info('Shopify price updated and verified successfully', [
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

                Log::error('Shopify API returned error', [
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
            Log::error('Shopify price update exception', [
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
