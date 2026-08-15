<?php

namespace App\Services;

use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Temu2ApiService extends TemuApiService
{
    protected function openApiRouterUrl(): string
    {
        return rtrim((string) config('services.temu2.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
    }

    /**
     * Sign with Temu 2 credentials only (never TEMU_*).
     */
    protected function generateSignValue($requestBody)
    {
        $appKey = trim((string) (config('services.temu2.app_key') ?? ''));
        $appSecret = trim((string) (config('services.temu2.secret_key') ?? ''));
        $accessToken = trim((string) (config('services.temu2.access_token') ?? ''));

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
            $temp .= $key.(string) $value;
        }

        $signStr = $appSecret.$temp.$appSecret;
        $params['sign'] = strtoupper(md5($signStr));

        return array_merge($params, $requestBody);
    }

    public function isConfigured(): bool
    {
        $appKey = trim((string) (config('services.temu2.app_key') ?? ''));
        $secret = trim((string) (config('services.temu2.secret_key') ?? ''));
        $token = trim((string) (config('services.temu2.access_token') ?? ''));

        return $appKey !== '' && $secret !== '' && $token !== '';
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu 2 API credentials missing. Set TEMU2_APP_KEY, TEMU2_SECRET_KEY, and TEMU2_ACCESS_TOKEN in .env.',
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
            $url = rtrim((string) config('services.temu2.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
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
                    'message' => 'Connected to Temu 2 Open API. Sample page returned '.count($items)." item(s); total reported: {$total}.",
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
                'message' => 'Temu 2 connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify Local Price Management permission via bg.local.goods.sku.list.price.query.
     * Uses one sku_id + goods_id pair from temu2_metrics when available.
     */
    public function testPriceAccess(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu 2 API credentials missing. Set TEMU2_ACCESS_TOKEN (after authorizing Inventory Temu 2) in .env.',
            ];
        }

        $sample = Temu2Metric::query()
            ->whereNotNull('sku_id')->where('sku_id', '!=', '')
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->first(['sku', 'sku_id', 'goods_id']);

        if (! $sample) {
            return [
                'success' => false,
                'message' => 'No Temu 2 SKUs with goods_id + sku_id yet. Run app:fetch-temu2-metrics --only=skus first, then retest price.',
            ];
        }

        $requestBody = [
            'type' => 'bg.local.goods.sku.list.price.query',
            'querySupplierPriceBaseList' => [[
                'goodsId' => (int) $sample->goods_id,
                'skuIdList' => [(int) $sample->sku_id],
            ]],
            'language' => 'en',
        ];

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $url = $this->openApiRouterUrl();
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->timeout(45)->post($url, $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                $list = $data['result']['openapiGoodsSupplierPriceDTOList']
                    ?? $data['result']['skuPriceInfoList']
                    ?? [];

                return [
                    'success' => true,
                    'message' => 'Price API OK (Local Price Management). Sample SKU '.$sample->sku.' returned '.count($list).' price block(s).',
                    'sku' => $sample->sku,
                ];
            }

            $errorCode = (string) ($data['errorCode'] ?? $response->status());
            $errorMsg = (string) ($data['errorMsg'] ?? $response->body() ?: 'Unknown error');
            $hint = '';
            $lower = strtolower($errorMsg);
            if (str_contains($lower, 'permission') || str_contains($lower, 'auth') || $errorCode === '7000019') {
                $hint = ' Re-authorize Inventory Temu 2 with Local Price Management, then paste the new access token.';
            }

            return [
                'success' => false,
                'message' => trim($errorCode.': '.$errorMsg).$hint,
                'error_code' => $errorCode,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Temu 2 price API test failed: '.$e->getMessage(),
            ];
        }
    }

    protected function resolveTemuGoodsAndSku(string $identifier): array
    {
        $id = trim($identifier);
        if ($id === '') {
            return ['sku' => '', 'goods_id' => null];
        }

        $row = Temu2Metric::query()
            ->where('sku', $id)
            ->orWhere('sku', strtoupper($id))
            ->orWhere('sku', strtolower($id))
            ->first();

        if (! $row) {
            $row = Temu2Metric::query()
                ->where('goods_id', $id)
                ->orWhere('sku_id', $id)
                ->first();
        }

        if ($row) {
            return [
                'sku' => trim((string) ($row->sku ?: $id)),
                'goods_id' => trim((string) ($row->goods_id ?? '')) ?: null,
            ];
        }

        return ['sku' => $id, 'goods_id' => null];
    }

    public function getProductPrice(string $sku): ?float
    {
        $price = Temu2Metric::where('sku', trim($sku))->value('base_price');
        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        return null;
    }

    public function getGoodsIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $goodsId = Temu2Metric::where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('goods_id');
        if ($goodsId !== null && $goodsId !== '') {
            return (string) $goodsId;
        }

        return $this->findTemuGoodsIdBySkuViaApi($sku);
    }

    public function getSkuIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $skuId = Temu2Metric::where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('sku_id');
        if ($skuId !== null && $skuId !== '') {
            return (string) $skuId;
        }

        return $this->findTemuSkuIdBySkuViaApi($sku);
    }

    protected function persistTemuMapping(string $sku, ?string $goodsId, ?string $skuId): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        try {
            $update = [];
            if ($goodsId !== null && $goodsId !== '') {
                $update['goods_id'] = $goodsId;
            }
            if ($skuId !== null && $skuId !== '') {
                $update['sku_id'] = $skuId;
            }
            if ($update === []) {
                return;
            }

            Temu2Metric::updateOrCreate(['sku' => $sku], $update);

            if (Schema::hasTable('temu2_pricing')) {
                Temu2Pricing::updateOrCreate(['sku' => $sku], $update);
            }
        } catch (\Throwable $e) {
            Log::warning('Temu2 persistTemuMapping failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist stock to temu2_metrics only (never temu_metrics / inventory_temu).
     *
     * @param  array<int, array<string, mixed>>  $goodsList
     */
    public function persistGoodsListInventory(array $goodsList): int
    {
        $updated = 0;
        if (! Schema::hasColumn('temu2_metrics', 'quantity')) {
            return 0;
        }

        foreach ($goodsList as $titem) {
            $goodsQty = (int) ($titem['quantity'] ?? 0);
            $goodsId = isset($titem['goodsId']) ? (string) $titem['goodsId'] : '';
            $skuTargets = [];
            $skuIdQty = [];

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
                $updated += Temu2Metric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                if (Schema::hasTable('temu2_pricing')) {
                    Temu2Pricing::where('sku_id', $skuId)->update(['quantity' => $qty]);
                }
            }

            foreach ($skuTargets as $sku => $qty) {
                $updated += Temu2Metric::where('sku', $sku)->update(['quantity' => $qty]);
                $updated += Temu2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->where('quantity', '!=', $qty)
                    ->update(['quantity' => $qty]);
                if (Schema::hasTable('temu2_pricing')) {
                    Temu2Pricing::where('sku', $sku)->update(['quantity' => $qty]);
                }
            }

            if ($goodsId !== '' && $goodsQty >= 0) {
                $updated += Temu2Metric::where('goods_id', $goodsId)
                    ->where(function ($q) {
                        $q->whereNull('quantity')->orWhere('quantity', 0);
                    })
                    ->update(['quantity' => $goodsQty]);
            }
        }

        Log::info('Temu2 inventory persisted to temu2_metrics', [
            'goods' => count($goodsList),
            'metric_updates' => $updated,
        ]);

        return $updated;
    }

    public function syncSkuListStock(): int
    {
        if (! Schema::hasColumn('temu2_metrics', 'quantity')) {
            return 0;
        }

        $pageNumber = 1;
        $pageSize = 100;
        $totalPages = null;
        $updated = 0;
        $url = $this->openApiRouterUrl();

        Log::info('======================= Started Temu2 SKU Stock Sync =======================');

        do {
            $requestBody = [
                'type' => 'bg.local.goods.sku.list.query',
                'pageSize' => $pageSize,
                'pageNumber' => $pageNumber,
                'skuSearchType' => 2,
            ];

            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->post($url, $signedRequest);
            } catch (\Exception $e) {
                Log::error('Temu2 SKU stock sync HTTP exception page '.$pageNumber.': '.$e->getMessage());
                break;
            }

            if ($response->failed()) {
                Log::error('Temu2 SKU stock sync failed page '.$pageNumber, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                Log::warning('Temu2 SKU stock sync API error page '.$pageNumber.': '.($data['errorMsg'] ?? 'Unknown'));
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

                if ($skuId !== '') {
                    $updated += Temu2Metric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                    if (Schema::hasTable('temu2_pricing')) {
                        Temu2Pricing::where('sku_id', $skuId)->update(['quantity' => $qty]);
                    }
                }
                if ($outSkuSn !== '') {
                    $updated += Temu2Metric::where('sku', $outSkuSn)->update(['quantity' => $qty]);
                    $updated += Temu2Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($outSkuSn)])
                        ->where('quantity', '!=', $qty)
                        ->update(['quantity' => $qty]);
                    if (Schema::hasTable('temu2_pricing')) {
                        Temu2Pricing::where('sku', $outSkuSn)->update(['quantity' => $qty]);
                    }
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

        Log::info('Temu2 SKU stock sync finished', ['updated' => $updated, 'pages' => $pageNumber - 1]);

        return $updated;
    }

    protected function fetchCurrentTemuGoodsDesc(string $goodsId, string $sku = ''): string
    {
        try {
            if ($sku !== '' && Schema::hasTable('temu2_metrics') && Schema::hasColumn('temu2_metrics', 'sku')) {
                foreach (['goods_desc', 'description_master'] as $column) {
                    if (! Schema::hasColumn('temu2_metrics', $column)) {
                        continue;
                    }

                    $desc = DB::table('temu2_metrics')
                        ->where(function ($q) use ($sku) {
                            $q->where('sku', $sku)
                                ->orWhere('sku', strtoupper($sku))
                                ->orWhere('sku', strtolower($sku));
                        })
                        ->value($column);
                    $desc = trim((string) $desc);
                    if ($desc !== '') {
                        return $desc;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Temu2 DB-first goods_desc fetch failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'error' => $e->getMessage(),
            ]);
        }

        return parent::fetchCurrentTemuGoodsDesc($goodsId, $sku);
    }

    protected function saveGoodsSummaryToTemuMetrics(string $sku, string $goodsSummary): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('temu2_metrics') || ! Schema::hasColumn('temu2_metrics', 'sku')) {
                return false;
            }

            $update = [
                'bullet_points' => $goodsSummary,
                'goods_summary' => $goodsSummary,
            ];
            if (Schema::hasColumn('temu2_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('temu2_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('temu2_metrics', 'created_at')) {
                DB::table('temu2_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Temu2 saveGoodsSummaryToTemuMetrics failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function findTemuGoodsIdBySkuViaApi(string $sku): ?string
    {
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

                $data = $this->postTemuRequest($requestBody);
                if (! ($data['success'] ?? false)) {
                    break;
                }

                foreach (($data['result']['goodsList'] ?? []) as $good) {
                    $outGoodsSn = $good['outGoodsSn'] ?? null;
                    if ($outGoodsSn !== null && trim((string) $outGoodsSn) === $sku) {
                        $goodsId = $good['goodsId'] ?? null;
                        if ($goodsId !== null && $goodsId !== '') {
                            $this->persistTemuMapping($sku, (string) $goodsId, null);

                            return (string) $goodsId;
                        }
                    }

                    foreach (($good['skuInfoList'] ?? []) as $skuInfo) {
                        $skuSn = $skuInfo['skuSn'] ?? $skuInfo['outSkuSn'] ?? null;
                        if ($skuSn !== null && trim((string) $skuSn) === $sku) {
                            $goodsId = $good['goodsId'] ?? null;
                            if ($goodsId !== null && $goodsId !== '') {
                                $skuId = $skuInfo['skuId'] ?? null;
                                $this->persistTemuMapping($sku, (string) $goodsId, $skuId !== null && $skuId !== '' ? (string) $skuId : null);

                                return (string) $goodsId;
                            }
                        }
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu2 getGoodsIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function findTemuSkuIdBySkuViaApi(string $sku): ?string
    {
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

                $data = $this->postTemuRequest($requestBody);
                if (! ($data['success'] ?? false)) {
                    break;
                }

                foreach (($data['result']['skuList'] ?? []) as $item) {
                    $outSkuSn = isset($item['outSkuSn']) ? trim((string) $item['outSkuSn']) : null;
                    if ($outSkuSn === $sku) {
                        $skuId = $item['skuId'] ?? null;
                        if ($skuId !== null && $skuId !== '') {
                            $goodsId = $item['goodsId'] ?? null;
                            $this->persistTemuMapping($sku, $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null, (string) $skuId);

                            return (string) $skuId;
                        }
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu2 getSkuIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function postTemuRequest(array $requestBody): array
    {
        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($this->openApiRouterUrl(), $this->generateSignValue($requestBody));

        return $response->json() ?? [];
    }

    /**
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
            return ['success' => false, 'message' => (string) ($uploaded['message'] ?? 'Temu 2 video upload failed.')];
        }

        $res = $this->updateListingVideos($identifier, $uploaded['urls']);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = trim((string) ($resolved['sku'] ?? $identifier));
        $saved = $this->saveVideoUrlsToMetricsRow('temu2_metrics', $sku, $videos);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Temu 2 listing videos updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $videos;

        return $res;
    }

    /**
     * @return array{connected: bool, shop: ?string, message: string}
     */
    public function pingShopCached(int $ttlSeconds = 600): array
    {
        $key = 'mm.temu2.api.ping.v1';
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && array_key_exists('connected', $cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $ping = $this->testConnection();
        $result = [
            'connected' => ! empty($ping['success']),
            'shop' => null,
            'message' => (string) ($ping['message'] ?? ($ping['success'] ?? false ? 'Temu 2 API connected' : 'Temu 2 API not connected')),
        ];
        try {
            Cache::put($key, $result, now()->addSeconds(max(30, $ttlSeconds)));
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }
}
