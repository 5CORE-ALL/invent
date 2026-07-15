<?php

namespace App\Services\MarketplaceManager;

/**
 * Channels enabled in the Marketplace Manager UI.
 * Add new marketplaces here as they are implemented.
 */
class MarketplaceManagerRegistry
{
    /** Shared Laravel queue for all Marketplace Manager jobs (orders + inventory). */
    public const QUEUE = 'marketplace-manager';

    /**
     * @return array<int, array{slug: string, label: string, short: string, source_shop: string, logo: string, enabled: bool}>
     */
    public static function channels(): array
    {
        return [
            [
                'slug' => 'aliexpress',
                'label' => 'AliExpress',
                'short' => 'AE',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/aliexpress.png',
                'enabled' => true,
            ],
            [
                'slug' => 'alibaba',
                'label' => 'Alibaba',
                'short' => 'AB',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/alibaba.svg',
                'enabled' => true,
            ],
            [
                'slug' => 'reverb',
                'label' => 'Reverb',
                'short' => 'RV',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/reverb.png',
                'enabled' => true,
            ],
            [
                'slug' => 'newegg',
                'label' => 'Newegg',
                'short' => 'NE',
                'source_shop' => 'Shopify B2C',
                'logo' => 'uploads/newegg.svg',
                'enabled' => true,
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
