<?php

namespace App\Services;

use App\Models\DobaDataView;
use App\Models\DobaMetric;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductStockMapping;
use App\Services\Support\VideoMasterMarketplaceMethods;
use App\Services\Support\SavesMarketplaceImageMetrics;

class DobaApiService
{
    use VideoMasterMarketplaceMethods;
    use SavesMarketplaceImageMetrics;
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://openapi.doba.com/api';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.doba.app_key'))
            && filled(config('services.doba.private_key'));
    }

    /**
     * One page of seller orders. Pass ordBusiId to look up a single marketplace order.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    public function querySellerOrderDetail(array $body, int $timeout = 12): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $timestamp = $this->getMillisecond();
        $content = $this->getContent($timestamp);
        try {
            $sign = $this->generateSignature($content);
        } catch (\Throwable $e) {
            Log::warning('Doba order query signature failed', ['error' => $e->getMessage()]);

            return [];
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(max(3, $timeout))
                ->connectTimeout(4)
                ->withHeaders([
                    'appKey' => config('services.doba.app_key'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl.'/seller/queryOrderDetail', $body);
        } catch (\Throwable $e) {
            Log::warning('Doba order query failed', ['error' => $e->getMessage()]);

            return [];
        }

        $json = $response->json();
        if (! is_array($json) || (string) ($json['responseCode'] ?? '') !== '000000') {
            return [];
        }

        $businessData = $json['businessData'] ?? null;
        if (! is_array($businessData)) {
            return [];
        }
        $status = $businessData['businessStatus'] ?? $businessData[0]['businessStatus'] ?? null;
        if ($status !== null && (string) $status !== '000000') {
            return [];
        }

        $rows = $businessData[0]['data'] ?? $businessData['data'] ?? [];
        if (! is_array($rows)) {
            return [];
        }
        if ($rows !== [] && ! array_is_list($rows)) {
            $rows = [$rows];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * Push available inventory for a Doba itemNo (best-effort across known endpoints).
     *
     * @return array{success: bool, message: string, response?: mixed, errors?: string}
     */
    public function updateItemInventory(string $itemNo, int $qty): array
    {
        $itemNo = trim($itemNo);
        if ($itemNo === '') {
            return ['success' => false, 'message' => 'itemNo is required.', 'errors' => 'itemNo is required.'];
        }

        $qty = max(0, $qty);

        try {
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $headers = [
                'appKey' => config('services.doba.app_key'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $payloadAttempts = [
                ['itemNo' => $itemNo, 'availableInventory' => $qty],
                ['itemNo' => $itemNo, 'inventory' => $qty],
                ['itemNo' => $itemNo, 'availableInventory' => (string) $qty],
                ['itemNo' => $itemNo, 'inventory' => (string) $qty],
            ];

            $urls = [
                $this->baseUrl.'/goods/update',
                $this->baseUrl.'/goods/info/update',
            ];

            $lastMessage = 'Doba inventory update failed for all endpoints.';
            foreach ($urls as $url) {
                foreach ($payloadAttempts as $payload) {
                    Log::info('Doba inventory update attempt', ['url' => $url, 'item_no' => $itemNo, 'qty' => $qty]);
                    $response = Http::withHeaders($headers)->asForm()->post($url, $payload);
                    $responseData = $response->json() ?? [];
                    Log::info('Doba inventory update response', [
                        'url' => $url,
                        'status' => $response->status(),
                        'response' => $responseData,
                    ]);

                    if (! $response->successful()) {
                        $lastMessage = 'HTTP '.$response->status().': '.($responseData['responseMessage'] ?? $response->body());
                        continue;
                    }

                    if (isset($responseData['responseCode']) && $responseData['responseCode'] !== '000000') {
                        $lastMessage = (string) ($responseData['responseMessage'] ?? 'Doba API error '.$responseData['responseCode']);
                        continue;
                    }

                    return [
                        'success' => true,
                        'message' => 'Doba inventory updated.',
                        'response' => $responseData,
                    ];
                }
            }

            return ['success' => false, 'message' => $lastMessage, 'errors' => $lastMessage];
        } catch (\Throwable $e) {
            Log::error('Doba updateItemInventory failed', ['item_no' => $itemNo, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->getMessage()];
        }
    }

    /**
     * Update product title for the given SKU on Doba.
     *
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $title = trim($title);
        if (trim($sku) === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }

        return $this->dobaContentUpdate($sku, 'title', function (array $product) use ($title) {
            $product['productName'] = $title;

            return $product;
        }, 'Doba title updated.');
    }

    /**
     * goods/update saves the whole SPU (all SKUs with their variation props), so read the live
     * product from goods/detail, apply one change and send it all back as signed JSON.
     * The successful result is cached so the next part of the same push builds on it instead of
     * reverting it.
     *
     * @param  callable(array<string, mixed>, int): array<string, mixed>  $mutate  receives the product and the target SKU index
     * @return array{success: bool, message: string}
     */
    private function dobaContentUpdate(string $identifier, string $label, callable $mutate, string $okMessage): array
    {
        try {
            $itemNo = $this->resolveSkuToItemNo($identifier);
            if (! $itemNo) {
                return ['success' => false, 'message' => 'SKU or item_id not found in DobaMetric/DobaDataView. Sync Doba listings first.'];
            }
            $spuId = '';
            try {
                $spuId = trim((string) (DobaMetric::query()->where('item_id', $itemNo)->value('goods_id') ?? ''));
            } catch (\Throwable) {
                $spuId = '';
            }

            $product = $this->dobaLiveProduct($spuId, (string) $itemNo);
            if ($product === null) {
                return ['success' => false, 'message' => 'Could not load this product from Doba (goods/detail) to update it. Check the Doba listing exists, then push again.'];
            }
            $spuId = trim((string) ($product['spuId'] ?? $spuId));

            $skuIndex = $this->dobaSkuIndex($product, (string) $itemNo, trim($identifier));
            if ($skuIndex === null) {
                return ['success' => false, 'message' => 'This SKU was not found inside its Doba product ('.$spuId.').'];
            }

            $product = $mutate($product, $skuIndex);
            $payload = $this->dobaUpdatePayload($product);

            $response = null;
            for ($try = 0; $try < 2; $try++) {
                $timestamp = $this->getMillisecond();
                $sign = $this->generateSignature($this->getContent($timestamp));
                $response = Http::withoutVerifying()->timeout(40)->withHeaders([
                    'appKey' => config('services.doba.app_key'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                ])->asJson()->post($this->baseUrl.'/goods/update', $payload);
                if ($response->status() !== 429) {
                    break;
                }
                sleep(4);
            }

            $data = $response->json();
            $data = is_array($data) ? $data : [];
            $apiMsg = trim((string) ($data['responseMessage'] ?? $data['message'] ?? ''));
            $business = is_array($data['businessData'] ?? null) ? $data['businessData'] : [];
            $businessMsg = trim((string) ($business['businessMessage'] ?? ($business[0]['businessMessage'] ?? '')));
            $businessStatus = (string) ($business['businessStatus'] ?? ($business[0]['businessStatus'] ?? ''));
            Log::info('Doba content update response', [
                'item_no' => $itemNo,
                'spu_id' => $spuId,
                'part' => $label,
                'variation_props' => array_map(fn ($s) => [$s['skuCode'] ?? '', $s['variationProps'] ?? []], $payload['skus'] ?? []),
                'status' => $response->status(),
                'response' => mb_substr((string) $response->body(), 0, 800),
            ]);

            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Doba rate limit hit (HTTP 429). Wait a minute and push again.'];
            }
            if (! $response->successful()) {
                if (stripos($apiMsg, 'whitelist') !== false) {
                    return ['success' => false, 'message' => 'Doba API IP whitelist check failed — add this server IP in the Doba Open Platform app settings.'];
                }

                return ['success' => false, 'message' => 'HTTP '.$response->status().': '.($apiMsg !== '' ? $apiMsg : mb_substr((string) $response->body(), 0, 300))];
            }
            if (isset($data['responseCode']) && (string) $data['responseCode'] !== '000000') {
                return ['success' => false, 'message' => $apiMsg !== '' ? $apiMsg : 'Doba API error '.$data['responseCode']];
            }
            if (($businessStatus !== '' && $businessStatus !== '000000')
                || (array_key_exists('successful', $business) && $business['successful'] !== true)) {
                return ['success' => false, 'message' => $businessMsg !== '' ? $businessMsg : 'Doba rejected the '.$label.' update.'];
            }

            Cache::put($this->dobaProductCacheKey($spuId), $product, now()->addMinutes(10));

            return ['success' => true, 'message' => $okMessage];
        } catch (\Throwable $e) {
            Log::warning('Doba content update failed', ['identifier' => $identifier, 'part' => $label, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function dobaProductCacheKey(string $spuId): string
    {
        return 'doba_lm_product:'.$spuId;
    }

    /**
     * Full SPU from goods/detail (filtered lookups first, then a catalog scan).
     *
     * @return array<string, mixed>|null
     */
    private function dobaLiveProduct(string $spuId, string $itemNo): ?array
    {
        if ($spuId !== '') {
            $cached = Cache::get($this->dobaProductCacheKey($spuId));
            if (is_array($cached) && is_array($cached['skus'] ?? null)) {
                return $cached;
            }
        }

        $matches = function (array $product) use ($spuId, $itemNo): bool {
            if ($spuId !== '' && strcasecmp(trim((string) ($product['spuId'] ?? '')), $spuId) === 0) {
                return true;
            }
            foreach (($product['skus'] ?? []) as $sku) {
                foreach ((is_array($sku) ? ($sku['stocks'] ?? []) : []) as $stock) {
                    if (is_array($stock) && strcasecmp(trim((string) ($stock['itemNo'] ?? '')), $itemNo) === 0) {
                        return true;
                    }
                }
            }

            return false;
        };

        $filters = array_values(array_filter([
            $spuId !== '' ? ['spuId' => $spuId] : null,
            ['itemNo' => $itemNo],
        ]));
        foreach ($filters as $extra) {
            foreach ($this->fetchGoodsDetailPage(1, 50, $extra) as $product) {
                if (is_array($product) && $matches($product)) {
                    return $product;
                }
            }
        }
        for ($page = 1; $page <= 40; $page++) {
            $rows = $this->fetchGoodsDetailPage($page, 100);
            foreach ($rows as $product) {
                if (is_array($product) && $matches($product)) {
                    return $product;
                }
            }
            if (count($rows) < 100) {
                break;
            }
            usleep(150000);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function dobaSkuIndex(array $product, string $itemNo, string $skuCode): ?int
    {
        foreach (array_values($product['skus'] ?? []) as $i => $sku) {
            if (! is_array($sku)) {
                continue;
            }
            foreach (($sku['stocks'] ?? []) as $stock) {
                if (is_array($stock) && strcasecmp(trim((string) ($stock['itemNo'] ?? '')), $itemNo) === 0) {
                    return $i;
                }
            }
            if ($skuCode !== '' && strcasecmp(trim((string) ($sku['skuCode'] ?? '')), $skuCode) === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * goods/detail reports every SKU's variation prop with the full comma-joined value list
     * ("235 Black,58 Blue,…"), which goods/update rejects as an invalid variation property.
     * Give each SKU its own single value: the Shopify variant title when it is one of Doba's
     * values, otherwise the value at the SKU's position (Doba lists them in SKU order), otherwise
     * the Shopify variant title, otherwise the SKU code.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function dobaUpdatePayload(array $product): array
    {
        $skus = array_values(array_filter($product['skus'] ?? [], 'is_array'));
        $shopifyTitles = $this->shopifyVariantTitles(array_map(fn ($s) => (string) ($s['skuCode'] ?? ''), $skus));

        $used = [];
        foreach ($skus as $i => $sku) {
            $props = [];
            foreach (($sku['variationProps'] ?? []) as $prop) {
                if (! is_array($prop)) {
                    continue;
                }
                $name = trim((string) ($prop['propName'] ?? ''));
                $raw = trim((string) ($prop['propValue'] ?? ''));
                $options = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
                $shopify = $shopifyTitles[strtoupper(trim((string) ($sku['skuCode'] ?? '')))] ?? '';

                $value = count($options) === 1 ? $options[0] : '';
                if ($value === '' && $shopify !== '') {
                    foreach ($options as $opt) {
                        if (strcasecmp($opt, $shopify) === 0) {
                            $value = $opt;
                            break;
                        }
                    }
                }
                if ($value === '' && isset($options[$i]) && ! isset($used[$name][mb_strtolower($options[$i])])) {
                    $value = $options[$i];
                }
                if ($value === '') {
                    $value = $shopify !== '' ? $shopify : trim((string) ($sku['skuCode'] ?? ''));
                }
                $used[$name][mb_strtolower($value)] = true;
                $props[] = ['propName' => $name !== '' ? $name : 'Style', 'propValue' => $value];
            }
            if ($props === []) {
                $shopify = $shopifyTitles[strtoupper(trim((string) ($sku['skuCode'] ?? '')))] ?? '';
                $props[] = ['propName' => 'Style', 'propValue' => $shopify !== '' ? $shopify : trim((string) ($sku['skuCode'] ?? ''))];
            }
            $sku['variationProps'] = $props;
            $skus[$i] = array_filter($sku, fn ($v) => $v !== null);
        }

        $payload = array_filter($product, fn ($v) => $v !== null);
        $payload['skus'] = $skus;

        return $payload;
    }

    /**
     * @param  list<string>  $skuCodes
     * @return array<string, string> upper-cased SKU => Shopify variant title (blank for "Default Title")
     */
    private function shopifyVariantTitles(array $skuCodes): array
    {
        $skuCodes = array_values(array_filter(array_map('trim', $skuCodes), fn ($s) => $s !== ''));
        if ($skuCodes === []) {
            return [];
        }
        try {
            if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'variant_title')) {
                return [];
            }
            $rows = DB::table('shopify_skus')->whereIn('sku', $skuCodes)->get(['sku', 'variant_title']);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row->variant_title ?? ''));
            if ($title === '' || strcasecmp($title, 'Default Title') === 0) {
                continue;
            }
            $out[strtoupper(trim((string) $row->sku))] = $title;
        }

        return $out;
    }

    public function resolveItemNo(string $identifier): ?string
    {
        return $this->resolveSkuToItemNo($identifier);
    }

    private function resolveSkuToItemNo(string $identifier): ?string
    {
        $id = trim($identifier);
        if ($id === '') {
            return null;
        }

        $metric = DobaMetric::where('item_id', $id)->first();
        if ($metric && $metric->item_id) {
            return (string) $metric->item_id;
        }

        $metric = DobaMetric::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($id)])
            ->first();
        if ($metric && $metric->item_id) {
            return (string) $metric->item_id;
        }

        $compact = preg_replace('/\s+/', '', $id) ?: $id;
        if ($compact !== $id) {
            $metric = DobaMetric::query()
                ->whereRaw("LOWER(REPLACE(TRIM(sku), ' ', '')) = ?", [mb_strtolower($compact)])
                ->first();
            if ($metric && $metric->item_id) {
                return (string) $metric->item_id;
            }
        }

        $view = DobaDataView::query()
            ->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($id)])
            ->first();
        if ($view && isset($view->doba_product_id)) {
            return (string) $view->doba_product_id;
        }

        return null;
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

            // Payload: listing price and/or pickup (prepaid) price.
            // Pickup-only pushes omit anticipatedIncome so listing price is unchanged.
            $payload = [
                'itemNo' => (string)$itemId,
            ];

            if ($price !== null && $price !== '') {
                $payload['anticipatedIncome'] = round((float) $price, 2);
            }

            if ($selfPickPrice !== null && $selfPickPrice !== '') {
                $payload['selfPickAnticipatedIncome'] = round((float) $selfPickPrice, 2);
                // Doba 610016/610017: form-urlencoded turns PHP true into "1".
                // The goods/price/update API only accepts the strings "true" / "false".
                $payload['supportSelfPick'] = 'true';
            }

            if (!isset($payload['anticipatedIncome']) && !isset($payload['selfPickAnticipatedIncome'])) {
                return [
                    'errors' => 'Price or self pick price is required.',
                ];
            }

            Log::info('Doba API request prepared', [
                'item_id' => $itemId,
                'price' => $price,
                'self_pick_price' => $selfPickPrice,
                'payload' => $payload
            ]);

            $url = $this->baseUrl . "/goods/price/update";

            $headers = [
                'appKey'     => config('services.doba.app_key'),
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

            // Check for HTTP errors first (surface Doba's responseMessage — e.g. IP whitelist)
            if ($response->failed()) {
                $apiMsg = is_array($responseData)
                    ? (string) ($responseData['responseMessage'] ?? $responseData['message'] ?? '')
                    : '';
                if ($apiMsg !== '' && (stripos($apiMsg, 'whitelist') !== false || stripos($apiMsg, 'IP') !== false)) {
                    $errorMsg = 'Doba API IP whitelist check failed — add this server\'s public IP in the Doba Open Platform app settings. (' . $apiMsg . ')';
                } elseif ($apiMsg !== '') {
                    $errorMsg = "HTTP Error {$statusCode}: {$apiMsg}";
                } else {
                    $errorMsg = "HTTP Error {$statusCode}";
                }
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
                'appKey'     => config('services.doba.app_key'),
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
                'appKey' => config('services.doba.app_key'),
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
                'appKey' => config('services.doba.app_key'),
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
     * Signed GET to Doba OpenAPI. goods/get/item is not a real path (404).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function dobaSignedGet(string $path, array $query = []): array
    {
        $timestamp = $this->getMillisecond();
        $content = $this->getContent($timestamp);
        $sign = $this->generateSignature($content);
        $url = str_starts_with($path, 'http') ? $path : ($this->baseUrl.'/'.ltrim($path, '/'));

        $response = Http::withoutVerifying()->withHeaders([
            'appKey' => config('services.doba.app_key'),
            'signType' => 'rsa2',
            'timestamp' => $timestamp,
            'sign' => $sign,
            'Content-Type' => 'application/json',
        ])->get($url, $query);

        $json = $response->json();

        return is_array($json) ? $json : ['errors' => $response->body() ?: 'Empty Doba response'];
    }

    /**
     * One page from the working catalog endpoint (same as FetchDobaMetrics).
     *
     * @param  array<string, mixed>  $extra
     * @return list<array<string, mixed>>
     */
    private function fetchGoodsDetailPage(int $page, int $pageSize = 100, array $extra = []): array
    {
        $response = $this->dobaSignedGet('/goods/detail', array_merge([
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ], $extra));

        $rows = $response['businessData']['data']['dsGoodsDetailResultVOS'] ?? null;
        if (! is_array($rows)) {
            if (isset($response['status']) && (int) $response['status'] === 404) {
                return [];
            }

            return [];
        }

        return $rows;
    }

    /**
     * @return array{anticipatedIncome: float, selfPickAnticipatedIncome: float}
     */
    private function extractDobaStockPrices($stock): array
    {
        $stock = is_array($stock) ? $stock : [];
        $delivery = (float) ($stock['anticipatedIncome'] ?? $stock['anticipated_income'] ?? 0);
        $pickup = (float) ($stock['selfPickAnticipatedIncome']
            ?? $stock['self_pick_anticipated_income']
            ?? $stock['selfPickIncome']
            ?? 0);

        return [
            'anticipatedIncome' => $delivery > 0 ? $delivery : 0.0,
            'selfPickAnticipatedIncome' => $pickup > 0 ? $pickup : 0.0,
        ];
    }

    /**
     * Match Delivery / Pick Up prices from a goods/detail page.
     *
     * @param  list<array<string, mixed>>  $products
     * @param  array<string, true>  $wantItem
     * @param  array<string, true>  $wantSku
     * @param  array<string, array{anticipatedIncome: float, selfPickAnticipatedIncome: float}>  $found
     */
    private function collectLivePricesFromGoodsDetailPage(array $products, array $wantItem, array $wantSku, array &$found): void
    {
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            foreach (($product['skus'] ?? []) as $skuRow) {
                if (! is_array($skuRow)) {
                    continue;
                }
                $skuCode = strtoupper(trim((string) ($skuRow['skuCode'] ?? $skuRow['sku'] ?? '')));
                foreach (($skuRow['stocks'] ?? []) as $stock) {
                    if (! is_array($stock)) {
                        continue;
                    }
                    $itemNo = strtoupper(trim((string) ($stock['itemNo'] ?? $stock['item_no'] ?? '')));
                    $hit = ($itemNo !== '' && isset($wantItem[$itemNo]))
                        || ($skuCode !== '' && isset($wantSku[$skuCode]));
                    if (! $hit) {
                        continue;
                    }
                    $prices = $this->extractDobaStockPrices($stock);
                    if ($prices['anticipatedIncome'] <= 0 && $prices['selfPickAnticipatedIncome'] <= 0) {
                        continue;
                    }
                    if ($itemNo !== '') {
                        $found[$itemNo] = $prices;
                    }
                    if ($skuCode !== '') {
                        $found[$skuCode] = $prices;
                    }
                }
            }
        }
    }

    /**
     * Live Delivery / Pick Up from goods/detail. Scans the catalog once for every lookup.
     *
     * @param  list<array{itemNo?:string,sku?:string,goodsId?:string}>  $lookups
     * @return array<string, array{anticipatedIncome: float, selfPickAnticipatedIncome: float}>
     */
    public function pullLivePricesFromGoodsDetail(array $lookups): array
    {
        $wantItem = [];
        $wantSku = [];
        $filterQueries = [];
        foreach ($lookups as $lookup) {
            if (! is_array($lookup)) {
                continue;
            }
            $itemNo = strtoupper(trim((string) ($lookup['itemNo'] ?? '')));
            $sku = strtoupper(trim((string) ($lookup['sku'] ?? '')));
            $goodsId = trim((string) ($lookup['goodsId'] ?? ''));
            if ($itemNo !== '') {
                $wantItem[$itemNo] = true;
                $filterQueries[] = ['itemNo' => $lookup['itemNo'] ?? $itemNo];
            }
            if ($sku !== '') {
                $wantSku[$sku] = true;
                $filterQueries[] = ['skuCode' => $lookup['sku'] ?? $sku];
            }
            if ($goodsId !== '') {
                $filterQueries[] = ['goodsId' => $goodsId];
            }
        }
        if ($wantItem === [] && $wantSku === []) {
            return [];
        }

        $found = [];
        $resolved = function () use ($lookups, &$found) {
            $n = 0;
            foreach ($lookups as $lookup) {
                if (! is_array($lookup)) {
                    continue;
                }
                $item = strtoupper(trim((string) ($lookup['itemNo'] ?? '')));
                $skuKey = strtoupper(trim((string) ($lookup['sku'] ?? '')));
                if (($item !== '' && isset($found[$item])) || ($skuKey !== '' && isset($found[$skuKey]))) {
                    $n++;
                }
            }

            return $n;
        };
        $need = count($lookups);
        foreach (array_slice($filterQueries, 0, 6) as $extra) {
            $rows = $this->fetchGoodsDetailPage(1, 50, $extra);
            if ($rows === []) {
                continue;
            }
            $this->collectLivePricesFromGoodsDetailPage($rows, $wantItem, $wantSku, $found);
            if ($resolved() >= $need) {
                return $found;
            }
        }

        for ($page = 1; $page <= 30; $page++) {
            $rows = $this->fetchGoodsDetailPage($page, 100);
            if ($rows === []) {
                break;
            }
            $this->collectLivePricesFromGoodsDetailPage($rows, $wantItem, $wantSku, $found);
            if ($resolved() >= $need) {
                break;
            }
            if (count($rows) < 100) {
                break;
            }
            usleep(120000);
        }

        return $found;
    }

    /**
     * Get product detail from Doba by item_id (goods/detail — goods/get/item is 404).
     */
    public function getItemDetail($itemId)
    {
        try {
            $itemId = trim((string) $itemId);
            if ($itemId === '') {
                return ['errors' => 'itemNo is required'];
            }
            $map = $this->pullLivePricesFromGoodsDetail([['itemNo' => $itemId]]);
            $key = strtoupper($itemId);
            if (! isset($map[$key])) {
                return ['errors' => 'Live Doba item not found'];
            }

            return $map[$key];
        } catch (Exception $e) {
            return [
                'errors' => 'API Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Live listing Delivery / Pick Up prices from goods/detail.
     *
     * @return array{anticipatedIncome: float, selfPickAnticipatedIncome: float}|array{errors: string}
     */
    public function pullLiveItemPrices(string $itemId, ?string $sku = null, ?string $goodsId = null): array
    {
        $map = $this->pullLivePricesFromGoodsDetail([[
            'itemNo' => $itemId,
            'sku' => $sku,
            'goodsId' => $goodsId,
        ]]);
        $itemKey = strtoupper(trim($itemId));
        $skuKey = strtoupper(trim((string) $sku));
        $found = $map[$itemKey] ?? $map[$skuKey] ?? null;
        if (! is_array($found) || (($found['anticipatedIncome'] ?? 0) <= 0 && ($found['selfPickAnticipatedIncome'] ?? 0) <= 0)) {
            return ['errors' => 'Live Doba price not returned'];
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{anticipatedIncome: float, selfPickAnticipatedIncome: float}  $found
     */
    private function collectDobaLivePrices($node, array &$found): void
    {
        if (! is_array($node)) {
            return;
        }
        if (isset($node['anticipatedIncome']) && is_numeric($node['anticipatedIncome'])) {
            $n = (float) $node['anticipatedIncome'];
            if ($n > 0) {
                $found['anticipatedIncome'] = $n;
            }
        }
        if (isset($node['selfPickAnticipatedIncome']) && is_numeric($node['selfPickAnticipatedIncome'])) {
            $n = (float) $node['selfPickAnticipatedIncome'];
            if ($n > 0) {
                $found['selfPickAnticipatedIncome'] = $n;
            }
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectDobaLivePrices($child, $found);
            }
        }
    }

    private function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return intval((float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000));
    }

    private function generateSignature($content)
    {
        $privateKeyContent = trim(config('services.doba.private_key'));
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
        $appKey = config('services.doba.app_key');
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
                'appKey' => config('services.doba.app_key'),
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
                'app_key' => config('services.doba.app_key'),
                'private_key_length' => strlen(config('services.doba.private_key')),
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
                    'app_key' => config('services.doba.app_key'),
                    'private_key_length' => strlen(config('services.doba.private_key')),
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
                    'appKey' => config('services.doba.app_key'),
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
                    'appKey' => config('services.doba.app_key'),
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
                    'appKey' => config('services.doba.app_key'),
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'formatted' => "appKey=" . config('services.doba.app_key') . "&signType=rsa2&timestamp=" . $timestamp
                ],
                'signature' => $signature,
                'app_key' => config('services.doba.app_key'),
                'private_key_length' => strlen(config('services.doba.private_key')),
                'private_key_start' => substr(config('services.doba.private_key'), 0, 50) . '...',
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
        Log::info("Fetching Doba Metrics...");
        $page = 1;
         do {
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withoutVerifying()->withHeaders([
                'appKey' => config('services.doba.app_key'),
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
                $goodsId = $product['goodsId'] ?? $product['goods_id'] ?? $product['spuId'] ?? null;
                $catId = $product['catId'] ?? $product['categoryId'] ?? $product['goodsCatId'] ?? $product['cat_id'] ?? null;
                $goodsId = is_scalar($goodsId) ? trim((string) $goodsId) : '';
                $catId = is_scalar($catId) ? trim((string) $catId) : '';

                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;

                    if (!$item) continue;

                    // Doba API returns inventory (availableInventory) in the same stocks object — store it on doba_metrics.
                    // SKU normalized at write time (strtoupper+trim) to match every reader.
                    $normalizedSku = strtoupper(trim((string) ($sku['skuCode'] ?? '')));
                    if ($normalizedSku === '') continue;
                    $payload = [
                        'item_id' => $item['itemNo'],
                        'anticipated_income' => $item['anticipatedIncome'],
                        'inventory' => (int) ($item['availableInventory'] ?? 0),
                    ];
                    if ($goodsId !== '') {
                        $payload['goods_id'] = $goodsId;
                    }
                    if ($catId !== '') {
                        $payload['cat_id'] = $catId;
                    }
                    DobaMetric::updateOrCreate(
                        ['sku' => $normalizedSku],
                        $payload
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
                'appKey' => config('services.doba.app_key'),
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
                $goodsId = $product['goodsId'] ?? $product['goods_id'] ?? $product['spuId'] ?? null;
                $catId = $product['catId'] ?? $product['categoryId'] ?? $product['goodsCatId'] ?? $product['cat_id'] ?? null;
                $goodsId = is_scalar($goodsId) ? trim((string) $goodsId) : '';
                $catId = is_scalar($catId) ? trim((string) $catId) : '';

                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;
                    if (!$item) continue;

                    $quantity = (int) ($item['availableInventory'] ?? 0);
                    // Normalize SKU at write time — see comment in FetchDobaMetrics for the
                    // full rationale. Same normalization (strtoupper + trim) the DobaController
                    // reader uses on every keyBy() so writes and reads always meet on the same key.
                    $itemsku  = strtoupper(trim((string) ($sku['skuCode'] ?? '')));
                    if ($itemsku === '') continue;

                    $allStock[] = [
                        'sku'      => $itemsku,
                        'quantity' => $quantity,
                    ];

                    // Keep doba_metrics in sync with the same fields FetchDobaMetrics writes,
                    // so /doba-tabulator's INV column (which reads doba_metrics.inventory) and
                    // every other consumer of doba_metrics sees fresh data even when this fetch
                    // is triggered from a controller / missing-listing job rather than the cron.
                    $payload = [
                        'item_id'            => $item['itemNo'] ?? null,
                        'anticipated_income' => $item['anticipatedIncome'] ?? null,
                        'inventory'          => $quantity,
                    ];
                    if ($goodsId !== '') {
                        $payload['goods_id'] = $goodsId;
                    }
                    if ($catId !== '') {
                        $payload['cat_id'] = $catId;
                    }
                    \App\Models\DobaMetric::updateOrCreate(
                        ['sku' => $itemsku],
                        $payload
                    );
                }
            }
            $page++;
        } while (count($data) === 100);
        foreach ($allStock as $sku => $data) {
            $sku = $data['sku'] ?? null;
            $quantity = $data['quantity'];

            // product_stock_mappings.sku is also normalized elsewhere; case-insensitive
            // MySQL collation handles the match either way.
            ProductStockMapping::where('sku', $sku)->update(['inventory_doba' => (int) $quantity]);
        }
        return $allStock;
    }

    /**
     * Bullets are Doba's "Highlights" (sellPoint list), separate from the long description.
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []), fn ($s) => $s !== ''));
        if (trim($identifier) === '' || $lines === []) {
            return ['success' => false, 'message' => 'SKU (or item_id) and bullet points are required.'];
        }

        return $this->dobaContentUpdate($identifier, 'bullets', function (array $product) use ($lines) {
            $product['sellingPoints'] = array_slice($lines, 0, 5);

            return $product;
        }, 'Doba highlights (bullet points) updated.');
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        $description = trim($description);
        if (trim($identifier) === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU (or item_id) and description are required.'];
        }

        return $this->dobaContentUpdate($identifier, 'description', function (array $product) use ($description) {
            $product['productDetails'] = $description;

            return $product;
        }, 'Doba product description updated.');
    }

    /**
     * Push product video URL to Doba OpenAPI (goods/info/update).
     * Doba accepts one custom video link per listing (videoSource=2).
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        Log::info('Doba updateVideos', ['identifier' => $identifier, 'mode' => $mode]);

        try {
            $videos = array_slice(
                array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))),
                0,
                1
            );
            if (trim($identifier) === '' || $videos === []) {
                return ['success' => false, 'message' => 'SKU (or item_id) and at least one video URL are required.'];
            }

            foreach ($videos as $url) {
                if (! preg_match('#^https?://#i', $url)) {
                    return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
                }
            }

            $itemNo = $this->resolveSkuToItemNo($identifier);
            if (! $itemNo) {
                return ['success' => false, 'message' => 'SKU or item_id not found in DobaMetric/DobaDataView.'];
            }

            $primaryUrl = $videos[0];
            $timestamp = $this->getMillisecond();
            $content = $this->getContent($timestamp);
            $sign = $this->generateSignature($content);

            $headers = [
                'appKey' => config('services.doba.app_key'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $payloadAttempts = [
                [
                    'itemNo' => (string) $itemNo,
                    'video' => $primaryUrl,
                    'videoSource' => 2,
                    'videoStatus' => 1,
                ],
                [
                    'itemNo' => (string) $itemNo,
                    'video' => $primaryUrl,
                    'videoSource' => '2',
                    'videoStatus' => '1',
                ],
                [
                    'itemNo' => (string) $itemNo,
                    'productVideoUrl' => $primaryUrl,
                ],
                [
                    'itemNo' => (string) $itemNo,
                    'videoUrl' => $primaryUrl,
                ],
            ];

            $attempts = [
                'https://openapi.doba.com/api/goods/info/update',
                'https://openapi.doba.com/api/goods/update',
            ];

            $lastMessage = 'Doba video update failed for all endpoints.';
            foreach ($attempts as $url) {
                foreach ($payloadAttempts as $payload) {
                    Log::info('Doba video update attempt', ['url' => $url, 'item_no' => $itemNo, 'payload_keys' => array_keys($payload)]);

                    $response = Http::withHeaders($headers)->asForm()->post($url, $payload);
                    $responseData = $response->json() ?? [];
                    Log::info('Doba video update response', ['url' => $url, 'status' => $response->status(), 'response' => $responseData]);

                    if (! $response->successful()) {
                        $lastMessage = 'HTTP '.$response->status().': '.($responseData['responseMessage'] ?? $response->body());
                        continue;
                    }

                    if (isset($responseData['responseCode']) && $responseData['responseCode'] !== '000000') {
                        $lastMessage = (string) ($responseData['responseMessage'] ?? 'Unknown Doba API error');
                        continue;
                    }

                    return [
                        'success' => true,
                        'message' => 'Doba product video updated.',
                        'normalized_urls' => $videos,
                    ];
                }
            }

            return ['success' => false, 'message' => $lastMessage];
        } catch (\Throwable $e) {
            Log::error('Doba updateVideos failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        Log::info('Doba updateImages', ['identifier' => $identifier, 'mode' => $mode, 'count' => count($images)]);

        try {
            $images = array_slice(
                array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))),
                0,
                12
            );
            if (trim($identifier) === '' || $images === []) {
                return ['success' => false, 'message' => 'SKU (or item_id) and at least one image URL are required.'];
            }

            foreach ($images as $url) {
                if (! preg_match('#^https?://#i', $url)) {
                    return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
                }
            }

            $res = $this->dobaContentUpdate($identifier, 'images', function (array $product, int $skuIndex) use ($images) {
                $product['skus'][$skuIndex]['images'] = $images;

                return $product;
            }, 'Doba product images updated.');
            if (empty($res['success'])) {
                return $res;
            }

            $this->saveImageUrlsToMetricsRow('doba_metrics', trim($identifier), $images);

            return $res + ['normalized_urls' => $images];
        } catch (\Throwable $e) {
            Log::error('Doba updateImages failed', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
