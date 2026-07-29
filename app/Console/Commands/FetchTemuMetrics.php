<?php

namespace App\Console\Commands;

use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTemuMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-temu-metrics
                            {--only= : Run only one step: skus|goods|qty|price|ads|stock}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Temu SKUs, goods IDs, order qty, stock, base prices, and ads analytics';

    /**
     * Execute the console command.
     */
    public function handle()
    {       
        Log::info('Starting FetchTemuMetrics command');
        $this->info('Starting FetchTemuMetrics command');

        // Verify credentials first
        if (!$this->verifyCredentials()) {
            $this->error('Invalid Temu API credentials. Please check your .env file.');
            return 1;
        }

        $only = strtolower(trim((string) $this->option('only')));
        if ($only !== '' && ! in_array($only, ['skus', 'goods', 'qty', 'price', 'ads', 'stock'], true)) {
            $this->error('Invalid --only value. Use: skus|goods|qty|price|ads|stock');
            return 1;
        }

        try {
            $runAll = $only === '';

            if ($runAll || $only === 'skus') {
                $this->info('Step 1/6: Fetching SKUs...');
                $this->fetchSkus();
            }

            if ($runAll || $only === 'goods') {
                $this->info('Step 2/6: Fetching Goods IDs...');
                $this->fetchGoodsId();
            }

            if ($runAll || $only === 'qty') {
                $this->info('Step 3/6: Fetching Order Quantities (L30 & L60)...');
                $this->fetchQuantity();
            }

            if ($runAll || $only === 'stock') {
                $this->info('Step 4/6: Fetching Stock (goods list inventory)...');
                $this->fetchStock();
            }

            if ($runAll || $only === 'price') {
                $this->info('Step 5/6: Fetching Prices...');
                $this->fetchBasePrice();
            }

            if ($runAll || $only === 'ads') {
                $this->info('Step 6/6: Fetching Product Analytics Data...');
                $this->fetchProductAnalyticsData();
            }

            if ($runAll) {
                $this->debugSkuStatus();
            }

            Log::info('Completed FetchTemuMetrics command successfully', ['only' => $only ?: 'all']);
            $this->info('✅ Completed FetchTemuMetrics command successfully');
            return 0;
        } catch (\Exception $e) {
            Log::error('Error in FetchTemuMetrics command: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in FetchTemuMetrics command: ' . $e->getMessage());
            return 1;
        }
    }

    private function verifyCredentials()
    {
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');

        if (empty($appKey) || empty($appSecret) || empty($accessToken)) {
            $this->error('Missing Temu API credentials in .env file');
            $this->line('Required: TEMU_APP_KEY, TEMU_SECRET_KEY, TEMU_ACCESS_TOKEN');
            return false;
        }

        $this->info('Credentials found (App Key: ' . substr($appKey, 0, 8) . '…)');

        // Test API connection with a simple call
        $this->info('Testing API connection...');
        try {
            $requestBody = [
                "type" => "temu.local.sku.list.retrieve",                
                "skuSearchType" => "ACTIVE",
                "pageSize" => 1,
            ];

            $signedRequest = $this->generateSignValue($requestBody);

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

            $data = $response->json();
            
            if ($data['success'] ?? false) {
                $this->info("✅ API Connection Successful!");
                return true;
            } else {
                $errorCode = $data['errorCode'] ?? 'N/A';
                $errorMsg = $data['errorMsg'] ?? 'Unknown';
                $this->error("❌ API Connection Failed!");
                $this->error("Error [{$errorCode}]: {$errorMsg}");
                $this->line("\n🔍 Debug Info:");
                $this->line("Full Response: " . json_encode($data, JSON_PRETTY_PRINT));
                Log::error("Temu API Verification Failed", [
                    'error_code' => $errorCode,
                    'error_msg' => $errorMsg,
                    'response' => $data,
                    'request' => $signedRequest
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $this->error("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    private function fetchProductAnalyticsData(){
        Log::info('Starting fetchProductAnalyticsData');
        $this->info('Fetching product analytics data...');

        try {
            $goodsIds = TemuMetric::whereNotNull('goods_id')->pluck('goods_id')->toArray();
            
            if (empty($goodsIds)) {
                $this->warn("No goods_id found in database. Run fetchGoodsId() first.");
                Log::warning("No goods_id found for fetchProductAnalyticsData");
                return;
            }

            $startTs = Carbon::yesterday()->startOfDay()->timestamp * 1000;
            $endTs = Carbon::yesterday()->endOfDay()->timestamp * 1000;

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


            foreach ($goodsIds as $goodId) {
                $metrics = [
                    'product_impressions_l30' => 0,
                    'product_clicks_l30' => 0,
                    'product_impressions_l60' => 0,
                    'product_clicks_l60' => 0,
                ];
                foreach ($ranges as $label => $range) {
                    $requestBody = [
                        'type' => 'temu.searchrec.ad.reports.goods.query',
                        'goodsId' => $goodId,
                        'startTs' => $range['startTs'],
                        'endTs' => $range['endTs'],
                    ];

                    $signedRequest = $this->generateSignValue($requestBody);
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                    if ($response->failed()) {
                        $this->error("Request failed for Goods ID: {$goodId} | " . $response->body());
                        Log::error("Request failed for Goods ID: {$goodId}", ['response' => $response->body()]);
                        continue;
                    }

                    $data = $response->json();
                    if (!($data['success'] ?? false)) {
                        $this->error("Temu API error for Goods ID: {$goodId} | " . ($data['errorMsg'] ?? 'Unknown'));
                        Log::error("Temu API error for Goods ID: {$goodId}", ['error' => $data['errorMsg'] ?? 'Unknown']);
                        continue;
                    }

                    $summary = $data['result']['reportInfo']['reportsSummary'] ?? null;

                    if ($summary) {
                        if ($label === 'L30') {
                            $metrics['product_impressions_l30'] = $summary['imprCntAll']['val'] ?? 0;
                            $metrics['product_clicks_l30'] = $summary['clkCntAll']['val'] ?? 0;
                        } elseif ($label === 'L60') {
                            $metrics['product_impressions_l60'] = $summary['imprCntAll']['val'] ?? 0;
                            $metrics['product_clicks_l60'] = $summary['clkCntAll']['val'] ?? 0;
                        }
                    }
                }

                TemuMetric::updateOrCreate(
                    ['goods_id' => $goodId],
                    $metrics
                );
                Log::info("Updated metrics for Goods ID: {$goodId}", $metrics);
            }


            $this->info("Analytics data updated successfully.");
            Log::info('Completed fetchProductAnalyticsData successfully');
        } catch (\Exception $e) {
            Log::error('Error in fetchProductAnalyticsData: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchProductAnalyticsData: ' . $e->getMessage());
        }
    }

    /**
     * Sync warehouse stock from bg.local.goods.list.query into temu_metrics.quantity
     * (used by temu-decrease Temu Stock / Map / N Map — not the sheet).
     */
    private function fetchStock(): void
    {
        Log::info('Starting fetchStock');
        $this->info('Fetching Temu stock from goods list + SKU list APIs...');

        try {
            $service = new TemuApiService();
            $items = $service->getinventory();
            $goodsCount = is_array($items) ? count($items) : 0;
            $skuUpdates = $service->syncSkuListStock();
            $withQty = TemuMetric::where('quantity', '>', 0)->count();
            $this->info("Goods list items: {$goodsCount}; SKU stock updates: {$skuUpdates}; temu_metrics qty>0: {$withQty}");
            Log::info('Completed fetchStock', [
                'goods' => $goodsCount,
                'sku_updates' => $skuUpdates,
                'with_qty' => $withQty,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in fetchStock: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchStock: '.$e->getMessage());
        }
    }

    private function fetchBasePrice()
    {
        Log::info('Starting fetchBasePrice');
        $this->info('Fetching base prices...');

        try {
            // Official API: bg.local.goods.sku.list.price.query
            // Requires querySupplierPriceBaseList[{ goodsId, skuIdList }] — NOT top-level skuIdList.
            $rows = TemuMetric::query()
                ->whereNotNull('sku_id')
                ->where('sku_id', '!=', '')
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->get(['sku', 'sku_id', 'goods_id']);

            if ($rows->isEmpty()) {
                $this->warn('No rows with both goods_id and sku_id. Run fetchSkus() + fetchGoodsId() first.');
                Log::warning('No sku_id+goods_id pairs for fetchBasePrice');
                return;
            }

            // Group skuIds under each goodsId (batch-friendly)
            $byGoods = [];
            foreach ($rows as $row) {
                $gid = (string) $row->goods_id;
                $sid = (int) $row->sku_id;
                if ($gid === '' || $sid <= 0) {
                    continue;
                }
                $byGoods[$gid][$sid] = true;
            }

            $goodsChunks = array_chunk($byGoods, 20, true); // up to 20 goods per request
            $updatedCount = 0;
            $errorCount = 0;

            foreach ($goodsChunks as $chunkIndex => $goodsMap) {
                $queryList = [];
                foreach ($goodsMap as $goodsId => $skuIdSet) {
                    $queryList[] = [
                        'goodsId' => (int) $goodsId,
                        'skuIdList' => array_map('intval', array_keys($skuIdSet)),
                    ];
                }

                $requestBody = [
                    'type' => 'bg.local.goods.sku.list.price.query',
                    'querySupplierPriceBaseList' => $queryList,
                    'language' => 'en',
                ];

                $signedRequest = $this->generateSignValue($requestBody);

                $response = Http::timeout(60)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                if ($response->failed()) {
                    $this->error('Price batch request failed (chunk '.($chunkIndex + 1).')');
                    Log::error('Price batch request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $errorCount += count($queryList);
                    continue;
                }

                $data = $response->json();
                if (! ($data['success'] ?? false)) {
                    $errorCode = $data['errorCode'] ?? 'N/A';
                    $errorMsg = $data['errorMsg'] ?? 'Unknown';
                    $this->error("Temu Price API error [{$errorCode}]: {$errorMsg} (chunk ".($chunkIndex + 1).')');
                    Log::error('Temu Price API batch error', [
                        'error_code' => $errorCode,
                        'error_msg' => $errorMsg,
                        'chunk' => $chunkIndex + 1,
                    ]);
                    $errorCount += count($queryList);
                    usleep(200000);
                    continue;
                }

                // Response: result.openapiGoodsSupplierPriceDTOList[].openapiSkuSupplierPriceDTOList[]
                //   { skuId, supplierPrice: { amount, currency } }
                $goodsPriceList = $data['result']['openapiGoodsSupplierPriceDTOList']
                    ?? $data['result']['skuPriceInfoList']
                    ?? [];

                if (empty($goodsPriceList)) {
                    $this->warn('No price list in response for chunk '.($chunkIndex + 1));
                    usleep(200000);
                    continue;
                }

                foreach ($goodsPriceList as $goodsBlock) {
                    // New shape
                    $skuPriceList = $goodsBlock['openapiSkuSupplierPriceDTOList'] ?? null;
                    if (is_array($skuPriceList)) {
                        foreach ($skuPriceList as $skuPrice) {
                            $skuId = $skuPrice['skuId'] ?? null;
                            $amount = $skuPrice['supplierPrice']['amount']
                                ?? $skuPrice['supplierPrice']['val']
                                ?? $skuPrice['basePrice']
                                ?? null;
                            if ($skuId === null || $amount === null || ! is_numeric($amount)) {
                                continue;
                            }
                            $n = TemuMetric::where('sku_id', (string) $skuId)->update([
                                'base_price' => (float) $amount,
                            ]);
                            if ($n) {
                                $updatedCount += $n;
                            }
                        }
                        continue;
                    }

                    // Legacy fallback shape: { skuId / sku_id, basePrice, currency }
                    $skuId = $goodsBlock['skuId'] ?? $goodsBlock['sku_id'] ?? null;
                    $amount = $goodsBlock['basePrice']
                        ?? ($goodsBlock['supplierPrice']['amount'] ?? null);
                    if ($skuId !== null && $amount !== null && is_numeric($amount)) {
                        $n = TemuMetric::where('sku_id', (string) $skuId)->update([
                            'base_price' => (float) $amount,
                        ]);
                        if ($n) {
                            $updatedCount += $n;
                        }
                    }
                }

                $this->info('Price chunk '.($chunkIndex + 1).'/'.count($goodsChunks).' OK');
                usleep(250000); // rate limit
            }

            $this->info("✅ Base prices updated: {$updatedCount} row(s)".($errorCount ? " ({$errorCount} chunk error(s))" : ''));
            Log::info('Completed fetchBasePrice successfully', [
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in fetchBasePrice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchBasePrice: ' . $e->getMessage());
        }
    }

    private function fetchGoodsId(){
        Log::info('Starting fetchGoodsId');
        $this->info('Fetching goods IDs...');

        try {
            $pageToken = null;
            do {
                $requestBody = [
                    "type" => "temu.local.goods.list.retrieve",                
                    "goodsSearchType" => "ALL",
                    "pageSize" => 100,
                ];

                if ($pageToken) {
                    $requestBody["pageToken"] = $pageToken;
                }

                $signedRequest = $this->generateSignValue($requestBody);

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json'
                ])->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);

                if ($response->failed()) {
                    $this->error("Request failed: " . $response->body());
                    Log::error("Request failed in fetchGoodsId", ['response' => $response->body()]);
                    break;
                }

                $data = $response->json();
                
                if (!($data['success'] ?? false)) {
                    $this->error("Temu Error: " . $data['errorMsg'] ?? 'Unknown');
                    Log::error("Temu Error in fetchGoodsId", ['error' => $data['errorMsg'] ?? 'Unknown']);
                    break;
                }

                $goodsList = $data['result']['goodsList'] ?? [];

                foreach ($goodsList as $good) {
                    $goodsId = $good['goodsId'] ?? null;
                    foreach ($good['skuInfoList'] ?? [] as $sku) {
                        $skuSn = $sku['skuSn'] ?? null;
                        
                        if ($skuSn && $goodsId) {
                            // Try both 'sku' and 'sku_id' columns since data might be in either.
                            // Cast to string so MySQL does a string comparison — a numeric bind
                            // coerces the VARCHAR columns to DOUBLE and errors on text SKUs.
                            $skuSnKey = (string) $skuSn;
                            $updated = TemuMetric::where('sku', $skuSnKey)
                                ->orWhere('sku_id', $skuSnKey)
                                ->update([
                                    'goods_id' => $goodsId,
                                ]);
                            if ($updated) {
                                $this->info("Updated goods_id for SKU: {$skuSn} to {$goodsId} ({$updated} records)");
                                Log::info("Updated goods_id for SKU: {$skuSn}", ['goods_id' => $goodsId, 'count' => $updated]);
                            } else {
                                $this->warn("No record found for SKU: {$skuSn} to update goods_id");
                                Log::warning("No record found for SKU: {$skuSn} to update goods_id");
                            }
                        }
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;

            } while ($pageToken);

            $this->info("Goods ID Updated Successfully.");
            Log::info('Completed fetchGoodsId successfully');
        } catch (\Exception $e) {
            Log::error('Error in fetchGoodsId: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchGoodsId: ' . $e->getMessage());
        }
    }

    private function fetchQuantity(){
        Log::info('Starting fetchQuantity');
        $this->info('Fetching quantities...');

        try {
            // 🔹 Define dynamic L30 and L60 date ranges
            $today = Carbon::today();

            $toL30 = $today->copy()->subDay(); // e.g. June 1
            $fromL30 = $toL30->copy()->subDays(29); // e.g. May 3

            $toL60 = $fromL30->copy()->subDay(); // e.g. May 2
            $fromL60 = $toL60->copy()->subDays(29); // e.g. April 2

            $ranges = [
                'L30' => [$fromL30, $toL30],
                'L60' => [$fromL60, $toL60],
            ];

            $finalSkuQuantities = [];
            foreach($ranges as $label => [$from, $to]){
                $pageNumber = 1;
                $hasMorePages = true;
        
                do {
                    $requestBody = [
                        "type" => "bg.order.list.v2.get",
                        "pageSize" => 100,
                        "pageNumber" => $pageNumber,
                        "createAfter" => $from->timestamp,     // ✅ UNIX timestamp
                        "createBefore" => $to->copy()->endOfDay()->timestamp, // ✅ End of day
                    ];
        
                    $signedRequest = $this->generateSignValue($requestBody);
        
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
        
                    if ($response->failed()) {
                        $this->error("Request failed: " . $response->body());
                        Log::error("Request failed in fetchQuantity for {$label}", ['response' => $response->body()]);
                        break;
                    }
        
                    $data = $response->json();
        
                    if (!($data['success'] ?? false)) {
                        $this->error("Temu Error: " . ($data['errorMsg'] ?? 'Unknown'));
                        Log::error("Temu Error in fetchQuantity for {$label}", ['error' => $data['errorMsg'] ?? 'Unknown']);
                        break;
                    }
                    
                    $orders = $data['result']['pageItems'] ?? [];
                    $totalCount = $data['result']['totalCount'] ?? 0;
                    
                    $this->info("Fetching {$label} - Page {$pageNumber}: " . count($orders) . " orders (Total: {$totalCount})");
                    Log::info("Fetching {$label} page {$pageNumber}", ['orders_count' => count($orders), 'total_count' => $totalCount]);
                    
                    if (empty($orders)) {
                        $this->warn("No more orders found for {$label} on page {$pageNumber}");
                        break;
                    }
                        
                    foreach ($orders as $order) {
                        
                        foreach ($order['orderList'] ?? [] as $item) {
                            $skuId = $item['skuId'];
                            $qty = $item['quantity'];

                            if (!isset($finalSkuQuantities[$skuId])) {
                                $finalSkuQuantities[$skuId] = ['quantity_purchased_l30' => 0, 'quantity_purchased_l60' => 0];
                            }
                            if ($label === 'L30') {
                                $finalSkuQuantities[$skuId]['quantity_purchased_l30'] += $qty;
                            } elseif ($label === 'L60') {
                                $finalSkuQuantities[$skuId]['quantity_purchased_l60'] += $qty;
                            }
                        }
                    }
        
                    // Check if there are more pages
                    $processedSoFar = $pageNumber * 100;
                    $hasMorePages = $processedSoFar < $totalCount && count($orders) >= 100;
                    
                    if (!$hasMorePages) {
                        $this->info("Finished fetching all pages for {$label}. Total pages: {$pageNumber}");
                        Log::info("Completed pagination for {$label}", ['total_pages' => $pageNumber, 'total_count' => $totalCount]);
                    }
                    
                    $pageNumber++;
                    
                    // Small delay to avoid rate limits
                    usleep(300000); // 0.3 seconds
                    
                } while ($hasMorePages);
            }

            foreach ($finalSkuQuantities as $skuId => $data) {
                // Cast to string: `sku_id`/`sku` are VARCHAR columns. Binding a numeric
                // value makes MySQL coerce every row's value to DOUBLE, which throws
                // "Truncated incorrect DOUBLE value" on non-numeric SKUs (e.g. 'WM SPK SLV').
                $skuKey = (string) $skuId;
                $updated = TemuMetric::where('sku_id', $skuKey)
                    ->update([
                        'quantity_purchased_l30' => $data['quantity_purchased_l30'],
                        'quantity_purchased_l60' => $data['quantity_purchased_l60'],
                    ]);
                if ($updated) {
                    $this->info("Successfully updated quantity for SKU_ID: {$skuId} ({$updated} records)");
                    Log::info("Updated quantities for SKU: {$skuId}", $data);
                } else {
                    // Try by SKU column if sku_id didn't work
                    $updated = TemuMetric::where('sku', $skuKey)
                        ->update([
                            'quantity_purchased_l30' => $data['quantity_purchased_l30'],
                            'quantity_purchased_l60' => $data['quantity_purchased_l60'],
                        ]);
                    if ($updated) {
                        $this->info("Successfully updated quantity for SKU: {$skuId} ({$updated} records)");
                        Log::info("Updated quantities for SKU: {$skuId}", $data);
                    } else {
                        $this->warn("No record found for SKU_ID: {$skuId} to update quantity");
                        Log::warning("No record found for SKU_ID: {$skuId} to update quantity");
                    }
                }
            }

            $this->info("Quantity Purchased Update Successfully.");
            Log::info('Completed fetchQuantity successfully');
        } catch (\Exception $e) {
            Log::error('Error in fetchQuantity: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchQuantity: ' . $e->getMessage());
        }
    }

    private function fetchSkus()
    {
        Log::info('Starting fetchSkus');
        $this->info('Fetching SKUs from Temu...');

        try {
            $pageToken = null;
            $pageCount = 0;
            $totalProcessed = 0;

            do {
                $requestBody = [
                    "type" => "temu.local.sku.list.retrieve",                
                    "skuSearchType" => "ACTIVE",
                    "pageSize" => 100,
                ];

                if ($pageToken) {
                    $requestBody["pageToken"] = $pageToken;
                }

                $signedRequest = $this->generateSignValue($requestBody);

                try {
                    $response = Http::timeout(60)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->post('https://openapi-b-us.temu.com/openapi/router', $signedRequest);
                } catch (\Exception $e) {
                    $this->error("HTTP Request Exception: " . $e->getMessage());
                    Log::error("HTTP Request Exception in fetchSkus", ['exception' => $e->getMessage()]);
                    break;
                }

                if ($response->failed()) {
                    $this->error("Request failed: " . $response->status() . " | " . $response->body());
                    Log::error("Request failed in fetchSkus", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    break;
                }

                $data = $response->json();
                
                if (!($data['success'] ?? false)) {
                    $errorMsg = $data['errorMsg'] ?? 'Unknown error';
                    $errorCode = $data['errorCode'] ?? 'N/A';
                    $this->error("Temu API Error [{$errorCode}]: {$errorMsg}");
                    Log::error("Temu Error in fetchSkus", [
                        'error_code' => $errorCode,
                        'error_msg' => $errorMsg,
                        'full_response' => $data
                    ]);
                    break;
                }

                $skus = $data['result']['skuList'] ?? [];

                if (empty($skus)) {
                    $this->warn("No SKUs found on page " . ($pageCount + 1));
                    Log::warning("No SKUs found on page " . ($pageCount + 1));
                    break;
                }

                foreach ($skus as $sku) {
                    $outSkuSn = $sku['outSkuSn'] ?? null;
                    $skuId = $sku['skuId'] ?? null;

                    if (!$outSkuSn || !$skuId) {
                        Log::warning("Missing SKU data", $sku);
                        continue;
                    }

                    // Extract price
                    $price = null;
                    if (isset($sku['priceInfo'])) {
                        $price = $sku['priceInfo']['salePrice'] 
                            ?? $sku['priceInfo']['price'] 
                            ?? null;
                    }
                    if (!$price && isset($sku['salePrice'])) {
                        $price = $sku['salePrice'];
                    }
                    $price = is_numeric($price) ? (float) $price : null;

                    $stock = $sku['stock']
                        ?? $sku['quantity']
                        ?? $sku['skuStockQuantity']
                        ?? null;

                    $payload = [
                        'sku_id' => (string) $skuId,
                        'base_price' => $price,
                    ];
                    if ($stock !== null && is_numeric($stock)) {
                        $payload['quantity'] = (int) $stock;
                    }

                    TemuMetric::updateOrCreate(
                        ['sku' => (string) $outSkuSn],
                        $payload
                    );
                    $totalProcessed++;
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
                $pageCount++;
                
                $this->info("  Page {$pageCount}: Processed " . count($skus) . " SKUs (Total: {$totalProcessed})");

                usleep(300000); // 0.3 sec delay

            } while ($pageToken);

            $this->info("✅ SKUs Synced: {$totalProcessed} total");
            Log::info('Completed fetchSkus successfully', ['total' => $totalProcessed]);
        } catch (\Exception $e) {
            Log::error('Error in fetchSkus: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in fetchSkus: ' . $e->getMessage());
        }
    }

    
    private function generateSignValue($requestBody)
    {
        // Environment/config variables
        $appKey = config('services.temu.app_key');
        $appSecret = config('services.temu.secret_key');
        $accessToken = config('services.temu.access_token');
        $timestamp = time(); // Unix timestamp in seconds
        
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

        // Debug logging
        Log::debug("🔍 API Request Details", [
            'type' => $requestBody['type'] ?? 'unknown',
            'timestamp' => $timestamp,
            'app_key' => substr($appKey, 0, 10) . '...',
            'access_token' => substr($accessToken, 0, 10) . '...',
            'sign_string_length' => strlen($temp),
            'sign' => $sign,
            'full_params' => $signParams
        ]);
        
        return array_merge($params, $requestBody);
    }

    private function debugSkuStatus()
    {
        Log::info('Starting debugSkuStatus');
        $this->info('🔍 Debugging SKU Status...');

        $totalSkus = TemuMetric::count();
        $skusWithSkuId = TemuMetric::whereNotNull('sku_id')->count();
        $skusWithGoodsId = TemuMetric::whereNotNull('goods_id')->count();
        $skusWithPrice = TemuMetric::whereNotNull('base_price')->count();
        $skusWithQuantity = TemuMetric::where('quantity_purchased_l30', '>', 0)->count();
        
        $this->line("\n📊 SKU Update Statistics:");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Total SKUs: {$totalSkus}");
        $this->line("SKUs with sku_id: {$skusWithSkuId} (" . number_format(($skusWithSkuId/$totalSkus)*100, 1) . "%)");
        $this->line("SKUs with goods_id: {$skusWithGoodsId} (" . number_format(($skusWithGoodsId/$totalSkus)*100, 1) . "%)");
        $this->line("SKUs with base_price: {$skusWithPrice} (" . number_format(($skusWithPrice/$totalSkus)*100, 1) . "%)");
        $this->line("SKUs with quantity (L30): {$skusWithQuantity} (" . number_format(($skusWithQuantity/$totalSkus)*100, 1) . "%)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Show SKUs missing data
        $incomplete = TemuMetric::where(function($q) {
            $q->whereNull('goods_id')
              ->orWhereNull('sku_id')
              ->orWhereNull('base_price');
        })->pluck('sku', 'id');

        if ($incomplete->count() > 0) {
            $this->warn("⚠️  " . $incomplete->count() . " SKUs have incomplete data:");
            foreach ($incomplete as $id => $sku) {
                $this->line("  - ID: $id, SKU: {$sku}");
            }
        }

        Log::info('Completed debugSkuStatus', [
            'total' => $totalSkus,
            'with_sku_id' => $skusWithSkuId,
            'with_goods_id' => $skusWithGoodsId,
            'with_price' => $skusWithPrice,
            'incomplete' => $incomplete->count()
        ]);
    }
}
