<?php

namespace App\Services\MarketplaceManager;

/**
 * Channels enabled in the Marketplace Manager UI.
 * Add new marketplaces here as they are implemented — each gets its own queue worker.
 */
class MarketplaceManagerRegistry
{
    /**
     * Legacy shared queue (still watched during migration). Prefer queueFor($slug).
     *
     * @deprecated Use queueFor() — inventory/order jobs are per-marketplace for parallelism.
     */
    public const QUEUE = 'marketplace-manager';

    public const QUEUE_PREFIX = 'mm-';

    /**
     * Dedicated Laravel queue name for one marketplace (parallel workers).
     * Example: reverb → mm-reverb
     */
    public static function queueFor(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || ! self::isSupported($slug)) {
            return self::QUEUE;
        }

        return self::QUEUE_PREFIX.$slug;
    }

    /**
     * All dedicated marketplace queue names (for workers / status).
     *
     * @return list<string>
     */
    public static function queueNames(): array
    {
        return array_map(
            static fn (string $slug) => self::queueFor($slug),
            self::slugs()
        );
    }

    /**
     * @return array<int, array{
     *     slug: string,
     *     label: string,
     *     short: string,
     *     source_shop: string,
     *     logo: string,
     *     enabled: bool,
     *     mp_channel_keys: list<string>
     * }>
     *
     * mp_channel_keys = channel_master.channel names that match this marketplace
     * on /all-marketplace-master (case-insensitive lookup).
     */
    public static function channels(): array
    {
        return [
            [
                'slug' => 'amazon',
                'label' => 'Amazon',
                'short' => 'AMZ',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/amazon.png',
                'enabled' => true,
                // Active Channels Master stores this as Amazon.
                'mp_channel_keys' => ['Amazon', 'amazon'],
            ],
            [
                'slug' => 'aliexpress',
                'label' => 'AliExpress',
                'short' => 'AE',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/aliexpress.png',
                'enabled' => true,
                'mp_channel_keys' => ['Aliexpress', 'AliExpress'],
            ],
            [
                'slug' => 'alibaba',
                'label' => 'Alibaba',
                'short' => 'AB',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/alibaba.svg',
                'enabled' => true,
                'mp_channel_keys' => ['Alibaba'],
            ],
            [
                'slug' => 'reverb',
                'label' => 'Reverb',
                'short' => 'RV',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/reverb.png',
                'enabled' => true,
                'mp_channel_keys' => ['Reverb'],
            ],
            [
                'slug' => 'newegg',
                'label' => 'Newegg',
                'short' => 'NE',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/newegg.svg',
                'enabled' => true,
                'mp_channel_keys' => ['Newegg', 'NewEgg'],
            ],
            [
                'slug' => 'shein',
                'label' => 'Shein',
                'short' => 'SH',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/Shein.jpg',
                'enabled' => true,
                'mp_channel_keys' => ['Shein'],
            ],
            [
                'slug' => 'topdawg',
                'label' => 'TopDawg',
                'short' => 'TD',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/topdawg.svg',
                'enabled' => true,
                'mp_channel_keys' => ['TopDawg', 'topdawg', 'Topdawg'],
            ],
            [
                'slug' => 'temu',
                'label' => 'Temu',
                'short' => 'TM',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/temu.jpeg',
                'enabled' => true,
                'mp_channel_keys' => ['Temu', 'temu'],
            ],
            [
                'slug' => 'temu2',
                'label' => 'Temu 2',
                'short' => 'T2',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/temu.jpeg',
                'enabled' => true,
                'mp_channel_keys' => ['Temu 2', 'Temu2', 'temu2', 'TemuTwo'],
            ],
            [
                'slug' => 'ebay1',
                'label' => 'eBay 1',
                'short' => 'E1',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/1.png',
                'enabled' => true,
                'mp_channel_keys' => ['Ebay', 'eBay', 'eBay 1', 'Ebay 1', 'ebay1'],
            ],
            [
                'slug' => 'ebay2',
                'label' => 'eBay 2',
                'short' => 'E2',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/2.png',
                'enabled' => true,
                // Active Channels Master stores this as EbayTwo (alias "Ebay 2").
                'mp_channel_keys' => ['EbayTwo', 'eBay 2', 'Ebay 2'],
            ],
            [
                'slug' => 'ebay3',
                'label' => 'eBay 3',
                'short' => 'E3',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/3.png',
                'enabled' => true,
                // Active Channels Master stores this as EbayThree (alias "Ebay 3").
                'mp_channel_keys' => ['EbayThree', 'eBay 3', 'Ebay 3'],
            ],
            [
                'slug' => 'faire',
                'label' => 'Faire',
                'short' => 'FR',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/faire.svg',
                'enabled' => true,
                'mp_channel_keys' => ['Faire'],
            ],
            [
                'slug' => 'purchasingpower',
                'label' => 'Purchasing Power',
                'short' => 'PP',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/purchasingpower.svg',
                'enabled' => true,
                'mp_channel_keys' => ['Purchasing Power', 'purchasingpower', 'PurchasingPower'],
            ],
            [
                'slug' => 'wayfair',
                'label' => 'Wayfair',
                'short' => 'WF',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/wayfair.png',
                'enabled' => true,
                'mp_channel_keys' => ['Wayfair', 'wayfair'],
            ],
            [
                'slug' => 'bestbuy',
                'label' => 'Best Buy',
                'short' => 'BB',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/bestbuy.jpeg',
                'enabled' => true,
                'mp_channel_keys' => ['Best Buy USA', 'BestBuy USA', 'Bestbuy USA', 'Best Buy', 'bestbuy', 'Bestbuy'],
            ],
            [
                'slug' => 'macy',
                'label' => "Macy's",
                'short' => 'MC',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/macy.png',
                'enabled' => true,
                'mp_channel_keys' => ["Macy's", "Macy's, Inc.", 'Macys', 'Macy', 'macy', 'macys'],
            ],
            [
                'slug' => 'doba',
                'label' => 'Doba',
                'short' => 'DB',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/doba.png',
                'enabled' => true,
                'mp_channel_keys' => ['Doba', 'doba', 'DOBA'],
            ],
        ];
    }

    public static function logoUrl(string $slug): ?string
    {
        $channel = self::find($slug);

        if ($channel === null || empty($channel['logo'])) {
            return null;
        }

        return asset($channel['logo']);
    }

    public static function slugs(): array
    {
        return array_column(self::channels(), 'slug');
    }

    public static function find(string $slug): ?array
    {
        foreach (self::channels() as $channel) {
            if ($channel['slug'] === strtolower($slug)) {
                return $channel;
            }
        }

        return null;
    }

    public static function isSupported(string $slug): bool
    {
        return self::find($slug) !== null;
    }
}
