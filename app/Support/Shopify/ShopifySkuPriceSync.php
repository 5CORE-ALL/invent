<?php

namespace App\Support\Shopify;

/**
 * Maps a Shopify variant price onto shopify_skus and decides when a long
 * product crawl may replace the Price column.
 *
 * /shopify-b2c-pricing reads shopify_skus.price. The live inventory sync used
 * to refresh quantities only, so Price stayed on the last crawl. A crawl that
 * started before a push also wrote that old price back after Shopify had moved.
 */
class ShopifySkuPriceSync
{
    /**
     * Columns for shopify_skus. Empty when Shopify did not return a selling price.
     *
     * @return array{price?: float, b2c_price?: float, b2b_price?: float}
     */
    public static function columnsFromVariant(mixed $price, mixed $compareAtPrice = null): array
    {
        $cols = [];
        $selling = self::money($price);
        if ($selling !== null) {
            $cols['price'] = $selling;
            $cols['b2c_price'] = $selling;
        }
        $compare = self::money($compareAtPrice);
        if ($compare !== null) {
            $cols['b2b_price'] = $compare;
        }

        return $cols;
    }

    /**
     * A crawl may write Price only when nothing newer (a push, or a live pull)
     * has already saved this SKU since the crawl started.
     */
    public static function crawlMayOverwrite(?string $priceUpdatedAt, ?string $crawlStartedAt): bool
    {
        if ($priceUpdatedAt === null || trim($priceUpdatedAt) === '') {
            return true;
        }
        if ($crawlStartedAt === null || trim($crawlStartedAt) === '') {
            return true;
        }
        $updated = strtotime($priceUpdatedAt);
        $started = strtotime($crawlStartedAt);
        if ($updated === false || $started === false) {
            return true;
        }

        return $updated < $started;
    }

    private static function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }
}
