<?php

namespace App\Support\Lqs;

class LqsMarketplaceCatalog
{
    /**
     * @return array<string, array{
     *     slug: string,
     *     label: string,
     *     title: string,
     *     subtitle: string,
     *     listing_label: string,
     *     lqs_source: string,
     *     metrics: string|null,
     *     listing_url: string|null
     * }>
     */
    public static function all(): array
    {
        $rows = [
            ['ebay', 'eBay', 'Item ID', 'sheet', 'ebay', 'https://www.ebay.com/itm/{id}'],
            ['ebay2', 'eBay 2', 'Item ID', 'sheet', 'ebay2', 'https://www.ebay.com/itm/{id}'],
            ['ebay3', 'eBay 3', 'Item ID', 'sheet', 'ebay3', 'https://www.ebay.com/itm/{id}'],
            ['shopify', 'Shopify', 'Listing ID', 'sheet', 'shopify', null],
            ['temu', 'Temu', 'Goods ID', 'sheet', 'temu', null],
            ['temu2', 'Temu 2', 'Goods ID', 'sheet', 'temu2', null],
            ['temu3', 'Temu 3', 'Goods ID', 'sheet', null, null],
            ['tiktok', 'TikTok', 'Listing ID', 'sheet', null, null],
            ['tiktok2', 'TikTok 2', 'Listing ID', 'sheet', null, null],
            ['aliexpress', 'AliExpress', 'Product ID', 'sheet', 'aliexpress', 'https://www.aliexpress.com/item/{id}.html'],
            ['alibaba', 'Alibaba', 'Product ID', 'sheet', 'alibaba', null],
            ['reverb', 'Reverb', 'Product ID', 'sheet', 'reverb', 'https://reverb.com/item/{id}'],
            ['newegg', 'Newegg', 'Product ID', 'sheet', 'newegg', null],
            ['shein', 'Shein', 'SKU Code', 'sheet', 'shein', null],
            ['wayfair', 'Wayfair', 'Listing ID', 'sheet', null, null],
            ['bestbuy', 'Best Buy', 'Listing ID', 'sheet', null, null],
            ['macy', "Macy's", 'Listing ID', 'sheet', null, null],
            ['doba', 'Doba', 'Item ID', 'sheet', 'doba', null],
            ['faire', 'Faire', 'Product ID', 'sheet', 'faire', null],
            ['pls', 'PLS', 'Listing ID', 'sheet', null, null],
            ['purchasingpower', 'Purchasing Power', 'Listing ID', 'sheet', null, null],
            ['walmart', 'Walmart', 'Item ID', 'sheet', 'walmart', 'https://www.walmart.com/ip/{id}'],
            ['fbmarketplace', 'FB Marketplace', 'Listing ID', 'sheet', null, null],
            ['topdawg', 'TopDawg', 'Listing ID', 'sheet', null, null],
            ['b5cb2b', 'Business 5 Core', 'Listing ID', 'sheet', null, null],
        ];

        $map = [];
        foreach ($rows as [$slug, $label, $listingLabel, $lqsSource, $metrics, $listingUrl]) {
            $map[$slug] = [
                'slug' => $slug,
                'label' => $label,
                'title' => 'LQS '.$label,
                'subtitle' => $label.' Listing Quality Score – Parent SKU, '.$listingLabel.', Sessions, Units & LQS metrics',
                'listing_label' => $listingLabel,
                'lqs_source' => $lqsSource,
                'metrics' => $metrics,
                'listing_url' => $listingUrl,
            ];
        }

        return $map;
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function className(string $slug): string
    {
        return 'Lqs'.str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))).'Controller';
    }

    public static function controllerFqn(string $slug): string
    {
        return 'App\\Http\\Controllers\\MarketPlace\\Lqs\\'.self::className($slug);
    }

    public static function viewName(string $slug): string
    {
        return 'market-places.lqs.'.$slug;
    }

    /**
     * Map an Active Channel snapshot key to an LQS page source.
     * "amz" uses lqs_amz_history; other slugs use lqs_marketplace_history.
     */
    public static function sourceForSnapshotKey(string $snapshotKey): ?string
    {
        $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($snapshotKey)));

        return match ($key) {
            'amz', 'amazon', 'amazondotcom', 'amazonfbm', 'amazonfba' => 'amz',
            'ebay', 'ebay1', 'ebayone' => 'ebay',
            'ebay2', 'ebaytwo' => 'ebay2',
            'ebay3', 'ebaythree' => 'ebay3',
            'shopify', 'shopifyb2c' => 'shopify',
            'temu' => 'temu',
            'temu2', 'temutwo' => 'temu2',
            'temu3', 'temuthree' => 'temu3',
            'tiktok', 'tiktokshop' => 'tiktok',
            'tiktok2', 'tiktokshop2' => 'tiktok2',
            'bestbuy', 'bestbuyusa' => 'bestbuy',
            'macy', 'macys' => 'macy',
            'fbmarketplace', 'facebookmarketplace' => 'fbmarketplace',
            'b5cb2b', 'business5core', 'business5coreb2b' => 'b5cb2b',
            default => self::find($key) ? $key : null,
        };
    }
}
