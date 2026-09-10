<?php

namespace App\Support\Marketplace;

use App\Models\BestbuyUsaProduct;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\FaireMetric;
use App\Models\MacyProduct;
use App\Models\NeweggItem;
use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use App\Models\PLSProduct;
use App\Models\ReverbListingStatus;
use App\Models\ShopifyCatalogVariant;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Models\ReverbProduct;
use App\Models\SheinMetric;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use App\Models\TemuMetric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\WalmartMetrics;
use App\Models\WalmartPriceData;
use App\Models\WayfairPricingPrice;
use App\Models\WayfairListingStatus;
use App\Models\AlibabaMetric;
use App\Models\AliexpressMetric;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerProduct;
use App\Models\TopDawgProduct;

/**
 * Channel configs for automated listing pages (EbayTwo pattern).
 *
 * listed.type:
 * - column: metric model + id column
 * - price: model + price column (>0)
 * - status: ListingStatus.listed === Listed (last resort)
 * - custom: callable name on this class
 */
class ChannelListingRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'ebay' => [
                'dataView' => \App\Models\EbayDataView::class,
                'status' => \App\Models\EbayListingStatus::class,
                'listed' => ['type' => 'column', 'model' => EbayMetric::class, 'column' => 'item_id'],
                'id_field' => 'eBay_item_id',
                'buyer_tpl' => 'https://www.ebay.com/itm/{id}',
                'seller_tpl' => 'https://www.ebay.com/sh/lst/active?keyword={id}&action=search',
            ],
            'ebaytwo' => [
                'dataView' => \App\Models\EbayTwoDataView::class,
                'status' => \App\Models\EbayTwoListingStatus::class,
                'listed' => ['type' => 'column', 'model' => Ebay2Metric::class, 'column' => 'item_id'],
                'id_field' => 'eBay_item_id',
                'buyer_tpl' => 'https://www.ebay.com/itm/{id}',
                'seller_tpl' => 'https://www.ebay.com/sh/lst/active?keyword={id}&action=search',
            ],
            'ebaythree' => [
                'dataView' => \App\Models\EbayThreeDataView::class,
                'status' => \App\Models\EbayThreeListingStatus::class,
                'listed' => ['type' => 'column', 'model' => Ebay3Metric::class, 'column' => 'item_id'],
                'id_field' => 'eBay_item_id',
                'buyer_tpl' => 'https://www.ebay.com/itm/{id}',
                'seller_tpl' => 'https://www.ebay.com/sh/lst/active?keyword={id}&action=search',
            ],
            'ebayvariation' => [
                'dataView' => \App\Models\EbayVariationDataView::class,
                'status' => \App\Models\EbayVariationListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\EbayVariationListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'doba' => [
                'dataView' => \App\Models\DobaDataView::class,
                'status' => \App\Models\DobaListingStatus::class,
                'listed' => ['type' => 'column', 'model' => DobaMetric::class, 'column' => 'item_id'],
                'id_field' => 'item_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'walmart' => [
                'dataView' => \App\Models\WalmartDataView::class,
                'status' => \App\Models\WalmartListingStatus::class,
                'listed' => ['type' => 'custom', 'method' => 'listedWalmart'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'neweggb2c' => [
                'dataView' => \App\Models\Neweegb2cDataView::class,
                'status' => \App\Models\NeweggB2CListingStatus::class,
                // Listed = live Newegg catalog (newegg_pricing / newegg_items), not stale newegg_metric.
                'listed' => ['type' => 'custom', 'method' => 'listedNewegg'],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'neweggb2b' => [
                'dataView' => \App\Models\NeweggB2BDataView::class,
                'status' => \App\Models\NeweggB2BListingStatus::class,
                'listed' => ['type' => 'custom', 'method' => 'listedNewegg'],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'tiktokshop' => [
                'dataView' => \App\Models\TiktokShopDataView::class,
                'status' => \App\Models\TiktokShopListingStatus::class,
                'listed' => ['type' => 'column', 'model' => TikTokProduct::class, 'column' => 'product_id', 'reject_sku' => true],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'tiktokshop2' => [
                'dataView' => \App\Models\TiktokTwoShopDataView::class,
                'status' => \App\Models\TiktokTwoShopListingStatus::class,
                // Listed = TikTok Shop 2 products API → tiktok_products_two.product_id
                'listed' => ['type' => 'column', 'model' => TikTokProductTwo::class, 'column' => 'product_id', 'reject_sku' => true],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'reverb' => [
                'dataView' => null, // no dedicated DataView — NRL defaults REQ
                'status' => \App\Models\ReverbListingStatus::class,
                'listed' => ['type' => 'custom', 'method' => 'listedReverb'],
                'id_field' => 'reverb_listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'shein' => [
                'dataView' => \App\Models\SheinDataView::class,
                'status' => \App\Models\SheinListingStatus::class,
                'listed' => ['type' => 'column', 'model' => SheinMetric::class, 'column' => 'shein_sku_code', 'reject_sku' => true],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'aliexpress' => [
                'dataView' => \App\Models\AliexpressDataView::class,
                'status' => \App\Models\AliexpressListingStatus::class,
                'listed' => ['type' => 'custom', 'method' => 'listedAliexpress'],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'temu' => [
                'dataView' => \App\Models\TemuDataView::class,
                'status' => \App\Models\TemuListingStatus::class,
                'listed' => ['type' => 'column', 'model' => TemuMetric::class, 'column' => 'goods_id', 'reject_sku' => true],
                'id_field' => 'goods_id',
                'buyer_tpl' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id={id}',
                'seller_tpl' => 'https://seller.temu.com/product-info.html?add_method=1&click_type=1&goods_id={id}',
            ],
            'temu2' => [
                'dataView' => \App\Models\Temu2DataView::class,
                'status' => \App\Models\Temu2ListingStatus::class,
                // Listed = Temu 2 products API → temu2_metrics.goods_id
                'listed' => ['type' => 'column', 'model' => Temu2Metric::class, 'column' => 'goods_id', 'reject_sku' => true],
                'id_field' => 'goods_id',
                'buyer_tpl' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id={id}',
                'seller_tpl' => 'https://seller.temu.com/product-info.html?add_method=1&click_type=1&goods_id={id}',
            ],
            'macys' => [
                'dataView' => \App\Models\MacyDataView::class,
                'status' => \App\Models\MacysListingStatus::class,
                'listed' => ['type' => 'price', 'model' => MacyProduct::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'wayfair' => [
                'dataView' => null,
                'status' => \App\Models\WayfairListingStatus::class,
                // Listed = wayfair_pricing_prices OR wayfair_listing_statuses (listed / buyer link)
                'listed' => ['type' => 'custom', 'method' => 'listedWayfair'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'pls' => [
                'dataView' => \App\Models\PLSDataView::class,
                'status' => \App\Models\PlsListingStatus::class,
                // Listed = Shopify PLS catalog variant (shopify_catalog_* store=pls).
                'listed' => ['type' => 'custom', 'method' => 'listedPls'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'bestbuyusa' => [
                'dataView' => \App\Models\BestbuyUSADataView::class,
                'status' => \App\Models\BestbuyUSAListingStatus::class,
                'listed' => ['type' => 'price', 'model' => BestbuyUsaProduct::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'faire' => [
                'dataView' => \App\Models\FaireDataView::class,
                'status' => \App\Models\FaireListingStatus::class,
                // Listed = present in Faire products API (faire_metric).
                'listed' => ['type' => 'custom', 'method' => 'listedFaire'],
                'id_field' => 'product_id',
                'buyer_tpl' => 'https://www.faire.com/product/{id}',
                'seller_tpl' => 'https://www.faire.com/brand-portal/my-shop/products',
            ],
            'fbmarketplace' => [
                'dataView' => \App\Models\FBMarketplaceDataView::class,
                'status' => \App\Models\FBMarketplaceListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\FBMarketplaceListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'fbshop' => [
                'dataView' => \App\Models\FBShopDataView::class,
                'status' => \App\Models\FBShopListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\FBShopListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'instagramshop' => [
                'dataView' => \App\Models\InstagramShopDataView::class,
                'status' => \App\Models\InstagramShopListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\InstagramShopListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'shopifyb2c' => [
                'dataView' => \App\Models\Shopifyb2cDataView::class,
                'status' => \App\Models\ShopifyB2CListingStatus::class,
                // Listed = present in Shopify Admin API → shopify_skus.variant_id
                'listed' => ['type' => 'custom', 'method' => 'listedShopify'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'shopifywholesale' => [
                'dataView' => \App\Models\ShopifyWholesaleDataView::class,
                'status' => \App\Models\ShopifyWholesaleListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\ShopifyWholesaleListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'mercariwoship' => [
                'dataView' => \App\Models\MercariWoShipDataView::class,
                'status' => \App\Models\MercariWoShipListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\MercariWoShipListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'mercariwship' => [
                'dataView' => \App\Models\MercariWShipDataView::class,
                'status' => \App\Models\MercariWShipListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\MercariWShipListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'autods' => [
                'dataView' => \App\Models\AutoDSDataView::class,
                'status' => \App\Models\AutoDSListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\AutoDSListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'poshmark' => [
                'dataView' => \App\Models\PoshmarkDataView::class,
                'status' => \App\Models\PoshmarkListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\PoshmarkListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'spocket' => [
                'dataView' => \App\Models\SpocketDataView::class,
                'status' => \App\Models\SpocketListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\SpocketListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'zendrop' => [
                'dataView' => \App\Models\ZendropDataView::class,
                'status' => \App\Models\ZendropListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\ZendropListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'syncee' => [
                'dataView' => \App\Models\SynceeDataView::class,
                'status' => \App\Models\SynceeListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\SynceeListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'offerup' => [
                'dataView' => \App\Models\OfferupDataView::class,
                'status' => \App\Models\OfferupListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\OfferupListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'appscenic' => [
                'dataView' => \App\Models\AppscenicDataView::class,
                'status' => \App\Models\AppscenicListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\AppscenicListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'yamibuy' => [
                'dataView' => \App\Models\YamibuyDataView::class,
                'status' => \App\Models\YamibuyListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\YamibuyListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'swgearexchange' => [
                'dataView' => \App\Models\SWGearExchangeDataView::class,
                'status' => \App\Models\SWGearExchangeListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\SWGearExchangeListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'business5core' => [
                'dataView' => \App\Models\Business5CoreDataView::class,
                'status' => \App\Models\Business5CoreListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\Business5CoreListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'topdawg' => [
                'dataView' => \App\Models\TopDawgDataView::class,
                'status' => \App\Models\TopDawgListingStatus::class,
                // Listed = row in topdawg_products (API catalog). TopDawg keys on product_code.
                'listed' => ['type' => 'custom', 'method' => 'listedTopDawg'],
                'id_field' => 'topdawg_listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'purchasingpower' => [
                'dataView' => \App\Models\PurchasingPowerDataView::class,
                'status' => \App\Models\PurchasingPowerListingStatus::class,
                'listed' => ['type' => 'column', 'model' => PurchasingPowerProduct::class, 'column' => 'sku'],
                'id_field' => 'sku',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'alibaba' => [
                'dataView' => null,
                'status' => null,
                'listed' => ['type' => 'column', 'model' => AlibabaMetric::class, 'column' => 'product_id', 'reject_sku' => true],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        $normalized = ListingChannelCounts::normalize($key);
        $aliases = [
            'ebay1' => 'ebay',
            'ebayone' => 'ebay',
            'ebay2' => 'ebaytwo',
            'ebay3' => 'ebaythree',
            'tiktok' => 'tiktokshop',
            'tiktok2' => 'tiktokshop2',
            'shopify' => 'shopifyb2c',
            'bestbuy' => 'bestbuyusa',
            'temutwo' => 'temu2',
            'facebookmarketplace' => 'fbmarketplace',
            'shopifyb2b' => 'shopifywholesale',
            'shopifywholesaleds' => 'shopifywholesale',
            'newegg' => 'neweggb2c',
        ];
        $resolved = $aliases[$normalized] ?? $normalized;

        return $all[$resolved] ?? $all[strtolower(trim($key))] ?? null;
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(string $key, bool $requirePositiveInv = true): array
    {
        $cfg = self::get($key);
        if ($cfg === null) {
            return ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0, 'MissingL' => 0];
        }

        $skus = ListingCountsEngine::countUniverseSkus($requirePositiveInv);
        $dataView = $cfg['dataView'] ?? null;
        $nrValues = ($dataView && class_exists($dataView))
            ? ListingCountsEngine::loadNrValues($dataView, $skus)
            : collect();
        $nrValues = self::overlayListingStatusNr($nrValues, $cfg['status'] ?? null, $skus);
        $listedMap = self::loadListedIds($cfg, $skus);

        return ListingCountsEngine::counts($nrValues, $listedMap, $requirePositiveInv);
    }

    /**
     * Shape expected by ListingChannelCounts / controllers.
     *
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int}
     */
    public static function nrReqCountArray(string $key): array
    {
        $c = self::counts($key);

        return [
            'REQ' => $c['REQ'],
            'NRL' => $c['NRL'],
            'Listed' => $c['Listed'],
            'Pending' => $c['MissingL'],
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function loadListedIds(array $cfg, array $skus): array
    {
        $listed = $cfg['listed'] ?? [];
        $type = $listed['type'] ?? 'status';

        return match ($type) {
            'column' => ListingCountsEngine::listedIdsFromColumn(
                (string) ($listed['model'] ?? ''),
                $skus,
                (string) ($listed['column'] ?? 'item_id'),
                (bool) ($listed['reject_sku'] ?? false)
            ),
            'price' => ListingCountsEngine::listedIdsFromPrice(
                (string) ($listed['model'] ?? ''),
                $skus,
                (string) ($listed['column'] ?? 'price')
            ),
            'custom' => self::{(string) $listed['method']}($skus),
            default => ListingCountsEngine::listedIdsFromStatus(
                (string) ($listed['model'] ?? $cfg['status'] ?? ''),
                $skus
            ),
        };
    }

    /**
     * Overlay listing-status NRL onto DataView values (same as listing-page rows).
     *
     * @param  Collection<string, mixed>  $nrValues
     * @param  class-string|null  $statusClass
     * @param  list<string>  $skus
     * @return Collection<string, mixed>
     */
    public static function overlayListingStatusNr($nrValues, $statusClass, array $skus)
    {
        if (! is_string($statusClass) || $statusClass === '' || ! class_exists($statusClass) || $skus === []) {
            return $nrValues;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable((new $statusClass)->getTable())) {
            return $nrValues;
        }

        $wanted = [];
        foreach ($skus as $sku) {
            foreach (ListingCountsEngine::skuLookupKeys((string) $sku) as $key) {
                $wanted[$key] = true;
            }
        }

        $statusClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'value'])
            ->each(function ($row) use ($nrValues, $wanted) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
                    return;
                }
                $keys = ListingCountsEngine::skuLookupKeys($sku);
                $hit = false;
                foreach ($keys as $key) {
                    if (isset($wanted[$key])) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit) {
                    return;
                }
                if (ListingCountsEngine::nrReqFromDataView($row->value) !== 'NR') {
                    return;
                }
                foreach ($keys as $key) {
                    $nrValues[$key] = ['NRL' => 'NRL'];
                }
            });

        return $nrValues;
    }

    /**
     * Walmart Listed = walmart_price_data.item_id (not sheet price).
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedWalmart(array $skus): array
    {
        if ($skus === [] || ! class_exists(WalmartPriceData::class)) {
            return ListingCountsEngine::listedIdsFromPrice(WalmartMetrics::class, $skus, 'price');
        }

        $fromItemId = ListingCountsEngine::listedIdsFromColumn(
            WalmartPriceData::class,
            $skus,
            'item_id',
            true
        );
        if ($fromItemId !== []) {
            return $fromItemId;
        }

        return ListingCountsEngine::listedIdsFromPrice(WalmartMetrics::class, $skus, 'price');
    }

    /**
     * Faire Listed = SKU present in faire_metric. Listing id prefers product_id.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedFaire(array $skus): array
    {
        if ($skus === [] || ! class_exists(FaireMetric::class)) {
            return [];
        }

        $wantedNorm = self::wantedNormalizedSkus($skus);
        if ($wantedNorm === []) {
            return [];
        }

        $byNorm = [];
        FaireMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'product_id'])
            ->each(function ($row) use (&$byNorm, $wantedNorm) {
                $sku = trim((string) $row->sku);
                $id = trim((string) ($row->product_id ?? ''));
                if ($sku === '' || $id === '' || strcasecmp($id, $sku) === 0) {
                    return;
                }
                self::putListedId($byNorm, $wantedNorm, $sku, $id);
            });

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * AliExpress Listed = onSelling aliexpress_metric.product_id OR priced
     * aliexpress_pricing_prices row, matched with normalized / compact SKU.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedAliexpress(array $skus): array
    {
        $metrics = AliexpressListingCounts::metricsByNormalizedSku();
        $pricing = AliexpressListingCounts::pricingSkusByNormalizedSku();
        $wanted = self::wantedNormalizedSkus($skus);
        $byKey = [];
        foreach ($skus as $raw) {
            $sku = trim((string) $raw);
            if ($sku === '') {
                continue;
            }
            $resolved = AliexpressListingCounts::resolveListed($sku, $metrics, $pricing);
            if (! ($resolved['listed'] ?? false)) {
                continue;
            }
            $id = trim((string) ($resolved['product_id'] ?? ''));
            self::putListedId($byKey, $wanted, $sku, $id !== '' ? $id : $sku);
        }

        return self::listedMapFromByNorm($skus, $byKey);
    }

    /**
     * Wayfair Listed = SKU in wayfair_pricing_prices OR wayfair_listing_statuses
     * (value.listed = Listed, or a buyer/seller/listing link), matched with
     * normalized SKU (spaces/case). Pricing-only was ~250; listing statuses
     * hold the rest of Partner Home listings.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedWayfair(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $wantedNorm = self::wantedNormalizedSkus($skus);
        $byNorm = [];

        if (class_exists(WayfairPricingPrice::class)) {
            WayfairPricingPrice::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        if ($sku !== '') {
                            self::putListedId($byNorm, $wantedNorm, $sku, $sku);
                        }
                    }
                });
        }

        if (class_exists(WayfairListingStatus::class)) {
            WayfairListingStatus::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $val = is_array($row->value)
                            ? $row->value
                            : (json_decode((string) $row->value, true) ?: []);
                        $listedFlag = strtolower(trim((string) ($val['listed'] ?? '')));
                        $buyer = trim((string) ($val['buyer_link'] ?? ''));
                        $listingId = trim((string) ($val['listing_id'] ?? $val['item_id'] ?? ''));
                        if ($listedFlag !== 'listed' && $listedFlag !== 'yes' && $buyer === '' && $listingId === '') {
                            continue;
                        }
                        if (ListingCountsEngine::isPendingOrReviewListingState($listedFlag)
                            || ListingCountsEngine::isPendingOrReviewListingState($val['state'] ?? null)) {
                            continue;
                        }
                        $sku = trim((string) $row->sku);
                        $id = $listingId !== '' ? $listingId : ($buyer !== '' ? $buyer : $sku);
                        self::putListedId($byNorm, $wantedNorm, $sku, $id);
                    }
                });
        }

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * Reverb Listed = reverb_products.reverb_listing_id (preferred), else price > 0,
     * else ReverbListingStatus.value.listing_id (keeps /listing-reverb correct when
     * reverb_products lags behind the listing-status sync).
     *
     * @param  list<string>  $skus
     * @return array<string, string> lowercase product-master SKU => listing id (or SKU sentinel)
     */
    public static function listedReverb(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $map = [];
        $lookup = ReverbProduct::buildLookupByNormalizedSku($skus);

        foreach ($skus as $rawSku) {
            $sku = trim((string) $rawSku);
            if ($sku === '') {
                continue;
            }

            $norm = ReverbProduct::normalizeSkuForLookup($sku);
            $row = $norm !== '' ? ($lookup[$norm] ?? null) : null;
            if (! $row) {
                $compact = ShopifySku::compactSkuForLookup($sku);
                $row = $compact !== '' ? ($lookup['c:'.$compact] ?? null) : null;
            }
            if (! $row) {
                continue;
            }
            if (ListingCountsEngine::isPendingOrReviewListingState($row->listing_state ?? $row->state ?? null)) {
                continue;
            }

            $id = trim((string) ($row->reverb_listing_id ?? ''));
            if ($id !== '') {
                $map[strtolower($sku)] = $id;
                continue;
            }

            if ((float) ($row->price ?? 0) > 0) {
                $map[strtolower($sku)] = $sku;
            }
        }

        // Normalized SKU => lowercase ProductMaster SKU (only rows still missing a listing id)
        $wanted = [];
        foreach ($skus as $rawSku) {
            $sku = trim((string) $rawSku);
            if ($sku === '' || isset($map[strtolower($sku)])) {
                continue;
            }
            $norm = ReverbProduct::normalizeSkuForLookup($sku);
            if ($norm !== '') {
                $wanted[$norm] = strtolower($sku);
            }
            $compact = ShopifySku::compactSkuForLookup($sku);
            if ($compact !== '') {
                $wanted['c:'.$compact] = strtolower($sku);
            }
        }

        if ($wanted === []) {
            return $map;
        }

        $statusRows = ReverbListingStatus::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'value']);

        foreach ($statusRows as $row) {
            $norm = ReverbProduct::normalizeSkuForLookup((string) $row->sku);
            $compact = ShopifySku::compactSkuForLookup((string) $row->sku);
            $pmKey = ($norm !== '' && isset($wanted[$norm]))
                ? $wanted[$norm]
                : (($compact !== '' && isset($wanted['c:'.$compact])) ? $wanted['c:'.$compact] : null);
            if ($pmKey === null) {
                continue;
            }

            $value = is_array($row->value)
                ? $row->value
                : (json_decode((string) $row->value, true) ?? []);
            $id = trim((string) ($value['listing_id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $state = strtolower(trim((string) ($value['state'] ?? '')));
            if (ListingCountsEngine::isPendingOrReviewListingState($state)) {
                continue;
            }
            // Prefer live/published when duplicate status rows exist for one SKU.
            if (! isset($map[$pmKey]) || in_array($state, ['live', 'published'], true)) {
                $map[$pmKey] = $id;
            }
        }

        return $map;
    }

    /**
     * Shopify B2C listed = Admin API variant present on shopify_skus.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedShopify(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $wanted = self::wantedNormalizedSkus($skus);
        $byKey = [];
        $shopifyData = ShopifySku::mapByProductSkus($skus);
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $row = $shopifyData[$sku] ?? null;
            $variantId = trim((string) ($row->variant_id ?? ''));
            if ($variantId === '' || $variantId === '0') {
                continue;
            }
            self::putListedId($byKey, $wanted, $sku, $variantId);
        }

        return self::listedMapFromByNorm($skus, $byKey);
    }

    /**
     * Newegg Listed = SKU in the live catalog (newegg_pricing / newegg_items),
     * not only newegg_metric.product_id (that table lags when auto-link is Off).
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedNewegg(array $skus): array
    {
        $wantedNorm = self::wantedNormalizedSkus($skus);
        if ($wantedNorm === []) {
            return [];
        }

        $byNorm = [];

        if (class_exists(NeweggItem::class) && \Illuminate\Support\Facades\Schema::hasTable('newegg_items')) {
            NeweggItem::query()
                ->whereNotNull('seller_part_number')
                ->where('seller_part_number', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->seller_part_number);
                        $id = trim((string) ($row->newegg_item_number ?? ''));
                        self::putListedId($byNorm, $wantedNorm, $sku, $id !== '' ? $id : $sku);
                    }
                });
        }

        if (class_exists(NeweggPricing::class) && \Illuminate\Support\Facades\Schema::hasTable('newegg_pricing')) {
            NeweggPricing::query()
                ->whereNotNull('seller_part_number')
                ->where('seller_part_number', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->seller_part_number);
                        $itemNumber = trim((string) ($row->newegg_item_number ?? ''));
                        $active = $row->active ?? $row->inventory_active;
                        $isActive = $active === true || $active === 1 || $active === '1';
                        $hasPrice = (float) ($row->selling_price ?? 0) > 0;
                        $hasInv = $row->available_quantity !== null;
                        if ($itemNumber === '' && ! $hasPrice && ! $isActive && ! $hasInv) {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $itemNumber !== '' ? $itemNumber : $sku);
                    }
                });
        }

        if (class_exists(NeweggMetric::class) && \Illuminate\Support\Facades\Schema::hasTable('newegg_metric')) {
            NeweggMetric::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        $id = trim((string) ($row->product_id ?? ''));
                        if ($id === '' || strcasecmp($id, $sku) === 0) {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $id);
                    }
                });
        }

        try {
            $cached = app(\App\Services\MarketplaceManager\NeweggLiveListingsService::class)->peekCached();
            if (is_array($cached)) {
                foreach ($cached as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $sku = trim((string) ($row['sku'] ?? ''));
                    $id = trim((string) ($row['product_id'] ?? ''));
                    self::putListedId($byNorm, $wantedNorm, $sku, $id !== '' ? $id : $sku);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache misses
        }

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * PLS Listed = variant on the Shopify PLS catalog (store=pls).
     * pls_products.price is a sales overlay and can stay at 0 / stale.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedPls(array $skus): array
    {
        $wantedNorm = self::wantedNormalizedSkus($skus);
        if ($wantedNorm === []) {
            return [];
        }

        $byNorm = [];

        if (class_exists(ShopifyCatalogVariant::class) && \Illuminate\Support\Facades\Schema::hasTable('shopify_catalog_variants')) {
            ShopifyCatalogVariant::query()
                ->where('store', 'pls')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        if ($sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                            continue;
                        }
                        $variantId = trim((string) ($row->shopify_variant_id ?? ''));
                        $productId = trim((string) ($row->shopify_product_id ?? ''));
                        $id = $variantId !== '' && $variantId !== '0' ? $variantId : $productId;
                        if ($id === '' || $id === '0') {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $id);
                    }
                });
        }

        if (class_exists(PLSProduct::class) && \Illuminate\Support\Facades\Schema::hasTable('pls_products')) {
            $fromPrice = ListingCountsEngine::listedIdsFromPrice(PLSProduct::class, $skus, 'price');
            foreach ($fromPrice as $key => $id) {
                self::putListedId($byNorm, $wantedNorm, (string) $key, (string) $id);
            }
        }

        try {
            $cached = app(\App\Services\MarketplaceManager\PlsLiveListingsService::class)->peekCached();
            if (is_array($cached)) {
                foreach ($cached as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $sku = trim((string) ($row['sku'] ?? ''));
                    if (ListingCountsEngine::isPendingOrReviewListingState($row['state'] ?? null)) {
                        continue;
                    }
                    $id = trim((string) ($row['sku_id'] ?? $row['product_id'] ?? ''));
                    self::putListedId($byNorm, $wantedNorm, $sku, $id !== '' ? $id : $sku);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache misses
        }

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * TopDawg Listed = live/Yes catalog row in topdawg_products.
     * Uploaded / review / unable-to-list stay in Missing L.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedTopDawg(array $skus): array
    {
        $wantedNorm = self::wantedNormalizedSkus($skus);
        if ($wantedNorm === []) {
            return [];
        }

        $byNorm = [];

        if (class_exists(TopDawgProduct::class) && \Illuminate\Support\Facades\Schema::hasTable('topdawg_products')) {
            TopDawgProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                    foreach ($rows as $row) {
                        // Uploaded / review / unable-to-list stay in Missing L.
                        if (ListingCountsEngine::isPendingOrReviewListingState($row->listing_state ?? null)) {
                            continue;
                        }
                        $sku = trim((string) $row->sku);
                        $listingId = trim((string) ($row->topdawg_listing_id ?? ''));
                        $tdid = trim((string) ($row->tdid ?? ''));
                        $id = $listingId !== '' ? $listingId : ($tdid !== '' ? $tdid : $sku);
                        if ($id === '') {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $id);
                    }
                });
        }

        try {
            $cached = app(\App\Services\MarketplaceManager\TopDawgLiveListingsService::class)->peekCached();
            if (is_array($cached)) {
                foreach ($cached as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $sku = trim((string) ($row['sku'] ?? ''));
                    if (ListingCountsEngine::isPendingOrReviewListingState($row['state'] ?? null)) {
                        continue;
                    }
                    $id = trim((string) ($row['product_id'] ?? ''));
                    self::putListedId($byNorm, $wantedNorm, $sku, $id !== '' ? $id : $sku);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache misses
        }

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, true>
     */
    private static function wantedNormalizedSkus(array $skus): array
    {
        return ListingCountsEngine::wantedSkuKeySet($skus);
    }

    /**
     * @param  array<string, string>  $byNorm
     * @param  array<string, true>  $wantedNorm
     */
    private static function putListedId(array &$byNorm, array $wantedNorm, string $sku, string $id): void
    {
        ListingCountsEngine::putListedForSku($byNorm, $wantedNorm, $sku, $id);
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, string>  $byNorm
     * @return array<string, string>
     */
    private static function listedMapFromByNorm(array $skus, array $byNorm): array
    {
        return ListingCountsEngine::listedMapForProductSkus($skus, $byNorm);
    }
}
