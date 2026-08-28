<?php

namespace App\Services;

use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use App\Models\TemuMetric;
use App\Models\TemuPricing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * After an S PRC auto-push, pull live listing Price for only the SKUs that were pushed.
 */
class ChannelPushedPricePullService
{
    /**
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string,skipped?:bool}>
     */
    public function pullSkus(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtoupper(trim((string) $s));
        }, $skus), static fn ($s) => $s !== '')));
        $skus = array_slice($skus, 0, 100);
        if ($skus === []) {
            return [];
        }

        if (in_array($channel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true)) {
            $mp = $channel === 'ebay2op' ? 'ebay2' : $channel;
            $items = array_map(static fn ($sku) => ['sku' => $sku, 'marketplace' => $mp], $skus);

            return app(PefEbayPricePullService::class)->pullItems($items);
        }

        if (in_array($channel, ['shopify_b2b', 'shopify_b2c'], true)) {
            return $this->pullShopifyStore($skus, $channel);
        }

        if (in_array($channel, ['temu', 'temu2'], true)) {
            return $this->pullTemu($skus, $channel);
        }

        return array_map(static fn ($sku) => [
            'success' => false,
            'sku' => $sku,
            'marketplace' => $channel,
            'price' => null,
            'sprice' => null,
            'message' => 'live pull not available for this channel',
            'skipped' => true,
        ], $skus);
    }

    /**
     * Live Temu / Temu 2 supplier (base) price via bg.local.goods.sku.list.price.query.
     *
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,base_price?:float,sprice:?float,message:string,skipped?:bool}>
     */
    private function pullTemu(array $skus, string $channel): array
    {
        $temu2 = $channel === 'temu2';
        $api = $temu2 ? app(Temu2ApiService::class) : app(TemuApiService::class);
        $metricClass = $temu2 ? Temu2Metric::class : TemuMetric::class;
        $pricingClass = $temu2 ? Temu2Pricing::class : TemuPricing::class;
        $pricingTable = $temu2 ? 'temu2_pricing' : 'temu_pricing';

        $wanted = [];
        foreach ($skus as $sku) {
            $key = strtoupper(trim((string) $sku));
            if ($key !== '') {
                $wanted[$key] = trim((string) $sku);
            }
        }

        $rows = $wanted === []
            ? collect()
            : $metricClass::query()
                ->where(function ($q) use ($wanted) {
                    foreach (array_keys($wanted) as $key) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$key]);
                    }
                })
                ->get(['sku', 'sku_id', 'goods_id']);

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[strtoupper(trim((string) $row->sku))] = $row;
        }

        $queryByGoods = [];
        $skuIdToKey = [];
        foreach ($wanted as $key => $orig) {
            $row = $byKey[$key] ?? null;
            $goodsId = $row ? trim((string) ($row->goods_id ?? '')) : '';
            $skuId = $row ? trim((string) ($row->sku_id ?? '')) : '';
            if ($goodsId === '') {
                $goodsId = (string) ($api->getGoodsIdBySku($orig) ?? '');
            }
            if ($skuId === '') {
                $skuId = (string) ($api->getSkuIdBySku($orig) ?? '');
            }
            $skuIdInt = (int) $skuId;
            if ($goodsId === '' || $skuIdInt <= 0) {
                continue;
            }
            $queryByGoods[$goodsId][$skuIdInt] = $key;
            $skuIdToKey[(string) $skuIdInt] = $key;
        }

        $queryList = [];
        foreach ($queryByGoods as $goodsId => $ids) {
            $queryList[] = [
                'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
                'skuIdList' => array_map('intval', array_keys($ids)),
            ];
        }

        $prices = $queryList !== [] ? $api->querySkuSupplierPrices($queryList) : [];

        $out = [];
        foreach ($wanted as $key => $orig) {
            $skuId = null;
            foreach ($skuIdToKey as $sid => $mapped) {
                if ($mapped === $key) {
                    $skuId = $sid;
                    break;
                }
            }
            $live = $skuId !== null
                ? ($prices[$skuId] ?? $prices[(string) ((int) $skuId)] ?? null)
                : null;
            if (! ($live > 0)) {
                $out[] = [
                    'success' => false,
                    'sku' => $orig,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => $skuId
                        ? 'Live Temu base price not returned'
                        : 'goods_id / sku_id missing — run Temu metrics fetch',
                ];
                continue;
            }

            try {
                $metricClass::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                    ->update(['base_price' => $live]);
                if (Schema::hasTable($pricingTable)) {
                    $pricingClass::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                        ->update(['base_price' => $live]);
                }
            } catch (\Throwable $e) {
                Log::warning('Temu live price persist failed', [
                    'sku' => $orig,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }

            $out[] = [
                'success' => true,
                'sku' => $orig,
                'marketplace' => $channel,
                'price' => $live,
                'base_price' => $live,
                'sprice' => null,
                'message' => 'Pulled Temu base $'.number_format($live, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullShopifyStore(array $skus, string $channel): array
    {
        $sync = app(StorePriceSyncService::class);
        $out = [];
        foreach ($skus as $sku) {
            try {
                $sync->sync($sku);
                $price = $this->shopifyLivePrice($sku, $channel);
                $ok = $price !== null && $price > 0;
                $out[] = [
                    'success' => $ok,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => $ok ? round($price, 2) : null,
                    'sprice' => null,
                    'message' => $ok ? ('Pulled store price $'.number_format($price, 2)) : 'Store price not found',
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Shopify pull failed', [
                    'sku' => $sku,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }

    private function shopifyLivePrice(string $sku, string $channel): ?float
    {
        $row = StoreListingPrice::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->orderByDesc('is_variant')
            ->first();
        if ($row && $row->selling_price !== null && (float) $row->selling_price > 0) {
            return (float) $row->selling_price;
        }

        $ss = ShopifySku::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first();
        if (! $ss) {
            return null;
        }
        $price = $channel === 'shopify_b2b'
            ? (float) ($ss->b2b_price ?: $ss->price)
            : (float) ($ss->b2c_price ?: $ss->price);

        return $price > 0 ? $price : null;
    }
}
