<?php

namespace App\Services;

use App\Models\TemuMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch temu.local.goods.recommendedprice.query and store into temu_metrics.recommended_base_price.
 */
class TemuRecommendedPriceService
{
    public function __construct(protected TemuApiService $temuApiService)
    {
    }

    /**
     * Call recommended-price API for a batch of goods IDs.
     *
     * @param  array<int, int|string>  $goodsIds
     * @param  array<string, mixed>  $extra  Optional request overrides (language, recommendedPriceType, omit flags)
     * @return array{ok: bool, goodsList: array, error_code: mixed, error_msg: ?string, request: array}
     */
    public function fetchBatch(array $goodsIds, int $recommendedPriceType = 1, string $language = 'en', array $extra = []): array
    {
        $goodsIdList = array_values(array_unique(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : $id;
        }, $goodsIds)));

        if ($goodsIdList === []) {
            return [
                'ok' => false,
                'goodsList' => [],
                'error_code' => null,
                'error_msg' => 'Empty goodsIdList',
                'request' => [],
            ];
        }

        $requestBody = [
            'type' => 'temu.local.goods.recommendedprice.query',
            'goodsIdList' => $goodsIdList,
        ];

        $omitType = (bool) ($extra['omit_type'] ?? false);
        $omitLang = (bool) ($extra['omit_language'] ?? false);

        if (! $omitType) {
            $requestBody['recommendedPriceType'] = $extra['recommendedPriceType'] ?? $recommendedPriceType;
        }
        if (! $omitLang && $language !== '') {
            $requestBody['language'] = $extra['language'] ?? $language;
        }

        $signed = $this->temuApiService->signRequest($requestBody);
        $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(60);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $signed);
            $data = $response->json() ?? [];

            if ($response->failed() || ! ($data['success'] ?? false)) {
                return [
                    'ok' => false,
                    'goodsList' => [],
                    'error_code' => $data['errorCode'] ?? $response->status(),
                    'error_msg' => $data['errorMsg'] ?? ('HTTP '.$response->status()),
                    'request' => $requestBody,
                ];
            }

            $goodsList = $data['result']['goodsList']
                ?? $data['result']['openapiGoodsRecommendedPriceList']
                ?? [];

            return [
                'ok' => true,
                'goodsList' => is_array($goodsList) ? $goodsList : [],
                'error_code' => null,
                'error_msg' => null,
                'request' => $requestBody,
                'raw_result_keys' => array_keys($data['result'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::error('TemuRecommendedPriceService::fetchBatch failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'goodsList' => [],
                'error_code' => null,
                'error_msg' => $e->getMessage(),
                'request' => $requestBody,
            ];
        }
    }

    /**
     * Probe a few request variants for one goods ID (for diagnosing 150010003 / permission errors).
     *
     * @return array<int, array{label: string, ok: bool, error_code: mixed, error_msg: ?string, sku_count: int}>
     */
    public function probe(string|int $goodsId): array
    {
        $variants = [
            ['label' => 'type=1 + language=en', 'type' => 1, 'lang' => 'en', 'extra' => []],
            ['label' => 'type=1 no language', 'type' => 1, 'lang' => '', 'extra' => ['omit_language' => true]],
            ['label' => 'type=2 no language', 'type' => 2, 'lang' => '', 'extra' => ['omit_language' => true]],
            ['label' => 'no type no language', 'type' => 1, 'lang' => '', 'extra' => ['omit_type' => true, 'omit_language' => true]],
            ['label' => 'type=1 language=en-US', 'type' => 1, 'lang' => 'en-US', 'extra' => []],
        ];

        $out = [];
        foreach ($variants as $v) {
            $r = $this->fetchBatch([(int) $goodsId], (int) $v['type'], (string) $v['lang'], $v['extra']);
            $skuCount = 0;
            foreach ($r['goodsList'] as $g) {
                $skuCount += count($g['skuList'] ?? []);
            }
            $out[] = [
                'label' => $v['label'],
                'ok' => $r['ok'],
                'error_code' => $r['error_code'],
                'error_msg' => $r['error_msg'],
                'sku_count' => $skuCount,
                'result_keys' => $r['raw_result_keys'] ?? [],
            ];
            usleep(200000);
        }

        return $out;
    }

    /**
     * Extract recommended amount from a sku row (flexible field names).
     */
    private function extractRecommendedAmount(array $skuRow): ?float
    {
        $candidates = [
            $skuRow['recommendedSupplyPrice']['amount'] ?? null,
            $skuRow['recommendedSupplyPrice']['val'] ?? null,
            $skuRow['recommendSupplyPrice']['amount'] ?? null,
            $skuRow['recommendedBasePrice']['amount'] ?? null,
            $skuRow['recommendBasePrice']['amount'] ?? null,
            $skuRow['recommendedPrice']['amount'] ?? null,
            $skuRow['recommendedSupplyPrice'] ?? null,
            $skuRow['recommendedBasePrice'] ?? null,
        ];

        foreach ($candidates as $amount) {
            if (is_array($amount)) {
                $amount = $amount['amount'] ?? $amount['val'] ?? null;
            }
            if ($amount !== null && is_numeric($amount)) {
                return (float) $amount;
            }
        }

        return null;
    }

    /**
     * Fetch all goods and update temu_metrics.recommended_base_price by sku_id.
     *
     * @return array{total_goods: int, upserted: int, batches_ok: int, batches_fail: int, last_error: ?string, empty_ok_batches: int}
     */
    public function fetchAndInsertAll(int $recommendedPriceType = 1, int $batchSize = 20, array $requestExtra = []): array
    {
        $goodsIds = TemuMetric::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->pluck('goods_id')
            ->unique()
            ->values()
            ->all();

        $chunks = array_chunk($goodsIds, max(1, $batchSize));
        $upserted = 0;
        $batchesOk = 0;
        $batchesFail = 0;
        $emptyOk = 0;
        $lastError = null;

        foreach ($chunks as $chunk) {
            $result = $this->fetchBatch($chunk, $recommendedPriceType, 'en', $requestExtra);
            if (! $result['ok']) {
                $batchesFail++;
                $lastError = '['.($result['error_code'] ?? '?').'] '.($result['error_msg'] ?? 'unknown');
                Log::warning('Temu recommended price batch failed', [
                    'error_code' => $result['error_code'],
                    'error_msg' => $result['error_msg'],
                    'goods_count' => count($chunk),
                    'sample_goods' => $chunk[0] ?? null,
                ]);
                usleep(250000);
                continue;
            }

            $batchesOk++;
            $batchUpserts = 0;

            foreach ($result['goodsList'] as $goods) {
                $goodsId = (string) ($goods['goodsId'] ?? '');

                foreach ($goods['skuList'] ?? [] as $skuRow) {
                    $skuId = isset($skuRow['skuId']) ? (string) $skuRow['skuId'] : null;
                    $amount = $this->extractRecommendedAmount(is_array($skuRow) ? $skuRow : []);

                    if ($skuId === null || $skuId === '' || $amount === null) {
                        continue;
                    }

                    $n = TemuMetric::where('sku_id', $skuId)->update([
                        'recommended_base_price' => $amount,
                    ]);

                    // Fallback: match by goods_id if sku_id row missing
                    if (! $n && $goodsId !== '') {
                        $n = TemuMetric::where('goods_id', $goodsId)
                            ->where(function ($q) use ($skuId) {
                                $q->whereNull('sku_id')->orWhere('sku_id', $skuId);
                            })
                            ->limit(1)
                            ->update(['recommended_base_price' => $amount]);
                    }

                    if ($n) {
                        $upserted += $n;
                        $batchUpserts += $n;
                    }
                }
            }

            if ($batchUpserts === 0) {
                $emptyOk++;
                Log::info('Temu recommended price batch OK but empty', [
                    'goods_count' => count($chunk),
                    'result_keys' => $result['raw_result_keys'] ?? [],
                    'goods_list_count' => count($result['goodsList']),
                ]);
            }

            usleep(250000);
        }

        return [
            'total_goods' => count($goodsIds),
            'upserted' => $upserted,
            'batches_ok' => $batchesOk,
            'batches_fail' => $batchesFail,
            'empty_ok_batches' => $emptyOk,
            'last_error' => $lastError,
        ];
    }
}
