<?php

namespace App\Support\Marketplace;

use App\Models\AppscenicListingStatus;
use App\Models\AutoDSListingStatus;
use App\Models\Business5CoreListingStatus;
use App\Models\DepopListingStatus;
use App\Models\DHGateListingStatus;
use App\Models\EbayVariationListingStatus;
use App\Models\FBMarketplaceListingStatus;
use App\Models\FBShopListingStatus;
use App\Models\InstagramShopListingStatus;
use App\Models\MercariWoShipListingStatus;
use App\Models\MercariWShipListingStatus;
use App\Models\OfferupListingStatus;
use App\Models\PoshmarkListingStatus;
use App\Models\ShopifyWholesaleListingStatus;
use App\Models\SpocketListingStatus;
use App\Models\SWGearExchangeListingStatus;
use App\Models\SynceeListingStatus;
use App\Models\TiendamiaListingStatus;
use App\Models\VintedListingStatus;
use App\Models\YamibuyListingStatus;
use App\Models\ZendropListingStatus;

/**
 * Manual / sheet marketplaces with no listing API. Current listings come from
 * an uploaded sheet and are matched to CP Master.
 */
class SheetListingCatalog
{
    /**
     * @return array<string, array{label: string, status: class-string, seller_portal: ?string}>
     */
    public static function all(): array
    {
        return [
            'depop' => ['label' => 'Depop', 'status' => DepopListingStatus::class, 'seller_portal' => 'https://www.depop.com/sellinghub/'],
            'vinted' => ['label' => 'Vinted', 'status' => VintedListingStatus::class, 'seller_portal' => 'https://www.vinted.com/inbox'],
            'dhgate' => ['label' => 'DHGate', 'status' => DHGateListingStatus::class, 'seller_portal' => 'https://seller.dhgate.com/'],
            'tiendamia' => ['label' => 'Tiendamia', 'status' => TiendamiaListingStatus::class, 'seller_portal' => 'https://www.tiendamia.com/'],
            'fbmarketplace' => ['label' => 'FB Marketplace', 'status' => FBMarketplaceListingStatus::class, 'seller_portal' => null],
            'fbshop' => ['label' => 'FB Shop', 'status' => FBShopListingStatus::class, 'seller_portal' => null],
            'instagramshop' => ['label' => 'Instagram Shop', 'status' => InstagramShopListingStatus::class, 'seller_portal' => null],
            'mercariwship' => ['label' => 'Mercari w Ship', 'status' => MercariWShipListingStatus::class, 'seller_portal' => null],
            'mercariwoship' => ['label' => 'Mercari w/o Ship', 'status' => MercariWoShipListingStatus::class, 'seller_portal' => null],
            'poshmark' => ['label' => 'Poshmark', 'status' => PoshmarkListingStatus::class, 'seller_portal' => null],
            'offerup' => ['label' => 'OfferUp', 'status' => OfferupListingStatus::class, 'seller_portal' => null],
            'autods' => ['label' => 'AutoDS', 'status' => AutoDSListingStatus::class, 'seller_portal' => null],
            'spocket' => ['label' => 'Spocket', 'status' => SpocketListingStatus::class, 'seller_portal' => null],
            'zendrop' => ['label' => 'Zendrop', 'status' => ZendropListingStatus::class, 'seller_portal' => null],
            'syncee' => ['label' => 'Syncee', 'status' => SynceeListingStatus::class, 'seller_portal' => null],
            'appscenic' => ['label' => 'AppScenic', 'status' => AppscenicListingStatus::class, 'seller_portal' => null],
            'yamibuy' => ['label' => 'Yamibuy', 'status' => YamibuyListingStatus::class, 'seller_portal' => null],
            'swgearexchange' => ['label' => 'SW Gear Exchange', 'status' => SWGearExchangeListingStatus::class, 'seller_portal' => null],
            'business5core' => ['label' => 'Business 5 Core', 'status' => Business5CoreListingStatus::class, 'seller_portal' => null],
            'shopifywholesale' => ['label' => 'Shopify Wholesale', 'status' => ShopifyWholesaleListingStatus::class, 'seller_portal' => null],
            'ebayvariation' => ['label' => 'eBay Variation', 'status' => EbayVariationListingStatus::class, 'seller_portal' => null],
        ];
    }

    /** @return array<string, string> */
    private static function aliases(): array
    {
        return [
            'facebookmarketplace' => 'fbmarketplace',
            'shopifywholesaleds' => 'shopifywholesale',
            'shopifyb2b' => 'shopifywholesale',
        ];
    }

    public static function canonical(string $channel): string
    {
        $key = ListingChannelCounts::normalize($channel);

        return self::aliases()[$key] ?? $key;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function routePattern(): string
    {
        return implode('|', array_merge(self::slugs(), array_keys(self::aliases())));
    }

    public static function has(string $channel): bool
    {
        return isset(self::all()[self::canonical($channel)]);
    }

    /**
     * @return array{label: string, status: class-string, seller_portal: ?string}|null
     */
    public static function get(string $channel): ?array
    {
        return self::all()[self::canonical($channel)] ?? null;
    }

    public static function label(string $channel): string
    {
        return self::get($channel)['label'] ?? $channel;
    }

    /** @return class-string|null */
    public static function statusClass(string $channel): ?string
    {
        return self::get($channel)['status'] ?? null;
    }

    public static function listingPath(string $channel): string
    {
        return '/listing-'.self::canonical($channel);
    }

    public static function importUrl(string $channel): string
    {
        return url('/missing-listing/csv-import/'.self::canonical($channel));
    }
}
