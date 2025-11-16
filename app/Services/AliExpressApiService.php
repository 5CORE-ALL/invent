<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
class AliExpressApiService
{

    public function getAccessToken(){

// Replace these variables with your actual values
$appKey = env('ALIEXPRESS_APP_KEY');
$appSecret = env('ALIEXPRESS_APP_SECRET');
$code = '0_2DL4DV3jcU1UOT7WGI1A4rY91'; // The code obtained from the authorization callback URL
$uuid = ''; // Optional parameter
$url = 'https://api-sg.aliexpress.com/rest/'; // The API endpoint

// Prepare the data to send
$data = array(
    'app_key' => $appKey,
    'timestamp' => time() * 1000, // in milliseconds
    'sign_method' => 'sha256',
    'sign' => $this->generateSignature($appKey, $appSecret, $code, $uuid, $url),
    'code' => $code,
    'uuid' => $uuid
);

// Initialize curl session
$ch = curl_init();

// Set curl options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

// Execute the request
$response = curl_exec($ch);

// Close curl session
curl_close($ch);

// Output the response
echo $response;

    }

/**
 * Generate the signature for the API request
 */
public function generateSignature($appKey, $appSecret, $code, $uuid, $url) {
    // Concatenate the parameters in a specific order
    $stringToSign = 'app_key=' . urlencode($appKey) . '&code=' . urlencode($code) . '&timestamp=' . time() * 1000 . '&uuid=' . urlencode($uuid);
    $signature = hash_hmac('sha256', $stringToSign, $appSecret, true);
    return bin2hex($signature);
}


public function getInventory2()
{
    $apiUrl = 'https://ship.5coremanagement.com/api/aliexpress/product-list';
    $page=1;
    $page_size=20;
            $response = Http::withoutVerifying()->asForm()->post($apiUrl, [
                'page' => $page,
                'page_size' => $page_size,
            ]);

            return($response->body());
}

public function getInventory()
{$apiUrl = 'https://api-sg.aliexpress.com/sync';

    $appKey = '520170';
    $appSecret = env('ALIEXPRESS_APP_SECRET');
    $accessToken = env('ALIEXPRESS_ACCESS_TOKEN');
    $timestamp = round(microtime(true) * 1000); // current time in ms

    $method = 'aliexpress.local.service.products.list';
    $signMethod = 'sha256';

    // Request parameters
    $params = [
        'app_key' => $appKey,
        'timestamp' => $timestamp,
        'access_token' => $accessToken,
        'sign_method' => $signMethod,
        'method' => $method,
        'channel_seller_id' => '2678881002',
        'channel' => 'AE_GLOBAL',
        'page_size' => 20,
        'current_page' => 1,
        'search_condition_do' => json_encode([
            "product_id" => null,
            "product_status" => "ONLINE",
            "update_before" => null,
            "update_after" => null,
            "create_before" => null,
            "create_after" => null,
            "leaf_category_id" => null
        ])
    ];

    // Generate signature
$paramsForSign = $params;
unset($paramsForSign['sign']); // just to be sure
ksort($paramsForSign);

$signStr = $appSecret;
foreach ($paramsForSign as $key => $val) {
    if ($val !== null && $val !== '') {
        $signStr .= $key . $val;
    }
}
$signStr .= $appSecret;

$signature = strtoupper(hash_hmac('sha256', $signStr, $appSecret));
$params['sign'] = $signature;
    // Send POST request
    $response = Http::withOptions([
    'verify' => false,
])->asForm()->post($apiUrl, $params);

    if ($response->failed()) {
        Log::error('AliExpress API Error', [
            'response' => $response->body()
        ]);
        return response()->json(['error' => 'Failed to fetch inventory', 'details' => $response->body()], 500);
    }

    return $response->json();
}

private function generateAliExpressSignature($params, $appSecret)
{
    ksort($params);
    $stringToSign = $appSecret;
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $stringToSign .= $key . $value;
        }
    }
    $stringToSign .= $appSecret;

    return strtoupper(hash_hmac('sha256', $stringToSign, $appSecret));
}

}
