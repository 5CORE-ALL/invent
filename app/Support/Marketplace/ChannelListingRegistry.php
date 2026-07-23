<?php

namespace App\Support\Marketplace;

use App\Models\BestbuyUsaProduct;
use App\Models\DobaMetric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\FairePricingPrice;
use App\Models\MacyProduct;
use App\Models\NeweggMetric;
use App\Models\PLSProduct;
use App\Models\ReverbProduct;
use App\Models\SheinMetric;
use App\Models\TemuMetric;
use App\Models\TiendamiaProduct;
use App\Models\TikTokProduct;
use App\Models\WalmartMetrics;
use App\Models\WayfairPricingPrice;
use App\Models\ProductMaster;

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
                'listed' => ['type' => 'price', 'model' => WalmartMetrics::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'neweggb2c' => [
                'dataView' => \App\Models\Neweegb2cDataView::class,
                'status' => \App\Models\NeweggB2CListingStatus::class,
                'listed' => ['type' => 'column', 'model' => NeweggMetric::class, 'column' => 'product_id', 'reject_sku' => true],
                'id_field' => 'product_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'neweggb2b' => [
                'dataView' => \App\Models\NeweggB2BDataView::class,
                'status' => \App\Models\NeweggB2BListingStatus::class,
                'listed' => ['type' => 'column', 'model' => NeweggMetric::class, 'column' => 'product_id', 'reject_sku' => true],
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
                'listed' => ['type' => 'price', 'model' => SheinMetric::class, 'column' => 'price'],
                'id_field' => 'listing_id',
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
            'macys' => [
                'dataView' => \App\Models\MacyDataView::class,
                'status' => \App\Models\MacysListingStatus::class,
                'listed' => ['type' => 'price', 'model' => MacyProduct::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'wayfair' => [
                'dataView' => \App\Models\WayfairDataView::class,
                'status' => \App\Models\WayfairListingStatus::class,
                'listed' => ['type' => 'price', 'model' => WayfairPricingPrice::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'pls' => [
                'dataView' => \App\Models\PLSDataView::class,
                'status' => \App\Models\PlsListingStatus::class,
                'listed' => ['type' => 'price', 'model' => PLSProduct::class, 'column' => 'price'],
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
            'tiendamia' => [
                'dataView' => \App\Models\TiendamiaDataView::class,
                'status' => \App\Models\TiendamiaListingStatus::class,
                'listed' => ['type' => 'price', 'model' => TiendamiaProduct::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
            ],
            'faire' => [
                'dataView' => \App\Models\FaireDataView::class,
                'status' => \App\Models\FaireListingStatus::class,
                'listed' => ['type' => 'price', 'model' => FairePricingPrice::class, 'column' => 'price'],
                'id_field' => 'listing_id',
                'buyer_tpl' => null,
                'seller_tpl' => null,
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
                'listed' => ['type' => 'status', 'model' => \App\Models\ShopifyB2CListingStatus::class],
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
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();

        return $all[strtolower(trim($key))] ?? null;
    }

    /**
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(string $key): array
    {
        $cfg = self::get($key);
        if ($cfg === null) {
            return ['REQ' => 0, 'NRL' => 0, 'Listed' => 0, 'Pending' => 0, 'MissingL' => 0];
        }

        $skus = ProductMaster::whereNull('deleted_at')->pluck('sku')->unique()->filter()->values()->all();
        $dataView = $cfg['dataView'] ?? null;
        $nrValues = ($dataView && class_exists($dataView))
            ? ListingCountsEngine::loadNrValues($dataView, $skus)
            : collect();
        $listedMap = self::loadListedIds($cfg, $skus);

        return ListingCountsEngine::counts($nrValues, $listedMap);
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
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedReverb(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $map = [];
        $rows = ReverbProduct::whereIn('sku', $skus)->get(['sku', 'reverb_listing_id', 'price']);
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
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

        return $map;
    }
}
