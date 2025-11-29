<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function getAccessToken()
    {
        $client = new Client();
        $response = $client->post('https://api.amazon.com/auth/o2/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['access_token'];
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

    public function updateAmazonPriceUS($sku, $price)
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
        try {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                Log::error("Amazon Price Update: Failed to get access token", ['sku' => $sku]);
                return [
                    'errors' => [[
                        'code' => 'AuthenticationError',
                        'message' => 'Failed to authenticate with Amazon API.'
                    ]]
                ];
            }

            // Find the correct SKU format in Amazon (handles case sensitivity issues)
            $amazonSku = $this->findAmazonSkuFormat($sku);
            if (empty($amazonSku)) {
                Log::error("Amazon Price Update: SKU not found in Amazon", ['sku' => $sku]);
                return [
                    'errors' => [[
                        'code' => 'InvalidInput',
                        'message' => 'SKU not found in Amazon. Please ensure the SKU exists in your Amazon listings.'
                    ]]
                ];
            }

            // Get product type using the correct SKU format (pass Amazon SKU to avoid duplicate lookup)
            $productType = $this->getAmazonProductType($sku, $amazonSku);
            if (empty($productType)) {
                Log::error("Amazon Price Update: Product type not found", [
                    'sku' => $sku,
                    'amazon_sku' => $amazonSku
                ]);
                return [
                    'errors' => [[
                        'code' => 'InvalidInput',
                        'message' => 'Product type not found for SKU. Please ensure the SKU exists in Amazon.'
                    ]]
                ];
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

            // Log request for debugging (can be commented out in production)
            Log::info("Amazon Price Update Request", [
                "original_sku" => $sku,
                "amazon_sku" => $amazonSku,
                "price" => $price,
                "productType" => $productType,
                "endpoint" => $endpoint,
                "body" => $body
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

            if ($response->failed()) {
                Log::error("Amazon Price Update Failed", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku,
                    "price" => $price,
                    "status" => $response->status(),
                    "response" => $responseData
                ]);

                // Return the error response from Amazon
                return $responseData ?: [
                    'errors' => [[
                        'code' => 'RequestFailed',
                        'message' => 'Failed to update price on Amazon. HTTP Status: ' . $response->status()
                    ]]
                ];
            } else {
                Log::info("Amazon Price Update Success", [
                    "original_sku" => $sku,
                    "amazon_sku" => $amazonSku,
                    "price" => $price,
                    "response" => $responseData
                ]);
            }

            return $responseData ?: ['success' => true];
        } catch (\Exception $e) {
            Log::error("Amazon Price Update Exception", [
                "original_sku" => $sku,
                "amazon_sku" => $amazonSku ?: 'not_found',
                "price" => $price,
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]);

            return [
                'errors' => [[
                    'code' => 'Exception',
                    'message' => 'An error occurred while updating price: ' . $e->getMessage()
                ]]
            ];
        }
    }

    /**
     * Find the correct SKU format in Amazon by trying different case variations
     * Returns the SKU format that works with Amazon API, or null if not found
     */
    private function findAmazonSkuFormat($sku)
    {
        $sku = trim($sku);
        if (empty($sku)) {
            return null;
        }

        $sellerId = env('AMAZON_SELLER_ID');
        if (empty($sellerId)) {
            return null;
        }

        $accessToken = $this->getAccessToken();
        if (empty($accessToken)) {
            return null;
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

    public function getAmazonProductType($sku, $amazonSku = null)
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

            $accessToken = $this->getAccessToken();
            if (empty($accessToken)) {
                Log::warning("getAmazonProductType: Failed to get access token", ['sku' => $sku]);
                return null;
            }

            // Use provided Amazon SKU or find it
            if (empty($amazonSku)) {
                $amazonSku = $this->findAmazonSkuFormat($sku);
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
}
