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
}
