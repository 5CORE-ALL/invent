<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\TemuPricing;
use App\Models\TemuMetric;
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

/**
     * Generate signed request for Temu Open API.
     * Uses access_token (underscore), app_key, timestamp, data_type; adds sign.
     * All credentials are trimmed to avoid "application information query is abnormal".
     *
     * @param array $requestBody API-specific params only (e.g. type, outGoodsSn, goodsName)
     * @return array Full request with access_token, app_key, timestamp, data_type, sign, and requestBody keys
     */
    private function generateSignValue($requestBody)
    {
        $appKey = trim((string) (config('services.temu.app_key') ?? ''));
        $appSecret = trim((string) (config('services.temu.secret_key') ?? ''));
        $accessToken = trim((string) (config('services.temu.access_token') ?? ''));

        $timestamp = time();
        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => (string) $timestamp,
            'data_type' => 'JSON',
        ];

        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key . (string) $value;
        }

        $signStr = $appSecret . $temp . $appSecret;
        $sign = strtoupper(md5($signStr));
        $params['sign'] = $sign;

        return array_merge($params, $requestBody);
    }

    /**
     * Sign a request body for Temu API (for testing). Use generateSignValue internally.
     *
     * @param array $requestBody API-specific params (type, goodsId, goodsName, etc.)
     * @return array Full signed request with access_token, app_key, timestamp, sign, etc.
     */
    public function signRequest(array $requestBody): array
    {
        return $this->generateSignValue($requestBody);
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
        foreach ($this->allItems as $titem) {
            $sku = $titem['outGoodsSn'] ?? null;
            $quantity = $titem['quantity'] ?? 0;
            if ($sku) {
                ProductStockMapping::where('sku', $sku)->update(['inventory_temu' => (int) $quantity]);
            }
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
            Log::error("Temu getInventory__ request failed: " . $response->body());
            break;
        }

        $data = $response->json();
        if (!($data['success'] ?? false)) {
            Log::error("Temu getInventory__ API error: " . ($data['errorMsg'] ?? 'Unknown'));
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
            Log::error("Temu API request failed (Page {$pageNumber})", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            break;
        }

        $data = $response->json();
        

        if (!($data['success'] ?? false)) {
            Log::error("Temu API error (Page {$pageNumber})", [
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

            if ($skuId) {
                ProductStockMapping::where('sku', $skuId)->update(['inventory_temu' => (int) $qty]);
            }
        }

        // Stop if this is the last page (fewer items than page size)
        if (count($items) < $pageSize) {break;}

        $pageNumber++;

        // Prevent rate limiting: wait 200ms between requests
        if ($pageNumber <= $maxPages) {
            usleep(200000); // 0.2 seconds
        }

    } while ($pageNumber <= $maxPages);

    Log::info('Total Temu inventory items collected: ' . count($allItems));

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
     * Resolve seller SKU to Temu goodsId (required for update API).
     * Checks TemuPricing and TemuMetric first; if not found, calls list API to find by SKU.
     *
     * @param string $sku Seller SKU (outGoodsSn / outSkuSn)
     * @return string|null goodsId (numeric string) or null if not found
     */
    public function getGoodsIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $goodsId = TemuPricing::where('sku', $sku)->value('goods_id');
        if ($goodsId !== null && $goodsId !== '') {
            return (string) $goodsId;
        }
        $goodsId = TemuMetric::where('sku', $sku)->orWhere('sku_id', $sku)->value('goods_id');
        if ($goodsId !== null && $goodsId !== '') {
            return (string) $goodsId;
        }

        // Fallback: call list API and find good where SKU matches outGoodsSn or skuSn/outSkuSn in skuInfoList
        try {
            $pageToken = null;
            do {
                $requestBody = [
                    'type' => 'temu.local.goods.list.retrieve',
                    'goodsSearchType' => 'ALL',
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $requestBody['pageToken'] = $pageToken;
                }
                $signedRequest = $this->generateSignValue($requestBody);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
                $data = $response->json();
                if ($response->failed() || ! ($data['success'] ?? false)) {
                    break;
                }
                $goodsList = $data['result']['goodsList'] ?? [];
                foreach ($goodsList as $good) {
                    $outGoodsSn = $good['outGoodsSn'] ?? null;
                    if ($outGoodsSn !== null && trim((string) $outGoodsSn) === $sku) {
                        $gid = $good['goodsId'] ?? null;
                        if ($gid !== null && $gid !== '') {
                            return (string) $gid;
                        }
                    }
                    foreach ($good['skuInfoList'] ?? [] as $skuInfo) {
                        $skuSn = $skuInfo['skuSn'] ?? $skuInfo['outSkuSn'] ?? null;
                        if ($skuSn !== null && trim((string) $skuSn) === $sku) {
                            $gid = $good['goodsId'] ?? null;
                            if ($gid !== null && $gid !== '') {
                                return (string) $gid;
                            }
                        }
                    }
                }
                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu getGoodsIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Resolve seller SKU to Temu skuId (internal SKU ID) for update APIs that require "at least one SKU".
     * Checks TemuPricing/TemuMetric first; if not found, calls temu.local.sku.list.retrieve.
     *
     * @param string $sku Seller SKU (outSkuSn)
     * @return string|null skuId (numeric string) or null
     */
    public function getSkuIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }
        $skuId = TemuPricing::where('sku', $sku)->value('sku_id');
        if ($skuId !== null && $skuId !== '') {
            return (string) $skuId;
        }
        $skuId = TemuMetric::where('sku', $sku)->orWhere('sku_id', $sku)->value('sku_id');
        if ($skuId !== null && $skuId !== '') {
            return (string) $skuId;
        }
        try {
            $pageToken = null;
            do {
                $requestBody = [
                    'type' => 'temu.local.sku.list.retrieve',
                    'skuSearchType' => 'ACTIVE',
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $requestBody['pageToken'] = $pageToken;
                }
                $signedRequest = $this->generateSignValue($requestBody);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
                $data = $response->json();
                if ($response->failed() || ! ($data['success'] ?? false)) {
                    break;
                }
                $skuList = $data['result']['skuList'] ?? [];
                foreach ($skuList as $item) {
                    $outSkuSn = isset($item['outSkuSn']) ? trim((string) $item['outSkuSn']) : null;
                    if ($outSkuSn === $sku) {
                        $id = $item['skuId'] ?? null;
                        if ($id !== null && $id !== '') {
                            return (string) $id;
                        }
                    }
                }
                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu getSkuIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Get product price for SKU (from TemuPricing or TemuMetric).
     *
     * @param string $sku Seller SKU
     * @return float|null Price or null if not found
     */
    public function getProductPrice(string $sku): ?float
    {
        $price = TemuPricing::where('sku', $sku)->value('base_price');
        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }
        $price = TemuMetric::where('sku', $sku)->orWhere('sku_id', $sku)->value('base_price');
        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }
        return null;
    }

    /**
     * Get product image URLs from ProductMaster for Temu API.
     * Collects main_image, image1..image12 and converts to absolute URLs.
     *
     * @param string $sku Seller SKU (ProductMaster sku or parent)
     * @return array<int, string> Array of absolute image URLs
     */
    public function getProductImages(string $sku): array
    {
        $sku = trim($sku);
        $product = ProductMaster::where('sku', $sku)
            ->orWhere('parent', $sku)
            ->first();

        if (! $product) {
            return [];
        }

        $imageColumns = ['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7', 'image8', 'image9', 'image10', 'image11', 'image12'];
        $urls = [];
        $seen = [];

        foreach ($imageColumns as $col) {
            $val = trim((string) ($product->{$col} ?? ''));
            if ($val === '') {
                continue;
            }
            $url = $this->toAbsoluteImageUrl($val);
            if ($url !== '' && ! isset($seen[$url])) {
                $urls[] = $url;
                $seen[$url] = true;
            }
        }

        return $urls;
    }

    /**
     * Convert image path to absolute URL for Temu API.
     *
     * @param string $path Storage path, relative path, or full URL
     * @return string Absolute URL or empty if invalid
     */
    protected function toAbsoluteImageUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }
        return asset(ltrim($path, '/'));
    }

    /**
     * Get product dimensions for SKU. Returns defaults when not in database.
     *
     * @param string $sku Seller SKU
     * @return array{weight: string, length: string, width: string, height: string, weightUnit: string, volumeUnit: string}
     */
    public function getProductDimensions(string $sku): array
    {
        return [
            'weight' => '1',
            'length' => '1',
            'width' => '1',
            'height' => '1',
            'weightUnit' => 'g',
            'volumeUnit' => 'cm',
        ];
    }

    /**
     * Get SKU info (skuId, outSkuSn) for a given seller SKU. Used when update API requires skuList.
     *
     * @param string $goodsId Temu goodsId (already resolved)
     * @param string $sku Seller SKU
     * @return array{skuId: string, outSkuSn: string}|null
     */
    public function getSkuInfoForGoodsAndSku(string $goodsId, string $sku): ?array
    {
        $sku = trim($sku);
        $skuId = $this->getSkuIdBySku($sku);
        if ($skuId !== null && $skuId !== '') {
            return ['skuId' => $skuId, 'outSkuSn' => $sku];
        }
        return null;
    }

    /**
     * Update product title on Temu by seller SKU.
     * Resolves SKU → goodsId (required by API); uses goodsId + goodsName in request.
     * API type is configurable via config('services.temu.goods_update_type') or TEMU_GOODS_UPDATE_TYPE.
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

        $goodsId = $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            Log::warning('Temu updateTitle: goodsId not found for SKU', ['sku' => $sku]);
            return [
                'success' => false,
                'message' => 'goodsId not found for SKU. Ensure the product exists on Temu and run goods-id sync (e.g. FetchTemuMetrics) or add sku/goods_id in temu_pricing.',
            ];
        }

        $skuInfo = $this->getSkuInfoForGoodsAndSku($goodsId, $sku);
        Log::info('Temu - SKU resolution for updateTitle', [
            'sku' => $sku,
            'goodsId' => $goodsId,
            'skuInfo' => $skuInfo,
        ]);

        $apiType = config('services.temu.goods_update_type', 'bg.local.goods.partial.update');
        $url = 'https://openapi-b-us.temu.com/openapi/router';

        Log::debug('Temu config check (updateTitle)', [
            'app_key_exists' => ! empty(config('services.temu.app_key')),
            'secret_key_exists' => ! empty(config('services.temu.secret_key')),
            'access_token_exists' => ! empty(config('services.temu.access_token')),
        ]);

        $skuListField = config('services.temu.update_sku_list_field', 'skuInfoList');
        $goodsBasicField = config('services.temu.goods_basic_field', 'goodsBasic');
        $skuIdField = config('services.temu.sku_id_field', 'skuId');
        $skuCodeField = config('services.temu.sku_code_field', 'outSkuSn');

        $requestBody = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => [
                'goodsName' => $title,
            ],
        ];

        if ($skuInfo !== null && isset($skuInfo['skuId'])) {
            $skuEntry = [
                $skuIdField => (int) $skuInfo['skuId'],
                $skuCodeField => $sku,
            ];
            $requestBody[$skuListField] = [$skuEntry];
        }

        Log::info('Temu updateTitle request', [
            'sku' => $sku,
            'goodsId' => $goodsId,
            'body' => $requestBody,
        ]);

        $signedRequest = $this->generateSignValue($requestBody);

        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post($url, $signedRequest);
            $status = $response->status();
            $bodyRaw = $response->body();
            $data = $response->json();

            Log::info('Temu updateTitle response', [
                'status' => $status,
                'body' => $bodyRaw,
            ]);

            if ($response->successful() && ($data['success'] ?? false)) {
                Log::info('Temu title updated successfully', ['sku' => $sku, 'goodsId' => $goodsId]);
                return ['success' => true, 'message' => "Title updated for SKU: {$sku}."];
            }

            $errorCode = $data['errorCode'] ?? null;
            $errorMsg = $data['errorMsg'] ?? $data['message'] ?? $bodyRaw;
            $isAddSkuError = stripos((string) $errorMsg, 'Add at least one SKU') !== false;

            if ((int) $errorCode === 150011003) {
                Log::warning('Temu API "Invalid Request Parameters [goodsId]" (150011003). Ensure goodsId is resolved from SKU (TemuPricing/TemuMetric or list API) and is a valid Temu product ID.', [
                    'sku' => $sku,
                    'goodsId' => $goodsId,
                    'requestBody' => $requestBody,
                ]);
            }
            if ($isAddSkuError) {
                Log::warning('Temu API "Add at least one SKU". Request now includes outGoodsSn and skuInfoList when skuId is available. If still failing, check Temu docs for exact skuInfoList structure.', [
                    'sku' => $sku,
                    'goodsId' => $goodsId,
                    'skuInfo' => $skuInfo ?? null,
                    'requestBody_keys' => array_keys($requestBody),
                ]);
            }
            if ((int) $errorCode === 3000003) {
                Log::warning('Temu API "type not exists" (3000003). Set TEMU_GOODS_UPDATE_TYPE in .env to the correct type from Temu Partner API docs.', [
                    'sku' => $sku,
                    'current_type' => $apiType,
                ]);
            }

            Log::warning('Temu title update failed', [
                'sku' => $sku,
                'goodsId' => $goodsId,
                'response' => $data,
                'status' => $status,
            ]);
            return ['success' => false, 'message' => (string) $errorMsg];
        } catch (\Throwable $e) {
            Log::error('Temu updateTitle exception: ' . $e->getMessage(), [
                'sku' => $sku,
                'goodsId' => $goodsId ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
