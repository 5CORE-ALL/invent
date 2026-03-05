<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductStockMapping;
use Carbon\Carbon;

class TemuApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $region;
    protected $marketplaceId;
    protected $awsAccessKey;
    protected $awsSecretKey;
    protected $endpoint;
    protected $allItems = [];

private function generateSignValue($requestBody)
    {
        // Environment/config variables
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');
        $timestamp = time();
        
        // Top-level params
        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'data_type' => 'JSON',
        ];

        // Flatten and sort for signing
        $signParams = array_merge($params, $requestBody);
        ksort($signParams);
        
        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key . $value;
        }

        $signStr = $appSecret . $temp . $appSecret;
        $sign = strtoupper(md5($signStr));
        $params['sign'] = $sign;

        
        // Log the request
        // Log::info("Generated Sign: $sign");
        return array_merge($params, $requestBody);
    }


        public function getInventory()
    {
        $pageNumber = 1;
        $pageSize = 100;
        $totalPages = null;

        Log::info("======================= Started Inventory Sync =======================");

        do {
            // OLD CODE (commented for reference):
                
            // $requestBody = [
            //     "type" => "bg.local.goods.list.query",
            //     "goodsSearchType" => 1,
            //     "goodsStatusFilterType" => 1,
            //     "pageSize" => $pageSize,
            //     "pageNumber" => $pageNumber,
            //     "orderStatusFilterType" => [3, 4], // 3=Shipped, 4=Delivered
            // ];
             $requestBody = [
                "type" => "bg.local.goods.list.query",
                "goodsSearchType" => 1,
                "goodsStatusFilterType" => 1, // 1=On sale (excludes canceled/removed products)
                "pageSize" => $pageSize,
                "pageNumber" => $pageNumber,
            ];

            $signedRequest = $this->generateSignValue($requestBody);

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);

            // Only disable SSL verification in local dev (not recommended for production)
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
            } catch (\Exception $e) {
                Log::error("HTTP request exception on page {$pageNumber}: " . $e->getMessage());
                break;
            }

            if ($response->failed()) {
                Log::error("Request failed (page {$pageNumber}) with status: " . $response->status() . ", body: " . $response->body());
                break;
            }

            // dd($response->body());
            $data = $response->json();

            if (!($data['success'] ?? false)) {
                Log::error("Temu API Error (page {$pageNumber}): " . ($data['errorMsg'] ?? 'Unknown error'));
                break;
            }

            $result = $data['result'] ?? [];
            $items = $result['goodsList'] ?? [];

            if (empty($items)) {
                break;
            }

            $this->allItems = array_merge($this->allItems, $items);
            Log::info("Temu Items: " . count($items) . " collected from page No: " . $pageNumber);

            // Set total pages once
            if ($totalPages === null) {
                $total = $result['total'] ?? 0;
                $totalPages = ceil($total / $pageSize);
                Log::info("Total inventory items reported by Temu: {$total}, total pages: {$totalPages}");
            }

            $pageNumber++;

            // Safety guard
            if ($pageNumber > 1000) {
                Log::warning("Pagination exceeded 1000 pages – stopping.");
                break;
            }

        } while ($pageNumber <= $totalPages);

        Log::info("======================= Ended Inventory Sync =======================");
        Log::info("Total Temu inventory items collected: " . count($this->allItems));
        foreach($this->allItems as $titem){    
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $titem['outSkuSnList'][0]],
            //     ['inventory_temu' => $titem['quantity']]
            // );

                                     ProductStockMapping::where('sku', $sku)->update(['inventory_temu' => (int) $quantity]); 
                         ProductStockMapping::where('sku', $sku)->update(['inventory_temu' => (int) $quantity]);    
        }
        Log::info($this->allItems);
        return $this->allItems;
    }

public function getInventory__()
{

    $pageNumber = 1;
    $pageSize = 100;
    $maxPages = PHP_INT_MAX; // Start with a very high number
    Log::info("=======================Started=====================================");
    do {
        $requestBody = [
            "type" => "bg.local.goods.list.query",
            "goodsSearchType" => 1,
            "goodsStatusFilterType" => 1,
            "pageSize" => $pageSize,
            "pageNumber" => $pageNumber,
        ];

        $signedRequest = $this->generateSignValue($requestBody);

        $request = Http::withHeaders([
            'Content-Type' => 'application/json'
        ]);

        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

        if ($response->failed()) {
            $this->error("Request failed: " . $response->body());
            break;
        }

        $data = $response->json();
        if (!($data['success'] ?? false)) {
            $this->error("Temu Error: " . ($data['errorMsg'] ?? 'Unknown'));
            break;
        }

        $result = $data['result'] ?? [];
        $items = $result['goodsList'] ?? [];
        if (empty($items)) {
            break;
        }
        
          $this->allItems = array_merge($this->allItems, $items);

        // foreach ($items as $item) {
        //     $skuId = $item['outGoodsSn'] ?? null;
        //     $qty = $item['quantity'] ?? 0;

        //     if (!$skuId) {
        //         continue;
        //     }

        //     $allItems = array_merge($allItems, [
        //         'sku' => $skuId,
        //         'quantity' => $qty
        //     ]);
            
        //     // $this->allItems[] = [
        //     //    'sku' => $skuId,
        //     //     'quantity' => $qty 
        //     // ];
           
        // }
       Log::info('Temu Items: ' .count($items)." collected from page No:".$pageNumber);
        // Set maxPages once we know the total
        if ($pageNumber === 1 && isset($result['total'])) {
            $maxPages = ceil($result['total'] / $pageSize);
        }
        
        $pageNumber++;

        if ($pageNumber <= $maxPages) {
            usleep(200000); // 0.2 seconds
        }
 
    } while ($pageNumber <= $maxPages);


    
    Log::info("=======================Ended=====================================");
    Log::info('Total Temu inventory items collected: ' . count($this->allItems));
        Log::info($this->allItems);
        foreach($this->allItems as $titem){            
            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $titem['outGoodsSn']],
            //     ['inventory_temu' => $titem['quantity']]
            // );
            ProductStockMapping::where('sku', $titem['outGoodsSn'])->update(['inventory_temu' => (int) $titem['quantity']]);    
        }
 
    return $this->allItems;
}

public function getInventory1()
{
    $allItems = [];
    $pageNumber = 1;
    $maxPages = 100; // Safety limit
    $pageSize = 100;

    do {
        $requestBody = [
            "type" => "bg.local.goods.list.query",
            "goodsSearchType" => 1,
            "pageSize" => $pageSize,
            "pageNumber" => $pageNumber,
        ];

        $signedRequest = $this->generateSignValue($requestBody);

        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ]);

        // Only disable TLS verification in local dev if absolutely 
        if (config('app.env') === 'local') { $request = $request->withoutVerifying(); }

        // 🔥 Fixed URL: no trailing spaces
        $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

        if ($response->failed()) {
            \Log::error("Temu API request failed (Page {$pageNumber})", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            break;
        }

        $data = $response->json();
        

        if (!($data['success'] ?? false)) {
            \Log::error("Temu API error (Page {$pageNumber})", [
                'errorCode' => $data['errorCode'] ?? null,
                'errorMsg' => $data['errorMsg'] ?? 'Unknown error',
            ]);
            // Stop on API error to avoid infinite loop
            break;
        }

        $items = $data['result']['goodsList'] ?? [];
        if (empty($items)) {
            break; // No more data
        }

        foreach ($items as $item) {
            $skuId = $item['outGoodsSn'] ?? null;
            $qty = $item['quantity'] ?? 0;

            if (!$skuId) {
                continue;
            }
            $allItems[] = [
                'sku' => $skuId,
                'quantity' => $qty,
            ];

            // ProductStockMapping::updateOrCreate(
            //     ['sku' => $skuId],
            //     ['inventory_temu' => $qty]
            // );     
            
            ProductStockMapping::where('sku', $sku)->update(['inventory_temu' => (int) $qty]);    
        }

        // Stop if this is the last page (fewer items than page size)
        if (count($items) < $pageSize) {break;}

        $pageNumber++;

        // Prevent rate limiting: wait 200ms between requests
        if ($pageNumber <= $maxPages) {
            usleep(200000); // 0.2 seconds
        }

    } while ($pageNumber <= $maxPages);

    \Log::info('Total Temu inventory items collected: ' . count($allItems));

    return $allItems;
}

/**
 * Fetch Temu ads data for a specific goods ID
 * 
 * @param string $goodsId
 * @param int $startTs Unix timestamp in milliseconds
 * @param int $endTs Unix timestamp in milliseconds
 * @return array|null
 */
public function fetchAdsData($goodsId, $startTs = null, $endTs = null)
{
    if ($startTs === null) {
        $startTs = Carbon::now()->subDays(30)->startOfDay()->timestamp * 1000;
    }
    if ($endTs === null) {
        $endTs = Carbon::yesterday()->endOfDay()->timestamp * 1000;
    }

    $requestBody = [
        'type' => 'temu.searchrec.ad.reports.goods.query',
        'goodsId' => $goodsId,
        'startTs' => $startTs,
        'endTs' => $endTs,
    ];

    $signedRequest = $this->generateSignValue($requestBody);

    $request = Http::withHeaders([
        'Content-Type' => 'application/json',
    ]);

    if (config('filesystems.default') === 'local') {
        $request = $request->withoutVerifying();
    }

    try {
        $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

        if ($response->failed()) {
            Log::error("Temu Ads API request failed for Goods ID: {$goodsId}", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            Log::error("Temu Ads API error for Goods ID: {$goodsId}", [
                'error' => $data['errorMsg'] ?? 'Unknown error',
                'errorCode' => $data['errorCode'] ?? null
            ]);
            return null;
        }

        return $data['result'] ?? null;
    } catch (\Exception $e) {
        Log::error("Exception fetching Temu ads data for Goods ID: {$goodsId}", [
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Fetch ads data for all goods IDs
 * 
 * @param array $goodsIds
 * @param string $period L30 or L60
 * @return array
 */
public function fetchAllAdsData(array $goodsIds, $period = 'L30')
{
    $results = [];
    
    $ranges = [
        'L30' => [
            'startTs' => Carbon::now()->subDays(30)->startOfDay()->timestamp * 1000,
            'endTs' => Carbon::yesterday()->endOfDay()->timestamp * 1000,
        ],
        'L60' => [
            'startTs' => Carbon::now()->subDays(60)->startOfDay()->timestamp * 1000,
            'endTs' => Carbon::now()->subDays(31)->endOfDay()->timestamp * 1000,
        ],
    ];

    $range = $ranges[$period] ?? $ranges['L30'];

    foreach ($goodsIds as $goodsId) {
        $data = $this->fetchAdsData($goodsId, $range['startTs'], $range['endTs']);
        
        if ($data) {
            $summary = $data['reportInfo']['reportsSummary'] ?? null;
            if ($summary) {
                $results[$goodsId] = [
                    'impressions' => $summary['imprCntAll']['val'] ?? 0,
                    'clicks' => $summary['clkCntAll']['val'] ?? 0,
                    'ctr' => isset($summary['clkCntAll']['val']) && isset($summary['imprCntAll']['val']) && $summary['imprCntAll']['val'] > 0
                        ? ($summary['clkCntAll']['val'] / $summary['imprCntAll']['val']) * 100
                        : 0,
                ];
            }
        }
        
        // Rate limiting - small delay between requests
        usleep(200000); // 0.2 seconds
    }

    return $results;
}

    /**
     * Update product title on Temu by seller SKU (outGoodsSn).
     * API type is configurable via config('services.temu.goods_update_type') or TEMU_GOODS_UPDATE_TYPE.
     * If you get "type not exists" (3000003), set the correct type from Temu Partner API docs.
     *
     * @param string $sku Seller SKU (outGoodsSn)
     * @param string $title New title
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $sku = trim($sku);
        $title = trim($title);
        if ($sku === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }

        $apiType = config('services.temu.goods_update_type', 'bg.local.goods.update');
        $url = 'https://openapi-b-us.temu.com/openapi/router';

        $requestBody = [
            'type' => $apiType,
            'outGoodsSn' => $sku,
            'goodsName' => $title,
        ];

        Log::info('Temu API Request - updateTitle', [
            'url' => $url,
            'method' => 'POST',
            'type' => $apiType,
            'sku' => $sku,
            'title_preview' => mb_substr($title, 0, 50),
            'payload_keys' => array_keys($requestBody),
        ]);

        $signedRequest = $this->generateSignValue($requestBody);
        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post($url, $signedRequest);
            $status = $response->status();
            $data = $response->json();
            $bodyRaw = $response->body();

            Log::info('Temu API Response - updateTitle', [
                'sku' => $sku,
                'status' => $status,
                'success' => $data['success'] ?? null,
                'errorCode' => $data['errorCode'] ?? null,
                'errorMsg' => $data['errorMsg'] ?? null,
                'response_preview' => mb_substr($bodyRaw, 0, 500),
            ]);

            if ($response->successful() && ($data['success'] ?? false)) {
                Log::info('Temu title updated successfully', ['sku' => $sku]);
                return ['success' => true, 'message' => "Title updated for SKU: {$sku}."];
            }

            $errorCode = $data['errorCode'] ?? null;
            $errorMsg = $data['errorMsg'] ?? $data['message'] ?? $bodyRaw;

            if ((int) $errorCode === 3000003) {
                Log::warning('Temu API "type not exists" (3000003). Set TEMU_GOODS_UPDATE_TYPE in .env to the correct type from Temu Partner API docs (e.g. bg.goods.update, bg.local.goods.update).', [
                    'sku' => $sku,
                    'current_type' => $apiType,
                ]);
            }

            Log::warning('Temu title update failed', [
                'sku' => $sku,
                'response' => $data,
                'status' => $status,
            ]);
            return ['success' => false, 'message' => (string) $errorMsg];
        } catch (\Throwable $e) {
            Log::error('Temu updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
