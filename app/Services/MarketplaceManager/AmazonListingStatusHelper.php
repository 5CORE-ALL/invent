<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ShopifySku;

/**
 * Shared parsing for amazon_listing_statuses (sku + JSON value).
 */
final class AmazonListingStatusHelper
{
    /**
     * @return array<string, mixed>
     */
    public static function valueArray(?AmazonListingStatus $row): array
    {
        if (! $row) {
            return [];
        }
        $value = $row->value;

        return is_array($value) ? $value : [];
    }

    public static function resolveAsin(?AmazonListingStatus $row): string
    {
        $value = self::valueArray($row);
        foreach (['asin', 'ASIN', 'asin1', 'product_id'] as $key) {
            $candidate = strtoupper(trim((string) ($value[$key] ?? '')));
            if ($candidate !== '' && preg_match('/^[A-Z0-9]{10}$/', $candidate)) {
                return $candidate;
            }
        }

        // Most amazon_listing_statuses rows store ASIN only inside buyer/seller links.
        foreach (['buyer_link', 'seller_link', 'listing_url', 'url', 'link'] as $key) {
            $url = trim((string) ($value[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            if (preg_match('#/(?:dp|gp/product|ASIN)/([A-Z0-9]{10})#i', $url, $m)) {
                return strtoupper($m[1]);
            }
            if (preg_match('#[?&]asin=([A-Z0-9]{10})#i', $url, $m)) {
                return strtoupper($m[1]);
            }
        }

        return '';
    }

    /**
     * Marketplace product id for link map / live listings (ASIN preferred, else prefixed sku).
     */
    public static function resolveProductId(?AmazonListingStatus $row): string
    {
        if (! $row) {
            return '';
        }
        $asin = self::resolveAsin($row);
        if ($asin !== '') {
            return $asin;
        }
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return '';
        }

        return 'AMZ:'.$sku;
    }

    public static function resolveListingState(?AmazonListingStatus $row): string
    {
        $value = self::valueArray($row);
        $state = strtolower(trim((string) (
            $value['listing_status']
            ?? $value['status']
            ?? $value['state']
            ?? ''
        )));
        if ($state === '') {
            return 'active';
        }
        if (in_array($state, ['active', 'inactive', '1', '0', 'true', 'false'], true)) {
            return in_array($state, ['inactive', '0', 'false'], true) ? 'inactive' : 'active';
        }

        return $state;
    }

    /**
     * Linked when row exists with sku and meaningful listing data.
     */
    public static function isLinked(?AmazonListingStatus $row, ?string $shopifySku = null): bool
    {
        if (! $row) {
            return false;
        }
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return false;
        }
        if ($shopifySku !== null && $shopifySku !== '') {
            $normA = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $normB = ShopifySku::normalizeSkuForShopifyLookup($shopifySku);
            if ($normA !== '' && $normB !== '' && $normA !== $normB && strcasecmp($sku, $shopifySku) !== 0) {
                return false;
            }
        }

        $asin = self::resolveAsin($row);
        if ($asin !== '' && strcasecmp($asin, $sku) !== 0) {
            return true;
        }

        $value = self::valueArray($row);

        return $value !== [];
    }

    /**
     * Product id accepted by MarketplaceLiveInventoryRules::isLinked (must differ from sku when possible).
     */
    public static function inventoryProductId(?AmazonListingStatus $row): string
    {
        $asin = self::resolveAsin($row);
        if ($asin !== '') {
            return $asin;
        }
        $sku = trim((string) ($row->sku ?? ''));

        return $sku !== '' ? 'AMZ:'.$sku : '';
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, AmazonListingStatus>
     */
    public static function mapForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === []) {
            return [];
        }

        $rows = AmazonListingStatus::query()
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus);
                foreach ($skus as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
                }
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $raw = (string) $row->sku;
            $out[$raw] = $row;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);
            if ($norm !== '') {
                $out[$norm] = $row;
            }
            $upper = strtoupper(trim($raw));
            if ($upper !== '') {
                $out[$upper] = $row;
            }
        }

        return $out;
    }
}
