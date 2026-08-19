<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\TemuMetric;
use App\Services\Support\DescriptionWithImagesFormatter;
use App\Services\Support\ShopifyBulletPointsFormatter;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;
use Carbon\Carbon;

class TemuApiService
{
    use SavesMarketplaceVideoMetrics;
    use VideoMasterMarketplaceMethods;
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
    protected function generateSignValue($requestBody)
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
        $this->persistGoodsListInventory($this->allItems);
        return $this->allItems;
    }

    /**
     * Persist Temu goods-list inventory onto temu_metrics.quantity (API stock for
     * temu-decrease / map-issues) and product_stock_mappings.inventory_temu.
     *
     * @param  array<int, array<string, mixed>>  $goodsList
     */
    public function persistGoodsListInventory(array $goodsList): int
    {
        $updated = 0;
        if (! Schema::hasColumn('temu_metrics', 'quantity')) {
            return 0;
        }

        foreach ($goodsList as $titem) {
            $goodsQty = (int) ($titem['quantity'] ?? 0);
            $goodsId = isset($titem['goodsId']) ? (string) $titem['goodsId'] : '';
            $skuTargets = [];
            $skuIdQty = []; // skuId => qty (prefer per-SKU stock when API provides it)

            foreach ($titem['outSkuSnList'] ?? [] as $outSku) {
                $outSku = trim((string) $outSku);
                if ($outSku !== '') {
                    $skuTargets[$outSku] = $goodsQty;
                }
            }

            foreach ($titem['skuInfoList'] ?? [] as $skuInfo) {
                $skuQty = $goodsQty;
                foreach (['stock', 'quantity', 'skuStockQuantity', 'virtualStock'] as $stockKey) {
                    if (isset($skuInfo[$stockKey]) && is_numeric($skuInfo[$stockKey])) {
                        $skuQty = (int) $skuInfo[$stockKey];
                        break;
                    }
                }

                foreach (['outSkuSn', 'skuSn', 'extCode'] as $key) {
                    $candidate = trim((string) ($skuInfo[$key] ?? ''));
                    if ($candidate !== '') {
                        $skuTargets[$candidate] = $skuQty;
                    }
                }

                $skuId = isset($skuInfo['skuId']) ? (string) $skuInfo['skuId'] : '';
                if ($skuId !== '') {
                    $skuIdQty[$skuId] = $skuQty;
                }
            }

            $outGoodsSn = trim((string) ($titem['outGoodsSn'] ?? ''));
            if ($outGoodsSn !== '' && $skuTargets === []) {
                $skuTargets[$outGoodsSn] = $goodsQty;
            }

            foreach ($skuIdQty as $skuId => $qty) {
                $updated += TemuMetric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                // Loose match: some rows store sku_id as int-like string with different casting
                $updated += TemuMetric::whereRaw('CAST(sku_id AS CHAR) = ?', [$skuId])
                    ->where('quantity', '!=', $qty)
                    ->update(['quantity' => $qty]);
            }

            // Match seller SKUs case-insensitively (API outSkuSn vs temu_metrics.sku)
            foreach ($skuTargets as $sku => $qty) {
                $updated += TemuMetric::where('sku', $sku)->update(['quantity' => $qty]);
                $updated += TemuMetric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->where('quantity', '!=', $qty)
                    ->update(['quantity' => $qty]);

                ProductStockMapping::where('sku', $sku)->update([
                    'inventory_temu' => (string) $qty,
                ]);
            }

            // Fallback: goods-level qty onto metrics for this goods_id that still have no stock
            // (covers outSkuSn / sku string mismatches). Do not overwrite non-zero per-SKU stock.
            if ($goodsId !== '' && $goodsQty >= 0) {
                $updated += TemuMetric::where('goods_id', $goodsId)
                    ->where(function ($q) {
                        $q->whereNull('quantity')->orWhere('quantity', 0);
                    })
                    ->update(['quantity' => $goodsQty]);
            }
        }

        Log::info('Temu inventory persisted to temu_metrics / product_stock_mappings', [
            'goods' => count($goodsList),
            'metric_updates' => $updated,
        ]);

        return $updated;
    }

    /**
     * Sync per-SKU stock via bg.local.goods.sku.list.query (returns stock per SKU).
     * Complements goods-list inventory when outSkuSn matching misses temu_metrics rows.
     */
    public function syncSkuListStock(): int
    {
        if (! Schema::hasColumn('temu_metrics', 'quantity')) {
            return 0;
        }

        $pageNumber = 1;
        $pageSize = 100;
        $totalPages = null;
        $updated = 0;

        Log::info('======================= Started SKU Stock Sync =======================');

        do {
            $requestBody = [
                'type' => 'bg.local.goods.sku.list.query',
                'pageSize' => $pageSize,
                'pageNumber' => $pageNumber,
                'skuSearchType' => 2, // on sale
            ];

            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
            } catch (\Exception $e) {
                Log::error('SKU stock sync HTTP exception page '.$pageNumber.': '.$e->getMessage());
                break;
            }

            if ($response->failed()) {
                Log::error('SKU stock sync failed page '.$pageNumber, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                // Some accounts use pageNo instead of pageNumber / different skuSearchType
                Log::warning('SKU stock sync API error page '.$pageNumber.': '.($data['errorMsg'] ?? 'Unknown'));
                break;
            }

            $result = $data['result'] ?? [];
            $items = $result['skuList'] ?? [];
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $qty = $item['stock'] ?? $item['quantity'] ?? null;
                if ($qty === null || ! is_numeric($qty)) {
                    continue;
                }
                $qty = (int) $qty;

                $skuId = isset($item['skuId']) ? (string) $item['skuId'] : '';
                $outSkuSn = trim((string) ($item['outSkuSn'] ?? $item['skuSn'] ?? ''));
                $goodsId = isset($item['goodsId']) ? (string) $item['goodsId'] : '';

                if ($skuId !== '') {
                    $updated += TemuMetric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                }
                if ($outSkuSn !== '') {
                    $updated += TemuMetric::where('sku', $outSkuSn)->update(['quantity' => $qty]);
                    $updated += TemuMetric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($outSkuSn)])
                        ->where('quantity', '!=', $qty)
                        ->update(['quantity' => $qty]);
                }
                // Last resort for this SKU row: goods_id when only one metric shares it
                if ($goodsId !== '' && $skuId === '' && $outSkuSn === '') {
                    $updated += TemuMetric::where('goods_id', $goodsId)->update(['quantity' => $qty]);
                }
            }

            if ($totalPages === null) {
                $total = (int) ($result['total'] ?? 0);
                $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : $pageNumber;
            }

            $pageNumber++;
            if ($pageNumber > 1000) {
                break;
            }
        } while ($pageNumber <= ($totalPages ?? 1));

        Log::info('SKU stock sync finished', ['updated' => $updated, 'pages' => $pageNumber - 1]);

        return $updated;
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
    $detailed = $this->fetchAdsDataDetailed($goodsId, $startTs, $endTs);

    return ($detailed['ok'] ?? false) ? ($detailed['result'] ?? null) : null;
}

/**
 * Seller Center "Last N days" windows (America/Los_Angeles, inclusive of today).
 * L30 matches Data Report Last 30 days (e.g. 07/17–08/15 when today is 08/15).
 * L60 is the prior 30-day comparison window (e.g. 06/17–07/16).
 */
public function adsPeriodRanges(): array
{
    $today = Carbon::now();

    return [
        'L7' => [
            'startTs' => $today->copy()->subDays(6)->startOfDay()->timestamp * 1000,
            'endTs' => $today->copy()->endOfDay()->timestamp * 1000,
        ],
        'L30' => [
            'startTs' => $today->copy()->subDays(29)->startOfDay()->timestamp * 1000,
            'endTs' => $today->copy()->endOfDay()->timestamp * 1000,
        ],
        'L60' => [
            'startTs' => $today->copy()->subDays(59)->startOfDay()->timestamp * 1000,
            'endTs' => $today->copy()->subDays(30)->endOfDay()->timestamp * 1000,
        ],
    ];
}

/**
 * Same as fetchAdsData but returns error details for storage/UI.
 *
 * @return array{ok: bool, result: ?array, error_code: mixed, error_msg: ?string, http_status: ?int}
 */
public function fetchAdsDataDetailed($goodsId, $startTs = null, $endTs = null): array
{
    if ($startTs === null || $endTs === null) {
        $l30 = $this->adsPeriodRanges()['L30'];
        $startTs = $startTs ?? $l30['startTs'];
        $endTs = $endTs ?? $l30['endTs'];
    }

    // Temu expects numeric goodsId (same as updateTitle / other goods APIs)
    $goodsIdParam = is_numeric($goodsId) ? (int) $goodsId : $goodsId;

    $requestBody = [
        'type' => 'temu.searchrec.ad.reports.goods.query',
        'goodsId' => $goodsIdParam,
        'startTs' => (int) $startTs,
        'endTs' => (int) $endTs,
    ];

    $signedRequest = $this->generateSignValue($requestBody);

    $request = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->timeout(60);

    if (config('filesystems.default') === 'local') {
        $request = $request->withoutVerifying();
    }

    try {
        $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
        $httpStatus = $response->status();

        if ($response->failed()) {
            Log::error("Temu Ads API request failed for Goods ID: {$goodsId}", [
                'status' => $httpStatus,
            ]);

            return [
                'ok' => false,
                'result' => null,
                'error_code' => $httpStatus,
                'error_msg' => 'HTTP '.$httpStatus,
                'http_status' => $httpStatus,
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'ok' => false,
                'result' => null,
                'error_code' => null,
                'error_msg' => 'Invalid JSON response',
                'http_status' => $httpStatus,
            ];
        }

        if (! ($data['success'] ?? false)) {
            $errorCode = $data['errorCode'] ?? null;
            $errorMsg = (string) ($data['errorMsg'] ?? 'Unknown error');
            Log::error("Temu Ads API error for Goods ID: {$goodsId}", [
                'error' => $errorMsg,
                'errorCode' => $errorCode,
            ]);

            return [
                'ok' => false,
                'result' => null,
                'error_code' => $errorCode,
                'error_msg' => trim($errorCode !== null ? "{$errorCode}: {$errorMsg}" : $errorMsg),
                'http_status' => $httpStatus,
            ];
        }

        return [
            'ok' => true,
            'result' => $data['result'] ?? null,
            'error_code' => null,
            'error_msg' => null,
            'http_status' => $httpStatus,
        ];
    } catch (\Exception $e) {
        Log::error("Exception fetching Temu ads data for Goods ID: {$goodsId}", [
            'error' => $e->getMessage(),
        ]);

        return [
            'ok' => false,
            'result' => null,
            'error_code' => null,
            'error_msg' => $e->getMessage(),
            'http_status' => null,
        ];
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
    
    $ranges = $this->adsPeriodRanges();
    $range = $ranges[$period] ?? $ranges['L30'];

    foreach ($goodsIds as $goodsId) {
        $data = $this->fetchAdsData($goodsId, $range['startTs'], $range['endTs']);
        
        if ($data) {
            $overall = is_array($data['reportInfo']['summary'] ?? null) ? $data['reportInfo']['summary'] : [];
            $adOnly = is_array($data['reportInfo']['reportsSummary'] ?? null) ? $data['reportInfo']['reportsSummary'] : [];
            $impressions = $overall['imprCnt']['total']['val'] ?? $adOnly['imprCntAll']['val'] ?? 0;
            $clicks = $overall['clkCnt']['total']['val'] ?? $adOnly['clkCntAll']['val'] ?? 0;
            $results[$goodsId] = [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
            ];
        }
        
        // Rate limiting - small delay between requests
        usleep(200000); // 0.2 seconds
    }

    return $results;
}

    /**
     * Create a Temu search ad for one goods ID.
     * Budget is sent in cents (Temu money unit). ROAS is the target multiple (e.g. 12 = 12x).
     *
     * @return array{ok: bool, result: mixed, error_code: mixed, error_msg: ?string, http_status: ?int, request: array}
     */
    public function createAd(string $goodsId, float $budgetDollars, float $roas): array
    {
        $goodsIdParam = is_numeric($goodsId) ? (int) $goodsId : $goodsId;
        $budgetCents = (int) round($budgetDollars * 100);

        $requestBody = [
            'type' => 'temu.searchrec.ad.create',
            'createAdReqs' => [
                [
                    'goodsId' => $goodsIdParam,
                    'budget' => $budgetCents,
                    'roas' => $roas,
                ],
            ],
        ];

        return $this->postAdsRouter($requestBody, (string) $goodsId);
    }

    /**
     * Pause a Temu search ad (temu.searchrec.ad.modify, status 2 = paused).
     * One modify call only — do not prefetch ad.detail.query (that doubled runtime).
     *
     * @return array{ok: bool, already?: bool, result: mixed, error_code: mixed, error_msg: ?string, http_status: ?int, request: array}
     */
    public function pauseAd(string $goodsId): array
    {
        $goodsIdParam = is_numeric($goodsId) ? (int) $goodsId : $goodsId;
        $modified = $this->postAdsRouter([
            'type' => 'temu.searchrec.ad.modify',
            'modifyAdDTO' => ['goodsId' => $goodsIdParam],
            'status' => 2,
        ], (string) $goodsId, 15);

        if ($modified['ok'] ?? false) {
            $list = is_array($modified['result'] ?? null)
                ? ($modified['result']['modifyGoodsRespList'] ?? null)
                : null;
            if (is_array($list)) {
                foreach ($list as $row) {
                    if (is_array($row) && array_key_exists('success', $row) && ! $row['success']) {
                        $modified['ok'] = false;
                        $modified['error_msg'] = (string) ($row['reason'] ?? 'Temu did not pause this ad');
                        break;
                    }
                }
            }
        }

        return $modified;
    }

    /**
     * Suggested target ROAS from Temu (temu.searchrec.ad.roas.pred).
     *
     * @return array{ok: bool, result: mixed, error_code: mixed, error_msg: ?string, http_status: ?int}
     */
    public function predictAdRoas(string $goodsId): array
    {
        $goodsIdParam = is_numeric($goodsId) ? (int) $goodsId : $goodsId;

        return $this->postAdsRouter([
            'type' => 'temu.searchrec.ad.roas.pred',
            'goodsInfoList' => [
                ['goodsId' => $goodsIdParam],
            ],
        ], (string) $goodsId);
    }

    /**
     * Ad campaign status for goods IDs (temu.searchrec.ad.detail.query).
     * Official result list is result.adsDetail[]; status field is adShowStatus.
     *
     * Failed / unparseable chunks are omitted (never written as "No ad").
     * "No ad" is only used when Temu returns a successful empty/missing adsDetail row.
     *
     * @param  array<int, string|int>  $goodsIds
     * @return array{statuses: array<string, string>, failed: array<int, string>, error: ?string}
     */
    public function queryAdStatuses(array $goodsIds): array
    {
        $ids = [];
        foreach ($goodsIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[$id] = is_numeric($id) ? (int) $id : $id;
            }
        }
        if ($ids === []) {
            return ['statuses' => [], 'failed' => [], 'error' => null];
        }

        $statuses = [];
        $failed = [];
        $error = null;

        foreach (array_chunk(array_values($ids), 20) as $chunk) {
            $result = $this->postAdsRouter([
                'type' => 'temu.searchrec.ad.detail.query',
                'goodsList' => $chunk,
            ], (string) $chunk[0]);

            if (! ($result['ok'] ?? false)) {
                $error = $error ?? (string) ($result['error_msg'] ?? 'Temu ad.detail.query failed');
                foreach ($chunk as $gid) {
                    $failed[] = (string) $gid;
                }
                usleep(150000);
                continue;
            }

            $payload = $result['result'] ?? null;
            $items = $this->extractAdDetailItems($payload);
            if ($items === null) {
                $error = $error ?? 'Temu ad.detail.query returned an unrecognized payload';
                Log::warning('Temu ad.detail.query: could not parse adsDetail', [
                    'goods_ids' => $chunk,
                    'result_type' => gettype($payload),
                    'result_keys' => is_array($payload) ? array_keys($payload) : null,
                ]);
                foreach ($chunk as $gid) {
                    $failed[] = (string) $gid;
                }
                usleep(150000);
                continue;
            }

            $seen = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $gid = (string) ($item['goodsId'] ?? $item['goods_id'] ?? '');
                if ($gid === '') {
                    continue;
                }
                $statuses[$gid] = self::statusFromAdDetail($item);
                $seen[$gid] = true;
            }

            foreach ($chunk as $gid) {
                $key = (string) $gid;
                if (! isset($statuses[$key]) && ! isset($seen[$key])) {
                    $statuses[$key] = 'No ad';
                }
            }

            usleep(150000);
        }

        return [
            'statuses' => $statuses,
            'failed' => $failed,
            'error' => $error,
        ];
    }

    /**
     * Official Temu result is { adsDetail: [...] } or a list of those objects.
     *
     * @return array<int, mixed>|null null = unrecognized shape (do not treat as No ad)
     */
    protected function extractAdDetailItems(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return isset($payload[0]) && is_array($payload[0]) ? $payload : [];
        }

        foreach ([
            'adsDetail', 'adsDetails', 'adDetailList', 'adDetails',
            'adList', 'adsList', 'goodsList', 'list',
            'adInfoList', 'goodsAdList', 'goodsAdDetailList',
            'detailList', 'data', 'records',
        ] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $v = $payload[$key];
            if (! is_array($v)) {
                continue;
            }
            if ($v === []) {
                return [];
            }
            if (array_is_list($v)) {
                return $v;
            }
            if (isset($v['goodsId']) || isset($v['goods_id'])) {
                return [$v];
            }
        }

        if (isset($payload['goodsId']) || isset($payload['goods_id']) || isset($payload['adShowStatus'])) {
            return [$payload];
        }

        return null;
    }

    public static function statusFromAdDetail(array $item): string
    {
        $raw = $item['adShowStatus']
            ?? $item['adStatus']
            ?? $item['status']
            ?? $item['campaignStatus']
            ?? $item['adState']
            ?? $item['adPhase']
            ?? null;

        if ($raw === null && is_array($item['siteStatusInfoList'] ?? null)) {
            foreach ($item['siteStatusInfoList'] as $site) {
                if (is_array($site) && isset($site['adShowStatus'])) {
                    $raw = $site['adShowStatus'];
                    break;
                }
            }
        }

        $mapped = self::normalizeAdStatus($raw);
        $hasAd = isset($item['budget']) || isset($item['roas']) || isset($item['adShowStatus']) || isset($item['adPhase']);
        if ($mapped === 'No ad' && $hasAd) {
            return 'Inactive';
        }
        if ($mapped === 'Unknown' && $hasAd) {
            return 'Active';
        }

        return $mapped;
    }

    /**
     * adShowStatus / adPhase from ad.detail.query: 0 none, 1 delivering, 2 paused, 3 deleted.
     * String labels from Seller Center are also accepted.
     */
    public static function normalizeAdStatus(mixed $raw): string
    {
        if (is_array($raw)) {
            $raw = $raw['val'] ?? $raw['status'] ?? $raw['adStatus'] ?? $raw['adShowStatus'] ?? null;
        }
        if ($raw === null || $raw === '') {
            return 'Unknown';
        }

        $n = is_numeric($raw) ? (int) $raw : null;
        $s = strtolower(trim((string) $raw));

        if ($n === 1 || in_array($s, ['1', 'active', 'enable', 'enabled', 'online', 'on', 'running', 'delivering', 'deliver', 'showing'], true)) {
            return 'Active';
        }
        if ($n === 2 || $n === 4 || in_array($s, ['2', '4', 'inactive', 'pause', 'paused', 'offline', 'off', 'stop', 'stopped', 'suspend', 'forbidden'], true)) {
            return 'Inactive';
        }
        if ($n === 3 || in_array($s, ['3', 'deleted', 'delete', 'removed'], true)) {
            return 'Deleted';
        }
        if ($n === 0 || in_array($s, ['0', 'none', 'no ad', 'no_ad', 'not_created'], true)) {
            return 'No ad';
        }

        return 'Unknown';
    }

    /**
     * Signed POST to Temu OpenAPI router for ads endpoints.
     *
     * @return array{ok: bool, result: mixed, error_code: mixed, error_msg: ?string, http_status: ?int, request: array}
     */
    protected function postAdsRouter(array $requestBody, string $goodsId, int $timeoutSeconds = 60): array
    {
        $signedRequest = $this->generateSignValue($requestBody);
        $request = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(max(5, $timeoutSeconds));

        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
            $httpStatus = $response->status();
            $data = $response->json();

            if ($response->failed() || ! is_array($data)) {
                Log::error('Temu ads router request failed', [
                    'type' => $requestBody['type'] ?? null,
                    'goods_id' => $goodsId,
                    'status' => $httpStatus,
                ]);

                return [
                    'ok' => false,
                    'result' => is_array($data) ? ($data['result'] ?? $data) : null,
                    'error_code' => $httpStatus,
                    'error_msg' => is_array($data) ? (string) ($data['errorMsg'] ?? 'HTTP '.$httpStatus) : 'HTTP '.$httpStatus,
                    'http_status' => $httpStatus,
                    'request' => $requestBody,
                ];
            }

            if (! ($data['success'] ?? false)) {
                $errorCode = $data['errorCode'] ?? null;
                $errorMsg = (string) ($data['errorMsg'] ?? 'Unknown error');
                Log::error('Temu ads router API error', [
                    'type' => $requestBody['type'] ?? null,
                    'goods_id' => $goodsId,
                    'error' => $errorMsg,
                    'errorCode' => $errorCode,
                ]);

                return [
                    'ok' => false,
                    'result' => $data['result'] ?? null,
                    'error_code' => $errorCode,
                    'error_msg' => trim($errorCode !== null ? "{$errorCode}: {$errorMsg}" : $errorMsg),
                    'http_status' => $httpStatus,
                    'request' => $requestBody,
                ];
            }

            return [
                'ok' => true,
                'result' => $data['result'] ?? $data,
                'error_code' => null,
                'error_msg' => null,
                'http_status' => $httpStatus,
                'request' => $requestBody,
            ];
        } catch (\Exception $e) {
            Log::error('Temu ads router exception', [
                'type' => $requestBody['type'] ?? null,
                'goods_id' => $goodsId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'result' => null,
                'error_code' => null,
                'error_msg' => $e->getMessage(),
                'http_status' => null,
                'request' => $requestBody,
            ];
        }
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

        $goodsId = TemuMetric::where(function ($q) use ($sku) {
            $q->where('sku', $sku)
                ->orWhere('sku_id', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
        })->value('goods_id');
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
                            $this->persistTemuMapping($sku, (string) $gid, null);

                            return (string) $gid;
                        }
                    }
                    foreach ($good['skuInfoList'] ?? [] as $skuInfo) {
                        $skuSn = $skuInfo['skuSn'] ?? $skuInfo['outSkuSn'] ?? null;
                        if ($skuSn !== null && trim((string) $skuSn) === $sku) {
                            $gid = $good['goodsId'] ?? null;
                            if ($gid !== null && $gid !== '') {
                                $innerSkuId = $skuInfo['skuId'] ?? null;
                                $this->persistTemuMapping($sku, (string) $gid, $innerSkuId !== null && $innerSkuId !== '' ? (string) $innerSkuId : null);

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
     * Cache the goodsId/skuId mapping in temu_metrics so the next call short-circuits to the DB
     * instead of paginating the goods/sku list API again. List-API pagination is the source of
     * the intermittent "goodsId not found" failures on title-master pushes.
     */
    protected function persistTemuMapping(string $sku, ?string $goodsId, ?string $skuId): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        try {
            if (! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'sku')) {
                return;
            }
            $update = [];
            if ($goodsId !== null && $goodsId !== '' && Schema::hasColumn('temu_metrics', 'goods_id')) {
                $update['goods_id'] = $goodsId;
            }
            if ($skuId !== null && $skuId !== '' && Schema::hasColumn('temu_metrics', 'sku_id')) {
                $update['sku_id'] = $skuId;
            }
            if ($update === []) {
                return;
            }
            TemuMetric::updateOrCreate(['sku' => $sku], $update);
        } catch (\Throwable $e) {
            Log::warning('Temu persistTemuMapping failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);
        }
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
        $skuId = TemuMetric::where(function ($q) use ($sku) {
            $q->where('sku', $sku)
                ->orWhere('sku_id', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
        })->value('sku_id');
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
                            $gid = $item['goodsId'] ?? null;
                            $this->persistTemuMapping($sku, $gid !== null && $gid !== '' ? (string) $gid : null, (string) $id);

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
     * Push SKU base/supplier price to Temu via bg.local.goods.priceorder.change.sku.price.
     *
     * @param  string  $sku  Seller SKU
     * @param  float  $price  New supplier/base price (not storefront Temu Price)
     * @param  string|null  $goodsId  Optional goodsId from row (skips lookup)
     * @param  string|null  $skuId  Optional skuId from row (skips lookup)
     * @param  string  $currency
     * @return array{success: bool, message: string, goods_id?: string, sku_id?: string, response?: mixed}
     */
    public function updateSkuBasePrice(
        string $sku,
        float $price,
        ?string $goodsId = null,
        ?string $skuId = null,
        string $currency = 'USD'
    ): array {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if ($price <= 0) {
            return ['success' => false, 'message' => 'Price must be greater than 0.'];
        }

        $goodsId = $goodsId !== null && $goodsId !== '' ? (string) $goodsId : $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            return [
                'success' => false,
                'message' => 'goodsId not found for SKU. Run app:fetch-temu-metrics first.',
            ];
        }

        $skuId = $skuId !== null && $skuId !== '' ? (string) $skuId : $this->getSkuIdBySku($sku);
        if (! $skuId) {
            return [
                'success' => false,
                'message' => 'skuId not found for SKU. Run app:fetch-temu-metrics --only=skus first.',
            ];
        }

        $amount = number_format($price, 2, '.', '');
        $currency = strtoupper(trim($currency)) ?: 'USD';

        $requestBody = [
            'type' => 'bg.local.goods.priceorder.change.sku.price',
            'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
            'changeSkuPriceDTOList' => [
                [
                    'skuChangePriceBaseDTOList' => [
                        [
                            'skuId' => is_numeric($skuId) ? (int) $skuId : $skuId,
                            'newSupplierPrice' => [
                                'amount' => $amount,
                                'currency' => $currency,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $signedRequest = $this->generateSignValue($requestBody);
        $url = 'https://openapi-b-us.temu.com/openapi/router';

        $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(60);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            Log::info('Temu updateSkuBasePrice request', [
                'sku' => $sku,
                'goodsId' => $goodsId,
                'skuId' => $skuId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            $response = $request->post($url, $signedRequest);
            $data = $response->json() ?? [];

            Log::info('Temu updateSkuBasePrice response', [
                'sku' => $sku,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful() && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => "Price updated on Temu for SKU: {$sku} → {$amount} {$currency}.",
                    'goods_id' => (string) $goodsId,
                    'sku_id' => (string) $skuId,
                    'response' => $data['result'] ?? $data,
                ];
            }

            $errorCode = $data['errorCode'] ?? $response->status();
            $errorMsg = (string) ($data['errorMsg'] ?? $data['message'] ?? $response->body() ?: 'Unknown error');

            return [
                'success' => false,
                'message' => trim("[{$errorCode}] {$errorMsg}"),
                'goods_id' => (string) $goodsId,
                'sku_id' => (string) $skuId,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Temu updateSkuBasePrice exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Temu price push failed: '.$e->getMessage(),
            ];
        }
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

        $skuListField = config('services.temu.update_sku_list_field', 'skuList');
        $goodsBasicField = config('services.temu.goods_basic_field', 'goodsBasic');
        $skuIdField = config('services.temu.sku_id_field', 'skuId');
        $skuCodeField = config('services.temu.sku_code_field', 'outSkuSn');

        Log::info('Temu - API type and config for updateTitle', [
            'type' => $apiType,
            'sku_list_field' => $skuListField,
            'goods_basic_field' => $goodsBasicField,
        ]);

        $requestBody = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => [
                'goodsName' => $title,
            ],
        ];

        $price = $this->getProductPrice($sku);
        $dimensions = $this->getProductDimensions($sku);
        $images = $this->getProductImages($sku);

        // Always send skuList — Temu's bg.local.goods.partial.update rejects requests without it
        // ("Add at least one SKU"). When skuId resolution fails (transient list-API failure),
        // we still send the minimal entry (outSkuSn + price/dim/images) so the request is valid.
        $skuEntry = [
            $skuCodeField => $sku,
            'listPrice' => [
                'amount' => (string) ($price ?? 1.00),
                'currency' => 'USD',
            ],
            'listPriceType' => 0,
            'weight' => $dimensions['weight'],
            'length' => $dimensions['length'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'weightUnit' => $dimensions['weightUnit'],
            'volumeUnit' => $dimensions['volumeUnit'],
            'images' => $images,
        ];
        if ($skuInfo !== null && isset($skuInfo['skuId'])) {
            $skuEntry[$skuIdField] = (int) $skuInfo['skuId'];
        }
        $requestBody[$skuListField] = [$skuEntry];

        Log::info('Temu - Full SKU entry (field name: ' . $skuListField . ')', [
            'sku' => $sku,
            'skuEntry' => $skuEntry,
            'skuId_resolved' => isset($skuEntry[$skuIdField]),
        ]);

        Log::info('Temu updateTitle - exact request body (field name: ' . $skuListField . ')', [
            'sku' => $sku,
            'goodsId' => $goodsId,
            'body' => $requestBody,
            'body_json' => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
        ]);

        $signedRequest = $this->generateSignValue($requestBody);

        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            // 2-attempt loop (same shape as pushTemuGoodsBasicField) — covers transient 5xx / network
            // blips that previously surfaced as one-off "title push failed" on title-master.
            $status = 0;
            $bodyRaw = '';
            $data = [];
            $errorCode = null;
            $errorMsg = '';
            $maxAttempts = 2;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = $request->post($url, $signedRequest);
                $status = $response->status();
                $bodyRaw = $response->body();
                $data = $response->json() ?? [];

                Log::info('Temu updateTitle response', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'status' => $status,
                    'body' => $bodyRaw,
                ]);

                if ($response->successful() && ($data['success'] ?? false)) {
                    Log::info('Temu title updated successfully', ['sku' => $sku, 'goodsId' => $goodsId, 'attempt' => $attempt]);
                    return ['success' => true, 'message' => "Title updated for SKU: {$sku}."];
                }

                $errorCode = $data['errorCode'] ?? null;
                $errorMsg = (string) ($data['errorMsg'] ?? $data['message'] ?? $bodyRaw);

                // Don't retry on validation-style errors — they won't change on a retry.
                $nonRetryableCodes = [150011003, 3000003];
                $isAddSkuError = stripos($errorMsg, 'Add at least one SKU') !== false;
                if (in_array((int) $errorCode, $nonRetryableCodes, true) || $isAddSkuError) {
                    break;
                }
                if ($attempt < $maxAttempts) {
                    usleep(500000);
                }
            }

            $isAddSkuError = stripos((string) $errorMsg, 'Add at least one SKU') !== false;

            if ((int) $errorCode === 150011003) {
                Log::warning('Temu API "Invalid Request Parameters [goodsId]" (150011003). Ensure goodsId is resolved from SKU (TemuPricing/TemuMetric or list API) and is a valid Temu product ID.', [
                    'sku' => $sku,
                    'goodsId' => $goodsId,
                    'requestBody' => $requestBody,
                ]);
            }
            if ($isAddSkuError) {
                Log::warning('Temu API "Add at least one SKU" - SKU fields may not be recognized. Official docs use skuList (not skuInfoList). Try TEMU_UPDATE_SKU_LIST_FIELD=skuList in .env if using skuInfoList.', [
                    'sku' => $sku,
                    'goodsId' => $goodsId,
                    'sku_list_field_used' => $skuListField ?? config('services.temu.update_sku_list_field'),
                    'skuInfo' => $skuInfo ?? null,
                    'requestBody_keys' => array_keys($requestBody),
                    'requestBody' => $requestBody,
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

    /**
     * Resolve seller SKU and goods_id from SKU, goods_id, or internal sku_id (Temu metrics).
     *
     * @return array{sku: string, goods_id: ?string}
     */
    protected function resolveTemuGoodsAndSku(string $identifier): array
    {
        $id = trim($identifier);
        if ($id === '') {
            return ['sku' => '', 'goods_id' => null];
        }

        $m = TemuMetric::query()
            ->where('sku', $id)
            ->orWhere('sku', strtoupper($id))
            ->orWhere('sku', strtolower($id))
            ->first();
        if (! $m) {
            $m = TemuMetric::query()
                ->where('goods_id', $id)
                ->orWhere('sku_id', $id)
                ->first();
        }

        if ($m) {
            $sku = $m->sku ? trim((string) $m->sku) : $id;
            $gid = $m->goods_id;

            return [
                'sku' => $sku,
                'goods_id' => $gid !== null && $gid !== '' ? (string) $gid : null,
            ];
        }

        return ['sku' => $id, 'goods_id' => null];
    }

    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => 'SKU (or goods_id) is required.'];
        }

        $lines = array_values(array_filter(array_map(
            fn ($line) => ShopifyBulletPointsFormatter::cleanBulletLine((string) $line),
            preg_split('/\r\n|\r|\n/', (string) $bulletPoints) ?: []
        )));
        if ($lines === []) {
            return ['success' => false, 'message' => 'SKU (or goods_id) and bullet points are required.'];
        }

        $field = config('services.temu.goods_summary_field', 'goodsSummary');
        $format = strtolower((string) config('services.temu.goods_summary_format', 'string'));
        $value = $format === 'array' ? $lines : implode("\n", $lines);

        $includeSkuList = (bool) config('services.temu.bullet_update_include_sku_list', false);

        $res = $this->pushTemuGoodsBasicField(
            $identifier,
            $value,
            $field,
            'Temu bullet points updated.',
            'SKU (or goods_id) and bullet points are required.',
            'Temu updateBulletPoints',
            $includeSkuList,
            null,
            false
        );
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = trim((string) ($resolved['sku'] ?? $identifier));
        $saved = $this->saveGoodsSummaryToTemuMetrics($sku, is_array($value) ? implode("\n", $value) : (string) $value);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Temu bullet points updated.').' Metrics save failed.';
        }

        return $res;
    }

    /**
     * Partial goods update: one field inside `goodsBasic` (e.g. goodsSummary for bullets, goodsDesc for long description).
     *
     * @param  string|array<int, string>  $text  Scalar string or list of lines (e.g. goodsSummary as array)
     * @return array{success: bool, message: string}
     */
    protected function pushTemuGoodsBasicField(
        string $identifier,
        string|array $text,
        string $basicFieldKey,
        string $successMessage,
        string $emptyIdentifierMessage,
        string $logContext,
        bool $includeSkuList = true,
        ?string $preservedGoodsDesc = null,
        bool $preserveGoodsDesc = true,
    ): array {
        if (trim($identifier) === '') {
            return ['success' => false, 'message' => $emptyIdentifierMessage];
        }
        if (is_string($text)) {
            $text = trim($text);
        } else {
            $text = array_values(array_filter(array_map('trim', $text), fn ($s) => $s !== ''));
        }
        if ($text === '' || $text === []) {
            return ['success' => false, 'message' => $emptyIdentifierMessage];
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = $resolved['sku'];
        $goodsId = $resolved['goods_id'] ?? $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            return ['success' => false, 'message' => 'goodsId not found for SKU or marketplace id.'];
        }

        $skuInfo = $this->getSkuInfoForGoodsAndSku($goodsId, $sku);
        $apiType = config('services.temu.goods_update_type', 'bg.local.goods.partial.update');
        $url = 'https://openapi-b-us.temu.com/openapi/router';
        $skuListField = config('services.temu.update_sku_list_field', 'skuList');
        $goodsBasicField = config('services.temu.goods_basic_field', 'goodsBasic');

        $requestBody = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => [
                $basicFieldKey => $text,
            ],
        ];

        // Preserve current goodsDesc when updating goodsSummary to avoid accidental description loss.
        $goodsDescField = (string) config('services.temu.goods_desc_field', 'goodsDesc');
        if ($preserveGoodsDesc && $basicFieldKey !== $goodsDescField) {
            $currentDesc = $preservedGoodsDesc !== null
                ? trim($preservedGoodsDesc)
                : $this->fetchCurrentTemuGoodsDesc((string) $goodsId, $sku);
            if ($currentDesc !== '') {
                $requestBody[$goodsBasicField][$goodsDescField] = $currentDesc;
            }
        }

        $price = $this->getProductPrice($sku);
        $dimensions = $this->getProductDimensions($sku);
        $images = $this->getProductImages($sku);

        if ($includeSkuList && $skuInfo !== null && isset($skuInfo['skuId'])) {
            $skuIdField = config('services.temu.sku_id_field', 'skuId');
            $skuCodeField = config('services.temu.sku_code_field', 'outSkuSn');
            $requestBody[$skuListField] = [[
                $skuIdField => (int) $skuInfo['skuId'],
                $skuCodeField => $sku,
                'listPrice' => [
                    'amount' => (string) ($price ?? 1.00),
                    'currency' => 'USD',
                ],
                'listPriceType' => 0,
                'weight' => $dimensions['weight'],
                'length' => $dimensions['length'],
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'weightUnit' => $dimensions['weightUnit'],
                'volumeUnit' => $dimensions['volumeUnit'],
                'images' => $images,
            ]];
        }

        if ($logContext === 'Temu updateBulletPoints') {
            Log::info('Temu updateBulletPoints request', [
                'sku' => $sku,
                'goodsId' => $goodsId,
                'goodsBasicKey' => $basicFieldKey,
                'includeSkuList' => $includeSkuList,
                'goods_summary_format' => is_array($text) ? 'array' : 'string',
            ]);
        }

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            $lastBody = '';
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $response = $request->post($url, $signedRequest);
                $data = $response->json();
                $lastBody = (string) ($data['errorMsg'] ?? $data['message'] ?? $response->body());
                if ($response->successful() && ($data['success'] ?? false)) {
                    return ['success' => true, 'message' => $successMessage];
                }
                if ($attempt < 2) {
                    usleep(500000);
                }
            }

            return ['success' => false, 'message' => $lastBody];
        } catch (\Throwable $e) {
            Log::error($logContext, ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Description Master: return the current Temu long description (goodsDesc) for one SKU. Read-only
     * (DB-first, then Temu detail/list API fallback via fetchCurrentTemuGoodsDesc).
     *
     * @return array{success: bool, message: string, html?: string}
     */
    public function fetchDescriptionHtml(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }

        $goodsId = $this->getGoodsIdBySku($identifier);
        if (! $goodsId) {
            return ['success' => false, 'message' => 'Temu goods_id not found for this SKU.'];
        }

        $desc = trim($this->fetchCurrentTemuGoodsDesc((string) $goodsId, $identifier));
        if ($desc === '') {
            return ['success' => false, 'message' => 'Temu returned no description for this SKU.'];
        }

        return ['success' => true, 'message' => 'Temu description loaded.', 'html' => $desc];
    }

    protected function fetchCurrentTemuGoodsDesc(string $goodsId, string $sku = ''): string
    {
        // 1) Database first (requested): prefer persisted copy to avoid API gaps.
        try {
            if ($sku !== '' && Schema::hasTable('temu_metrics') && Schema::hasColumn('temu_metrics', 'sku')) {
                if (Schema::hasColumn('temu_metrics', 'goods_desc')) {
                    $desc = DB::table('temu_metrics')
                        ->where(function ($q) use ($sku) {
                            $q->where('sku', $sku)
                                ->orWhere('sku', strtoupper($sku))
                                ->orWhere('sku', strtolower($sku));
                        })
                        ->value('goods_desc');
                    $desc = trim((string) $desc);
                    if ($desc !== '') {
                        return $desc;
                    }
                }
                if (Schema::hasColumn('temu_metrics', 'description_master')) {
                    $desc = DB::table('temu_metrics')
                        ->where(function ($q) use ($sku) {
                            $q->where('sku', $sku)
                                ->orWhere('sku', strtoupper($sku))
                                ->orWhere('sku', strtolower($sku));
                        })
                        ->value('description_master');
                    $desc = trim((string) $desc);
                    if ($desc !== '') {
                        return $desc;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Temu DB-first goods_desc fetch failed', ['sku' => $sku, 'goods_id' => $goodsId, 'error' => $e->getMessage()]);
        }

        // 2) API fallback
        try {
            // 1) Try direct detail APIs first (best chance to include goodsDesc).
            $detailTypes = array_filter([
                (string) config('services.temu.goods_detail_type', 'temu.local.goods.detail.retrieve'),
                'bg.local.goods.detail.query',
                'temu.local.goods.retrieve',
            ]);
            foreach ($detailTypes as $type) {
                if ($type === '') {
                    continue;
                }
                $detailReq = [
                    'type' => $type,
                    'goodsId' => (int) $goodsId,
                ];
                $signed = $this->generateSignValue($detailReq);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signed);
                $data = $response->json();
                if (! $response->successful() || ! ($data['success'] ?? false)) {
                    continue;
                }
                $result = $data['result'] ?? [];
                $goodsBasic = is_array($result['goodsBasic'] ?? null) ? $result['goodsBasic'] : [];
                $desc = trim((string) ($goodsBasic['goodsDesc'] ?? $result['goodsDesc'] ?? ''));
                if ($desc !== '') {
                    return $desc;
                }
            }

            // 2) Fallback to list API and scan goodsBasic.goodsDesc.
            $pageToken = null;
            do {
                $body = [
                    'type' => 'temu.local.goods.list.retrieve',
                    'goodsSearchType' => 'ALL',
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $body['pageToken'] = $pageToken;
                }
                $signed = $this->generateSignValue($body);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signed);
                $data = $response->json();
                if (! $response->successful() || ! ($data['success'] ?? false)) {
                    break;
                }
                $list = $data['result']['goodsList'] ?? [];
                foreach ($list as $good) {
                    $gid = (string) ($good['goodsId'] ?? '');
                    if ($gid !== $goodsId) {
                        continue;
                    }
                    $goodsBasic = is_array($good['goodsBasic'] ?? null) ? $good['goodsBasic'] : [];
                    $desc = (string) ($goodsBasic['goodsDesc'] ?? $good['goodsDesc'] ?? '');

                    return trim($desc);
                }
                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu fetchCurrentTemuGoodsDesc failed', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);
        }

        return '';
    }

    protected function saveGoodsSummaryToTemuMetrics(string $sku, string $goodsSummary): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'sku')) {
                return false;
            }
            if (! Schema::hasColumn('temu_metrics', 'goods_summary')) {
                return false;
            }

            $update = ['goods_summary' => $goodsSummary];
            if (Schema::hasColumn('temu_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('temu_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('temu_metrics', 'created_at')) {
                DB::table('temu_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Temu saveGoodsSummaryToTemuMetrics failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Upload a remote image via Temu Open API so carousel/sku fields accept Temu-hosted URLs.
     *
     * @return array{success: bool, url: ?string, message: string}
     */
    public function uploadTemuImageFromUrl(string $imageUrl): array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '' || ! preg_match('#^https?://#i', $imageUrl)) {
            return ['success' => false, 'url' => null, 'message' => 'Invalid image URL.'];
        }

        $primary = trim((string) config('services.temu.image_upload_type', 'files/upload_image'));
        $extra = config('services.temu.image_upload_types');
        if (! is_array($extra)) {
            $extra = [];
        }
        $types = array_values(array_unique(array_filter(array_map('trim', array_merge(
            $primary !== '' ? [$primary] : [],
            $extra
        )))));
        if ($types === []) {
            $types = ['files/upload_image'];
        }

        $router = rtrim((string) config('services.temu.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
        $lastMsg = '';

        $postSigned = function (array $body) use ($router, &$lastMsg): ?string {
            try {
                $signedRequest = $this->generateSignValue($body);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->timeout(120)->post($router, $signedRequest);
                $data = $response->json() ?? [];
                $lastMsg = (string) ($data['errorMsg'] ?? $data['message'] ?? $response->body());

                if ($response->successful() && ($data['success'] ?? false)) {
                    $hosted = $this->extractTemuUploadedImageUrl($data);

                    return ($hosted !== null && $hosted !== '') ? $hosted : null;
                }
            } catch (\Throwable $e) {
                $lastMsg = $e->getMessage();
            }

            return null;
        };

        $tryBase64 = function () use ($imageUrl, $types, $postSigned): ?array {
            if (! config('services.temu.image_upload_try_base64', true)) {
                return null;
            }
            try {
                $imgResp = Http::withoutVerifying()->timeout(90)->get($imageUrl);
                if (! $imgResp->successful()) {
                    return null;
                }
                $bytes = $imgResp->body();
                $b64 = base64_encode($bytes);
                $mime = $imgResp->header('Content-Type') ?: 'image/jpeg';
                foreach ($types as $type) {
                    if ($type === '') {
                        continue;
                    }
                    $baseBodies = [
                        ['type' => $type, 'image' => $b64],
                        ['type' => $type, 'imageBase64' => $b64],
                        ['type' => $type, 'base64' => $b64],
                        ['type' => $type, 'fileBase64' => $b64],
                        ['type' => $type, 'content' => $b64, 'mimeType' => $mime],
                    ];
                    foreach ($baseBodies as $body) {
                        $hosted = $postSigned($body);
                        if ($hosted !== null) {
                            return ['success' => true, 'url' => $hosted, 'message' => 'OK (base64)'];
                        }
                    }
                }
            } catch (\Throwable) {
            }

            return null;
        };

        $tryUrl = function () use ($imageUrl, $types, $postSigned): ?array {
            foreach ($types as $type) {
                if ($type === '') {
                    continue;
                }

                $urlVariants = [
                    ['type' => $type, 'fileUrl' => $imageUrl],
                    ['type' => $type, 'url' => $imageUrl],
                    ['type' => $type, 'imageUrl' => $imageUrl],
                    ['type' => $type, 'imgUrl' => $imageUrl],
                    ['type' => $type, 'image_url' => $imageUrl],
                ];

                foreach ($urlVariants as $body) {
                    $hosted = $postSigned($body);
                    if ($hosted !== null) {
                        return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
                    }
                }
            }

            return null;
        };

        $preferBase64 = config('services.temu.image_upload_prefer_base64', true);
        $attempts = $preferBase64 ? [$tryBase64, $tryUrl] : [$tryUrl, $tryBase64];
        foreach ($attempts as $attempt) {
            $result = $attempt();
            if (is_array($result)) {
                return $result;
            }
        }

        $message = $lastMsg !== '' ? $lastMsg : 'Temu image upload failed for all configured types.';
        if ($this->isTemuIpWhitelistError($message)) {
            $message = $this->temuIpWhitelistHelpMessage($message);
        }

        return ['success' => false, 'url' => null, 'message' => $message];
    }

    /**
     * Upload a remote video via Temu Open API so goodsBasic video fields accept Temu-hosted URLs.
     *
     * @return array{success: bool, url: ?string, message: string}
     */
    public function uploadTemuVideoFromUrl(string $videoUrl): array
    {
        $videoUrl = trim($videoUrl);
        if ($videoUrl === '' || ! preg_match('#^https?://#i', $videoUrl)) {
            return ['success' => false, 'url' => null, 'message' => 'Invalid video URL.'];
        }

        $primary = trim((string) config('services.temu.video_upload_type', 'files/upload_video'));
        $extra = config('services.temu.video_upload_types');
        if (! is_array($extra)) {
            $extra = [];
        }
        $types = array_values(array_unique(array_filter(array_map('trim', array_merge(
            $primary !== '' ? [$primary] : [],
            $extra
        )))));
        if ($types === []) {
            $types = ['files/upload_video'];
        }

        $router = rtrim((string) config('services.temu.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
        $lastMsg = '';

        $postSigned = function (array $body) use ($router, &$lastMsg): ?string {
            try {
                $signedRequest = $this->generateSignValue($body);
                $request = Http::withHeaders(['Content-Type' => 'application/json']);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                $response = $request->timeout(300)->post($router, $signedRequest);
                $data = $response->json() ?? [];
                $lastMsg = (string) ($data['errorMsg'] ?? $data['message'] ?? $response->body());

                if ($response->successful() && ($data['success'] ?? false)) {
                    $hosted = $this->extractTemuUploadedImageUrl($data);

                    return ($hosted !== null && $hosted !== '') ? $hosted : null;
                }
            } catch (\Throwable $e) {
                $lastMsg = $e->getMessage();
            }

            return null;
        };

        $tryBase64 = function () use ($videoUrl, $types, $postSigned): ?array {
            if (! config('services.temu.video_upload_try_base64', true)) {
                return null;
            }
            try {
                $vidResp = Http::withoutVerifying()->timeout(180)->get($videoUrl);
                if (! $vidResp->successful()) {
                    return null;
                }
                $bytes = $vidResp->body();
                $b64 = base64_encode($bytes);
                $mime = $vidResp->header('Content-Type') ?: 'video/mp4';
                foreach ($types as $type) {
                    if ($type === '') {
                        continue;
                    }
                    $baseBodies = [
                        ['type' => $type, 'video' => $b64],
                        ['type' => $type, 'videoBase64' => $b64],
                        ['type' => $type, 'base64' => $b64],
                        ['type' => $type, 'fileBase64' => $b64],
                        ['type' => $type, 'content' => $b64, 'mimeType' => $mime],
                    ];
                    foreach ($baseBodies as $body) {
                        $hosted = $postSigned($body);
                        if ($hosted !== null) {
                            return ['success' => true, 'url' => $hosted, 'message' => 'OK (base64)'];
                        }
                    }
                }
            } catch (\Throwable) {
            }

            return null;
        };

        $tryUrl = function () use ($videoUrl, $types, $postSigned): ?array {
            foreach ($types as $type) {
                if ($type === '') {
                    continue;
                }

                $urlVariants = [
                    ['type' => $type, 'url' => $videoUrl],
                    ['type' => $type, 'videoUrl' => $videoUrl],
                    ['type' => $type, 'video_url' => $videoUrl],
                ];

                foreach ($urlVariants as $body) {
                    $hosted = $postSigned($body);
                    if ($hosted !== null) {
                        return ['success' => true, 'url' => $hosted, 'message' => 'OK'];
                    }
                }
            }

            return null;
        };

        $preferBase64 = config('services.temu.video_upload_prefer_base64', false);
        $attempts = $preferBase64 ? [$tryBase64, $tryUrl] : [$tryUrl, $tryBase64];
        foreach ($attempts as $attempt) {
            $result = $attempt();
            if (is_array($result)) {
                return $result;
            }
        }

        $message = $lastMsg !== '' ? $lastMsg : 'Temu video upload failed for all configured types.';
        if ($this->isTemuIpWhitelistError($message)) {
            $message = $this->temuIpWhitelistHelpMessage($message);
        }

        return ['success' => false, 'url' => null, 'message' => $message];
    }

    /**
     * @param  list<string>  $sourceUrls
     * @return array{success: bool, urls: list<string>, message: string}
     */
    private function uploadTemuVideosFromSourceUrls(array $sourceUrls): array
    {
        $temuUrls = [];
        $errors = [];
        foreach ($sourceUrls as $i => $u) {
            $up = $this->uploadTemuVideoFromUrl($u);
            if ($up['success'] ?? false) {
                $temuUrls[] = (string) ($up['url'] ?? '');
            } else {
                $errors[] = 'Video '.($i + 1).': '.($up['message'] ?? 'upload failed');
            }
        }
        $temuUrls = array_values(array_filter($temuUrls, fn ($s) => $s !== ''));
        if ($temuUrls === []) {
            return ['success' => false, 'urls' => [], 'message' => implode(' | ', $errors) ?: 'Temu video upload failed.'];
        }

        $msg = 'Uploaded '.count($temuUrls).' video(s) to Temu.';
        if ($errors !== []) {
            $msg .= ' Partial failures: '.implode(' | ', $errors);
        }

        return ['success' => true, 'urls' => $temuUrls, 'message' => $msg];
    }

    private function isTemuIpWhitelistError(string $message): bool
    {
        return stripos($message, 'NOT_IN_IP_WHITE_LIST') !== false
            || stripos($message, 'IP_WHITE') !== false;
    }

    private function temuIpWhitelistHelpMessage(string $apiMessage): string
    {
        return trim($apiMessage).' — Temu Open API calls must come from a whitelisted IP. '
            .'In Temu Partner Platform (partner.temu.com), add your server public IP to the app IP whitelist. '
            .'Local Image Master pushes use your PC IP; production pushes use the droplet IP (inventory.5coremanagement.com).';
    }

    /**
     * @param  list<string>  $sourceUrls
     * @return array{success: bool, urls: list<string>, message: string}
     */
    private function uploadTemuGalleryImagesFromSourceUrls(array $sourceUrls): array
    {
        $temuUrls = [];
        $errors = [];
        foreach ($sourceUrls as $i => $u) {
            $up = $this->uploadTemuImageFromUrl($u);
            if ($up['success'] && ! empty($up['url'])) {
                $temuUrls[] = $up['url'];
            } else {
                $msg = trim((string) ($up['message'] ?? 'upload failed'));
                $errors[] = 'Image '.($i + 1).': '.$msg;
                if ($this->isTemuIpWhitelistError($msg)) {
                    return [
                        'success' => false,
                        'urls' => [],
                        'message' => $this->temuIpWhitelistHelpMessage($msg),
                    ];
                }
            }
            usleep(100000);
        }

        if ($temuUrls === []) {
            return [
                'success' => false,
                'urls' => [],
                'message' => 'Temu image upload failed.'.($errors !== [] ? ' '.implode('; ', $errors) : ''),
            ];
        }

        if (count($temuUrls) < count($sourceUrls)) {
            return [
                'success' => false,
                'urls' => $temuUrls,
                'message' => 'Only '.count($temuUrls).'/'.count($sourceUrls).' images uploaded to Temu. '.implode('; ', $errors),
            ];
        }

        return ['success' => true, 'urls' => $temuUrls, 'message' => 'OK'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractTemuUploadedImageUrl(array $data): ?string
    {
        $r = $data['result'] ?? null;
        if (is_string($r) && preg_match('#^https?://#i', trim($r))) {
            return trim($r);
        }
        if (! is_array($r)) {
            $r = $data;
        }
        foreach (['url', 'imageUrl', 'image_url', 'fileUrl', 'file_url', 'cdnUrl', 'picUrl'] as $k) {
            if (! empty($r[$k]) && is_string($r[$k]) && preg_match('#^https?://#i', trim($r[$k]))) {
                return trim($r[$k]);
            }
        }
        if (! empty($r['image']['url']) && is_string($r['image']['url'])) {
            return trim($r['image']['url']);
        }
        if (! empty($r['data']['url']) && is_string($r['data']['url'])) {
            return trim($r['data']['url']);
        }

        return null;
    }

    private function saveTemuBulletAndDescToMetrics(string $sku, string $goodsSummary, string $goodsDesc): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'sku')) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('temu_metrics', 'goods_summary')) {
                $update['goods_summary'] = $goodsSummary;
            }
            if (Schema::hasColumn('temu_metrics', 'goods_desc')) {
                $update['goods_desc'] = trim($goodsDesc) !== '' ? $goodsDesc : null;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('temu_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('temu_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('temu_metrics', 'created_at')) {
                DB::table('temu_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Temu saveTemuBulletAndDescToMetrics failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Long-form description via `goodsDesc` (text only — images go to carouselImageUrlList after upload).
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        if (trim($identifier) === '' || trim($description) === '') {
            return ['success' => false, 'message' => 'SKU (or goods_id) and description are required.'];
        }

        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is empty.'];
        }

        $field = config('services.temu.goods_desc_field', 'goodsDesc');

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $skuForImages = (string) ($resolved['sku'] ?? $identifier);

        $built = DescriptionWithImagesFormatter::buildHtmlWithImages(
            $description,
            $identifier,
            $skuForImages,
            'Product Image',
            12,
            $imageUrls
        );
        $descriptionForGoodsDesc = $built['text_html'];

        $res = $this->pushTemuGoodsBasicField(
            $identifier,
            $descriptionForGoodsDesc,
            $field,
            'Temu product description updated.',
            'SKU (or goods_id) and description are required.',
            'Temu updateDescription'
        );

        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $raw = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $raw = array_slice($raw, 0, 12);
        if ($raw === []) {
            return $res;
        }

        $uploaded = $this->uploadTemuGalleryImagesFromSourceUrls($raw);
        if (! ($uploaded['success'] ?? false)) {
            Log::warning('Temu updateDescription: no images uploaded; carousel unchanged', [
                'sku' => $skuForImages,
                'message' => $uploaded['message'] ?? '',
            ]);

            return [
                'success' => true,
                'message' => ($res['message'] ?? 'Temu product description updated.').' Image upload failed — carousel not updated.',
            ];
        }

        $imgRes = $this->updateListingImages($identifier, $uploaded['urls']);
        if (! ($imgRes['success'] ?? false)) {
            return [
                'success' => true,
                'message' => ($res['message'] ?? 'Temu product description updated.').' Carousel: '.($imgRes['message'] ?? 'failed'),
            ];
        }

        return [
            'success' => true,
            'message' => ($res['message'] ?? 'Temu product description updated.').' Carousel images updated.',
        ];
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        return $this->updateDescription($identifier, $description, $imageUrls);
    }

    /**
     * Partial goods update: carousel / gallery image URLs (goodsBasic + sku images when skuList is used).
     *
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingImages(string $identifier, array $imageUrls): array
    {
        $urls = array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 12);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or goods_id) and image URLs are required.'];
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = $resolved['sku'];
        $goodsId = $resolved['goods_id'] ?? $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            return ['success' => false, 'message' => 'goodsId not found for SKU. Sync Temu metrics or TemuPricing.'];
        }

        $skuInfo = $this->getSkuInfoForGoodsAndSku($goodsId, $sku);
        $apiType = config('services.temu.goods_update_type', 'bg.local.goods.partial.update');
        $url = 'https://openapi-b-us.temu.com/openapi/router';
        $skuListField = config('services.temu.update_sku_list_field', 'skuList');
        $goodsBasicField = config('services.temu.goods_basic_field', 'goodsBasic');
        $imageField = config('services.temu.goods_image_urls_field', 'carouselImageUrlList');
        $skuIdField = config('services.temu.sku_id_field', 'skuId');
        $skuCodeField = config('services.temu.sku_code_field', 'outSkuSn');

        $requestBody = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => [
                $imageField => $urls,
            ],
        ];

        $price = $this->getProductPrice($sku);
        $dimensions = $this->getProductDimensions($sku);

        if ($skuInfo !== null && isset($skuInfo['skuId'])) {
            $requestBody[$skuListField] = [[
                $skuIdField => (int) $skuInfo['skuId'],
                $skuCodeField => $sku,
                'listPrice' => [
                    'amount' => (string) ($price ?? 1.00),
                    'currency' => 'USD',
                ],
                'listPriceType' => 0,
                'weight' => $dimensions['weight'],
                'length' => $dimensions['length'],
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'weightUnit' => $dimensions['weightUnit'],
                'volumeUnit' => $dimensions['volumeUnit'],
                'images' => $urls,
            ]];
        }

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->post($url, $signedRequest);
            $data = $response->json();
            if ($response->successful() && ($data['success'] ?? false)) {
                return ['success' => true, 'message' => 'Temu listing images updated.'];
            }

            return ['success' => false, 'message' => $this->formatTemuApiErrorMessage((string) ($data['errorMsg'] ?? $data['message'] ?? $response->body()))];
        } catch (\Throwable $e) {
            Log::error('Temu updateListingImages', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Image Master compatibility method: upload to Temu, push carousel, persist source URLs in temu_metrics.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if ($images === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        $uploaded = $this->uploadTemuGalleryImagesFromSourceUrls($images);
        if (! ($uploaded['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($uploaded['message'] ?? 'Temu image upload failed.')];
        }

        $res = $this->updateListingImages($identifier, $uploaded['urls']);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = trim((string) ($resolved['sku'] ?? $identifier));
        $saved = $this->saveImageUrlsToTemuMetrics($sku, $images);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Temu listing images updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $images;

        return $res;
    }

    private function formatTemuApiErrorMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Temu API error.';
        }
        if ($this->isTemuIpWhitelistError($message)) {
            return $this->temuIpWhitelistHelpMessage($message);
        }

        return $message;
    }

    /**
     * @param  list<string>  $images
     */
    private function saveImageUrlsToTemuMetrics(string $sku, array $images): bool
    {
        try {
            if (! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'sku')) {
                return false;
            }
            $payload = json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn('temu_metrics', 'image_urls')) {
                $update['image_urls'] = $payload;
            }
            if (Schema::hasColumn('temu_metrics', 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn('temu_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('temu_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('temu_metrics', 'created_at')) {
                DB::table('temu_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Temu image_urls save failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Partial goods update: product video URL(s) in goodsBasic.
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string}
     */
    public function updateListingVideos(string $identifier, array $videoUrls): array
    {
        $urls = array_values(array_filter(array_map('trim', $videoUrls), fn ($s) => $s !== ''));
        $urls = array_slice($urls, 0, 5);
        if (trim($identifier) === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU (or goods_id) and video URLs are required.'];
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = $resolved['sku'];
        $goodsId = $resolved['goods_id'] ?? $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            return ['success' => false, 'message' => 'goodsId not found for SKU. Sync Temu metrics or TemuPricing.'];
        }

        $apiType = config('services.temu.goods_update_type', 'bg.local.goods.partial.update');
        $url = 'https://openapi-b-us.temu.com/openapi/router';
        $goodsBasicField = config('services.temu.goods_basic_field', 'goodsBasic');
        $videoField = config('services.temu.goods_video_urls_field', 'productVideoUrlList');

        $requestBody = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => [
                $videoField => $urls,
                'mainVideoUrl' => $urls[0],
            ],
        ];

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->post($url, $signedRequest);
            $data = $response->json();
            if ($response->successful() && ($data['success'] ?? false)) {
                return ['success' => true, 'message' => 'Temu listing videos updated.'];
            }

            return ['success' => false, 'message' => $this->formatTemuApiErrorMessage((string) ($data['errorMsg'] ?? $data['message'] ?? $response->body()))];
        } catch (\Throwable $e) {
            Log::error('Temu updateListingVideos', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Video Master compatibility method: upload to Temu, push video field, persist source URLs in temu_metrics.
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $uploaded = $this->uploadTemuVideosFromSourceUrls($videos);
        if (! ($uploaded['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($uploaded['message'] ?? 'Temu video upload failed.')];
        }

        $res = $this->updateListingVideos($identifier, $uploaded['urls']);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = trim((string) ($resolved['sku'] ?? $identifier));
        $saved = $this->saveVideoUrlsToMetricsRow('temu_metrics', $sku, $videos);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Temu listing videos updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $videos;

        return $res;
    }

    public function isConfigured(): bool
    {
        $appKey = trim((string) (config('services.temu.app_key') ?? ''));
        $secret = trim((string) (config('services.temu.secret_key') ?? ''));
        $token = trim((string) (config('services.temu.access_token') ?? ''));

        return $appKey !== '' && $secret !== '' && $token !== '';
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu API credentials missing. Set TEMU_APP_KEY, TEMU_SECRET_KEY, and TEMU_ACCESS_TOKEN in .env.',
            ];
        }

        $requestBody = [
            'type' => 'bg.local.goods.list.query',
            'goodsSearchType' => 1,
            'goodsStatusFilterType' => 1,
            'pageSize' => 5,
            'pageNumber' => 1,
        ];

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $url = rtrim((string) config('services.temu.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->timeout(30)->post($url, $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                $items = $data['result']['goodsList'] ?? [];
                $total = (int) ($data['result']['total'] ?? count($items));

                return [
                    'success' => true,
                    'message' => 'Connected to Temu Open API. Sample page returned '.count($items)." item(s); total reported: {$total}.",
                    'sample_count' => count($items),
                ];
            }

            $errorMsg = (string) ($data['errorMsg'] ?? $response->body() ?: 'Unknown error');

            return [
                'success' => false,
                'message' => trim(($data['errorCode'] ?? $response->status()).': '.$errorMsg),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, response?: mixed}
     */
    public function updateSkuStockTarget(string|int $goodsId, string|int $skuId, int $stockTarget): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Temu API credentials missing.'];
        }

        $requestBody = [
            'type' => 'bg.local.goods.stock.edit',
            'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
            'skuStockTargetList' => [[
                'skuId' => is_numeric($skuId) ? (int) $skuId : $skuId,
                'stockTarget' => max(0, $stockTarget),
            ]],
        ];

        return $this->postStockEdit($requestBody);
    }

    /**
     * @param  array<int, array{goods_id: string|int, sku_id?: string|int|null, sku?: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, message?: string}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        if ($items === []) {
            return ['pushed' => 0, 'failed' => 0, 'message' => 'No items to push.'];
        }

        if (! $this->isConfigured()) {
            return ['pushed' => 0, 'failed' => count($items), 'message' => 'Temu API credentials missing.'];
        }

        $byGoods = [];
        foreach ($items as $item) {
            $goodsId = trim((string) ($item['goods_id'] ?? ''));
            if ($goodsId === '') {
                continue;
            }
            $skuId = $item['sku_id'] ?? null;
            if ($skuId === null || $skuId === '') {
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku !== '') {
                    $skuId = $this->getSkuIdBySku($sku);
                }
            }
            if ($skuId === null || $skuId === '') {
                continue;
            }
            $byGoods[$goodsId][] = [
                'skuId' => is_numeric($skuId) ? (int) $skuId : $skuId,
                'stockTarget' => max(0, (int) ($item['quantity'] ?? 0)),
            ];
        }

        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach ($byGoods as $goodsId => $skuStockTargetList) {
            $requestBody = [
                'type' => 'bg.local.goods.stock.edit',
                'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
                'skuStockTargetList' => $skuStockTargetList,
            ];
            $result = $this->postStockEdit($requestBody);
            if ($result['success'] ?? false) {
                $pushed += count($skuStockTargetList);
            } else {
                $failed += count($skuStockTargetList);
                $errors[] = $result['message'] ?? 'Stock edit failed';
            }
            usleep(100000);
        }

        return [
            'pushed' => $pushed,
            'failed' => $failed,
            'message' => $errors === []
                ? "Pushed stock for {$pushed} SKU(s) to Temu."
                : implode('; ', array_slice($errors, 0, 3)),
        ];
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @return array{success: bool, message: string, response?: mixed}
     */
    protected function postStockEdit(array $requestBody): array
    {
        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $url = $this->openApiRouterUrl();
            $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(60);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->post($url, $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Stock updated on Temu.',
                    'response' => $data['result'] ?? $data,
                ];
            }

            return [
                'success' => false,
                'message' => trim(($data['errorCode'] ?? $response->status()).': '.($data['errorMsg'] ?? $response->body())),
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function openApiRouterUrl(): string
    {
        return rtrim((string) config('services.temu.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
    }

    /**
     * Generic Temu Open API call (signed POST to router).
     *
     * @param  array<string, mixed>  $params
     * @return array{success: bool, errorCode?: mixed, errorMsg?: string, message?: string, result?: mixed, raw?: mixed}
     */
    public function callOpenApi(string $type, array $params = [], int $timeout = 60): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu API credentials missing.',
                'errorMsg' => 'Temu API credentials missing.',
                'result' => null,
            ];
        }

        $requestBody = array_merge(['type' => $type], $params);

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(max(10, $timeout));
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->post($this->openApiRouterUrl(), $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'result' => $data['result'] ?? $data,
                    'raw' => $data,
                ];
            }

            $errorMsg = (string) ($data['errorMsg'] ?? $response->body() ?: 'Unknown Temu API error');

            return [
                'success' => false,
                'errorCode' => $data['errorCode'] ?? $response->status(),
                'errorMsg' => $errorMsg,
                'message' => trim(($data['errorCode'] ?? $response->status()).': '.$errorMsg),
                'result' => $data['result'] ?? null,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('TemuApiService::callOpenApi failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errorMsg' => $e->getMessage(),
                'result' => null,
            ];
        }
    }

    /**
     * @return array{success: bool, companies: list<array{id:int,name:string,brand:?string}>, message?: string}
     */
    public function listLogisticsCompanies(?int $regionId = null): array
    {
        $params = [];
        if ($regionId !== null && $regionId > 0) {
            $params['regionId'] = $regionId;
        }

        $res = $this->callOpenApi('bg.logistics.companies.get', $params);
        if (empty($res['success'])) {
            return [
                'success' => false,
                'companies' => [],
                'message' => (string) ($res['message'] ?? $res['errorMsg'] ?? 'Failed to list logistics companies'),
            ];
        }

        $raw = $res['result'] ?? [];
        $list = [];
        if (is_array($raw)) {
            if (array_is_list($raw)) {
                $list = $raw;
            } elseif (isset($raw['companyList']) && is_array($raw['companyList'])) {
                $list = $raw['companyList'];
            } elseif (isset($raw['logisticsCompanies']) && is_array($raw['logisticsCompanies'])) {
                $list = $raw['logisticsCompanies'];
            }
        }

        $companies = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = $row['logisticsServiceProviderId']
                ?? $row['carrierId']
                ?? $row['shipCompanyId']
                ?? $row['id']
                ?? null;
            $name = trim((string) (
                $row['logisticsBrandName']
                ?? $row['logisticsServiceProviderName']
                ?? $row['carrierName']
                ?? $row['name']
                ?? ''
            ));
            if (! is_numeric($id) || $name === '') {
                continue;
            }
            $companies[] = [
                'id' => (int) $id,
                'name' => $name,
                'brand' => isset($row['logisticsBrandName']) ? trim((string) $row['logisticsBrandName']) : null,
            ];
        }

        return ['success' => true, 'companies' => $companies];
    }

    /**
     * @return array{success: bool, warehouses: list<array{id:string,name:string,default:bool}>, message?: string}
     */
    public function listWarehouses(): array
    {
        $res = $this->callOpenApi('bg.logistics.warehouse.list.get', [
            'returnEnableBuyShippingLabelOnly' => false,
        ]);
        if (empty($res['success'])) {
            return [
                'success' => false,
                'warehouses' => [],
                'message' => (string) ($res['message'] ?? $res['errorMsg'] ?? 'Failed to list warehouses'),
            ];
        }

        $raw = $res['result'] ?? [];
        $list = [];
        if (is_array($raw)) {
            $list = is_array($raw['warehouseList'] ?? null) ? $raw['warehouseList'] : (array_is_list($raw) ? $raw : []);
        }

        $warehouses = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['warehouseId'] ?? $row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $warehouses[] = [
                'id' => $id,
                'name' => trim((string) ($row['warehouseName'] ?? $row['name'] ?? $id)),
                'default' => (bool) ($row['defaultWarehouse'] ?? false),
            ];
        }

        return ['success' => true, 'warehouses' => $warehouses];
    }

    /**
     * Self-fulfilled shipment confirm (Shopify/own label → Temu shipped).
     * API: bg.logistics.shipment.v2.confirm
     *
     * @param  list<array{parentOrderSn:string,orderSn:string,quantity:int,goodsId?:int|null,skuId?:int|null}>  $orderSendInfoList
     * @return array{success: bool, message: string, carrier_id?: int|null, result?: mixed}
     */
    public function confirmSelfShipment(
        string $trackingNumber,
        int $carrierId,
        string $warehouseId,
        array $orderSendInfoList,
        int $sendType = 0
    ): array {
        $trackingNumber = preg_replace('/[\s\-%#]+/', '', trim($trackingNumber)) ?? trim($trackingNumber);
        $warehouseId = trim($warehouseId);

        if ($trackingNumber === '' || $carrierId <= 0 || $warehouseId === '' || $orderSendInfoList === []) {
            return [
                'success' => false,
                'message' => 'trackingNumber, carrierId, warehouseId, and order lines are required.',
            ];
        }

        $sendRequest = [
            'carrierId' => $carrierId,
            'trackingNumber' => $trackingNumber,
            'selfShippingWarehouseId' => $warehouseId,
            'orderSendInfoList' => array_values($orderSendInfoList),
        ];

        $res = $this->callOpenApi('bg.logistics.shipment.v2.confirm', [
            'sendType' => $sendType,
            'sendRequestList' => [$sendRequest],
        ]);

        if (! empty($res['success'])) {
            return [
                'success' => true,
                'message' => "Temu shipment confirmed with tracking {$trackingNumber}.",
                'carrier_id' => $carrierId,
                'result' => $res['result'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) ($res['message'] ?? $res['errorMsg'] ?? 'Temu shipment confirm failed'),
            'carrier_id' => $carrierId,
            'result' => $res['result'] ?? null,
            'errorCode' => $res['errorCode'] ?? null,
        ];
    }

    /**
     * Fetch shipment tracking/carrier for a parent order from Temu OpenAPI.
     *
     * Working path (US semi):
     *   1) bg.order.detail.v2.get → packageSn from orderList[].packageSnInfo
     *   2) bg.logistics.shipment.result.get → trackingNumber + shippingCompanyName
     *
     * Legacy bg.logistics.shipment.get is deprecated (3000037); v2.get often returns BUSINESS_SERVICE_ERROR.
     *
     * @return array{
     *   success: bool,
     *   tracking_number: ?string,
     *   carrier: ?string,
     *   carrier_id: ?int,
     *   package_sn: ?string,
     *   shipments: list<array{tracking_number:?string,carrier:?string,carrier_id:?int,package_sn:?string,order_sn:?string}>,
     *   message?: string,
     *   raw?: mixed
     * }
     */
    public function getShipmentInfo(string $parentOrderSn, ?string $orderSn = null): array
    {
        $parentOrderSn = trim($parentOrderSn);
        $orderSn = $orderSn !== null ? trim($orderSn) : '';
        if ($parentOrderSn === '') {
            return [
                'success' => false,
                'tracking_number' => null,
                'carrier' => null,
                'carrier_id' => null,
                'package_sn' => null,
                'shipments' => [],
                'message' => 'parentOrderSn is required.',
            ];
        }

        $detail = $this->callOpenApi('bg.order.detail.v2.get', ['parentOrderSn' => $parentOrderSn]);
        if (empty($detail['success'])) {
            return [
                'success' => false,
                'tracking_number' => null,
                'carrier' => null,
                'carrier_id' => null,
                'package_sn' => null,
                'shipments' => [],
                'message' => (string) ($detail['message'] ?? $detail['errorMsg'] ?? 'bg.order.detail.v2.get failed'),
                'raw' => $detail['result'] ?? $detail['raw'] ?? null,
            ];
        }

        $detailResult = is_array($detail['result'] ?? null) ? $detail['result'] : [];
        $packageMeta = $this->extractPackageSnListFromOrderDetail($detailResult, $orderSn !== '' ? $orderSn : null);
        $packageSnList = $packageMeta['package_sns'];
        if ($packageSnList === []) {
            // Detail itself sometimes embeds tracking (rare) — try parse before failing.
            $fromDetail = $this->parseShipmentInfoResult($detailResult);
            if (($fromDetail['tracking_number'] ?? null) !== null) {
                return array_merge($fromDetail, [
                    'success' => true,
                    'message' => 'Tracking found on bg.order.detail.v2.get',
                    'raw' => $detailResult,
                ]);
            }

            return [
                'success' => false,
                'tracking_number' => null,
                'carrier' => null,
                'carrier_id' => null,
                'package_sn' => null,
                'shipments' => [],
                'message' => 'No packageSn on Temu order detail yet (not labeled / not shipped).',
                'raw' => $detailResult,
            ];
        }

        $resultRes = $this->callOpenApi('bg.logistics.shipment.result.get', [
            'packageSnList' => array_values($packageSnList),
        ]);
        if (empty($resultRes['success'])) {
            return [
                'success' => false,
                'tracking_number' => null,
                'carrier' => null,
                'carrier_id' => null,
                'package_sn' => $packageSnList[0] ?? null,
                'shipments' => [],
                'message' => (string) ($resultRes['message'] ?? $resultRes['errorMsg'] ?? 'bg.logistics.shipment.result.get failed'),
                'raw' => [
                    'detail' => $detailResult,
                    'shipment_result' => $resultRes['result'] ?? $resultRes['raw'] ?? null,
                ],
            ];
        }

        $parsed = $this->parseShipmentInfoResult(is_array($resultRes['result'] ?? null) ? $resultRes['result'] : []);
        // Attach order_sn hints from detail when shipment result omits them.
        if (($parsed['shipments'] ?? []) !== [] && ($packageMeta['by_package'] ?? []) !== []) {
            foreach ($parsed['shipments'] as $i => $ship) {
                $psn = (string) ($ship['package_sn'] ?? '');
                if ($psn !== '' && ($ship['order_sn'] ?? null) === null && isset($packageMeta['by_package'][$psn])) {
                    $parsed['shipments'][$i]['order_sn'] = $packageMeta['by_package'][$psn];
                }
            }
        }

        if (($parsed['tracking_number'] ?? null) === null && ($parsed['shipments'] ?? []) === []) {
            return [
                'success' => false,
                'tracking_number' => null,
                'carrier' => null,
                'carrier_id' => null,
                'package_sn' => $packageSnList[0] ?? null,
                'shipments' => [],
                'message' => 'Shipment result returned no trackingNumber yet.',
                'raw' => $resultRes['result'] ?? null,
            ];
        }

        if (($parsed['package_sn'] ?? null) === null) {
            $parsed['package_sn'] = $packageSnList[0] ?? null;
        }

        return array_merge($parsed, [
            'success' => true,
            'message' => 'Shipment loaded via bg.order.detail.v2.get + bg.logistics.shipment.result.get',
            'raw' => $resultRes['result'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $detailResult
     * @return array{package_sns: list<string>, by_package: array<string, string>}
     */
    protected function extractPackageSnListFromOrderDetail(array $detailResult, ?string $orderSn = null): array
    {
        $packageSns = [];
        $byPackage = [];
        $orderList = $detailResult['orderList'] ?? $detailResult['order_list'] ?? null;
        if (! is_array($orderList)) {
            $orderList = [];
        }

        foreach ($orderList as $order) {
            if (! is_array($order)) {
                continue;
            }
            $osn = trim((string) ($order['orderSn'] ?? $order['order_sn'] ?? ''));
            if ($orderSn !== null && $orderSn !== '' && $osn !== '' && strcasecmp($osn, $orderSn) !== 0) {
                continue;
            }
            $infoList = $order['packageSnInfo'] ?? $order['package_sn_info'] ?? null;
            if (! is_array($infoList)) {
                continue;
            }
            foreach ($infoList as $info) {
                if (! is_array($info)) {
                    continue;
                }
                $psn = trim((string) ($info['packageSn'] ?? $info['package_sn'] ?? $info['mainPackageSn'] ?? ''));
                if ($psn === '') {
                    continue;
                }
                $packageSns[$psn] = true;
                if ($osn !== '') {
                    $byPackage[$psn] = $osn;
                }
            }
        }

        return [
            'package_sns' => array_keys($packageSns),
            'by_package' => $byPackage,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{
     *   tracking_number: ?string,
     *   carrier: ?string,
     *   carrier_id: ?int,
     *   package_sn: ?string,
     *   shipments: list<array{tracking_number:?string,carrier:?string,carrier_id:?int,package_sn:?string,order_sn:?string}>
     * }
     */
    public function parseShipmentInfoResult(array $raw): array
    {
        $rows = [];
        $queue = [$raw];
        $seen = 0;
        while ($queue !== [] && $seen < 200) {
            $seen++;
            $node = array_shift($queue);
            if (! is_array($node)) {
                continue;
            }

            $tracking = $this->pickShipmentScalar($node, [
                'trackingNumber', 'tracking_number', 'mailNo', 'mail_no',
                'logisticsNo', 'logistics_no', 'waybillNo', 'waybill_no',
            ]);
            $carrier = $this->pickShipmentScalar($node, [
                'carrierName', 'carrier_name', 'shippingCompanyName', 'shipping_company_name',
                'logisticsBrandName', 'logistics_brand_name', 'logisticsServiceProviderName',
                'shipCompanyName', 'ship_company_name', 'carrier',
            ]);
            // shippingInfoList[].shippingCompanyName (shipment.result.get)
            if ($carrier === null && isset($node['shippingInfoList']) && is_array($node['shippingInfoList'])) {
                foreach ($node['shippingInfoList'] as $si) {
                    if (! is_array($si)) {
                        continue;
                    }
                    $carrier = $this->pickShipmentScalar($si, [
                        'shippingCompanyName', 'shipping_company_name', 'carrierName', 'carrier',
                    ]);
                    if ($carrier === null) {
                        $trackingFromSi = $this->pickShipmentScalar($si, ['trackingNumber', 'tracking_number']);
                        if ($tracking === null && $trackingFromSi !== null) {
                            $tracking = $trackingFromSi;
                        }
                    }
                    if ($carrier !== null) {
                        break;
                    }
                }
            }
            $carrierIdRaw = $node['carrierId'] ?? $node['carrier_id'] ?? $node['shipCompanyId']
                ?? $node['ship_company_id'] ?? $node['logisticsServiceProviderId'] ?? null;
            $carrierId = is_numeric($carrierIdRaw) ? (int) $carrierIdRaw : null;
            $packageSn = $this->pickShipmentScalar($node, [
                'packageSn', 'package_sn', 'mainPackageSn', 'main_package_sn',
                'packageNumber', 'package_number',
            ]);
            $orderSn = $this->pickShipmentScalar($node, [
                'orderSn', 'order_sn',
            ]);
            if ($orderSn === null && isset($node['orderSendInfoList']) && is_array($node['orderSendInfoList'])) {
                foreach ($node['orderSendInfoList'] as $osi) {
                    if (! is_array($osi)) {
                        continue;
                    }
                    $orderSn = $this->pickShipmentScalar($osi, ['orderSn', 'order_sn']);
                    if ($orderSn !== null) {
                        break;
                    }
                }
            }

            if ($tracking !== null || $packageSn !== null) {
                $rows[] = [
                    'tracking_number' => $tracking,
                    'carrier' => $carrier,
                    'carrier_id' => $carrierId,
                    'package_sn' => $packageSn,
                    'order_sn' => $orderSn,
                ];
            }

            foreach ([
                'shipmentInfoDTO', 'shipmentInfoList', 'shippingInfoList', 'shipmentList', 'packageList',
                'packageInfoList', 'packageInfoResultList', 'sendRequestList',
                'orderSendInfoList', 'packageInfoResult', 'packageSnInfo', 'orderList', 'result',
            ] as $key) {
                if (! isset($node[$key]) || ! is_array($node[$key])) {
                    continue;
                }
                $child = $node[$key];
                if (array_is_list($child)) {
                    foreach ($child as $item) {
                        if (is_array($item)) {
                            $queue[] = $item;
                        }
                    }
                } else {
                    $queue[] = $child;
                }
            }

            // Also walk list-shaped roots.
            if (array_is_list($node)) {
                foreach ($node as $item) {
                    if (is_array($item)) {
                        $queue[] = $item;
                    }
                }
            }
        }

        $best = null;
        foreach ($rows as $row) {
            if (($row['tracking_number'] ?? null) !== null) {
                $best = $row;
                break;
            }
            if ($best === null) {
                $best = $row;
            }
        }

        return [
            'tracking_number' => $best['tracking_number'] ?? null,
            'carrier' => $best['carrier'] ?? null,
            'carrier_id' => $best['carrier_id'] ?? null,
            'package_sn' => $best['package_sn'] ?? null,
            'shipments' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    protected function pickShipmentScalar(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $val = $row[$key];
            if (is_scalar($val) && trim((string) $val) !== '') {
                return trim((string) $val);
            }
        }

        return null;
    }

    /**
     * Decrypt / fetch ship-to for a parent order.
     * Tries bg.order.decryptshippinginfo.get, then shippinginfo.v2 / shippinginfo.get.
     *
     * @return array{success: bool, address: array<string, mixed>, raw?: mixed, message?: string}
     */
    public function getOrderShippingAddress(string $parentOrderSn): array
    {
        $parentOrderSn = trim($parentOrderSn);
        if ($parentOrderSn === '') {
            return ['success' => false, 'address' => [], 'message' => 'parentOrderSn is required.'];
        }

        $lastMessage = 'No shipping address returned.';
        foreach ([
            'bg.order.decryptshippinginfo.get',
            'bg.order.shippinginfo.v2.get',
            'bg.order.shippinginfo.get',
        ] as $type) {
            $res = $this->callOpenApi($type, ['parentOrderSn' => $parentOrderSn]);
            if (empty($res['success'])) {
                $lastMessage = (string) ($res['message'] ?? $res['errorMsg'] ?? $lastMessage);

                continue;
            }

            $raw = is_array($res['result'] ?? null) ? $res['result'] : [];
            $address = $this->normalizeShippingAddressResult($raw);
            if ($address !== []) {
                return [
                    'success' => true,
                    'address' => $address,
                    'raw' => $raw,
                    'message' => 'Shipping address loaded via '.$type,
                ];
            }

            $lastMessage = 'Temu returned empty shipping address from '.$type;
        }

        return ['success' => false, 'address' => [], 'message' => $lastMessage];
    }

    /**
     * Normalize Temu shipping-info payloads into Shein-like receipt_address keys.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalizeShippingAddressResult(array $raw): array
    {
        $candidates = [$raw];
        foreach ([
            'shippingInfo', 'shipping_info', 'receiptAddress', 'receipt_address',
            'address', 'decryptShippingInfo', 'decrypt_shipping_info', 'result',
        ] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $candidates[] = $raw[$key];
            }
        }

        foreach ($candidates as $src) {
            if (! is_array($src)) {
                continue;
            }
            $pick = static function (array $row, array $keys): ?string {
                foreach ($keys as $key) {
                    if (! array_key_exists($key, $row)) {
                        continue;
                    }
                    $val = $row[$key];
                    if (is_scalar($val) && trim((string) $val) !== '') {
                        return trim((string) $val);
                    }
                }

                return null;
            };

            $address1 = $pick($src, [
                'addressLine1', 'addressLineOne', 'address1', 'address_line_1',
                'detailAddress', 'detail_address', 'mailAddress', 'mail_address', 'address',
            ]);
            if ($address1 === null) {
                continue;
            }

            $mapped = array_filter([
                'contact_person' => $pick($src, [
                    'receiptName', 'receiverName', 'receiver_name', 'contactPerson',
                    'contact_person', 'name', 'fullName', 'full_name',
                ]),
                'address' => $address1,
                'address2' => $pick($src, [
                    'addressLine2', 'addressLineTwo', 'address2', 'address_line_2',
                    'addressLine3', 'address3',
                ]),
                'city' => $pick($src, [
                    'regionName2', 'region_name_2', 'city', 'cityName', 'city_name',
                ]),
                'province' => $pick($src, [
                    'regionName1', 'region_name_1', 'state', 'stateName', 'province',
                    'provinceName', 'province_name',
                ]),
                'zip' => $pick($src, [
                    'postCode', 'post_code', 'mail', 'zipCode', 'zip_code', 'zip',
                ]),
                'country' => $pick($src, [
                    'regionName0', 'region_name_0', 'countryCode', 'country_code', 'country',
                ]),
                'country_name' => $pick($src, [
                    'countryName', 'country_name', 'regionName0', 'region_name_0',
                ]),
                'mobile_no' => $pick($src, [
                    'mobile', 'mobileNo', 'mobile_no', 'phone', 'phoneNumber', 'phone_number',
                ]),
                'phone_number' => $pick($src, [
                    'phone', 'phoneNumber', 'phone_number', 'mobile', 'mobileNo', 'mobile_no',
                ]),
                'email' => $pick($src, ['email', 'mail', 'buyerEmail', 'buyer_email']),
                'company' => $pick($src, ['company', 'companyName', 'company_name']),
            ], static fn ($v) => $v !== null && $v !== '');

            if ($mapped !== []) {
                return $mapped;
            }
        }

        return [];
    }
}
