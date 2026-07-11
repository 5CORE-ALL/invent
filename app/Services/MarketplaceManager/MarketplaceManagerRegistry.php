<?php

namespace App\Services\MarketplaceManager;

/**
 * Channels enabled in the Marketplace Manager UI.
 * Add new marketplaces here as they are implemented.
 */
class MarketplaceManagerRegistry
{
    /**
     * @return array<int, array{slug: string, label: string, short: string, source_shop: string, enabled: bool}>
     */
    public static function channels(): array
    {
        return [
            [
                'slug' => 'aliexpress',
                'label' => 'AliExpress',
                'short' => 'AE',
                'source_shop' => 'Shopify B2C',
                'enabled' => true,
            ],
            [
                'slug' => 'alibaba',
                'label' => 'Alibaba',
                'short' => 'AB',
                'source_shop' => 'Shopify B2C',
                'enabled' => true,
            ],
        ];
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
