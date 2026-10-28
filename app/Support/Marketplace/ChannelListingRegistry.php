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
use App\Models\B5cB2bProduct;
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
                // An ended / unsold / deleted eBay listing is not live: its SKU is Missing L again.
                // MISSING = the seller portal Active and Unsold lists did not return the SKU.
                'listed' => [
                    'type' => 'column',
                    'model' => EbayMetric::class,
                    'column' => 'item_id',
                    'hide_statuses' => ['inactive', 'ended', 'unsold', 'completed', 'sold', 'missing', 'not_listed'],
                ],
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
            'temu3' => [
                'dataView' => \App\Models\Temu3DataView::class,
                'status' => \App\Models\Temu3ListingStatus::class,
                'listed' => ['type' => 'column', 'model' => \App\Models\Temu3Metric::class, 'column' => 'goods_id', 'reject_sku' => true],
                'id_field' => 'goods_id',
                'buyer_tpl' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id={id}',
                'seller_tpl' => 'https://seller.temu.com/product-info.html?add_method=1&click_type=1&goods_id={id}',
            ],
            'macys' => [
                'dataView' => \App\Models\MacyDataView::class,
                'status' => \App\Models\MacysListingStatus::class,
                // Listed = MCM offer exists (active with price, or inactive per OF51), not price > 0 only.
                'listed' => ['type' => 'custom', 'method' => 'listedMacy'],
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
                'listed' => ['type' => 'custom', 'method' => 'listedBestBuy'],
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
                'listed' => ['type' => 'custom', 'method' => 'listedPurchasingPower'],
                'id_field' => 'sku',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'alibaba' => [
                'dataView' => null,
                'status' => null,
                'listed' => ['type' => 'custom', 'method' => 'listedAlibaba'],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'b5cb2b' => [
                'dataView' => null,
                'status' => null,
                'listed' => ['type' => 'custom', 'method' => 'listedB5cB2b'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'cjdropshipping' => [
                'dataView' => null,
                'status' => \App\Models\CjdropshippingListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\CjdropshippingListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'depop' => [
                'dataView' => null,
                'status' => \App\Models\DepopListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\DepopListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'vinted' => [
                'dataView' => null,
                'status' => \App\Models\VintedListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\VintedListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'dhgate' => [
                'dataView' => null,
                'status' => \App\Models\DHGateListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\DHGateListingStatus::class],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'tiendamia' => [
                'dataView' => null,
                'status' => \App\Models\TiendamiaListingStatus::class,
                'listed' => ['type' => 'status', 'model' => \App\Models\TiendamiaListingStatus::class],
                'id_field' => 'listing_id',
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
            'macy' => 'macys',
            'temutwo' => 'temu2',
            'temuthree' => 'temu3',
            'facebookmarketplace' => 'fbmarketplace',
            'shopifyb2b' => 'shopifywholesale',
            'shopifywholesaleds' => 'shopifywholesale',
            'newegg' => 'neweggb2c',
            'b5cb2b' => 'b5cb2b',
            'business5coreb2b' => 'b5cb2b',
            'business5core(b2b)' => 'b5cb2b',
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

        // Counted from the very rows the listing page shows, so the two can never disagree.
        return AutomatedListingPage::counts($key, $requirePositiveInv);
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
                (bool) ($listed['reject_sku'] ?? false),
                (array) ($listed['hide_statuses'] ?? [])
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
     * Macy's Listed = offer on Macy's MCM. The OF21 price sync stores price 0 for
     * inactive offers, so those count via listing_status from the OF51 status sync
     * (recently seen only, so offers deleted on Macy's drop off).
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedMacy(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $index = app(\App\Services\MarketplaceManager\MiraklMcmOfferIndex::class)->get('macy', false);
        if (is_array($index) && ($index['offers'] ?? []) !== []) {
            return self::overlayConnectCreated('macy', $skus, self::listedFromMiraklOfferIndex($skus, $index));
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('macy_products')) {
            return self::overlayConnectCreated('macy', $skus, []);
        }

        $wanted = self::wantedNormalizedSkus($skus);
        if ($wanted === []) {
            return [];
        }

        $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn('macy_products', 'listing_status');
        $freshSince = now()->subDays(3);
        $byKey = [];
        MacyProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get($hasStatus ? ['sku', 'price', 'listing_status', 'updated_at'] : ['sku', 'price'])
            ->each(function ($row) use (&$byKey, $wanted, $hasStatus, $freshSince) {
                $sku = trim((string) $row->sku);
                if ($sku === '') {
                    return;
                }
                $status = $hasStatus ? strtolower(trim((string) ($row->listing_status ?? ''))) : '';
                $fresh = $row->updated_at !== null && $row->updated_at >= $freshSince;
                if ((float) ($row->price ?? 0) > 0 || ($status === 'active' && $fresh)) {
                    self::putListedId($byKey, $wanted, $sku, $sku);
                } elseif ($status === 'inactive' && $fresh) {
                    self::putListedId($byKey, $wanted, $sku, ListingCountsEngine::pageCreatedId($sku));
                }
            });

        return self::overlayConnectCreated('macy', $skus, self::listedMapFromByNorm($skus, $byKey));
    }

    /**
     * Best Buy Listed = offer on Best Buy MCM (active or inactive); price > 0 when MCM was never read.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedBestBuy(array $skus): array
    {
        $index = $skus === [] ? null : app(\App\Services\MarketplaceManager\MiraklMcmOfferIndex::class)->get('bestbuy', false);
        if (is_array($index) && ($index['offers'] ?? []) !== []) {
            return self::overlayConnectCreated('bestbuy', $skus, self::listedFromMiraklOfferIndex($skus, $index));
        }

        return self::overlayConnectCreated('bestbuy', $skus, ListingCountsEngine::listedIdsFromPrice(BestbuyUsaProduct::class, $skus, 'price'));
    }

    /**
     * Purchasing Power Listed = offer on Purchasing Power MCM (active or inactive);
     * purchasing_power_products rows when MCM was never read.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedPurchasingPower(array $skus): array
    {
        $index = $skus === [] ? null : app(\App\Services\MarketplaceManager\MiraklMcmOfferIndex::class)->get('purchasingpower', false);
        if (is_array($index) && ($index['offers'] ?? []) !== []) {
            return self::overlayConnectCreated('purchasingpower', $skus, self::listedFromMiraklOfferIndex($skus, $index));
        }

        return self::overlayConnectCreated('purchasingpower', $skus, ListingCountsEngine::listedIdsFromColumn(PurchasingPowerProduct::class, $skus, 'sku', false));
    }

    /**
     * Listed = active MCM offer whose shop SKU, Mirakl product SKU or UPC matches the
     * Product Master SKU. A matching inactive offer is a page created (neither Listed nor Missing L).
     *
     * @param  list<string>  $skus
     * @param  array{offers: list<array<string, mixed>>}  $index
     * @return array<string, string>
     */
    public static function listedFromMiraklOfferIndex(array $skus, array $index): array
    {
        $keyMap = \App\Services\MarketplaceManager\MiraklMcmOfferIndex::skuKeyMap($index);
        $upcMap = \App\Services\MarketplaceManager\MiraklMcmOfferIndex::upcMap($index);
        $upcBySku = $upcMap === [] ? [] : self::productMasterUpcs($skus);
        $activeShopSkus = [];
        foreach ($index['offers'] ?? [] as $offer) {
            if (! empty($offer['active']) && (string) ($offer['shop_sku'] ?? '') !== '') {
                $activeShopSkus[strtoupper((string) $offer['shop_sku'])] = true;
            }
        }

        $map = [];
        foreach ($skus as $raw) {
            $sku = trim((string) $raw);
            if ($sku === '') {
                continue;
            }
            $hit = '';
            foreach (ListingCountsEngine::skuLookupKeys($sku) as $key) {
                if (isset($keyMap[$key])) {
                    $hit = $keyMap[$key];
                    break;
                }
            }
            if ($hit === '') {
                $upc = $upcBySku[strtoupper($sku)] ?? '';
                if ($upc !== '' && isset($upcMap[$upc])) {
                    $hit = $upcMap[$upc];
                }
            }
            if ($hit !== '') {
                $map[strtolower($sku)] = isset($activeShopSkus[strtoupper($hit)])
                    ? $hit
                    : ListingCountsEngine::pageCreatedId($hit);
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>  upper SKU => digits-only UPC without leading zeros
     */
    private static function productMasterUpcs(array $skus): array
    {
        $out = [];
        try {
            ProductMaster::query()
                ->whereIn('sku', array_values(array_unique(array_map(static fn ($s) => trim((string) $s), $skus))))
                ->get(['sku', 'Values'])
                ->each(function ($pm) use (&$out) {
                    $values = is_array($pm->Values) ? $pm->Values : [];
                    $upc = ltrim(preg_replace('/\D/', '', (string) ($values['upc'] ?? $values['gtin'] ?? '')) ?? '', '0');
                    if ($upc !== '') {
                        $out[strtoupper(trim((string) $pm->sku))] = $upc;
                    }
                });
        } catch (\Throwable $e) {
            // UPC is a secondary match only
        }

        return $out;
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
            if (! empty($resolved['page_created'])) {
                self::putListedId($byKey, $wanted, $sku, ListingCountsEngine::pageCreatedId($sku));

                continue;
            }
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
            $pmKey = ($norm !== '' && isset($wanted[$norm])) ? $wanted[$norm] : null;
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
                        if (! self::isLiveNeweggListingId($id, $sku)) {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $id);
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
                        if (! self::isLiveNeweggListingId($itemNumber, $sku)) {
                            continue;
                        }
                        self::putListedId($byNorm, $wantedNorm, $sku, $itemNumber);
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
                        if (! self::isLiveNeweggListingId($id, $sku)) {
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
                    if (! self::isLiveNeweggListingId($id, $sku)) {
                        continue;
                    }
                    self::putListedId($byNorm, $wantedNorm, $sku, $id);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache misses
        }

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * True only for a real Newegg item #. SKU-as-id and NE- placeholders
     * are leftover from a failed / in-review create and are not live.
     */
    public static function isLiveNeweggListingId(string $id, string $sku = ''): bool
    {
        $id = trim($id);
        $sku = trim($sku);
        if ($id === '' || ! preg_match('/^9SI/i', $id)) {
            return false;
        }
        if ($sku !== '' && strcasecmp($id, $sku) === 0) {
            return false;
        }

        return true;
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

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * Business 5 Core (B2B) Listed = SKU on the Laravel store catalog (b5c_b2b_products).
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedB5cB2b(array $skus): array
    {
        $wantedNorm = self::wantedNormalizedSkus($skus);
        if ($wantedNorm === [] || ! class_exists(B5cB2bProduct::class) || ! \Illuminate\Support\Facades\Schema::hasTable('b5c_b2b_products')) {
            return [];
        }

        $byNorm = [];
        B5cB2bProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$byNorm, $wantedNorm) {
                foreach ($rows as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '' || MarketplaceLiveInventoryRules::isParentPlaceholderSku($sku)) {
                        continue;
                    }
                    $id = $row->listing_id !== null && (string) $row->listing_id !== ''
                        ? (string) $row->listing_id
                        : $sku;
                    self::putListedId($byNorm, $wantedNorm, $sku, $id);
                }
            });

        return self::listedMapFromByNorm($skus, $byNorm);
    }

    /**
     * Alibaba Listed = product id on alibaba_metrics or the analytics sheet.
     * Offline / pending rows, and SKUs that are only in the price catalog, are
     * pages created (neither Listed nor Missing L). Color and 06/6 aliases match.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedAlibaba(array $skus): array
    {
        $wanted = ListingCountsEngine::wantedSkuKeySet($skus, true);
        if ($wanted === []) {
            return [];
        }

        $byNorm = [];
        $offline = ['offline', 'inactive', 'disabled', 'rejected', 'pending', 'auditing', 'editingrequired'];

        if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_metrics')) {
            AlibabaMetric::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wanted) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        $id = trim((string) ($row->product_id ?? ''));
                        if ($sku === '') {
                            continue;
                        }
                        $value = ($id !== '' && strcasecmp($id, $sku) !== 0)
                            ? $id
                            : ListingCountsEngine::pageCreatedId($sku);
                        ListingCountsEngine::putListedForSku($byNorm, $wanted, $sku, $value, true);
                    }
                });
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_sheet_prices')) {
            \App\Models\AlibabaSheetPrice::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wanted, $offline) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        $id = trim((string) ($row->product_id ?? ''));
                        if ($sku === '') {
                            continue;
                        }
                        $status = strtolower(trim((string) ($row->status ?? '')));
                        $real = $id !== '' && strcasecmp($id, $sku) !== 0;
                        $value = (in_array($status, $offline, true) || ! $real)
                            ? ListingCountsEngine::pageCreatedId($real ? $id : $sku)
                            : $id;
                        ListingCountsEngine::putListedForSku($byNorm, $wanted, $sku, $value, true);
                    }
                });
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('alibaba_pricing_prices')) {
            \App\Models\AlibabaPricingPrice::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where(function ($query) {
                    $query->where('price', '>', 0)->orWhereNotNull('ab_stock');
                })
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm, $wanted) {
                    foreach ($rows as $row) {
                        $sku = trim((string) $row->sku);
                        if ($sku === '') {
                            continue;
                        }
                        ListingCountsEngine::putListedForSku($byNorm, $wanted, $sku, ListingCountsEngine::pageCreatedId($sku), true);
                    }
                });
        }

        return ListingCountsEngine::listedMapForProductSkus($skus, $byNorm, true);
    }

    /**
     * TopDawg Listed = published row from the live SupplierProduct/list catalog in
     * topdawg_products. Pending / review / rejected / inactive rows are pages created
     * (neither Listed nor Missing L).
     * Pack / GTR / parent product_code aliases count as listed.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedTopDawg(array $skus): array
    {
        $wantedNorm = ListingCountsEngine::wantedSkuKeySet($skus, true);
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
                        $sku = trim((string) $row->sku);
                        $listingId = trim((string) ($row->topdawg_listing_id ?? ''));
                        $tdid = trim((string) ($row->tdid ?? ''));
                        $id = self::isLiveTopDawgListingId($listingId, $sku)
                            ? $listingId
                            : (self::isLiveTopDawgListingId($tdid, $sku) ? $tdid : '');
                        if ($id === '') {
                            ListingCountsEngine::putListedForSku($byNorm, $wantedNorm, $sku, ListingCountsEngine::pageCreatedId($sku), true);

                            continue;
                        }
                        if (! self::topDawgStateIsPublished($row->listing_state ?? null)) {
                            $id = ListingCountsEngine::pageCreatedId($id);
                        }
                        ListingCountsEngine::putListedForSku($byNorm, $wantedNorm, $sku, $id, true);
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
                    $id = trim((string) ($row['product_id'] ?? ''));
                    if (! self::isLiveTopDawgListingId($id, $sku)) {
                        ListingCountsEngine::putListedForSku($byNorm, $wantedNorm, $sku, ListingCountsEngine::pageCreatedId($sku), true);

                        continue;
                    }
                    if (! self::topDawgStateIsPublished($row['state'] ?? null)) {
                        $id = ListingCountsEngine::pageCreatedId($id);
                    }
                    ListingCountsEngine::putListedForSku($byNorm, $wantedNorm, $sku, $id, true);
                }
            }
        } catch (\Throwable $e) {
            // ignore cache misses
        }

        return ListingCountsEngine::listedMapForProductSkus($skus, $byNorm, true);
    }

    /**
     * SupplierProduct/list rows carry no status field, so a blank state is a published product.
     */
    private static function topDawgStateIsPublished(?string $state): bool
    {
        $s = strtolower(trim((string) $state));
        if ($s === '' || $s === 'other') {
            return true;
        }
        if (ListingCountsEngine::isPendingOrReviewListingState($s)) {
            return false;
        }

        return ! in_array($s, ['inactive', 'disabled', 'deactivated', 'archived', 'suspended', 'no', 'false', '0'], true);
    }

    /**
     * True only for a real TopDawg listing / TDID. SKU-as-id and td- placeholders
     * are leftover from a failed create and are not in the supplier catalog.
     */
    public static function isLiveTopDawgListingId(string $id, string $sku = ''): bool
    {
        $id = trim($id);
        $sku = trim($sku);
        if ($id === '') {
            return false;
        }
        if (str_starts_with(strtolower($id), 'td-')) {
            return false;
        }
        if ($sku !== '' && strcasecmp($id, $sku) === 0) {
            return false;
        }

        return true;
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
    /**
     * SKUs present in Mirakl Connect but not an active offer are pages created.
     * They count as neither Listed nor Missing L.
     *
     * @param  list<string>  $skus
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private static function overlayConnectCreated(string $channel, array $skus, array $map): array
    {
        if ($skus === []) {
            return $map;
        }

        $index = app(\App\Services\MarketplaceManager\MiraklConnectProductIndex::class)->get($channel, true);
        if (! is_array($index)) {
            return $map;
        }

        foreach ($skus as $raw) {
            $sku = trim((string) $raw);
            if ($sku === '' || ! \App\Services\MarketplaceManager\MiraklConnectProductIndex::contains($index, $sku)) {
                continue;
            }
            $existing = ListingCountsEngine::listingIdFromMap($map, $sku);
            if ($existing !== '' && ! ListingCountsEngine::isPageCreatedId($existing)) {
                continue;
            }
            if ($existing === '') {
                $map[strtolower($sku)] = ListingCountsEngine::pageCreatedId($sku);
            }
        }

        return $map;
    }

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
