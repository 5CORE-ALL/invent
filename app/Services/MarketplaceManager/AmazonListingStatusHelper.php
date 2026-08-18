<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared parsing for amazon_listing_statuses (sku + JSON value).
 * Also treats amazon_listings_raw (Active catalog report) as a link source.
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
     * Linked Amazon seller SKUs from listing_statuses + amazon_listings_raw (Active report).
     *
     * @return list<string>
     */
    public static function linkedSkus(): array
    {
        $byNorm = [];

        if (Schema::hasTable('amazon_listing_statuses')) {
            AmazonListingStatus::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select(['id', 'sku', 'value'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm) {
                    foreach ($rows as $row) {
                        if (! self::isLinked($row)) {
                            continue;
                        }
                        self::rememberLinkedSku($byNorm, trim((string) $row->sku));
                    }
                });
        }

        if (Schema::hasTable('amazon_listings_raw')) {
            DB::table('amazon_listings_raw')
                ->whereNotNull('seller_sku')
                ->where('seller_sku', '!=', '')
                ->whereNotNull('asin1')
                ->where('asin1', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$byNorm) {
                    foreach ($rows as $row) {
                        $sku = trim((string) ($row->seller_sku ?? ''));
                        $asin = strtoupper(trim((string) ($row->asin1 ?? '')));
                        if ($sku === '' || ! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
                            continue;
                        }
                        self::rememberLinkedSku($byNorm, $sku);
                    }
                });
        }

        return array_values($byNorm);
    }

    /**
     * Keep one seller SKU per normalized key. Prefer the exact form (ND 58)
     * over a hyphen/underscore alias (ND-58) — those can be different listings
     * with different Shopify qty.
     *
     * @param  array<string, string>  $byNorm
     */
    protected static function rememberLinkedSku(array &$byNorm, string $sku): void
    {
        $sku = trim($sku);
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($sku === '' || $norm === '') {
            return;
        }
        if (! isset($byNorm[$norm])) {
            $byNorm[$norm] = $sku;

            return;
        }

        $existing = $byNorm[$norm];
        $existingExact = strtoupper(trim($existing)) === ShopifySku::normalizeSkuForShopifyLookup($existing);
        $newExact = strtoupper($sku) === $norm;
        if ($newExact && ! $existingExact) {
            $byNorm[$norm] = $sku;
        }
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
            self::putMapEntry($out, $row);
        }

        // Fill gaps from Active catalog report so "Not on Amazon" is not wrong when statuses lag.
        $missing = [];
        foreach ($skus as $sku) {
            if (isset($out[$sku]) || isset($out[strtoupper($sku)])) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && isset($out[$norm])) {
                continue;
            }
            $missing[] = $sku;
        }
        if ($missing !== [] && Schema::hasTable('amazon_listings_raw')) {
            $rawRows = DB::table('amazon_listings_raw')
                ->where(function ($q) use ($missing) {
                    $q->whereIn('seller_sku', $missing);
                    foreach ($missing as $sku) {
                        $q->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [strtoupper($sku)]);
                    }
                })
                ->get(['seller_sku', 'asin1', 'your_price', 'quantity', 'item_name', 'thumbnail_image', 'raw_data']);

            foreach ($rawRows as $raw) {
                $sku = trim((string) ($raw->seller_sku ?? ''));
                $asin = strtoupper(trim((string) ($raw->asin1 ?? '')));
                if ($sku === '' || ! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
                    continue;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm !== '' && isset($out[$norm])) {
                    continue;
                }

                $status = new AmazonListingStatus([
                    'sku' => $sku,
                    'value' => [
                        'asin' => $asin,
                        'buyer_link' => 'https://www.amazon.com/dp/'.$asin,
                        'listed' => 'Listed',
                        'listing_status' => 'active',
                        'price' => $raw->your_price ?? null,
                        'quantity' => isset($raw->quantity) ? (int) $raw->quantity : null,
                        'title' => $raw->item_name ?? null,
                        'image' => $raw->thumbnail_image ?? null,
                    ],
                ]);
                self::putMapEntry($out, $status);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, AmazonListingStatus>  $out
     */
    protected static function putMapEntry(array &$out, AmazonListingStatus $row): void
    {
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
}
