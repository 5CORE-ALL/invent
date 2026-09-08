<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\MacyController;
use App\Models\AliexpressListingStatus;
use App\Models\AliexpressPricingPrice;
use App\Models\AmazonDatasheet;
use App\Models\AmazonListingStatus;
use App\Models\BestbuyPriceData;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyUSAListingStatus;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayListingStatus;
use App\Models\EbayMetric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\FbaPrice;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\MacysPriceData;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerListingStatus;
use App\Models\PurchasingPowerProduct;
use App\Models\ReverbListingStatus;
use App\Models\ReverbProduct;
use App\Models\SheinListingStatus;
use App\Models\SheinPricingPrice;
use App\Models\ShopifyB2CListingStatus;
use App\Models\ShopifySku;
use App\Models\Temu2DataView;
use App\Models\Temu2ListingStatus;
use App\Models\Temu2Metric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokShopListingStatus;
use App\Models\TiktokTwoShopListingStatus;
use App\Models\TopDawgDataView;
use App\Models\TopDawgListingStatus;
use App\Models\TopDawgProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PrcCprReportService
{
    public const MAX_SKUS = 200;

    /** Channels excluded from the comparison body (Temu 1, not Temu 2). */
    public const EXCLUDED_CHANNEL_KEYS = [
        'temu',
        'doba',
        'depop',
        'shopify b2b',
        'shopifyb2b',
        'sb2b',
        'wayfair',
        'faire',
    ];

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function compareChannels(): array
    {
        return [
            ['key' => 'amazon', 'label' => 'Amazon'],
            ['key' => 'ebay1', 'label' => 'Ebay1'],
            ['key' => 'ebay2', 'label' => 'Ebay2'],
            ['key' => 'ebay3', 'label' => 'Ebay3'],
            ['key' => 'tiktok', 'label' => 'TikTok'],
            ['key' => 'tiktok2', 'label' => 'TikTok 2'],
            ['key' => 'shopify', 'label' => 'Shopify'],
            ['key' => 'macy', 'label' => 'MACY'],
            ['key' => 'reverb', 'label' => 'Reverb'],
            ['key' => 'bestbuy', 'label' => 'BestBuy'],
            ['key' => 'shein', 'label' => 'Shein'],
            ['key' => 'aliexpress', 'label' => 'AliExpress'],
            ['key' => 'ppower', 'label' => 'PPower'],
            ['key' => 'topdawg', 'label' => 'TopDawg'],
            ['key' => 'fba', 'label' => 'FBA'],
        ];
    }

    public static function isExcludedChannel(string $key): bool
    {
        $norm = strtolower(preg_replace('/\s+/', '', trim($key)) ?? '');
        foreach (self::EXCLUDED_CHANNEL_KEYS as $excluded) {
            if ($norm === strtolower(preg_replace('/\s+/', '', $excluded) ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Temu 2 customer price from base_price — same rule as /pricing-master-cvr OV L30.
     */
    public static function temu2DisplayPrice(float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0.0;
        }

        return round($basePrice <= 26.99 ? $basePrice + 2.99 : $basePrice, 2);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array{products: array<int, array<string, mixed>>, excluded: array<int, string>}
     */
    public function build(array $skus): array
    {
        $skus = $this->normalizeRequestedSkus($skus);
        if ($skus === []) {
            return [
                'products' => [],
                'excluded' => ['Temu', 'Doba', 'Depop', 'Shopify B2B', 'Wayfair', 'Faire'],
            ];
        }

        $resolved = $this->resolveProductSkus($skus);
        $lookupSkus = array_values(array_unique(array_merge($skus, array_values($resolved))));

        $temu2 = $this->loadTemu2BySku($lookupSkus);
        $prices = $this->loadChannelPrices($lookupSkus);
        $links = $this->loadChannelBuyerLinks($lookupSkus);

        $products = [];
        foreach ($skus as $requested) {
            $fullSku = $resolved[$requested] ?? $requested;
            $products[] = $this->composeProduct($fullSku, $temu2, $prices, $links);
        }

        return [
            'products' => $products,
            'excluded' => ['Temu', 'Doba', 'Depop', 'Shopify B2B', 'Wayfair', 'Faire'],
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @param  array<string, array{price: float|null, buyer_link: string}>  $temu2
     * @param  array<string, array<string, float|null>>  $prices  channel key => sku => price
     * @param  array<string, array<string, string>>  $links  channel key => sku => buyer_link
     * @return array<string, mixed>
     */
    public function composeProduct(string $sku, array $temu2, array $prices, array $links): array
    {
        $header = $this->lookupSkuMap($temu2, $sku) ?? ['price' => null, 'buyer_link' => ''];

        $channels = [];
        foreach (self::compareChannels() as $channel) {
            $key = $channel['key'];
            if (self::isExcludedChannel($key)) {
                continue;
            }
            $price = $this->lookupSkuMap($prices[$key] ?? [], $sku);
            $link = $this->lookupSkuMap($links[$key] ?? [], $sku);
            $priceVal = is_numeric($price) ? round((float) $price, 2) : null;
            $linkVal = is_string($link) ? $link : '';
            if (! ($priceVal > 0) && $linkVal === '') {
                continue;
            }
            $channels[] = [
                'channel' => $channel['label'],
                'key' => $key,
                'price' => $priceVal,
                'buyer_link' => $linkVal,
            ];
        }

        return [
            'sku' => $sku,
            'temu2' => [
                'price' => isset($header['price']) && is_numeric($header['price'])
                    ? round((float) $header['price'], 2)
                    : null,
                'buyer_link' => (string) ($header['buyer_link'] ?? ''),
            ],
            'channels' => $channels,
        ];
    }

    /**
     * Flat rows for Excel: Temu2 columns stay as the header context on every channel row.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<int, mixed>>
     */
    public static function excelRows(array $products): array
    {
        $rows = [[
            'SKU',
            'Temu2 Price',
            'Temu2 Buyer Link',
            'Channel',
            'Channel Price',
            'Channel Buyer Link',
        ]];

        foreach ($products as $product) {
            $sku = (string) ($product['sku'] ?? '');
            $temu2Price = $product['temu2']['price'] ?? null;
            $temu2Link = (string) ($product['temu2']['buyer_link'] ?? '');
            $channels = is_array($product['channels'] ?? null) ? $product['channels'] : [];
            if ($channels === []) {
                $rows[] = [$sku, $temu2Price, $temu2Link, '', null, ''];
                continue;
            }
            foreach ($channels as $channel) {
                $rows[] = [
                    $sku,
                    $temu2Price,
                    $temu2Link,
                    (string) ($channel['channel'] ?? ''),
                    $channel['price'] ?? null,
                    (string) ($channel['buyer_link'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $skus
     * @return array<int, string>
     */
    public function normalizeRequestedSkus(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || strncasecmp($sku, 'PARENT ', 7) === 0) {
                continue;
            }
            $out[$sku] = $sku;
        }

        return array_values($out);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, string> requested => product_master.sku
     */
    private function resolveProductSkus(array $skus): array
    {
        $map = array_fill_keys($skus, null);
        if (! Schema::hasTable('product_master')) {
            return array_combine($skus, $skus) ?: [];
        }

        try {
            $rows = ProductMaster::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get(['sku']);
            $indexed = $this->indexRowsBySku($rows);
            foreach ($skus as $sku) {
                $row = $this->lookupIndexed($indexed, $sku);
                $map[$sku] = $row ? (string) $row->sku : $sku;
            }
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: ProductMaster resolve failed: '.$e->getMessage());
            foreach ($skus as $sku) {
                $map[$sku] = $sku;
            }
        }

        foreach ($map as $requested => $resolved) {
            $map[$requested] = $resolved ?: $requested;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array{price: float|null, buyer_link: string}>
     */
    private function loadTemu2BySku(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $out[$sku] = ['price' => null, 'buyer_link' => ''];
        }

        $priceBySku = [];
        if (Schema::hasTable('temu2_metrics')) {
            try {
                $cols = ['sku', 'base_price'];
                $byNorm = [];
                $byNoSpace = [];
                $indexTemuRows = function ($rows) use (&$byNorm, &$byNoSpace) {
                    foreach ($rows as $row) {
                        $norm = $this->normalizeTemuSku((string) ($row->sku ?? ''));
                        if ($norm === '') {
                            continue;
                        }
                        if (! isset($byNorm[$norm])) {
                            $byNorm[$norm] = $row;
                        }
                        $byNoSpace[str_replace(' ', '', $norm)] = $row;
                    }
                };
                $indexTemuRows(Temu2Metric::query()
                    ->where(function ($q) use ($skus) {
                        $q->whereIn('sku', $skus)
                            ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                    })
                    ->get($cols));
                $missing = [];
                foreach ($skus as $sku) {
                    $norm = $this->normalizeTemuSku($sku);
                    $row = $byNorm[$norm] ?? $byNoSpace[str_replace(' ', '', $norm)] ?? null;
                    if ($row) {
                        $priceBySku[$sku] = self::temu2DisplayPrice((float) ($row->base_price ?? 0));
                    } else {
                        $missing[] = $sku;
                    }
                }
                if ($missing !== []) {
                    $indexTemuRows(Temu2Metric::query()->get($cols));
                    foreach ($missing as $sku) {
                        $norm = $this->normalizeTemuSku($sku);
                        $row = $byNorm[$norm] ?? $byNoSpace[str_replace(' ', '', $norm)] ?? null;
                        if ($row) {
                            $priceBySku[$sku] = self::temu2DisplayPrice((float) ($row->base_price ?? 0));
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PrcCpr: Temu2 price load failed: '.$e->getMessage());
            }
        }

        $linkBySku = $this->loadBuyerLinkMap(Temu2DataView::class, $skus, 'value');
        $statusLinks = $this->loadBuyerLinkMap(Temu2ListingStatus::class, $skus, 'value');

        foreach ($skus as $sku) {
            $link = $this->lookupSkuMap($linkBySku, $sku)
                ?: $this->lookupSkuMap($statusLinks, $sku)
                ?: '';
            $price = $priceBySku[$sku] ?? null;
            $out[$sku] = [
                'price' => ($price !== null && $price > 0) ? $price : null,
                'buyer_link' => $link,
            ];
            foreach ($this->skuKeys($sku) as $key) {
                $out[$key] = $out[$sku];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<string, float|null>>
     */
    private function loadChannelPrices(array $skus): array
    {
        return [
            'amazon' => $this->loadModelPriceMap(AmazonDatasheet::class, 'price', $skus),
            'ebay1' => $this->loadModelPriceMap(EbayMetric::class, 'ebay_price', $skus),
            'ebay2' => $this->loadModelPriceMap(Ebay2Metric::class, 'ebay_price', $skus),
            'ebay3' => $this->loadModelPriceMap(Ebay3Metric::class, 'ebay_price', $skus),
            'tiktok' => $this->loadModelPriceMap(TikTokProduct::class, 'price', $skus),
            'tiktok2' => $this->loadModelPriceMap(TikTokProductTwo::class, 'price', $skus),
            'shopify' => $this->loadShopifyPrices($skus),
            'macy' => $this->loadMacyPrices($skus),
            'reverb' => $this->loadNormalizedProductPrices(ReverbProduct::class, $skus),
            'bestbuy' => $this->loadBestbuyPrices($skus),
            'shein' => $this->loadModelPriceMap(SheinPricingPrice::class, 'special_offer_price', $skus),
            'aliexpress' => $this->loadModelPriceMap(AliexpressPricingPrice::class, 'price', $skus),
            'ppower' => $this->loadModelPriceMap(PurchasingPowerProduct::class, 'price', $skus),
            'topdawg' => $this->loadNormalizedProductPrices(TopDawgProduct::class, $skus),
            'fba' => $this->loadFbaPrices($skus),
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<string, string>>
     */
    private function loadChannelBuyerLinks(array $skus): array
    {
        $amazon = $this->loadBuyerLinkMap(AmazonListingStatus::class, $skus);
        $ppower = $this->loadBuyerLinkMap(PurchasingPowerListingStatus::class, $skus);
        $ppowerView = $this->loadBuyerLinkMap(PurchasingPowerDataView::class, $skus);
        $topdawg = $this->loadBuyerLinkMap(TopDawgListingStatus::class, $skus);
        $topdawgView = $this->loadBuyerLinkMap(TopDawgDataView::class, $skus);

        return [
            'amazon' => $amazon,
            'ebay1' => $this->loadBuyerLinkMap(EbayListingStatus::class, $skus),
            'ebay2' => $this->loadBuyerLinkMap(EbayTwoListingStatus::class, $skus),
            'ebay3' => $this->loadBuyerLinkMap(EbayThreeListingStatus::class, $skus),
            'tiktok' => $this->loadBuyerLinkMap(TiktokShopListingStatus::class, $skus),
            'tiktok2' => $this->loadBuyerLinkMap(TiktokTwoShopListingStatus::class, $skus),
            'shopify' => $this->loadBuyerLinkMap(ShopifyB2CListingStatus::class, $skus),
            'macy' => $this->loadBuyerLinkMap(MacysListingStatus::class, $skus),
            'reverb' => $this->loadBuyerLinkMap(ReverbListingStatus::class, $skus),
            'bestbuy' => $this->loadBuyerLinkMap(BestbuyUSAListingStatus::class, $skus),
            'shein' => $this->loadBuyerLinkMap(SheinListingStatus::class, $skus),
            'aliexpress' => $this->loadBuyerLinkMap(AliexpressListingStatus::class, $skus),
            'ppower' => $this->mergeLinkMaps($ppower, $ppowerView),
            'topdawg' => $this->mergeLinkMaps($topdawg, $topdawgView),
            'fba' => $amazon,
        ];
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadModelPriceMap(string $modelClass, string $priceField, array $skus): array
    {
        if ($skus === [] || ! class_exists($modelClass)) {
            return [];
        }
        try {
            $table = (new $modelClass)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $priceField) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            $rows = $modelClass::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get(['sku', $priceField]);

            return $this->indexNumericBySku($rows, $priceField);
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: '.$modelClass.' price load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadShopifyPrices(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('shopify_skus')) {
            return [];
        }
        try {
            $rows = ShopifySku::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get(['sku', 'price']);
            $map = $this->indexNumericBySku($rows, 'price');
            foreach ($skus as $sku) {
                if ($this->lookupSkuMap($map, $sku) !== null) {
                    continue;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                foreach ($rows as $row) {
                    if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) === $norm) {
                        $price = (float) ($row->price ?? 0);
                        $map[$sku] = $price > 0 ? $price : null;
                        break;
                    }
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: Shopify price load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadNormalizedProductPrices(string $modelClass, array $skus): array
    {
        if ($skus === [] || ! class_exists($modelClass)) {
            return [];
        }
        try {
            $table = (new $modelClass)->getTable();
            if (! Schema::hasTable($table)) {
                return [];
            }
            $rows = $modelClass::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get(['sku', 'price']);

            $map = $this->indexNumericBySku($rows, 'price');
            foreach ($skus as $sku) {
                if ($this->lookupSkuMap($map, $sku) !== null) {
                    continue;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                foreach ($rows as $row) {
                    if (ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) === $norm) {
                        $price = (float) ($row->price ?? 0);
                        $map[$sku] = $price > 0 ? $price : null;
                        break;
                    }
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: '.$modelClass.' normalized price load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadMacyPrices(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('macys_price_data')) {
            return [];
        }
        try {
            $sheetRows = MacysPriceData::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get();
            $products = Schema::hasTable('macy_products')
                ? MacyProduct::query()
                    ->where(function ($q) use ($skus) {
                        $q->whereIn('sku', $skus)
                            ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                    })
                    ->get()
                : collect();
            $sheetBySku = $this->indexRowsBySku($sheetRows);
            $productBySku = $this->indexRowsBySku($products);

            $map = [];
            foreach ($skus as $sku) {
                $resolved = MacyController::resolveListedPrice(
                    $this->lookupIndexed($productBySku, $sku),
                    $this->lookupIndexed($sheetBySku, $sku)
                );
                $map[$sku] = ! empty($resolved['listed']) && ($resolved['price'] ?? 0) > 0
                    ? round((float) $resolved['price'], 2)
                    : null;
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: Macy price load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadBestbuyPrices(array $skus): array
    {
        $sheet = $this->loadModelPriceMap(BestbuyPriceData::class, 'price', $skus);
        $product = $this->loadModelPriceMap(BestbuyUsaProduct::class, 'price', $skus);
        $map = [];
        foreach ($skus as $sku) {
            $fromSheet = $this->lookupSkuMap($sheet, $sku);
            $fromProduct = $this->lookupSkuMap($product, $sku);
            $map[$sku] = $fromSheet ?? $fromProduct;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, float|null>
     */
    private function loadFbaPrices(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('fba_prices')) {
            return [];
        }
        try {
            $upper = $this->upperSkus($skus);
            $rows = FbaPrice::query()
                ->where(function ($q) use ($upper) {
                    $q->whereIn(DB::raw('UPPER(TRIM(seller_sku))'), $upper)
                        ->orWhereIn(DB::raw('UPPER(TRIM(REPLACE(REPLACE(seller_sku, " FBA", ""), "FBA", "")))'), $upper);
                })
                ->get(['seller_sku', 'price']);

            $map = [];
            foreach ($rows as $row) {
                $seller = strtoupper(trim((string) ($row->seller_sku ?? '')));
                if ($seller === '' || stripos($seller, 'FBA') === false) {
                    continue;
                }
                $base = trim(str_replace([' FBA', 'FBA'], '', $seller));
                $price = (float) ($row->price ?? 0);
                if ($price <= 0) {
                    continue;
                }
                foreach (array_merge([$seller, $base], $this->skuKeys($base)) as $key) {
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = round($price, 2);
                    }
                }
            }

            $out = [];
            foreach ($skus as $sku) {
                $out[$sku] = $this->lookupSkuMap($map, $sku);
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: FBA price load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, string>
     */
    private function loadBuyerLinkMap(string $modelClass, array $skus, string $valueField = 'value'): array
    {
        if ($skus === [] || ! class_exists($modelClass)) {
            return [];
        }
        try {
            $table = (new $modelClass)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            $field = Schema::hasColumn($table, $valueField) ? $valueField : (Schema::hasColumn($table, 'value') ? 'value' : null);
            if ($field === null) {
                return [];
            }
            $rows = $modelClass::query()
                ->where(function ($q) use ($skus) {
                    $q->whereIn('sku', $skus)
                        ->orWhereIn(DB::raw('UPPER(TRIM(sku))'), $this->upperSkus($skus));
                })
                ->get(['sku', $field]);

            $map = [];
            foreach ($rows as $row) {
                $raw = $row->{$field} ?? null;
                $val = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
                $link = trim((string) ($val['buyer_link'] ?? ''));
                if ($link === '') {
                    continue;
                }
                foreach ($this->skuKeys((string) $row->sku) as $key) {
                    if ($key !== '' && ! isset($map[$key])) {
                        $map[$key] = $link;
                    }
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('PrcCpr: '.$modelClass.' buyer link load failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<string, string>  $primary
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function mergeLinkMaps(array $primary, array $fallback): array
    {
        foreach ($fallback as $key => $link) {
            if ($link !== '' && ! isset($primary[$key])) {
                $primary[$key] = $link;
            }
        }

        return $primary;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array<string, float|null>
     */
    private function indexNumericBySku(iterable $rows, string $field): array
    {
        $map = [];
        foreach ($rows as $row) {
            $price = (float) ($row->{$field} ?? 0);
            if ($price <= 0) {
                continue;
            }
            foreach ($this->skuKeys((string) ($row->sku ?? '')) as $key) {
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = round($price, 2);
                }
            }
        }

        return $map;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array<string, object>
     */
    private function indexRowsBySku(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            foreach ($this->skuKeys((string) ($row->sku ?? '')) as $key) {
                if ($key !== '' && ! isset($map[$key])) {
                    $map[$key] = $row;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function lookupIndexed(array $map, string $sku): mixed
    {
        return $this->lookupSkuMap($map, $sku);
    }

    /**
     * @param  array<string, mixed>  $map
     */
    public function lookupSkuMap(array $map, string $sku): mixed
    {
        foreach ($this->skuKeys($sku) as $key) {
            if ($key !== '' && array_key_exists($key, $map)) {
                return $map[$key];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function skuKeys(string $sku): array
    {
        $trim = trim(str_replace("\xc2\xa0", ' ', $sku));
        $upper = strtoupper($trim);
        $collapsed = preg_replace('/\s+/', ' ', $upper) ?? $upper;
        $nospace = str_replace(' ', '', $collapsed);

        return array_values(array_unique(array_filter([$sku, $trim, $upper, $collapsed, $nospace], static fn ($v) => $v !== '')));
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<int, string>
     */
    private function upperSkus(array $skus): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtoupper(trim((string) $s)),
            $skus
        ))));
    }

    private function normalizeTemuSku(string $sku): string
    {
        $sku = str_replace("\xc2\xa0", ' ', $sku);
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku) ?? $sku;

        return preg_replace('/\s+/', ' ', $sku) ?? $sku;
    }
}
