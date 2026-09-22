<?php

namespace App\Services;

use App\Models\Temu3Metric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Temu3ApiService
{
    public function adsPeriodRanges(): array
    {
        $today = Carbon::now('America/Los_Angeles');

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
     * @return array{ok: bool, result: ?array, error_code: mixed, error_msg: ?string, http_status: ?int}
     */
    public function fetchAdsDataDetailed($goodsId, $startTs = null, $endTs = null): array
    {
        if ($startTs === null || $endTs === null) {
            $l30 = $this->adsPeriodRanges()['L30'];
            $startTs = $startTs ?? $l30['startTs'];
            $endTs = $endTs ?? $l30['endTs'];
        }

        $goodsIdParam = is_numeric($goodsId) ? (int) $goodsId : $goodsId;

        return $this->postAdsRouter([
            'type' => 'temu.searchrec.ad.reports.goods.query',
            'goodsId' => $goodsIdParam,
            'startTs' => (int) $startTs,
            'endTs' => (int) $endTs,
        ], (string) $goodsId);
    }

    /**
     * @param  array<int, string|int>  $goodsIds
     * @return array{statuses: array<string, string>, details: array<string, array>, failed: array<int, string>, error: ?string}
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
            return ['statuses' => [], 'details' => [], 'failed' => [], 'error' => null];
        }

        $statuses = [];
        $details = [];
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
                $statuses[$gid] = TemuApiService::statusFromAdDetail($item);
                $details[$gid] = $item;
                $seen[$gid] = true;
            }

            foreach ($chunk as $gid) {
                $key = (string) $gid;
                if (! isset($statuses[$key]) && ! isset($seen[$key])) {
                    $statuses[$key] = 'No ad';
                    $details[$key] = ['goodsId' => is_numeric($gid) ? (int) $gid : $gid, 'adShowStatus' => 0];
                }
            }

            usleep(150000);
        }

        return [
            'statuses' => $statuses,
            'details' => $details,
            'failed' => $failed,
            'error' => $error,
        ];
    }

    /**
     * @return array<int, mixed>|null
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
            if ($v === null || $v === []) {
                return [];
            }
            if (! is_array($v)) {
                continue;
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

    /**
     * @return array{ok: bool, result: mixed, error_code: mixed, error_msg: ?string, http_status: ?int, request: array}
     */
    protected function postAdsRouter(array $requestBody, string $goodsId, int $timeoutSeconds = 60): array
    {
        $signedRequest = $this->generateSignValue($requestBody);
        $last = [
            'ok' => false,
            'result' => null,
            'error_code' => null,
            'error_msg' => 'Temu ads request not attempted',
            'http_status' => null,
            'request' => $requestBody,
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(max(5, $timeoutSeconds))
                    ->withoutVerifying()
                    ->post($this->openApiUrl(), $signedRequest);
                $httpStatus = $response->status();
                $data = $response->json();

                if ($response->failed() || ! is_array($data)) {
                    $last = [
                        'ok' => false,
                        'result' => is_array($data) ? ($data['result'] ?? $data) : null,
                        'error_code' => $httpStatus,
                        'error_msg' => is_array($data) ? (string) ($data['errorMsg'] ?? 'HTTP '.$httpStatus) : 'HTTP '.$httpStatus,
                        'http_status' => $httpStatus,
                        'request' => $requestBody,
                    ];
                    if ($attempt < 3 && $this->isTemuSslOrTimeoutError($last['error_msg'])) {
                        usleep(400000 * $attempt);
                        continue;
                    }

                    return $last;
                }

                if (! ($data['success'] ?? false)) {
                    $errorCode = $data['errorCode'] ?? null;
                    $errorMsg = (string) ($data['errorMsg'] ?? 'Unknown error');
                    Log::error('Temu 3 ads router API error', [
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
                $last = [
                    'ok' => false,
                    'result' => null,
                    'error_code' => null,
                    'error_msg' => $e->getMessage(),
                    'http_status' => null,
                    'request' => $requestBody,
                ];
                if ($attempt < 3 && $this->isTemuSslOrTimeoutError($e->getMessage())) {
                    usleep(400000 * $attempt);
                    continue;
                }

                return $last;
            }
        }

        return $last;
    }

    protected function isTemuSslOrTimeoutError(?string $message): bool
    {
        $msg = strtolower((string) $message);

        return str_contains($msg, 'curl error 28')
            || str_contains($msg, 'ssl connection timeout')
            || str_contains($msg, 'ssl certificate')
            || str_contains($msg, 'operation timed out')
            || str_contains($msg, 'connection reset')
            || str_contains($msg, 'connection timed out');
    }

    public function openApiUrl(): string
    {
        return rtrim((string) config('services.temu3.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
    }

    public function isConfigured(): bool
    {
        $appKey = trim((string) (config('services.temu3.app_key') ?? ''));
        $secret = trim((string) (config('services.temu3.secret_key') ?? ''));
        $token = trim((string) (config('services.temu3.access_token') ?? ''));

        return $appKey !== '' && $secret !== '' && $token !== '';
    }

    public function getGoodsIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }
        $goodsId = Temu3Metric::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhere('sku_id', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            })
            ->value('goods_id');

        return ($goodsId !== null && $goodsId !== '') ? (string) $goodsId : null;
    }

    public function getSkuIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }
        $skuId = Temu3Metric::query()
            ->where(function ($q) use ($sku) {
                $q->where('sku', $sku)
                    ->orWhere('sku_id', $sku)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
            })
            ->value('sku_id');

        return ($skuId !== null && $skuId !== '') ? (string) $skuId : null;
    }

    /**
     * Live supplier/base prices via bg.local.goods.sku.list.price.query.
     *
     * @param  list<array{goodsId:int|string, skuIdList:list<int|string>}>  $queryList
     * @return array<string, float> skuId => amount
     */
    public function querySkuSupplierPrices(array $queryList): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Temu 3 querySkuSupplierPrices skipped: credentials missing');

            return [];
        }

        $normalized = [];
        foreach ($queryList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $goodsId = $row['goodsId'] ?? null;
            $skuIds = $row['skuIdList'] ?? [];
            if ($goodsId === null || $goodsId === '' || ! is_array($skuIds) || $skuIds === []) {
                continue;
            }
            $ids = [];
            foreach ($skuIds as $skuId) {
                $n = (int) $skuId;
                if ($n > 0) {
                    $ids[$n] = true;
                }
            }
            if ($ids === []) {
                continue;
            }
            $normalized[] = [
                'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
                'skuIdList' => array_map('intval', array_keys($ids)),
            ];
        }
        if ($normalized === []) {
            return [];
        }

        try {
            $response = Http::timeout(45)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->openApiUrl(), $this->generateSignValue([
                    'type' => 'bg.local.goods.sku.list.price.query',
                    'querySupplierPriceBaseList' => $normalized,
                    'language' => 'en',
                ]));
        } catch (\Throwable $e) {
            Log::warning('Temu 3 querySkuSupplierPrices failed', ['error' => $e->getMessage()]);

            return [];
        }

        $data = $response->json() ?? [];
        if ($response->failed() || ! ($data['success'] ?? false)) {
            Log::warning('Temu 3 querySkuSupplierPrices failed', [
                'status' => $response->status(),
                'message' => $data['errorMsg'] ?? $data['message'] ?? null,
            ]);

            return [];
        }

        $result = is_array($data['result'] ?? null) ? $data['result'] : [];
        $goodsPriceList = $result['openapiGoodsSupplierPriceDTOList']
            ?? $result['skuPriceInfoList']
            ?? [];

        $out = [];
        foreach ($goodsPriceList as $goodsBlock) {
            if (! is_array($goodsBlock)) {
                continue;
            }
            $skuPriceList = $goodsBlock['openapiSkuSupplierPriceDTOList'] ?? null;
            if (is_array($skuPriceList)) {
                foreach ($skuPriceList as $skuPrice) {
                    if (is_array($skuPrice)) {
                        $this->collectQueriedSkuPrice($out, $skuPrice);
                    }
                }
                continue;
            }
            $this->collectQueriedSkuPrice($out, $goodsBlock);
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $out
     * @param  array<string, mixed>  $skuPrice
     */
    private function collectQueriedSkuPrice(array &$out, array $skuPrice): void
    {
        $skuId = $skuPrice['skuId'] ?? $skuPrice['sku_id'] ?? null;
        $amount = $skuPrice['supplierPrice']['amount']
            ?? $skuPrice['supplierPrice']['val']
            ?? $skuPrice['basePrice']
            ?? null;
        if ($skuId === null || $amount === null || ! is_numeric($amount)) {
            return;
        }
        $out[(string) (int) $skuId] = round((float) $amount, 2);
    }

    protected function generateSignValue(array $requestBody): array
    {
        $appKey = trim((string) (config('services.temu3.app_key') ?? ''));
        $appSecret = trim((string) (config('services.temu3.secret_key') ?? ''));
        $accessToken = trim((string) (config('services.temu3.access_token') ?? ''));
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
        $params['sign'] = strtoupper(md5($appSecret.$temp.$appSecret));

        return array_merge($params, $requestBody);
    }
}
