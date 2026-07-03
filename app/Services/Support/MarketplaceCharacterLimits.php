<?php

namespace App\Services\Support;

/**
 * Centralized marketplace character limits for Title / Description / Bullet masters.
 * Values align with channel listing rules; Shopify reference has no hard title cap (100-char tier in PM).
 */
class MarketplaceCharacterLimits
{
    /**
     * Title character limit by marketplace key or title tier (150, 100, 80, 60).
     */
    public static function titleLimit(string $marketplace, ?string $titleType = null): int
    {
        if ($titleType !== null && $titleType !== '') {
            return match ($titleType) {
                '150' => 150,
                '100' => 100,
                '80' => 80,
                '60' => 60,
                default => 150,
            };
        }

        $key = self::normalizeMarketplaceKey($marketplace);

        return (int) (config("marketplaces.character_limits.{$key}")
            ?? config("marketplaces.character_limits.".self::legacyConfigKey($key))
            ?? self::defaultTitleLimit($key));
    }

    public static function truncateTitle(string $title, string $marketplace, ?string $titleType = null): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $max = self::titleLimit($marketplace, $titleType);
        if (mb_strlen($title) > $max) {
            return mb_substr($title, 0, $max);
        }

        return $title;
    }

    /**
     * Plain-text description limits (Description Master).
     */
    public static function descriptionLimit(string $marketplace): int
    {
        $key = self::normalizeMarketplaceKey($marketplace);

        return match ($key) {
            'amazon', 'temu', 'temu2', 'walmart', 'shein', 'aliexpress', 'wayfair' => 2000,
            'shopify_main', 'shopify_pls', 'shopify', 'ebay', 'ebay1', 'ebay2', 'ebay3' => 500000,
            'reverb', 'bestbuy', 'doba' => 1500,
            'macy', 'faire' => 600,
            default => 1500,
        };
    }

    /**
     * Bullet master: max bullets and per-bullet char range.
     *
     * @return array{max_bullets: int, min_chars: int, max_chars: int}
     */
    public static function bulletLimits(string $marketplace): array
    {
        $key = self::normalizeMarketplaceKey($marketplace);

        return match ($key) {
            'amazon' => ['max_bullets' => 5, 'min_chars' => 1, 'max_chars' => 500],
            'ebay', 'ebay1', 'ebay2', 'ebay3' => ['max_bullets' => 5, 'min_chars' => 1, 'max_chars' => 500],
            'shopify_main', 'shopify_pls', 'shopify' => ['max_bullets' => 10, 'min_chars' => 1, 'max_chars' => 5000],
            'walmart' => ['max_bullets' => 10, 'min_chars' => 1, 'max_chars' => 4000],
            default => ['max_bullets' => 5, 'min_chars' => 90, 'max_chars' => 100],
        };
    }

    public static function normalizeMarketplaceKey(string $marketplace): string
    {
        $key = strtolower(trim($marketplace));
        $aliases = [
            'ebay1' => 'ebay',
            'shopify' => 'shopify_main',
            'shopifyb2c' => 'shopify_main',
            'macys' => 'macy',
            'bestbuyusa' => 'bestbuy',
        ];

        return $aliases[$key] ?? $key;
    }

    private static function legacyConfigKey(string $key): string
    {
        return match ($key) {
            'ebay' => 'ebay',
            'shopify_main' => 'shopify',
            default => $key,
        };
    }

    private static function defaultTitleLimit(string $key): int
    {
        return match ($key) {
            'ebay', 'ebay1', 'ebay2', 'ebay3' => 80,
            'shopify_main', 'shopify_pls', 'shopify', 'doba' => 100,
            'macy', 'faire' => 60,
            'shein' => (int) config('services.shein.title_max_length', 80),
            default => 150,
        };
    }
}
