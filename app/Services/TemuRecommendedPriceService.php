<?php

namespace App\Services;

use App\Models\TemuMetric;
use App\Models\TemuRPricing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch temu.local.goods.recommendedprice.query and upsert into temu_r_pricing.
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
     * @return array{ok: bool, goodsList: array, error_code: mixed, error_msg: ?string}
     */
    public function fetchBatch(array $goodsIds, int $recommendedPriceType = 1, string $language = 'en'): array
    {
        $goodsIdList = array_values(array_unique(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : $id;
        }, $goodsIds)));

        if ($goodsIdList === []) {
            return ['ok' => false, 'goodsList' => [], 'error_code' => null, 'error_msg' => 'Empty goodsIdList'];
        }

        $requestBody = [
            'type' => 'temu.local.goods.recommendedprice.query',
            'goodsIdList' => $goodsIdList,
            'recommendedPriceType' => $recommendedPriceType,
            'language' => $language,
        ];

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
                ];
            }

            $goodsList = $data['result']['goodsList'] ?? [];

            return [
                'ok' => true,
                'goodsList' => is_array($goodsList) ? $goodsList : [],
                'error_code' => null,
                'error_msg' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('TemuRecommendedPriceService::fetchBatch failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'goodsList' => [],
                'error_code' => null,
                'error_msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch all goods from temu_metrics and upsert recommended prices into temu_r_pricing.
     *
     * @return array{total_goods: int, upserted: int, batches_ok: int, batches_fail: int}
     */
    public function fetchAndInsertAll(int $recommendedPriceType = 1, int $batchSize = 20): array
    {
        $goodsIds = TemuMetric::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->pluck('goods_id')
            ->unique()
            ->values()
            ->all();

        // sku_id / sku / base_price lookup by goods_id + sku_id
        $metricsByGoods = TemuMetric::query()
            ->whereIn('goods_id', $goodsIds)
            ->get(['sku', 'sku_id', 'goods_id', 'base_price'])
            ->groupBy(fn ($m) => (string) $m->goods_id);

        $chunks = array_chunk($goodsIds, max(1, $batchSize));
        $upserted = 0;
        $batchesOk = 0;
        $batchesFail = 0;

        foreach ($chunks as $chunk) {
            $result = $this->fetchBatch($chunk, $recommendedPriceType);
            if (! $result['ok']) {
                $batchesFail++;
                Log::warning('Temu recommended price batch failed', [
                    'error_code' => $result['error_code'],
                    'error_msg' => $result['error_msg'],
                    'goods_count' => count($chunk),
                ]);
                usleep(250000);
                continue;
            }

            $batchesOk++;
            foreach ($result['goodsList'] as $goods) {
                $goodsId = (string) ($goods['goodsId'] ?? '');
                if ($goodsId === '') {
                    continue;
                }

                $metricRows = $metricsByGoods->get($goodsId) ?? collect();

                foreach ($goods['skuList'] ?? [] as $skuRow) {
                    $skuId = isset($skuRow['skuId']) ? (string) $skuRow['skuId'] : null;
                    $amount = $skuRow['recommendedSupplyPrice']['amount']
                        ?? $skuRow['recommendedSupplyPrice']['val']
                        ?? null;

                    if ($skuId === null || $skuId === '' || $amount === null || ! is_numeric($amount)) {
                        continue;
                    }

                    $metric = $metricRows->first(function ($m) use ($skuId) {
                        return (string) $m->sku_id === $skuId;
                    });

                    TemuRPricing::updateOrCreate(
                        ['sku_id' => $skuId],
                        [
                            'goods_id' => $goodsId,
                            'sku' => $metric->sku ?? null,
                            'current_base_price' => $metric->base_price ?? null,
                            'recommended_base_price' => (float) $amount,
                            'pricing_opportunity_type' => 'Recommended price (API)',
                            'action' => 'api_sync',
                            'date_created' => now(),
                        ]
                    );
                    $upserted++;
                }
            }

            usleep(250000);
        }

        return [
            'total_goods' => count($goodsIds),
            'upserted' => $upserted,
            'batches_ok' => $batchesOk,
            'batches_fail' => $batchesFail,
        ];
    }
}
