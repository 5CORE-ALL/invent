<?php

namespace App\Console\Commands;

use App\Models\Temu3Metric;
use App\Models\Temu3Pricing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FetchTemu3Metrics extends Command
{
    protected $signature = 'app:fetch-temu3-metrics
                            {--only= : Run only one step: skus|goods|qty|price|ads|stock}
                            {--from-json= : Import a previously fetched metrics JSON instead of calling the API}';

    protected $description = 'Fetch Temu 3 SKUs, goods IDs, order qty, stock, base prices, and ads analytics (Open API — same as Temu 1)';

    public function handle()
    {
        Log::info('Starting FetchTemu3Metrics command');
        $this->info('Starting FetchTemu3Metrics command');

        $fromJson = trim((string) $this->option('from-json'));
        if ($fromJson !== '') {
            return $this->importFromJson($fromJson);
        }

        if (! $this->verifyCredentials()) {
            $this->error('Invalid Temu 3 API credentials. Please check your .env file.');

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

            Log::info('Completed FetchTemu3Metrics command successfully', ['only' => $only ?: 'all']);
            $this->info('Completed FetchTemu3Metrics command successfully');

            return 0;
        } catch (\Exception $e) {
            Log::error('Error in FetchTemu3Metrics command: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Error in FetchTemu3Metrics command: '.$e->getMessage());

            return 1;
        }
    }

    private function importFromJson(string $path): int
    {
        if (! is_file($path)) {
            $this->error('JSON file not found: '.$path);

            return 1;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows)) {
            $this->error('Invalid metrics JSON.');

            return 1;
        }

        $imported = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $payload = array_filter([
                'sku_id' => isset($row['sku_id']) ? (string) $row['sku_id'] : null,
                'goods_id' => isset($row['goods_id']) ? (string) $row['goods_id'] : null,
                'quantity' => is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : null,
                'listing_status' => $row['listing_status'] ?? null,
                'base_price' => is_numeric($row['base_price'] ?? null) ? (float) $row['base_price'] : null,
                'quantity_purchased_l30' => is_numeric($row['quantity_purchased_l30'] ?? null) ? (int) $row['quantity_purchased_l30'] : null,
                'quantity_purchased_l60' => is_numeric($row['quantity_purchased_l60'] ?? null) ? (int) $row['quantity_purchased_l60'] : null,
            ], fn ($value) => $value !== null && $value !== '');

            Temu3Metric::updateOrCreate(['sku' => $sku], $payload);
            $this->mirrorPricingCache($sku, $payload);
            $imported++;
        }

        $this->info("Imported {$imported} Temu 3 metric row(s) from JSON.");
        $this->debugSkuStatus();

        return 0;
    }

    private function verifyCredentials(): bool
    {
        $appKey = config('services.temu3.app_key');
        $appSecret = config('services.temu3.secret_key');
        $accessToken = config('services.temu3.access_token');

        if (empty($appKey) || empty($appSecret) || empty($accessToken)) {
            $this->error('Missing Temu 3 API credentials in .env file');
            $this->line('Required: TEMU3_APP_KEY, TEMU3_SECRET_KEY, TEMU3_ACCESS_TOKEN');

            return false;
        }

        $this->info('Credentials found (App Key: '.substr($appKey, 0, 8).'…)');
        $this->info('Testing API connection...');

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->openApiUrl(), $this->generateSignValue([
                    'type' => 'temu.local.sku.list.retrieve',
                    'skuSearchType' => 'ACTIVE',
                    'pageSize' => 1,
                ]));

            $data = $response->json();
            if ($data['success'] ?? false) {
                $this->info('API connection successful.');

                return true;
            }

            $errorCode = $data['errorCode'] ?? 'N/A';
            $errorMsg = $data['errorMsg'] ?? 'Unknown';
            $this->error("API connection failed [{$errorCode}]: {$errorMsg}");
            Log::error('Temu 3 API verification failed', [
                'error_code' => $errorCode,
                'error_msg' => $errorMsg,
            ]);

            return false;
        } catch (\Exception $e) {
            $this->error('Connection test failed: '.$e->getMessage());

            return false;
        }
    }

    private function fetchSkus(): void
    {
        $this->info('Fetching SKUs from Temu 3...');
        $totalProcessed = 0;

        foreach (['INACTIVE', 'ACTIVE'] as $skuSearchType) {
            $pageToken = null;
            $pageCount = 0;

            do {
                $requestBody = [
                    'type' => 'temu.local.sku.list.retrieve',
                    'skuSearchType' => $skuSearchType,
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $requestBody['pageToken'] = $pageToken;
                }

                $response = Http::timeout(60)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->openApiUrl(), $this->generateSignValue($requestBody));

                if ($response->failed()) {
                    $this->error('SKU request failed: '.$response->status());
                    break;
                }

                $data = $response->json();
                if (! ($data['success'] ?? false)) {
                    $this->error('Temu 3 SKU API error ['.($data['errorCode'] ?? 'N/A').']: '.($data['errorMsg'] ?? 'Unknown'));
                    break;
                }

                $skus = $data['result']['skuList'] ?? [];
                if ($skus === []) {
                    break;
                }

                foreach ($skus as $sku) {
                    $outSkuSn = $sku['outSkuSn'] ?? null;
                    $skuId = $sku['skuId'] ?? null;
                    if (! $outSkuSn || ! $skuId) {
                        continue;
                    }

                    $price = $sku['priceInfo']['salePrice']
                        ?? $sku['priceInfo']['price']
                        ?? $sku['salePrice']
                        ?? null;
                    $price = is_numeric($price) ? (float) $price : null;
                    $stock = $sku['stock'] ?? $sku['quantity'] ?? $sku['skuStockQuantity'] ?? null;

                    $payload = [
                        'sku_id' => (string) $skuId,
                        'listing_status' => strtolower($skuSearchType) === 'active' ? 'active' : 'inactive',
                    ];
                    if ($price !== null && $price > 0) {
                        $payload['base_price'] = $price;
                    }
                    if ($stock !== null && is_numeric($stock)) {
                        $payload['quantity'] = (int) $stock;
                    }

                    Temu3Metric::updateOrCreate(['sku' => (string) $outSkuSn], $payload);
                    $this->mirrorPricingCache((string) $outSkuSn, $payload);
                    $totalProcessed++;
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
                $pageCount++;
                $this->info("  {$skuSearchType} page {$pageCount}: ".count($skus).' SKUs (total '.$totalProcessed.')');
                usleep(300000);
            } while ($pageToken);
        }

        $this->info("SKUs synced: {$totalProcessed}");
    }

    private function fetchGoodsId(): void
    {
        $this->info('Fetching goods IDs...');
        $pageToken = null;
        $updatedCount = 0;

        do {
            $requestBody = [
                'type' => 'temu.local.goods.list.retrieve',
                'goodsSearchType' => 'ALL',
                'pageSize' => 100,
            ];
            if ($pageToken) {
                $requestBody['pageToken'] = $pageToken;
            }

            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->openApiUrl(), $this->generateSignValue($requestBody));

            if ($response->failed()) {
                $this->error('Goods request failed: '.$response->body());
                break;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                $this->error('Temu 3 goods API error: '.($data['errorMsg'] ?? 'Unknown'));
                break;
            }

            foreach ($data['result']['goodsList'] ?? [] as $good) {
                $goodsId = $good['goodsId'] ?? null;
                foreach ($good['skuInfoList'] ?? [] as $sku) {
                    $skuSn = $sku['skuSn'] ?? null;
                    if (! $skuSn || ! $goodsId) {
                        continue;
                    }

                    $skuSnKey = (string) $skuSn;
                    $updated = Temu3Metric::where('sku', $skuSnKey)
                        ->orWhere('sku_id', $skuSnKey)
                        ->update(['goods_id' => $goodsId]);
                    if ($updated) {
                        $updatedCount += $updated;
                    }
                }
            }

            $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            usleep(300000);
        } while ($pageToken);

        $this->info("Goods IDs updated: {$updatedCount} row(s)");
    }

    private function fetchQuantity(): void
    {
        $this->info('Fetching order quantities...');
        $today = Carbon::today();
        $toL30 = $today->copy()->subDay();
        $fromL30 = $toL30->copy()->subDays(29);
        $toL60 = $fromL30->copy()->subDay();
        $fromL60 = $toL60->copy()->subDays(29);

        $ranges = [
            'L30' => [$fromL30, $toL30],
            'L60' => [$fromL60, $toL60],
        ];
        $finalSkuQuantities = [];

        foreach ($ranges as $label => [$from, $to]) {
            $pageNumber = 1;
            $hasMorePages = true;

            do {
                $response = Http::timeout(60)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->openApiUrl(), $this->generateSignValue([
                        'type' => 'bg.order.list.v2.get',
                        'pageSize' => 100,
                        'pageNumber' => $pageNumber,
                        'createAfter' => $from->timestamp,
                        'createBefore' => $to->copy()->endOfDay()->timestamp,
                    ]));

                if ($response->failed()) {
                    $this->error("Order request failed for {$label}: ".$response->body());
                    break;
                }

                $data = $response->json();
                if (! ($data['success'] ?? false)) {
                    $this->error("Temu 3 order API error for {$label}: ".($data['errorMsg'] ?? 'Unknown'));
                    break;
                }

                $orders = $data['result']['pageItems'] ?? [];
                $totalCount = $data['result']['totalCount'] ?? 0;
                $this->info("  {$label} page {$pageNumber}: ".count($orders)." orders (total {$totalCount})");

                if ($orders === []) {
                    break;
                }

                foreach ($orders as $order) {
                    foreach ($order['orderList'] ?? [] as $item) {
                        $skuId = $item['skuId'] ?? null;
                        $qty = (int) ($item['quantity'] ?? 0);
                        if ($skuId === null) {
                            continue;
                        }
                        if (! isset($finalSkuQuantities[$skuId])) {
                            $finalSkuQuantities[$skuId] = [
                                'quantity_purchased_l30' => 0,
                                'quantity_purchased_l60' => 0,
                            ];
                        }
                        $finalSkuQuantities[$skuId]['quantity_purchased_l'.strtolower($label)] += $qty;
                    }
                }

                $hasMorePages = ($pageNumber * 100) < $totalCount && count($orders) >= 100;
                $pageNumber++;
                usleep(300000);
            } while ($hasMorePages);
        }

        $updated = 0;
        foreach ($finalSkuQuantities as $skuId => $qtyData) {
            $skuKey = (string) $skuId;
            $n = Temu3Metric::where('sku_id', $skuKey)->update($qtyData);
            if (! $n) {
                $n = Temu3Metric::where('sku', $skuKey)->update($qtyData);
            }
            $updated += $n;
        }

        $this->info("Order quantities updated: {$updated} row(s)");
    }

    private function fetchStock(): void
    {
        $this->info('Fetching stock from goods list...');
        $pageNumber = 1;
        $updated = 0;
        $goodsList = [];
        $total = 0;

        do {
            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->openApiUrl(), $this->generateSignValue([
                    'type' => 'bg.local.goods.list.query',
                    'pageSize' => 50,
                    'pageNumber' => $pageNumber,
                ]));

            if ($response->failed()) {
                $this->error('Stock request failed: '.$response->body());
                break;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                $this->error('Temu 3 stock API error: '.($data['errorMsg'] ?? 'Unknown'));
                break;
            }

            $goodsList = $data['result']['goodsList'] ?? [];
            $total = (int) ($data['result']['total'] ?? 0);
            $this->info("  Goods list page {$pageNumber}: ".count($goodsList)." (total {$total})");

            if ($goodsList === []) {
                break;
            }

            foreach ($goodsList as $good) {
                foreach ($good['skuInfoList'] ?? [] as $sku) {
                    $stock = $sku['skuStockQuantity'] ?? $sku['stock'] ?? $sku['quantity'] ?? null;
                    if ($stock === null || ! is_numeric($stock)) {
                        continue;
                    }

                    $skuSn = (string) ($sku['skuSn'] ?? $sku['outSkuSn'] ?? '');
                    $skuId = (string) ($sku['skuId'] ?? '');
                    $n = 0;
                    if ($skuSn !== '') {
                        $n = Temu3Metric::where('sku', $skuSn)->update(['quantity' => (int) $stock]);
                    }
                    if (! $n && $skuId !== '') {
                        $n = Temu3Metric::where('sku_id', $skuId)->update(['quantity' => (int) $stock]);
                    }
                    $updated += $n;
                }
            }

            $pageNumber++;
            usleep(300000);
        } while (count($goodsList) >= 50 && ($pageNumber - 1) * 50 < $total);

        $this->info("Stock updated: {$updated} row(s)");
    }

    private function fetchBasePrice(): void
    {
        $this->info('Fetching base prices...');
        $rows = Temu3Metric::query()
            ->whereNotNull('sku_id')
            ->where('sku_id', '!=', '')
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['sku_id', 'goods_id']);

        if ($rows->isEmpty()) {
            $this->warn('No rows with both goods_id and sku_id. Run SKUs + goods first.');

            return;
        }

        $byGoods = [];
        foreach ($rows as $row) {
            $gid = (string) $row->goods_id;
            $sid = (int) $row->sku_id;
            if ($gid === '' || $sid <= 0) {
                continue;
            }
            $byGoods[$gid][$sid] = true;
        }

        $goodsChunks = array_chunk($byGoods, 20, true);
        $updatedCount = 0;

        foreach ($goodsChunks as $chunkIndex => $goodsMap) {
            $queryList = [];
            foreach ($goodsMap as $goodsId => $skuIdSet) {
                $queryList[] = [
                    'goodsId' => (int) $goodsId,
                    'skuIdList' => array_map('intval', array_keys($skuIdSet)),
                ];
            }

            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->openApiUrl(), $this->generateSignValue([
                    'type' => 'bg.local.goods.sku.list.price.query',
                    'querySupplierPriceBaseList' => $queryList,
                    'language' => 'en',
                ]));

            if ($response->failed() || ! (($response->json()['success'] ?? false))) {
                $this->error('Price chunk '.($chunkIndex + 1).' failed: '.($response->json()['errorMsg'] ?? $response->body()));
                usleep(200000);
                continue;
            }

            $goodsPriceList = $response->json()['result']['openapiGoodsSupplierPriceDTOList']
                ?? $response->json()['result']['skuPriceInfoList']
                ?? [];

            foreach ($goodsPriceList as $goodsBlock) {
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
                        $updatedCount += Temu3Metric::where('sku_id', (string) $skuId)->update([
                            'base_price' => (float) $amount,
                        ]);
                    }
                    continue;
                }

                $skuId = $goodsBlock['skuId'] ?? $goodsBlock['sku_id'] ?? null;
                $amount = $goodsBlock['basePrice'] ?? ($goodsBlock['supplierPrice']['amount'] ?? null);
                if ($skuId !== null && $amount !== null && is_numeric($amount)) {
                    $updatedCount += Temu3Metric::where('sku_id', (string) $skuId)->update([
                        'base_price' => (float) $amount,
                    ]);
                }
            }

            $this->info('  Price chunk '.($chunkIndex + 1).'/'.count($goodsChunks).' OK');
            usleep(250000);
        }

        $this->info("Base prices updated: {$updatedCount} row(s)");
    }

    private function fetchProductAnalyticsData(): void
    {
        $goodsIds = Temu3Metric::whereNotNull('goods_id')->pluck('goods_id')->unique()->values()->all();
        if ($goodsIds === []) {
            $this->warn('No goods_id found. Run goods fetch first.');

            return;
        }

        $ranges = [
            'L30' => [
                'startTs' => Carbon::now()->subDays(29)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::now()->endOfDay()->timestamp * 1000,
            ],
            'L60' => [
                'startTs' => Carbon::now()->subDays(59)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::now()->subDays(30)->endOfDay()->timestamp * 1000,
            ],
        ];

        $this->info('Fetching ads analytics for '.count($goodsIds).' goods...');
        foreach ($goodsIds as $goodId) {
            $metrics = [
                'product_impressions_l30' => 0,
                'product_clicks_l30' => 0,
                'product_impressions_l60' => 0,
                'product_clicks_l60' => 0,
            ];

            foreach ($ranges as $label => $range) {
                $response = Http::timeout(30)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->openApiUrl(), $this->generateSignValue([
                        'type' => 'temu.searchrec.ad.reports.goods.query',
                        'goodsId' => $goodId,
                        'startTs' => $range['startTs'],
                        'endTs' => $range['endTs'],
                    ]));

                $data = $response->json();
                if (! ($data['success'] ?? false)) {
                    continue;
                }

                $reportInfo = $data['result']['reportInfo'] ?? [];
                $overall = is_array($reportInfo['summary'] ?? null) ? $reportInfo['summary'] : [];
                $adOnly = is_array($reportInfo['reportsSummary'] ?? null) ? $reportInfo['reportsSummary'] : [];
                $impr = $overall['imprCnt']['total']['val'] ?? $adOnly['imprCntAll']['val'] ?? 0;
                $clicks = $overall['clkCnt']['total']['val'] ?? $adOnly['clkCntAll']['val'] ?? 0;

                if ($label === 'L30') {
                    $metrics['product_impressions_l30'] = $impr;
                    $metrics['product_clicks_l30'] = $clicks;
                } else {
                    $metrics['product_impressions_l60'] = $impr;
                    $metrics['product_clicks_l60'] = $clicks;
                }
                usleep(150000);
            }

            Temu3Metric::where('goods_id', $goodId)->update($metrics);
        }

        $this->info('Ads analytics updated.');
    }

    private function mirrorPricingCache(string $sku, array $payload): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('temu3_pricing')) {
            return;
        }

        $data = array_intersect_key($payload, array_flip(['sku_id', 'goods_id', 'quantity']));
        if ($data === []) {
            return;
        }

        Temu3Pricing::query()->where('sku', $sku)->update($data);
    }

    private function openApiUrl(): string
    {
        return rtrim((string) config('services.temu3.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
    }

    private function generateSignValue(array $requestBody): array
    {
        $appKey = config('services.temu3.app_key');
        $appSecret = config('services.temu3.secret_key');
        $accessToken = config('services.temu3.access_token');
        $timestamp = time();

        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => $timestamp,
            'data_type' => 'JSON',
        ];

        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key.$value;
        }

        $params['sign'] = strtoupper(md5($appSecret.$temp.$appSecret));

        return array_merge($params, $requestBody);
    }

    private function debugSkuStatus(): void
    {
        $totalSkus = Temu3Metric::count();
        if ($totalSkus === 0) {
            $this->warn('No Temu 3 metrics rows after fetch.');

            return;
        }

        $skusWithSkuId = Temu3Metric::whereNotNull('sku_id')->count();
        $skusWithGoodsId = Temu3Metric::whereNotNull('goods_id')->count();
        $skusWithPrice = Temu3Metric::whereNotNull('base_price')->count();
        $skusWithQuantity = Temu3Metric::where('quantity_purchased_l30', '>', 0)->count();
        $pct = fn (int $n): string => number_format(($n / $totalSkus) * 100, 1).'%';

        $this->line('');
        $this->line('Temu 3 SKU stats');
        $this->line("Total SKUs: {$totalSkus}");
        $this->line("SKUs with sku_id: {$skusWithSkuId} (".$pct($skusWithSkuId).')');
        $this->line("SKUs with goods_id: {$skusWithGoodsId} (".$pct($skusWithGoodsId).')');
        $this->line("SKUs with base_price: {$skusWithPrice} (".$pct($skusWithPrice).')');
        $this->line("SKUs with quantity L30: {$skusWithQuantity} (".$pct($skusWithQuantity).')');
    }
}
