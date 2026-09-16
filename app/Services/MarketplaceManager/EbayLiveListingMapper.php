<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;

/**
 * Map ebay_*_metrics rows to MM live listing state from seller listing_status only.
 */
final class EbayLiveListingMapper
{
    /**
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}|null
     */
    public static function mapMetricRow(object $row, bool $hasListingStatus, bool $hasInactiveReason = false): ?array
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku === '') {
            return null;
        }

        $itemId = trim((string) ($row->item_id ?? ''));
        $productId = $itemId !== '' ? $itemId : $sku;
        $inv = isset($row->ebay_stock) && $row->ebay_stock !== null ? (int) $row->ebay_stock : null;

        $raw = $hasListingStatus ? strtoupper(trim((string) ($row->listing_status ?? ''))) : '';
        if (in_array($raw, ['MISSING', 'NOT_LISTED'], true)) {
            return null;
        }

        $state = 'other';
        if ($raw === 'ACTIVE' || $raw === 'LIVE') {
            $state = 'active';
        } elseif (in_array($raw, ['INACTIVE', 'UNSOLD', 'ENDED', 'SOLD'], true)) {
            $state = 'inactive';
        } elseif ($raw !== '') {
            $state = MarketplacePortalStatusTabs::bucket($raw);
        }

        $reason = null;
        if ($state === 'inactive') {
            $stored = $hasInactiveReason ? trim((string) ($row->inactive_reason ?? '')) : '';
            $reason = $stored !== '' ? $stored : match ($raw) {
                'SOLD' => 'Sold / ended',
                'UNSOLD', 'ENDED' => 'Unsold / ended',
                default => 'Inactive on eBay',
            };
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $state,
            'inventory' => $inv,
            'title' => isset($row->ebay_title) && $row->ebay_title !== null ? (string) $row->ebay_title : null,
            'price' => isset($row->ebay_price) && $row->ebay_price !== null ? (float) $row->ebay_price : null,
            'inactive_reason' => $reason,
        ];
    }

    /**
     * Index live rows by seller SKU (always) and parent item id (first variation only).
     * Last-write-wins on product_id would show every sibling the same qty.
     *
     * @param  array<int, array{product_id?: string, sku?: string}>  $rows
     * @param  list<string>  $ids
     * @return array<string, array{product_id?: string, sku?: string}>
     */
    public static function indexDetailsForIds(array $rows, array $ids): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $wanted[$id] = true;
            $wanted[strtoupper($id)] = true;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($id);
            if ($norm !== '') {
                $wanted[$norm] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            $pid = trim((string) ($row['product_id'] ?? ''));
            $skuUpper = strtoupper($sku);
            $skuNorm = $sku !== '' ? ShopifySku::normalizeSkuForShopifyLookup($sku) : '';
            $hit = ($pid !== '' && isset($wanted[$pid]))
                || ($sku !== '' && isset($wanted[$sku]))
                || ($skuUpper !== '' && isset($wanted[$skuUpper]))
                || ($skuNorm !== '' && isset($wanted[$skuNorm]));
            if (! $hit) {
                continue;
            }
            if ($sku !== '') {
                $out[$sku] = $row;
                $out[$skuUpper] = $row;
                if ($skuNorm !== '') {
                    $out[$skuNorm] = $row;
                }
            }
            if ($pid !== '' && ! isset($out[$pid])) {
                $out[$pid] = $row;
            }
        }

        return $out;
    }

    /**
     * True when GetItem has a Variations node (multi-SKU listing).
     *
     * @param  array<string, mixed>  $item
     */
    public static function listingHasVariations(array $item): bool
    {
        $vars = $item['Variations']['Variation'] ?? null;

        return is_array($vars) && $vars !== [];
    }

    /**
     * Per-SKU qty from GetItem. Variation listings must not use parent Quantity
     * (often a sum or another sibling) for this seller SKU.
     *
     * @param  array<string, mixed>  $item
     */
    public static function quantityFromGetItem(array $item, string $sku): ?int
    {
        $vars = $item['Variations']['Variation'] ?? null;
        if (is_array($vars) && $vars !== []) {
            if (isset($vars['SKU']) || isset($vars['Quantity']) || isset($vars['QuantityAvailable'])) {
                $vars = [$vars];
            }
            foreach ($vars as $variation) {
                if (! is_array($variation)) {
                    continue;
                }
                $vSku = trim((string) ($variation['SKU'] ?? ''));
                if ($vSku === '' || ! self::skuEquals($vSku, $sku)) {
                    continue;
                }
                foreach (['Quantity', 'QuantityAvailable'] as $key) {
                    if (isset($variation[$key]) && is_numeric($variation[$key])) {
                        return (int) $variation[$key];
                    }
                }

                return null;
            }

            return null;
        }

        foreach (['Quantity', 'QuantityAvailable'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (int) $item[$key];
            }
        }

        return null;
    }

    public static function skuEquals(string $left, string $right): bool
    {
        $left = trim($left);
        $right = trim($right);
        if ($left === '' || $right === '') {
            return false;
        }
        if (strcasecmp($left, $right) === 0) {
            return true;
        }
        $ln = ShopifySku::normalizeSkuForShopifyLookup($left);
        $rn = ShopifySku::normalizeSkuForShopifyLookup($right);
        if ($ln !== '' && $ln === $rn) {
            return true;
        }
        $lc = ShopifySku::compactSkuForLookup($left);
        $rc = ShopifySku::compactSkuForLookup($right);

        return $lc !== '' && $lc === $rc;
    }

    /**
     * VariationSpecifics Name=>Value for ReviseFixedPriceItem.
     * Empty when the listing is single-SKU or the SKU is not a variation.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public static function variationSpecificsForSku(array $item, string $sku): array
    {
        $sku = trim($sku);
        $vars = $item['Variations']['Variation'] ?? null;
        if (! is_array($vars) || $vars === [] || $sku === '') {
            return [];
        }
        if (isset($vars['SKU']) || isset($vars['Quantity']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }

        foreach ($vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim((string) ($variation['SKU'] ?? ''));
            if ($vSku === '' || ! self::skuEquals($vSku, $sku)) {
                continue;
            }

            return self::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
        }

        return [];
    }

    /**
     * @param  mixed  $nameValueList
     * @return array<string, string>
     */
    public static function nameValueMap(mixed $nameValueList): array
    {
        if (! is_array($nameValueList) || $nameValueList === []) {
            return [];
        }
        $rows = isset($nameValueList['Name']) || isset($nameValueList['Value'])
            ? [$nameValueList]
            : $nameValueList;

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['Name'] ?? ''));
            $value = $row['Value'] ?? '';
            if (is_array($value)) {
                $value = (string) ($value[0] ?? '');
            }
            $value = trim((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }
            $out[$name] = $value;
        }

        return $out;
    }
}
