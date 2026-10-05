<?php

namespace App\Services;

use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\AlibabaSheetPrice;
use App\Models\DobaMetric;
use App\Models\NeweggPricing;
use App\Models\ShopifySku;
use App\Models\StoreListingPrice;
use App\Models\Temu2Metric;
use App\Models\Temu2Pricing;
use App\Models\Temu3Metric;
use App\Models\Temu3Pricing;
use App\Models\TemuMetric;
use App\Models\TemuPricing;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * After an S PRC auto-push, pull live listing Price for only the SKUs that were pushed.
 */
class ChannelPushedPricePullService
{
    /**
     * @param  list<string>  $skus
     * @param  array<string, float|int|string>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string,skipped?:bool}>
     */
    public function pullSkus(string $channel, array $skus, array $expectedBySku = []): array
    {
        $channel = strtolower(trim($channel));
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtoupper(trim((string) $s));
        }, $skus), static fn ($s) => $s !== '')));
        $skus = array_slice($skus, 0, 100);
        if ($skus === []) {
            return [];
        }
        $expected = [];
        foreach ($expectedBySku as $key => $value) {
            $skuKey = strtoupper(trim((string) $key));
            if ($skuKey !== '' && is_numeric($value) && (float) $value > 0) {
                $expected[$skuKey] = round((float) $value, 2);
            }
        }

        if (in_array($channel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true)) {
            $mp = $channel === 'ebay2op' ? 'ebay2' : $channel;
            $items = array_map(static fn ($sku) => ['sku' => $sku, 'marketplace' => $mp], $skus);

            return app(PefEbayPricePullService::class)->pullItems($items);
        }

        if ($channel === 'shopify_b2c') {
            return $this->pullShopifyB2cAdmin($skus, $expected);
        }

        if ($channel === 'shopify_b2b') {
            return $this->pullShopifyStore($skus, $channel, $expected);
        }

        if (in_array($channel, ['temu', 'temu2', 'temu3'], true)) {
            return $this->pullTemu($skus, $channel, $expected);
        }

        if ($channel === 'newegg') {
            return $this->pullNewegg($skus, $expected);
        }

        if (in_array($channel, ['tiktok', 'tiktok2'], true)) {
            return $this->pullTikTok($skus, $channel, $expected);
        }

        if (in_array($channel, ['doba', 'doba_withoutship'], true)) {
            return $this->pullDoba($skus, $channel, $expected);
        }

        if (in_array($channel, ['macys', 'macy'], true)) {
            return $this->pullMacys($skus, $expected);
        }

        if ($channel === 'topdawg') {
            return $this->pullTopDawg($skus, $expected);
        }

        if ($channel === 'alibaba') {
            return $this->pullAlibaba($skus, $expected);
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
     * Price the blue badge compares. Keep the calculated S PRC when the live
     * listing is only a few cents off. Temu stores supplier base, not full S PRC.
     *
     * @param  array<string, float>  $expectedBySku
     */
    private function listingPriceToStore(string $channel, string $sku, float $live, array $expectedBySku): float
    {
        $key = strtoupper(trim($sku));
        $expected = $expectedBySku[$key] ?? null;
        if (! ($expected > 0)) {
            $expected = ChannelLivePriceSync::lookupPushed($channel, $sku);
        }
        if (in_array($channel, ['temu', 'temu2', 'temu3'], true) && $expected > 0) {
            $base = TemuShopifySalesService::computePushBaseFromSprice((float) $expected);
            $expected = ($base !== null && $base > 0) ? $base : null;
        }
        $kept = \App\Support\PushedListingPrice::keepCalculated(
            $live,
            $expected !== null ? (float) $expected : null
        );

        return $kept ?? round($live, 2);
    }

    /**
     * Live Temu / Temu 2 / Temu 3 supplier (base) price via bg.local.goods.sku.list.price.query.
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,base_price?:float,sprice:?float,message:string,skipped?:bool}>
     */
    private function pullTemu(array $skus, string $channel, array $expectedBySku = []): array
    {
        if ($channel === 'temu3') {
            $api = app(Temu3ApiService::class);
            $metricClass = Temu3Metric::class;
            $pricingClass = Temu3Pricing::class;
            $pricingTable = 'temu3_pricing';
        } elseif ($channel === 'temu2') {
            $api = app(Temu2ApiService::class);
            $metricClass = Temu2Metric::class;
            $pricingClass = Temu2Pricing::class;
            $pricingTable = 'temu2_pricing';
        } else {
            $api = app(TemuApiService::class);
            $metricClass = TemuMetric::class;
            $pricingClass = TemuPricing::class;
            $pricingTable = 'temu_pricing';
        }

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

            $store = $this->listingPriceToStore($channel, $orig, (float) $live, $expectedBySku);
            try {
                $metricClass::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                    ->update(['base_price' => $store]);
                if (Schema::hasTable($pricingTable)) {
                    $pricingClass::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$key])
                        ->update(['base_price' => $store]);
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
                'price' => $store,
                'base_price' => $store,
                'sprice' => null,
                'message' => 'Pulled Temu base $'.number_format($store, 2),
            ];
        }

        return $out;
    }

    /**
     * Live TopDawg listing price via SupplierProduct/list (same source as TD Price).
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullTopDawg(array $skus, array $expectedBySku = []): array
    {
        $api = app(TopDawgApiService::class);
        $out = [];
        foreach ($skus as $i => $sku) {
            try {
                $expected = $expectedBySku[strtoupper(trim($sku))] ?? null;
                $live = $api->pullLiveListedPrice($sku, $expected);
                $price = is_array($live) ? (float) ($live['price'] ?? 0) : 0.0;
                $stale = is_array($live) && ! empty($live['stale']);
                if (! ($price > 0)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'topdawg',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Live TopDawg price not returned',
                    ];
                } elseif ($stale) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'topdawg',
                        'price' => $price,
                        'sprice' => null,
                        'message' => 'TopDawg still catching up',
                    ];
                } else {
                    $out[] = [
                        'success' => true,
                        'sku' => $sku,
                        'marketplace' => 'topdawg',
                        'price' => $price,
                        'sprice' => null,
                        'message' => 'Pulled TopDawg Price $'.number_format($price, 2),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price TopDawg pull failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'topdawg',
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }

            if ($i < count($skus) - 1) {
                usleep(150000);
            }
        }

        return $out;
    }

    /**
     * Live Alibaba unit price for the SKUs that were just pushed.
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullAlibaba(array $skus, array $expectedBySku = []): array
    {
        $api = app(AlibabaApiService::class);
        $out = [];
        foreach ($skus as $sku) {
            try {
                $sheet = AlibabaSheetPrice::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                    ->first();
                $productId = $sheet ? trim((string) $sheet->product_id) : '';
                if ($productId === '' && \Illuminate\Support\Facades\Schema::hasTable('alibaba_metrics')) {
                    $productId = trim((string) AlibabaMetric::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                        ->value('product_id'));
                }
                if ($productId === '') {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'alibaba',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'No Alibaba product id for this SKU.',
                    ];
                    continue;
                }

                $info = $api->getProductInfo($productId);
                if (empty($info['success'])) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'alibaba',
                        'price' => null,
                        'sprice' => null,
                        'message' => (string) ($info['message'] ?? 'Alibaba product lookup failed.'),
                    ];
                    continue;
                }

                $product = is_array($info['data'] ?? null) ? $info['data'] : [];
                $rows = $api->extractSkuRowsFromProductInfo($product, $productId);
                $want = strtoupper(trim($sku));
                $live = 0.0;
                foreach ($rows as $row) {
                    if (strtoupper(trim((string) ($row['sku'] ?? ''))) === $want) {
                        $live = (float) ($row['price'] ?? 0);
                        break;
                    }
                }
                if (! ($live > 0) && count($rows) === 1) {
                    $live = (float) ($rows[0]['price'] ?? 0);
                }
                if (! ($live > 0)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'alibaba',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Alibaba listing price was not returned.',
                    ];
                    continue;
                }

                $price = $this->listingPriceToStore('alibaba', $sku, $live, $expectedBySku);
                if ($sheet) {
                    $sheet->sku_price = $price;
                    $sheet->save();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_metrics')) {
                    AlibabaMetric::query()->where('product_id', $productId)->update(['price' => $price]);
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_pricing_prices')) {
                    AlibabaPricingPrice::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [$want])
                        ->update(['price' => $price]);
                }

                $out[] = [
                    'success' => true,
                    'sku' => $sku,
                    'marketplace' => 'alibaba',
                    'price' => $price,
                    'sprice' => null,
                    'message' => 'Pulled Alibaba Price $'.number_format($price, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Alibaba pull failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'alibaba',
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }

    /**
     * Live Macy listed price via MCM OF21 (same source as /macys-pricing MC Price).
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullMacys(array $skus, array $expectedBySku = []): array
    {
        $api = app(MacysApiService::class);
        $out = [];
        foreach ($skus as $i => $sku) {
            try {
                $expected = $expectedBySku[strtoupper(trim($sku))] ?? null;
                $live = $api->pullLiveListedPrice($sku, $expected);
                $price = is_array($live) ? (float) ($live['price'] ?? 0) : 0.0;
                $stale = is_array($live) && ! empty($live['stale']);
                if (! ($price > 0)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'macys',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Live Macy MCM price not returned',
                    ];
                } elseif ($stale) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'macys',
                        'price' => $price,
                        'sprice' => null,
                        'message' => 'MCM still catching up',
                    ];
                } else {
                    $out[] = [
                        'success' => true,
                        'sku' => $sku,
                        'marketplace' => 'macys',
                        'price' => $price,
                        'sprice' => null,
                        'message' => 'Pulled Macy Price $'.number_format($price, 2),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Macy pull failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'macys',
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }

            if ($i < count($skus) - 1) {
                usleep(150000);
            }
        }

        return $out;
    }

    /**
     * GET live variant.price from Shopify Admin (same store the B2C push writes) and persist Price.
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullShopifyB2cAdmin(array $skus, array $expectedBySku = []): array
    {
        $out = [];
        foreach ($skus as $i => $sku) {
            try {
                $row = ShopifySku::firstForProductSku($sku);
                $variantId = $row ? trim((string) ($row->variant_id ?? '')) : '';
                if ($variantId === '') {
                    $variantId = (string) (ShopifySku::mainCatalogVariantId($sku) ?? '');
                    if ($row && $variantId !== '') {
                        $row->variant_id = $variantId;
                    }
                }
                if ($variantId === '') {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'shopify_b2c',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Shopify variant not found',
                    ];
                    continue;
                }

                $live = $this->fetchShopifyAdminVariantPrice($variantId);
                if ($live > 0) {
                    $live = $this->listingPriceToStore('shopify_b2c', $sku, $live, $expectedBySku);
                }
                if (! ($live > 0)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => 'shopify_b2c',
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Live Shopify Price not returned',
                    ];
                    continue;
                }

                $this->persistShopifyB2cLivePrice($row, $live);
                $out[] = [
                    'success' => true,
                    'sku' => $sku,
                    'marketplace' => 'shopify_b2c',
                    'price' => $live,
                    'sprice' => null,
                    'message' => 'Pulled Shopify Price $'.number_format($live, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Shopify B2C Admin pull failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'shopify_b2c',
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }

            if ($i < count($skus) - 1) {
                usleep(150000);
            }
        }

        return $out;
    }

    private function fetchShopifyAdminVariantPrice(string $variantId): ?float
    {
        $storeUrl = 'https://'.preg_replace('#^https?://#', '', (string) config('services.shopify.store_url'));
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        if (! $storeUrl || ! $token) {
            throw new \RuntimeException('Shopify B2C credentials not configured');
        }

        $url = rtrim($storeUrl, '/').'/admin/api/2025-01/variants/'.rawurlencode($variantId).'.json';
        $maxAttempts = 10;
        $response = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            ShopifyAdminCallGate::acquire(ShopifyAdminCallGate::STORE_B2C);
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(45)->connectTimeout(20)->get($url);
            ShopifyAdminCallGate::record($response, ShopifyAdminCallGate::STORE_B2C);

            if (! ShopifyAdminCallGate::isRateLimited($response)) {
                break;
            }
        }

        if (! $response || ! $response->successful()) {
            Log::warning('Shopify B2C Admin variant GET failed', [
                'variant_id' => $variantId,
                'status' => $response ? $response->status() : null,
                'body' => $response ? $response->body() : null,
            ]);

            return null;
        }

        $price = $response->json('variant.price');

        return is_numeric($price) && (float) $price > 0 ? round((float) $price, 2) : null;
    }

    private function persistShopifyB2cLivePrice(ShopifySku $row, float $price): void
    {
        $row->price = $price;
        if (Schema::hasColumn('shopify_skus', 'b2c_price')) {
            $row->b2c_price = $price;
        }
        if (Schema::hasColumn('shopify_skus', 'price_updated_manually_at')) {
            $row->price_updated_manually_at = now();
        }
        $row->save();
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string}>
     */
    private function pullShopifyStore(array $skus, string $channel, array $expectedBySku = []): array
    {
        $sync = app(StorePriceSyncService::class);
        $out = [];
        foreach ($skus as $sku) {
            try {
                $sync->sync($sku);
                $raw = $this->shopifyLivePrice($sku, $channel);
                $price = $raw;
                if ($raw !== null && $raw > 0) {
                    $price = $this->listingPriceToStore($channel, $sku, $raw, $expectedBySku);
                    if (abs($price - $raw) >= 0.005) {
                        StoreListingPrice::query()
                            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                            ->update(['selling_price' => $price]);
                    }
                }
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

    /**
     * Live TikTok / TikTok 2 sale price via searchProducts / getProduct.
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string,skipped?:bool}>
     */
    private function pullTikTok(array $skus, string $channel, array $expectedBySku = []): array
    {
        $model = $channel === 'tiktok2' ? TikTokProductTwo::class : TikTokProduct::class;
        $service = $channel === 'tiktok2'
            ? app(TikTok2ShopService::class)
            : app(TikTokShopService::class);
        $cfgKey = $channel === 'tiktok2' ? 'tiktok2' : 'tiktok';

        if (! $service->isAuthenticated()) {
            $access = (string) config("services.{$cfgKey}.access_token", '');
            $refresh = (string) config("services.{$cfgKey}.refresh_token", '');
            if ($access !== '') {
                $service->setTokens($access, $refresh !== '' ? $refresh : null);
            }
        }
        if ($service->isAuthenticated()) {
            $service->refreshAccessToken();
        }

        $out = [];
        foreach ($skus as $i => $sku) {
            try {
                $cached = $model::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                    ->first();
                $live = $service->pullLiveSkuPrice(
                    $sku,
                    $cached?->product_id ? (string) $cached->product_id : null,
                    $cached?->sku_id ? (string) $cached->sku_id : null
                );
                $price = ($live['price'] ?? 0) > 0 ? round((float) $live['price'], 2) : 0.0;
                if ($price > 0) {
                    $price = $this->listingPriceToStore($channel, $sku, $price, $expectedBySku);
                }
                if (! ($price > 0)) {
                    $out[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => $channel,
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Live TikTok price not returned',
                    ];
                    continue;
                }

                if (! $cached) {
                    $cached = new $model;
                    $cached->sku = strtoupper(trim($sku));
                }
                $cached->product_id = (string) $live['product_id'];
                $cached->sku_id = (string) $live['sku_id'];
                $cached->price = $price;
                if (array_key_exists('stock', $live) && $live['stock'] !== null) {
                    $cached->stock = (int) $live['stock'];
                }
                $cached->listing_status = TikTokShopService::isLiveListingStatus($live['status'] ?? 'ACTIVATE')
                    ? 'active'
                    : 'inactive';
                $cached->save();

                $out[] = [
                    'success' => true,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => $price,
                    'sprice' => null,
                    'message' => 'Pulled TikTok Price $'.number_format($price, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price TikTok pull failed', [
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

            if ($i < count($skus) - 1) {
                usleep(150000);
            }
        }

        return $out;
    }

    /**
     * Live Doba Delivery (anticipatedIncome) / Pick Up (selfPickAnticipatedIncome).
     *
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,self_pick_price?:?float,sprice:?float,message:string}>
     */
    private function pullDoba(array $skus, string $channel, array $expectedBySku = []): array
    {
        $api = app(DobaApiService::class);
        $lookups = [];
        $rows = [];
        foreach ($skus as $sku) {
            try {
                $metric = DobaMetric::query()
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                    ->first()
                    ?: DobaMetric::query()->where('sku', $sku)->first();
                $itemId = $metric ? trim((string) ($metric->item_id ?? '')) : '';
                if ($itemId === '') {
                    $rows[] = [
                        'success' => false,
                        'sku' => $sku,
                        'marketplace' => $channel,
                        'price' => null,
                        'sprice' => null,
                        'message' => 'Item ID not found for this SKU',
                    ];
                    continue;
                }

                $lookups[] = [
                    'sku' => $sku,
                    'itemNo' => $itemId,
                    'goodsId' => $metric ? trim((string) ($metric->goods_id ?? '')) : '',
                    'metric' => $metric,
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Doba pull failed', [
                    'sku' => $sku,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $liveMap = [];
        if ($lookups !== []) {
            try {
                $liveMap = $api->pullLivePricesFromGoodsDetail($lookups);
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Doba catalog pull failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                foreach ($lookups as $lookup) {
                    $rows[] = [
                        'success' => false,
                        'sku' => $lookup['sku'],
                        'marketplace' => $channel,
                        'price' => null,
                        'sprice' => null,
                        'message' => $e->getMessage(),
                    ];
                }

                return $rows;
            }
        }

        foreach ($lookups as $lookup) {
            $sku = $lookup['sku'];
            $itemId = strtoupper(trim((string) $lookup['itemNo']));
            $skuKey = strtoupper(trim((string) $sku));
            $live = $liveMap[$itemId] ?? $liveMap[$skuKey] ?? null;
            if (! is_array($live)) {
                $rows[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => 'Live Doba price not returned',
                ];
                continue;
            }

            $delivery = round((float) ($live['anticipatedIncome'] ?? 0), 2);
            $pickup = round((float) ($live['selfPickAnticipatedIncome'] ?? 0), 2);
            $price = $channel === 'doba_withoutship' ? $pickup : $delivery;
            if ($price > 0) {
                $price = $this->listingPriceToStore($channel, $sku, $price, $expectedBySku);
            }
            if (! ($price > 0)) {
                $rows[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => $channel,
                    'price' => null,
                    'sprice' => null,
                    'message' => 'Live Doba price not returned',
                ];
                continue;
            }

            $metric = $lookup['metric'] ?? null;
            if ($metric) {
                if ($delivery > 0) {
                    $metric->anticipated_income = $channel === 'doba_withoutship' ? $delivery : $price;
                }
                if ($pickup > 0) {
                    $metric->self_pick_price = $channel === 'doba_withoutship' ? $price : $pickup;
                }
                $metric->save();
            }

            $rows[] = [
                'success' => true,
                'sku' => $sku,
                'marketplace' => $channel,
                'price' => $price,
                'self_pick_price' => $pickup > 0 ? $pickup : null,
                'sprice' => null,
                'message' => 'Pulled Doba Price $'.number_format($price, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, float>  $expectedBySku
     * @return list<array{success:bool,sku:string,marketplace:string,price:?float,sprice:?float,message:string,skipped?:bool}>
     */
    private function pullNewegg(array $skus, array $expectedBySku = []): array
    {
        $api = app(NeweggApiService::class);
        $spnByExact = [];
        $spnByNorm = [];
        foreach (NeweggPricing::query()->select('seller_part_number')->get() as $row) {
            $spn = trim((string) $row->seller_part_number);
            if ($spn === '') {
                continue;
            }
            $exact = strtoupper(preg_replace('/\s+/', ' ', str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $spn)) ?? '');
            $norm = strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $spn) ?? '');
            if ($exact !== '' && ! isset($spnByExact[$exact])) {
                $spnByExact[$exact] = $spn;
            }
            if ($norm !== '' && ! isset($spnByNorm[$norm])) {
                $spnByNorm[$norm] = $spn;
            }
        }

        $out = [];
        foreach ($skus as $sku) {
            $exact = strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
            $norm = strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $sku) ?? '');
            $spn = $spnByExact[$exact] ?? $spnByNorm[$norm] ?? null;
            if (! $spn) {
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'newegg',
                    'price' => null,
                    'sprice' => null,
                    'message' => 'No Newegg listing (SPN) found for SKU',
                ];
                continue;
            }

            try {
                $price = $api->refreshStoredSellingPrice($spn, 'USA');
                if ($price !== null && $price > 0) {
                    $kept = $this->listingPriceToStore('newegg', $sku, (float) $price, $expectedBySku);
                    if (abs($kept - (float) $price) >= 0.005) {
                        NeweggPricing::query()
                            ->where('seller_part_number', $spn)
                            ->update(['selling_price' => $kept]);
                    }
                    $price = $kept;
                }
                $out[] = [
                    'success' => $price !== null && $price > 0,
                    'sku' => $sku,
                    'marketplace' => 'newegg',
                    'price' => $price,
                    'sprice' => null,
                    'message' => $price !== null
                        ? ('Pulled Newegg SellingPrice $'.number_format($price, 2))
                        : 'Newegg SellingPrice not returned',
                ];
            } catch (\Throwable $e) {
                Log::warning('Channel pushed-price Newegg pull failed', [
                    'sku' => $sku,
                    'spn' => $spn,
                    'error' => $e->getMessage(),
                ]);
                $out[] = [
                    'success' => false,
                    'sku' => $sku,
                    'marketplace' => 'newegg',
                    'price' => null,
                    'sprice' => null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }
}
