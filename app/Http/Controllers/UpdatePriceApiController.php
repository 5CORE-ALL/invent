<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateEbaySPriceJob;
use Illuminate\Http\Request;
use App\Services\ShopifyAdminCallGate;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UpdatePriceApiController extends Controller
{
    //update price in shopify by variant id
    public static function updateShopifyVariantPrice($variantId, $newPrice, $store = 'b2c')
    {
        try {
            @set_time_limit(120);
            Log::info('Shopify price update started', [
                'variant_id' => $variantId,
                'new_price' => $newPrice,
                'store' => $store
            ]);

            // Determine which store credentials to use
            if ($store === 'pls' || $store === 'prolightsounds') {
                $storeUrl = "https://" . config('services.prolightsounds_shopify.store_url');
                $accessToken = app(ShopifyPlsTokenService::class)->getAccessToken();
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

            $response = null;
            $statusCode = 0;
            $responseBody = null;
            $maxAttempts = 8;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                ShopifyAdminCallGate::acquire($store);
                $response = Http::withHeaders([
                    "X-Shopify-Access-Token" => $accessToken,
                    "Content-Type" => "application/json",
                ])->timeout(45)->connectTimeout(20)->put($url, $payload);
                ShopifyAdminCallGate::record($response, $store);

                $statusCode = $response->status();
                $responseBody = $response->json();

                Log::info("$storeName API response received", [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                if (! ShopifyAdminCallGate::isRateLimited($response)) {
                    break;
                }

                if ($attempt < $maxAttempts) {
                    Log::warning("$storeName API rate limited, waiting on cache gate then retrying", [
                        'attempt' => $attempt,
                        'variant_id' => $variantId,
                    ]);
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
