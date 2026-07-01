<?php

namespace App\Services\Support;

/**
 * Canonical marketplace list aligned with /all-marketplace-master (ChannelMasterController $controllerMap).
 */
class AllMarketplaceChannelRegistry
{
    /**
     * @return list<array{key: string, label: string, short: string, cls: string, group: string, bullet: bool, description: bool, image: bool, video: bool}>
     */
    public function channels(): array
    {
        return [
            ['key' => 'amazon', 'label' => 'Amazon', 'short' => 'A', 'cls' => 'btn-amazon', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'amazon_fba', 'label' => 'Amazon FBA', 'short' => 'AF', 'cls' => 'btn-amazon-fba', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'ebay', 'label' => 'eBay 1', 'short' => 'E1', 'cls' => 'btn-ebay1', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'ebay2', 'label' => 'eBay 2', 'short' => 'E2', 'cls' => 'btn-ebay2', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'ebay3', 'label' => 'eBay 3', 'short' => 'E3', 'cls' => 'btn-ebay3', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'macy', 'label' => "Macy's", 'short' => 'M', 'cls' => 'btn-macy', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'tiendamia', 'label' => 'Tiendamia', 'short' => 'TM', 'cls' => 'btn-tiendamia', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'bestbuy', 'label' => 'Best Buy', 'short' => 'B', 'cls' => 'btn-bestbuy', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'newegg', 'label' => 'Newegg', 'short' => 'NE', 'cls' => 'btn-newegg', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => true],
            ['key' => 'reverb', 'label' => 'Reverb', 'short' => 'R', 'cls' => 'btn-reverb', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'doba', 'label' => 'Doba', 'short' => 'D', 'cls' => 'btn-doba', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'temu', 'label' => 'Temu 1', 'short' => 'T1', 'cls' => 'btn-temu', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'temu2', 'label' => 'Temu 2', 'short' => 'T2', 'cls' => 'btn-temu', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'walmart', 'label' => 'Walmart', 'short' => 'Wal', 'cls' => 'btn-walmart', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'shopify_pls', 'label' => 'Shopify PLS', 'short' => 'PLS', 'cls' => 'btn-shopify-pls', 'group' => 'shopify', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'wayfair', 'label' => 'Wayfair', 'short' => 'W', 'cls' => 'btn-wayfair', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'faire', 'label' => 'Faire', 'short' => 'F', 'cls' => 'btn-faire', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'purchasing_power', 'label' => 'Purchasing Power', 'short' => 'PP', 'cls' => 'btn-purchasing-power', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'shein', 'label' => 'Shein', 'short' => 'S', 'cls' => 'btn-shein', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'tiktok', 'label' => 'TikTok Shop', 'short' => 'TT', 'cls' => 'btn-tiktok', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => true],
            ['key' => 'tiktok2', 'label' => 'TikTok Shop 2', 'short' => 'TT2', 'cls' => 'btn-tiktok', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => true],
            ['key' => 'depop', 'label' => 'Depop', 'short' => 'Dp', 'cls' => 'btn-depop', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'instagram_shop', 'label' => 'Instagram Shop', 'short' => 'IG', 'cls' => 'btn-instagram', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'aliexpress', 'label' => 'AliExpress', 'short' => 'AE', 'cls' => 'btn-aliexpress', 'group' => 'marketplaces', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'mercari_wship', 'label' => 'Mercari w/ Ship', 'short' => 'Mw', 'cls' => 'btn-mercari', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'mercari_woship', 'label' => 'Mercari w/o Ship', 'short' => 'Mo', 'cls' => 'btn-mercari', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'fb_marketplace', 'label' => 'FB Marketplace', 'short' => 'FB', 'cls' => 'btn-fb', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'fb_shop', 'label' => 'FB Shop', 'short' => 'FS', 'cls' => 'btn-fb', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'shopify_b5c', 'label' => 'Business 5Core', 'short' => 'B5C', 'cls' => 'btn-shopify-b5c', 'group' => 'shopify', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
            ['key' => 'topdawg', 'label' => 'TopDawg', 'short' => 'TD', 'cls' => 'btn-topdawg', 'group' => 'marketplaces', 'bullet' => false, 'description' => false, 'image' => false, 'video' => true],
            ['key' => 'shopify_main', 'label' => 'Shopify B2C', 'short' => 'SM', 'cls' => 'btn-shopify', 'group' => 'shopify', 'bullet' => true, 'description' => true, 'image' => true, 'video' => true],
            ['key' => 'shopify_b2b', 'label' => 'Shopify B2B', 'short' => 'B2B', 'cls' => 'btn-shopify-b2b', 'group' => 'shopify', 'bullet' => false, 'description' => false, 'image' => false, 'video' => false],
        ];
    }

    /**
     * @return list<string>
     */
    public function allKeys(): array
    {
        return array_column($this->channels(), 'key');
    }

    /**
     * Title Master: registry key → API push key + title tier (150/100/80/60).
     *
     * @return array<string, array{push: string, type: string}>
     */
    public function titleMeta(): array
    {
        return [
            'amazon' => ['push' => 'amazon', 'type' => '150'],
            'ebay' => ['push' => 'ebay1', 'type' => '80'],
            'ebay2' => ['push' => 'ebay2', 'type' => '80'],
            'ebay3' => ['push' => 'ebay3', 'type' => '80'],
            'macy' => ['push' => 'macy', 'type' => '60'],
            'reverb' => ['push' => 'reverb', 'type' => '150'],
            'doba' => ['push' => 'doba', 'type' => '100'],
            'temu' => ['push' => 'temu', 'type' => '150'],
            'temu2' => ['push' => 'temu2', 'type' => '150'],
            'walmart' => ['push' => 'walmart', 'type' => '150'],
            'shopify_pls' => ['push' => 'shopify_pls', 'type' => '100'],
            'shopify_main' => ['push' => 'shopify_main', 'type' => '100'],
            'wayfair' => ['push' => 'wayfair', 'type' => '150'],
            'faire' => ['push' => 'faire', 'type' => '60'],
            'shein' => ['push' => 'shein', 'type' => '150'],
            'aliexpress' => ['push' => 'aliexpress', 'type' => '150'],
            'tiktok' => ['push' => 'tiktok', 'type' => '150'],
        ];
    }

    public function enabledFor(string $master): array
    {
        if ($master === 'title') {
            return array_keys($this->titleMeta());
        }

        $flag = match ($master) {
            'bullet' => 'bullet',
            'description' => 'description',
            'image' => 'image',
            'video' => 'video',
            default => 'bullet',
        };

        $enabled = [];
        foreach ($this->channels() as $ch) {
            if ($ch[$flag] ?? false) {
                $enabled[] = $ch['key'];
            }
        }

        return $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsConfig(string $master): array
    {
        $channels = $this->channels();
        $flag = match ($master) {
            'bullet' => 'bullet',
            'description' => 'description',
            'image' => 'image',
            'video' => 'video',
            'title' => 'title',
            default => 'bullet',
        };

        $labels = [];
        $tiles = [];
        $groups = ['gChannels' => [], 'gShopify' => []];
        $marketplaces = [];

        foreach ($channels as $ch) {
            $key = $ch['key'];
            $marketplaces[] = $key;
            $labels[$key] = $ch['label'];
            $tiles[$key] = ['cls' => $ch['cls'], 'short' => $ch['short']];
            $groupKey = ($ch['group'] ?? 'marketplaces') === 'shopify' ? 'gShopify' : 'gChannels';
            $groups[$groupKey][] = $key;
        }

        $enabled = $master === 'title'
            ? array_keys($this->titleMeta())
            : array_values(array_filter(array_map(
                fn (array $ch) => ($ch[$flag] ?? false) ? $ch['key'] : null,
                $channels
            )));

        $config = [
            'marketplaces' => $marketplaces,
            'groups' => $groups,
            'labels' => $labels,
            'tiles' => $tiles,
            'enabled' => $enabled,
            'master' => $master,
        ];

        if ($master === 'title') {
            $config['titleMeta'] = $this->titleMeta();
        }

        return $config;
    }
}
