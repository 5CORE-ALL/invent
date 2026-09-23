<?php

namespace App\Support;

/**
 * Marketplace labels stored on sku_reviews (/reviews) vs the keys analytics pages use.
 */
class SkuReviewMarketplace
{
    /**
     * @return list<string>
     */
    public static function aliases(string $marketplace): array
    {
        $compact = strtolower(preg_replace('/\s+/', '', trim($marketplace)) ?? '');
        if ($compact === '') {
            return [];
        }

        $aliases = match (true) {
            $compact === 'amazon' => ['amazon'],
            in_array($compact, ['ebay', 'ebay1', 'ebayone'], true) => ['ebay', 'ebay1', 'ebay one'],
            in_array($compact, ['ebay2', 'ebaytwo', 'ebay2op'], true) => ['ebay2', 'ebay two', 'ebay2op'],
            in_array($compact, ['ebay3', 'ebaythree'], true) => ['ebay3', 'ebay three'],
            in_array($compact, ['temu', 'temu1'], true) => ['temu', 'temu1', 'temu 1'],
            $compact === 'temu2' => ['temu2', 'temu 2'],
            $compact === 'temu3' => ['temu3', 'temu 3'],
            in_array($compact, ['tiktok', 'tiktok1', 'tiktokshop'], true) => ['tiktok', 'tiktok1', 'tiktok shop', 'tiktok 1'],
            in_array($compact, ['tiktok2', 'tiktokshop2'], true) => ['tiktok2', 'tiktok 2'],
            in_array($compact, ['shopify', 'sb2c', 'shopifyb2c', 'b2c'], true) => ['shopify', 'sb2c', 'shopifyb2c', 'shopify b2c', 'b2c'],
            in_array($compact, ['sb2b', 'shopifyb2b', 'b2b'], true) => ['sb2b', 'shopifyb2b', 'shopify b2b', 'b2b'],
            in_array($compact, ['newegg', 'neweggb2c'], true) => ['newegg', 'neweggb2c', 'newegg b2c'],
            in_array($compact, ['macy', 'macys'], true) => ['macy', 'macys', "macy's"],
            in_array($compact, ['aliexpress', 'ali'], true) => ['aliexpress', 'ali'],
            in_array($compact, ['walmart'], true) => ['walmart'],
            in_array($compact, ['reverb'], true) => ['reverb'],
            in_array($compact, ['faire'], true) => ['faire'],
            in_array($compact, ['shein'], true) => ['shein'],
            default => [trim($marketplace)],
        };

        $out = [];
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias !== '' && ! in_array($alias, $out, true)) {
                $out[] = $alias;
            }
        }

        return $out;
    }

    /**
     * One display name per channel. temu / temu1 / temu 1 all become Temu.
     */
    public static function label(string $marketplace): string
    {
        $compact = strtolower(preg_replace('/\s+/', '', trim($marketplace)) ?? '');
        if ($compact === '') {
            return '';
        }

        return match (true) {
            $compact === 'amazon' => 'Amazon',
            in_array($compact, ['ebay', 'ebay1', 'ebayone'], true) => 'Ebay',
            in_array($compact, ['ebay2', 'ebaytwo', 'ebay2op'], true) => 'Ebay 2',
            in_array($compact, ['ebay3', 'ebaythree'], true) => 'Ebay 3',
            in_array($compact, ['temu', 'temu1'], true) => 'Temu',
            $compact === 'temu2' => 'Temu 2',
            $compact === 'temu3' => 'Temu 3',
            in_array($compact, ['tiktok', 'tiktok1', 'tiktokshop'], true) => 'TikTok',
            in_array($compact, ['tiktok2', 'tiktokshop2'], true) => 'TikTok 2',
            in_array($compact, ['shopify', 'sb2c', 'shopifyb2c', 'b2c'], true) => 'Shopify B2C',
            in_array($compact, ['sb2b', 'shopifyb2b', 'b2b'], true) => 'Shopify B2B',
            in_array($compact, ['newegg', 'neweggb2c'], true) => 'Newegg',
            in_array($compact, ['macy', 'macys'], true) => 'Macys',
            in_array($compact, ['aliexpress', 'ali'], true) => 'Aliexpress',
            $compact === 'walmart' => 'Walmart',
            $compact === 'reverb' => 'Reverb',
            $compact === 'faire' => 'Faire',
            $compact === 'shein' => 'Shein',
            default => trim($marketplace),
        };
    }

    /**
     * Filter options. Includes channels that may have no reviews yet.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return [
            'Aliexpress',
            'Amazon',
            'Ebay',
            'Ebay 2',
            'Ebay 3',
            'Faire',
            'Macys',
            'Newegg',
            'Reverb',
            'Shein',
            'Shopify B2C',
            'Shopify B2B',
            'Temu',
            'Temu 2',
            'Temu 3',
            'TikTok',
            'TikTok 2',
            'Walmart',
        ];
    }
}
