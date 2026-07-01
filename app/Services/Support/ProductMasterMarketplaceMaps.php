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
            'doba' => 'doba_metrics',
            'walmart' => 'walmart_metrics',
            'faire' => 'faire_metrics',
            'shein' => 'shein_metrics',
            'aliexpress' => 'aliexpress_metrics',
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
            'doba' => \App\Services\DobaApiService::class,
            'walmart' => \App\Services\WalmartService::class,
            'faire' => \App\Services\FaireService::class,
            'shein' => \App\Services\SheinApiService::class,
            'aliexpress' => \App\Services\AliExpressApiService::class,
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bulletTableMap(): array
    {
        return array_intersect_key(self::metricsTableMap(), self::bulletServiceMap());
    }

    /**
     * @return array<string, string>
     */
    public static function descriptionTableMap(): array
    {
        return array_intersect_key(self::metricsTableMap(), self::descriptionServiceMap());
    }

    /**
     * Marketplaces with Image Master remote push implemented.
     *
     * @return array<string, string>
     */
    public static function imageTableMap(): array
    {
        $keys = [
            'ebay', 'ebay2', 'ebay3', 'amazon', 'temu', 'temu2', 'wayfair', 'bestbuy', 'macy', 'reverb',
            'shopify_main', 'shopify_pls', 'doba', 'walmart', 'faire', 'shein', 'aliexpress',
        ];

        return array_intersect_key(self::metricsTableMap(), array_flip($keys));
    }
}
