<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateEbaySPriceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UpdatePriceApiController extends Controller
{
    //update price in shopify by variant id
    public static function updateShopifyVariantPrice($variantId, $newPrice)
    {
        try {
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
