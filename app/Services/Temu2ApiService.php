<?php

namespace App\Services;

use App\Models\Temu2Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Temu2ApiService extends TemuApiService
{
    protected function resolveTemuGoodsAndSku(string $identifier): array
    {
        $id = trim($identifier);
        if ($id === '') {
            return ['sku' => '', 'goods_id' => null];
        }

        $row = Temu2Pricing::query()
            ->where('sku', $id)
            ->orWhere('sku', strtoupper($id))
            ->orWhere('sku', strtolower($id))
            ->first();

        if (! $row) {
            $row = Temu2Pricing::query()
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
        $price = Temu2Pricing::where('sku', trim($sku))->value('base_price');
        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        return parent::getProductPrice($sku);
    }

    public function getGoodsIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $goodsId = Temu2Pricing::where('sku', $sku)->value('goods_id');
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

        $skuId = Temu2Pricing::where('sku', $sku)->value('sku_id');
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
            if (! Schema::hasTable('temu2_pricing') || ! Schema::hasColumn('temu2_pricing', 'sku')) {
                return;
            }

            $update = [];
            if ($goodsId !== null && $goodsId !== '' && Schema::hasColumn('temu2_pricing', 'goods_id')) {
                $update['goods_id'] = $goodsId;
            }
            if ($skuId !== null && $skuId !== '' && Schema::hasColumn('temu2_pricing', 'sku_id')) {
                $update['sku_id'] = $skuId;
            }
            if ($update === []) {
                return;
            }

            Temu2Pricing::updateOrCreate(['sku' => $sku], $update);
        } catch (\Throwable $e) {
            Log::warning('Temu2 persistTemuMapping failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);
        }
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

        $response = $request->post('https://openapi-b-us.temu.com/openapi/router', $this->generateSignValue($requestBody));

        return $response->json() ?? [];
    }
}
