<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class JungleScoutController extends Controller
{
    /**
     * Fetch product data from Jungle Scout API
     */
    public static function fetchProducts(array $asins)
    {
        try {
            // Validate input
            if (empty($asins)) {
                throw new Exception("At least one ASIN is required");
            }

            // Make API request
            $response = Http::withOptions([
                'verify' => true, // Verify SSL certificate
                'timeout' => 15,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . config('services.junglescout.key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'YourApp/1.0',
            ])->post('https://api.junglescout.com/products/details/batch', [
                'asins' => $asins
            ]);

            // Verify server origin
            self::verifyServerOrigin($response);

            // Handle response
            return self::handleApiResponse($response);

        } catch (Exception $e) {
            Log::channel('api_errors')->error("JungleScout API Failure", [
                'asins' => $asins,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'server_verified' => false,
            ];
        }
    }

    /**
     * Verify response comes from Jungle Scout servers
     */
    private static function verifyServerOrigin($response)
    {
        $serverHeader = $response->header('Server');
        $poweredBy = $response->header('X-Powered-By');

        if (!str_contains($serverHeader, 'nginx') || 
            !str_contains($poweredBy, 'JungleScout')) {
            throw new Exception("Invalid server origin. Possible MITM attack.");
        }
    }

    /**
     * Handle API response
     */
    private static function handleApiResponse($response)
    {
        $status = $response->status();
        $body = $response->json();
        $headers = $response->headers();

        // Log full response for debugging
        Log::channel('api_responses')->debug("API Response", [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ]);

        // Handle success
        if ($response->successful()) {
            return [
                'success' => true,
                'status_code' => $status,
                'message' => 'Data retrieved successfully',
                'data' => $body['data'] ?? [],
                'server_verified' => true,
            ];
        }

        // Handle errors
        $errorMap = [
            400 => 'Bad request - check your input parameters',
            401 => 'Unauthorized - invalid API key',
            402 => 'Payment required - check subscription',
            403 => 'Forbidden - insufficient permissions',
            404 => 'Endpoint not found',
            429 => 'Too many requests - rate limit exceeded',
            500 => 'Jungle Scout server error',
            503 => 'Service unavailable - try again later',
        ];

        throw new Exception(
            $errorMap[$status] ?? "API request failed with status: $status",
            $status
        );
    }
}