<?php

namespace App\Services\Support;

/**
 * Shared marketplace => metrics table map for Product Master push UIs.
 */
class ProductMasterMarketplaceMaps
{
    /**
     * @return array<string, string>
     */
    public static function metricsTableMap(): array
    {
        return [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'temu2' => 'temu2_metrics',
            'wayfair' => 'wayfair_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'macy' => 'macy_metrics',
            'reverb' => 'reverb_metrics',
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
            'shopify_b5c' => 'shopify_pls_metrics',
            'doba' => 'doba_metrics',
            'walmart' => 'walmart_metrics',
            'faire' => 'faire_metrics',
            'shein' => 'shein_metrics',
            'aliexpress' => 'aliexpress_metric',
            'alibaba' => 'alibaba_metrics',
            'purchasing_power' => 'purchasing_power_metrics',
            'newegg' => 'newegg_metrics',
            'topdawg' => 'topdawg_metrics',
            'tiktok' => 'tiktok_metrics',
            'tiktok2' => 'tiktok_metrics',
        ];
    }

    /**
     * @return array<string, class-string>
     */
    public static function bulletServiceMap(): array
    {
        return [
            'ebay' => \App\Services\EbayApiService::class,
            'ebay2' => \App\Services\Ebay2ApiService::class,
            'ebay3' => \App\Services\EbayThreeApiService::class,
            'macy' => \App\Services\MacysApiService::class,
            'amazon' => \App\Services\AmazonSpApiService::class,
            'temu' => \App\Services\TemuApiService::class,
            'temu2' => \App\Services\Temu2ApiService::class,
            'reverb' => \App\Services\ReverbApiService::class,
            'wayfair' => \App\Services\WayfairApiService::class,
            'bestbuy' => \App\Services\BestBuyApiService::class,
            'shopify_main' => \App\Services\ShopifyApiService::class,
            'shopify_pls' => \App\Services\ShopifyPLSApiService::class,
            'shopify_b5c' => \App\Services\ShopifyPLSApiService::class,
            'doba' => \App\Services\DobaApiService::class,
            'walmart' => \App\Services\WalmartService::class,
            'faire' => \App\Services\FaireService::class,
            'shein' => \App\Services\SheinApiService::class,
            'aliexpress' => \App\Services\AliExpressApiService::class,
            'alibaba' => \App\Services\AlibabaApiService::class,
            'purchasing_power' => \App\Services\PurchasingPowerApiService::class,
            'newegg' => \App\Services\NeweggApiService::class,
            'topdawg' => \App\Services\TopDawgApiService::class,
            'tiktok' => \App\Services\TikTokShopService::class,
            'tiktok2' => \App\Services\TikTok2ShopService::class,
        ];
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function descriptionServiceMap(): array
    {
        return [
            'amazon' => [\App\Services\AmazonSpApiService::class, 'updateAplusContent'],
            'temu' => [\App\Services\TemuApiService::class, 'updateDescription'],
            'temu2' => [\App\Services\Temu2ApiService::class, 'updateDescription'],
            'reverb' => [\App\Services\ReverbApiService::class, 'updateDescription'],
            'macy' => [\App\Services\MacysApiService::class, 'updateDescription'],
            'ebay' => [\App\Services\EbayApiService::class, 'updateDescription'],
            'ebay2' => [\App\Services\Ebay2ApiService::class, 'updateDescription'],
            'ebay3' => [\App\Services\EbayThreeApiService::class, 'updateDescription'],
            'wayfair' => [\App\Services\WayfairApiService::class, 'updateProductDescription'],
            'bestbuy' => [\App\Services\BestBuyApiService::class, 'updateDescription'],
            'doba' => [\App\Services\DobaApiService::class, 'updateProductDescription'],
            'walmart' => [\App\Services\WalmartService::class, 'updateProductDescription'],
            'faire' => [\App\Services\FaireService::class, 'updateProductDescription'],
            'shein' => [\App\Services\SheinApiService::class, 'updateProductDescription'],
            'aliexpress' => [\App\Services\AliExpressApiService::class, 'updateProductDescription'],
            'alibaba' => [\App\Services\AlibabaApiService::class, 'updateProductDescription'],
            'shopify_main' => [\App\Services\ShopifyApiService::class, 'updateDescription'],
            'shopify_pls' => [\App\Services\ShopifyPLSApiService::class, 'updateDescription'],
            'shopify_b5c' => [\App\Services\ShopifyPLSApiService::class, 'updateDescription'],
            'purchasing_power' => [\App\Services\PurchasingPowerApiService::class, 'updateDescription'],
            'newegg' => [\App\Services\NeweggApiService::class, 'updateDescription'],
            'topdawg' => [\App\Services\TopDawgApiService::class, 'updateDescription'],
            'tiktok' => [\App\Services\TikTokShopService::class, 'updateDescription'],
            'tiktok2' => [\App\Services\TikTok2ShopService::class, 'updateDescription'],
        ];
    }

    public static function bulletTableMap(): array
    {
        return array_intersect_key(self::metricsTableMap(), self::bulletServiceMap());
    }

    public static function descriptionTableMap(): array
    {
        return array_intersect_key(self::metricsTableMap(), self::descriptionServiceMap());
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function imagePushMap(): array
    {
        return [
            'ebay' => [\App\Services\EbayApiService::class, 'updateListingImages'],
            'ebay2' => [\App\Services\Ebay2ApiService::class, 'updateListingImages'],
            'ebay3' => [\App\Services\EbayThreeApiService::class, 'updateListingImages'],
            'amazon' => [\App\Services\AmazonSpApiService::class, 'updateImages'],
            'temu' => [\App\Services\TemuApiService::class, 'updateImages'],
            'temu2' => [\App\Services\Temu2ApiService::class, 'updateImages'],
            'wayfair' => [\App\Services\WayfairApiService::class, 'updateImages'],
            'bestbuy' => [\App\Services\BestBuyApiService::class, 'updateImages'],
            'shopify_main' => [\App\Services\ShopifyApiService::class, 'updateImages'],
            'shopify_pls' => [\App\Services\ShopifyPLSApiService::class, 'updateImages'],
            'shopify_b5c' => [\App\Services\ShopifyPLSApiService::class, 'updateImages'],
            'macy' => [\App\Services\MacysApiService::class, 'updateImages'],
            'reverb' => [\App\Services\ReverbApiService::class, 'updateImages'],
            'doba' => [\App\Services\DobaApiService::class, 'updateImages'],
            'walmart' => [\App\Services\WalmartService::class, 'updateImages'],
            'faire' => [\App\Services\FaireService::class, 'updateImages'],
            'shein' => [\App\Services\SheinApiService::class, 'updateImages'],
            'aliexpress' => [\App\Services\AliExpressApiService::class, 'updateImages'],
            'alibaba' => [\App\Services\AlibabaApiService::class, 'updateImages'],
            'purchasing_power' => [\App\Services\PurchasingPowerApiService::class, 'updateImages'],
            'newegg' => [\App\Services\NeweggApiService::class, 'updateImages'],
            'topdawg' => [\App\Services\TopDawgApiService::class, 'updateImages'],
            'tiktok' => [\App\Services\TikTokShopService::class, 'updateImages'],
            'tiktok2' => [\App\Services\TikTok2ShopService::class, 'updateImages'],
        ];
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function videoPushMap(): array
    {
        return [
            'ebay' => [\App\Services\EbayApiService::class, 'updateListingVideos'],
            'ebay2' => [\App\Services\Ebay2ApiService::class, 'updateListingVideos'],
            'ebay3' => [\App\Services\EbayThreeApiService::class, 'updateListingVideos'],
            'amazon' => [\App\Services\AmazonSpApiService::class, 'updateVideos'],
            'temu' => [\App\Services\TemuApiService::class, 'updateVideos'],
            'temu2' => [\App\Services\Temu2ApiService::class, 'updateVideos'],
            'wayfair' => [\App\Services\WayfairApiService::class, 'updateVideos'],
            'bestbuy' => [\App\Services\BestBuyApiService::class, 'updateVideos'],
            'shopify_main' => [\App\Services\ShopifyApiService::class, 'updateVideos'],
            'shopify_pls' => [\App\Services\ShopifyPLSApiService::class, 'updateVideos'],
            'shopify_b5c' => [\App\Services\ShopifyPLSApiService::class, 'updateVideos'],
            'macy' => [\App\Services\MacysApiService::class, 'updateVideos'],
            'reverb' => [\App\Services\ReverbApiService::class, 'updateVideos'],
            'doba' => [\App\Services\DobaApiService::class, 'updateVideos'],
            'walmart' => [\App\Services\WalmartService::class, 'updateVideos'],
            'faire' => [\App\Services\FaireService::class, 'updateVideos'],
            'shein' => [\App\Services\SheinApiService::class, 'updateVideos'],
            'aliexpress' => [\App\Services\AliExpressApiService::class, 'updateVideos'],
            'alibaba' => [\App\Services\AlibabaApiService::class, 'updateVideos'],
            'purchasing_power' => [\App\Services\PurchasingPowerApiService::class, 'updateVideos'],
            'newegg' => [\App\Services\NeweggApiService::class, 'updateVideos'],
            'topdawg' => [\App\Services\TopDawgApiService::class, 'updateVideos'],
            'tiktok' => [\App\Services\TikTokShopService::class, 'updateVideos'],
            'tiktok2' => [\App\Services\TikTok2ShopService::class, 'updateVideos'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function titleMarketplaces(): array
    {
        return [
            'amazon', 'temu', 'temu2', 'reverb', 'wayfair', 'walmart',
            'shopify_main', 'shopify_pls', 'shopify_b5c', 'doba',
            'ebay', 'ebay2', 'ebay3', 'macy', 'faire', 'bestbuy',
            'shein', 'aliexpress', 'alibaba', 'purchasing_power',
            'newegg', 'topdawg', 'tiktok', 'tiktok2',
        ];
    }

    public static function imageTableMap(): array
    {
        return array_intersect_key(self::metricsTableMap(), self::imagePushMap());
    }

    public static function videoTableMap(): array
    {
        $keys = array_keys(self::videoPushMap());

        return array_intersect_key(self::metricsTableMap(), array_flip($keys));
    }
}
