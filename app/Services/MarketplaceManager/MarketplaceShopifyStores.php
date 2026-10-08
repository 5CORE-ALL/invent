<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;

/**
 * The Shopify stores Marketplace Manager channels import their orders into.
 */
final class MarketplaceShopifyStores
{
    public const MARKETPLACES = [
        'aliexpress', 'alibaba', 'amazon', 'b5cb2b', 'doba', 'ebay1', 'ebay2', 'ebay3', 'faire',
        'newegg', 'reverb', 'shein', 'temu', 'temu2', 'temu3', 'tiktok', 'tiktok2', 'topdawg', 'wayfair',
    ];

    /**
     * @return list<array{store_url: string, token: string, store_key: string}>
     */
    public static function configs(?string $only = null): array
    {
        $selector = app(ShopifyStoreSelector::class);
        $only = strtolower(trim((string) $only));
        $keys = $only !== '' ? [$only] : ['main'];
        if ($only === '') {
            foreach (self::MARKETPLACES as $slug) {
                try {
                    $keys[] = (string) (MarketplaceSyncSettings::getFor($slug)['order']['shopify_store'] ?? 'main');
                } catch (\Throwable $e) {
                }
            }
        }

        $configs = [];
        foreach (array_unique(array_filter($keys)) as $key) {
            $config = $selector->getConfigForStore($key);
            if (($config['store_url'] ?? '') !== '' && ($config['token'] ?? '') !== '') {
                $configs[$config['store_url']] = $config;
            }
        }

        return array_values($configs);
    }
}
