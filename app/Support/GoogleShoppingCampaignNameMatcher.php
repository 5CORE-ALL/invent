<?php

namespace App\Support;

/**
 * Match Google Shopping campaign names to product-master PARENT SKUs.
 * Collapses whitespace and strips trailing dots so e.g.
 * "PARENT CS 69 PAIR" matches "PARENT CS  69 PAIR" and "PARENT GS REST." matches "PARENT GS REST".
 */
final class GoogleShoppingCampaignNameMatcher
{
    public static function normalize(string $name): string
    {
        $s = strtoupper(trim($name));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return rtrim($s, '.');
    }

    public static function matches(string $campaignName, string $sku): bool
    {
        $skuNorm = self::normalize($sku);
        if ($skuNorm === '') {
            return false;
        }

        $campaignNorm = self::normalize($campaignName);
        if ($campaignNorm === $skuNorm) {
            return true;
        }

        foreach (explode(',', $campaignNorm) as $part) {
            if (self::normalize($part) === $skuNorm) {
                return true;
            }
        }

        return false;
    }
}
