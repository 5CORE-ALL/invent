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
            // Shopify Rate Limiting: 2 calls per second
            $rateLimitKey = 'shopify_api_rate_limit';
            $now = now();
            $windowStart = $now->copy()->startOfSecond();
            
            // Get timestamps of recent calls in this second
            $recentCalls = Cache::get($rateLimitKey, []);
            
            // Filter out calls from previous seconds
            $recentCalls = array_filter($recentCalls, function($timestamp) use ($windowStart) {
                return $timestamp >= $windowStart->timestamp;
            });
            
            // If we have 2 or more calls in this second, wait
            if (count($recentCalls) >= 2) {
                // Calculate how long to wait (remaining time in current second + small buffer)
                $waitTime = 1000000 - ($now->micro); // microseconds until next second
                usleep($waitTime + 100000); // add 100ms buffer
                
                // Clear the cache after waiting
                $recentCalls = [];
            }
            
            // Add current call timestamp
            $recentCalls[] = now()->timestamp;
            
            // Store with 2 second TTL
            Cache::put($rateLimitKey, $recentCalls, 2);

            Log::info('Shopify price update started', [
                'variant_id' => $variantId,
                'new_price' => $newPrice
            ]);

            $storeUrl = "https://" . env('SHOPIFY_STORE_URL');
            $apiVersion = "2025-01";
            $accessToken = env('SHOPIFY_PASSWORD');

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

            $response = Http::withHeaders([
                "X-Shopify-Access-Token" => $accessToken,
                "Content-Type" => "application/json",
            ])->put($url, $payload);

            $statusCode = $response->status();
            $responseBody = $response->json();

            Log::info('Shopify API response received', [
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            if ($response->successful()) {
                Log::info('Shopify price updated successfully', [
                    'variant_id' => $variantId,
                    'new_price' => $newPrice
                ]);
                return [
                    "status" => "success",
                    "data" => $responseBody
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
