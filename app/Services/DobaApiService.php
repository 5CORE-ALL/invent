<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
class DobaApiService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://openapi.doba.com/api';
    }

    /**
     * Update item price in Doba API
     */
    public function updateItemPrice($itemId, $price, $selfPickPrice = null)
    {
        Log::info('DobaApiService::updateItemPrice started', [
            'item_id' => $itemId,
            'price' => $price,
            'self_pick_price' => $selfPickPrice
        ]);

        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            // Ensure the payload format exactly matches Doba's requirements
            $payload = [
                'itemNo' => (string)$itemId,
                'anticipatedIncome' => (float)$price
            ];
            
            // Add selfPickAnticipatedIncome if provided
            if ($selfPickPrice !== null) {
                $payload['selfPickAnticipatedIncome'] = (float)$selfPickPrice;
            }

            Log::info('Doba API request prepared', [
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice,
                'payload' => $payload
            ]);

            $url = $this->baseUrl . "/goods/price/update";

            $headers = [
                'appKey'     => env('DOBA_APP_KEY'),
                'signType'   => 'rsa2',
                'timestamp'  => $timestamp,
                'sign'       => $sign,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            // Use POST with form data
            $response = Http::withHeaders($headers)->asForm()->post($url, $payload);

            $statusCode = $response->status();
            $responseData = $response->json();

            Log::info('Doba API response received', [
                'item_id' => $itemId,
                'status_code' => $statusCode,
                'response' => $responseData
            ]);

            // Check for HTTP errors first
            if ($response->failed()) {
                $errorMsg = "HTTP Error {$statusCode}";
                Log::error('Doba HTTP request failed', [
                    'item_id' => $itemId,
                    'status' => $statusCode,
                    'response' => $responseData
                ]);
                return [
                    'errors' => $errorMsg,
                    'debug' => array_merge(['statusCode' => $statusCode], $responseData ?? [])
                ];
            }

            // Check Doba's response code
            if (isset($responseData['responseCode']) && $responseData['responseCode'] !== '000000') {
                $responseMsg = $responseData['responseMessage'] ?? 'Unknown error';
                Log::warning('Doba API returned error code', [
                    'item_id' => $itemId,
                    'response_code' => $responseData['responseCode'],
                    'response_message' => $responseMsg
                ]);
                return [
                    'errors' => $responseMsg,
                    'debug' => $responseData
                ];
            }

            // Check business-level success
            if (isset($responseData['businessData'])) {
                $businessData = $responseData['businessData'];
                
                if (isset($businessData['successful']) && $businessData['successful'] !== true) {
                    $businessMsg = $businessData['businessMessage'] ?? 'Business validation failed';
                    Log::warning('Doba business validation failed', [
                        'item_id' => $itemId,
                        'business_status' => $businessData['businessStatus'] ?? 'Unknown',
                        'business_message' => $businessMsg
                    ]);
                    
                    return [
                        'errors' => $businessMsg,
                        'debug' => $responseData
                    ];
                }
            }

            Log::info('Doba price update successful', [
                'item_id' => $itemId,
                'price' => $price
            ]);
            
            return $responseData;

        } catch (Exception $e) {
            Log::error('Exception in Doba updateItemPrice', [
                'item_id' => $itemId,
                'price' => $price,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'errors' => 'API Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update sale price in Doba API using /api/goods/sale/update
     * This endpoint requires different format with saleDetails array
     */
    public function updateSalePrice($itemId, $salePrice, $selfPickSalePrice = null)
    {
        Log::info('DobaApiService::updateSalePrice started', [
            'item_id' => $itemId,
            'sale_price' => $salePrice,
            'self_pick_sale_price' => $selfPickSalePrice
        ]);

        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            // Calculate sale start and end dates as Unix timestamps in milliseconds (PST timezone)
            // Start: today, End: 30 days from now (minimum promotion period)
            $pstTimezone = new \DateTimeZone('America/Los_Angeles');
            $startTimestamp = (new \DateTime('now', $pstTimezone))->getTimestamp() * 1000; // milliseconds
            $endTimestamp = (new \DateTime('+30 days', $pstTimezone))->getTimestamp() * 1000; // milliseconds

            // Payload for sale price update - correct format per API docs
            $saleDetail = [
                'itemNo' => (string)$itemId,
                'openSale' => true,  // Enable sale (boolean, not string)
                'salePriceAnticipated' => (float)$salePrice
            ];
            
            // Add self pick sale price if provided
            if ($selfPickSalePrice !== null) {
                $saleDetail['selfPickSalePriceAnticipated'] = (float)$selfPickSalePrice;
            }

            $payload = [
                'saleStartDate' => (string)$startTimestamp,
                'saleEndDate' => (string)$endTimestamp,
                'saleDetails' => json_encode([$saleDetail])
            ];

            Log::info('Doba Sale API request prepared', [
                'item_id' => $itemId,
                'sale_price' => $salePrice,
                'self_pick_sale_price' => $selfPickSalePrice,
                'payload' => $payload
            ]);

            $url = $this->baseUrl . "/goods/sale/update";

            $headers = [
                'appKey'     => env('DOBA_APP_KEY'),
                'signType'   => 'rsa2',
                'timestamp'  => $timestamp,
                'sign'       => $sign,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            // Use POST with form data
            $response = Http::withHeaders($headers)->asForm()->post($url, $payload);

            $statusCode = $response->status();
            $responseData = $response->json();

            Log::info('Doba Sale API response received', [
                'item_id' => $itemId,
                'status_code' => $statusCode,
                'response' => $responseData
            ]);

            // Check for HTTP errors first
            if ($response->failed()) {
                $errorMsg = "HTTP Error {$statusCode}";
                Log::error('Doba Sale HTTP request failed', [
                    'item_id' => $itemId,
                    'status' => $statusCode,
                    'response' => $responseData
                ]);
                return [
                    'errors' => $errorMsg,
                    'debug' => array_merge(['statusCode' => $statusCode], $responseData ?? [])
                ];
            }

            // Check Doba's response code
            if (isset($responseData['responseCode']) && $responseData['responseCode'] !== '000000') {
                $responseMsg = $responseData['responseMessage'] ?? 'Unknown error';
                Log::warning('Doba Sale API returned error code', [
                    'item_id' => $itemId,
                    'response_code' => $responseData['responseCode'],
                    'response_message' => $responseMsg
                ]);
                return [
                    'errors' => $responseMsg,
                    'debug' => $responseData
                ];
            }

            // Check business-level success
            if (isset($responseData['businessData'])) {
                $businessData = $responseData['businessData'];
                
                if (isset($businessData['successful']) && $businessData['successful'] !== true) {
                    $businessMsg = $businessData['businessMessage'] ?? 'Business validation failed';
                    Log::warning('Doba Sale business validation failed', [
                        'item_id' => $itemId,
                        'business_status' => $businessData['businessStatus'] ?? 'Unknown',
                        'business_message' => $businessMsg
                    ]);
                    
                    return [
                        'errors' => $businessMsg,
                        'debug' => $responseData
                    ];
                }
            }

            Log::info('Doba sale price update successful', [
                'item_id' => $itemId,
                'sale_price' => $salePrice
            ]);
            
            return $responseData;

        } catch (Exception $e) {
            Log::error('Exception in Doba updateSalePrice', [
                'item_id' => $itemId,
                'sale_price' => $salePrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'errors' => 'API Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test different approaches to fix the "Item No cannot be empty" issue
     */
    public function testItemValidation($itemId, $price)
    {
        $results = [];
        
        // Test 1: Standard approach
        $results['standard'] = $this->updateItemPrice($itemId, $price);
        
        // Test 2: Try with additional fields that might be required
        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $payload = [
                'itemNo' => (string)$itemId,
                'anticipatedIncome' => (float)$price,
                'sku' => (string)$itemId, // Try adding SKU field
                'quantity' => 1 // Try adding quantity
            ];

            $url = $this->baseUrl . "/goods/price/update";
            $headers = [
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)->post($url, $payload);
            $results['with_additional_fields'] = [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload_used' => $payload
            ];

        } catch (Exception $e) {
            $results['with_additional_fields'] = ['error' => $e->getMessage()];
        }

        // Test 3: Try using different parameter names
        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $payload = [
                'item_no' => (string)$itemId,
                'anticipated_income' => (float)$price
            ];

            $response = Http::withHeaders([
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . "/goods/price/update", $payload);

            $results['snake_case_params'] = [
                'status' => $response->status(),
                'response' => $response->json(),
                'payload_used' => $payload
            ];

        } catch (Exception $e) {
            $results['snake_case_params'] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Get product detail from Doba by item_id
     */
    public function getItemDetail($itemId)
    {
        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $url = $this->baseUrl . "/goods/get/item";

            $response = Http::withHeaders([
                'appKey'     => env('DOBA_APP_KEY'),
                'signType'   => 'rsa2',
                'timestamp'  => $timestamp,
                'sign'       => $sign,
                'Content-Type' => 'application/json',
            ])->get($url, [
                'itemNo' => $itemId
            ]);

            $responseData = $response->json();
            Log::info('Doba API response received', ['response_data' => $responseData]);

            if (!isset($responseData['code']) || $responseData['code'] !== 200) {
                return [
                    'errors' => $responseData['message'] ?? 'API returned error'
                ];
            }

            return $responseData;

        } catch (Exception $e) {
            return [
                'errors' => 'API Error: ' . $e->getMessage()
            ];
        }
    }

    private function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return intval((float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000));
    }

    private function generateSignature($content)
    {
        $privateKeyContent = trim(env('DOBA_PRIVATE_KEY'));
        $privateKeyFormatted = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKeyContent, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $privateKey = openssl_pkey_get_private($privateKeyFormatted);
        if (!$privateKey) throw new Exception("Invalid private key.");

        $success = openssl_sign($content, $signature, $privateKey, 'sha256');
        openssl_free_key($privateKey);

        if (!$success) throw new Exception("Failed to generate signature");

        return base64_encode($signature);
    }

    private function getContent($timestamp)
    {
        $appKey = env('DOBA_APP_KEY');
        return "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
    }

    /**
     * Advanced debugging for Doba API - captures raw request/response
     */
    public function advancedDebugRequest($itemId, $price)
    {
        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $payload = [
                'itemNo' => (string)$itemId,
                'anticipatedIncome' => (float)$price
            ];

            $url = $this->baseUrl . "/goods/price/update";
            $headers = [
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ];

            Log::info('=== ADVANCED DOBA DEBUG START ===');
            Log::info('Request URL', ['url' => $url]);
            Log::info('Request Headers', ['headers' => $headers]);
            Log::info('Request Payload', ['payload' => $payload]);
            Log::info('JSON Payload', ['json' => json_encode($payload)]);
            Log::info('Signature Content', ['content' => $content]);
            Log::info('Environment Check', [
                'app_key' => env('DOBA_APP_KEY'),
                'private_key_length' => strlen(env('DOBA_PRIVATE_KEY')),
                'base_url' => $this->baseUrl
            ]);

            // Try multiple HTTP methods to see if any work
            $results = [];

            // Method 1: Standard POST with JSON
            $response1 = Http::withHeaders($headers)->post($url, $payload);
            $results['standard_post'] = [
                'status' => $response1->status(),
                'response' => $response1->json(),
                'raw_body' => $response1->body()
            ];

            // Method 2: POST with explicit JSON content type
            $response2 = Http::withHeaders($headers)->withBody(json_encode($payload), 'application/json')->post($url);
            $results['explicit_json'] = [
                'status' => $response2->status(),
                'response' => $response2->json(),
                'raw_body' => $response2->body()
            ];

            // Method 3: Try form data instead of JSON
            $formHeaders = $headers;
            $formHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
            $response3 = Http::withHeaders($formHeaders)->asForm()->post($url, $payload);
            $results['form_data'] = [
                'status' => $response3->status(),
                'response' => $response3->json(),
                'raw_body' => $response3->body()
            ];

            // Method 4: Try with query parameters instead of body
            $queryUrl = $url . '?' . http_build_query($payload);
            $response4 = Http::withHeaders($headers)->post($queryUrl);
            $results['query_params'] = [
                'status' => $response4->status(),
                'response' => $response4->json(),
                'raw_body' => $response4->body(),
                'url_used' => $queryUrl
            ];

            Log::info('=== ADVANCED DOBA DEBUG END ===');
            Log::info('All method results', ['results' => $results]);

            return [
                'item_id' => $itemId,
                'price' => $price,
                'timestamp' => $timestamp,
                'signature_content' => $content,
                'signature' => $sign,
                'test_methods' => $results,
                'environment' => [
                    'app_key' => env('DOBA_APP_KEY'),
                    'private_key_length' => strlen(env('DOBA_PRIVATE_KEY')),
                    'base_url' => $this->baseUrl
                ]
            ];

        } catch (Exception $e) {
            Log::error('Advanced debug failed', ['error' => $e->getMessage()]);
            return [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Test connection to Doba API
     */
    public function testConnection($itemId = null)
    {
        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            if ($itemId) {
                // Test with item detail endpoint
                $url = $this->baseUrl . "/goods/get/item";
                $payload = ['itemNo' => $itemId];
                $response = Http::withHeaders([
                    'appKey' => env('DOBA_APP_KEY'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                    'Content-Type' => 'application/json',
                ])->get($url, $payload);
            } else {
                // Test with price update endpoint (won't actually update)
                $url = $this->baseUrl . "/goods/price/update";
                $payload = ['itemNo' => 'test', 'anticipatedIncome' => 1.00];
                $response = Http::withHeaders([
                    'appKey' => env('DOBA_APP_KEY'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);
            }

            return [
                'status' => $response->status(),
                'response' => $response->json(),
                'url' => $url,
                'payload' => $payload,
                'headers' => $response->headers()
            ];

        } catch (Exception $e) {
            return [
                'error' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Debug signature generation
     */
    public function debugSignature($timestamp = null)
    {
        $timestamp = $timestamp ?: $this->getMillisecond();
        $content = $this->getContent($timestamp);
        
        try {
            $signature = $this->generateSignature($content);
            
            return [
                'timestamp' => $timestamp,
                'content_to_sign' => $content,
                'content_breakdown' => [
                    'appKey' => env('DOBA_APP_KEY'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'formatted' => "appKey=" . env('DOBA_APP_KEY') . "&signType=rsa2&timestamp=" . $timestamp
                ],
                'signature' => $signature,
                'app_key' => env('DOBA_APP_KEY'),
                'private_key_length' => strlen(env('DOBA_PRIVATE_KEY')),
                'private_key_start' => substr(env('DOBA_PRIVATE_KEY'), 0, 50) . '...',
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'timestamp' => $timestamp,
                'content_to_sign' => $content,
            ];
        }
    }

    public function getinventoryData(){
        $this->info("Fetching Doba Metrics...");
        $page = 1;
         do {
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withHeaders([
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->get('https://openapi.doba.com/api/goods/detail', [
                'pageNumber' => $page,
                'pageSize' => 100
            ]);
        
            if (!$response->ok()) {
                $this->error("API Failed: " . $response->body());
                return;
            }

            $data = $response['businessData']['data']['dsGoodsDetailResultVOS'];
            dd($data);
            if (empty($data)) break;
            foreach ($data as $product) {
                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;

                    if (!$item) continue;

                    DobaMetric::updateOrCreate(
                        ['sku' => $sku['skuCode']],
                        [
                            'item_id' => $item['itemNo'],
                            'anticipated_income' => $item['anticipatedIncome'],
                        ]
                    );
                }
            }
            $page++;
        } while (count($data) === 100);
    }


   
    public function getinventory(){
        $allStock=[];
        Log::info("Fetching Doba Metrics...");
        $page = 1;
         do {
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withoutVerifying()->withHeaders([
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->get('https://openapi.doba.com/api/goods/detail', [
                'pageNumber' => $page,
                'pageSize' => 100
            ]);
        
            if (!$response->ok()) {
                 Log::error("API Failed: " . $response->body());
                return;
            }

            $data = $response['businessData']['data']['dsGoodsDetailResultVOS'];
            if (empty($data)) break;
            foreach ($data as $product) {
                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;
                    $quantity=$item['availableInventory'];
                    $itemsku=$sku['skuCode'];
                    if (!$item) continue;

                    $allStock[]=[
                           'sku' => $itemsku,
                        'quantity' => (int) $quantity,
                    ];
                    // DobaMetric::updateOrCreate(
                    //     ['sku' => $sku['skuCode']],
                    //     [
                    //         'item_id' => $item['itemNo'],
                    //         'anticipated_income' => $item['anticipatedIncome'],
                    //     ]
                    // );
                }
            }
            $page++;
        } while (count($data) === 100);
        foreach ($allStock as $sku => $data) {
                $sku = $data['sku'] ?? null;
                $quantity = $data['quantity'];
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $sku],
            //     ['inventory_doba'=>$quantity,]
            // );
            
            ProductStockMapping::where('sku', $sku)->update(['inventory_doba' => (int) $quantity]);
        }
        return $allStock;
    }
}
