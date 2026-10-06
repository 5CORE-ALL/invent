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
                $replace = ! isset($out[$skuUpper]) || self::activeRowReplaces($out[$skuUpper], $row);
                if ($replace) {
                    $out[$sku] = $row;
                    $out[$skuUpper] = $row;
                    if ($skuNorm !== '') {
                        $out[$skuNorm] = $row;
                    }
                }
            }
            if ($pid !== '' && ! isset($out[$pid])) {
                $out[$pid] = $row;
            }
        }

        return $out;
    }

    /**
     * An ended row must not replace the active listing the qty columns and the push use.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     */
    public static function activeRowReplaces(array $current, array $incoming): bool
    {
        $currentBucket = MarketplacePortalStatusTabs::bucket((string) ($current['state'] ?? ''));
        $incomingBucket = MarketplacePortalStatusTabs::bucket((string) ($incoming['state'] ?? ''));

        return ! ($currentBucket === 'active' && $incomingBucket === 'inactive');
    }

    /**
     * Per-SKU listing row. A shared item id is only the first variation,
     * so siblings must not inherit that qty.
     *
     * @param  array<string, array<string, mixed>>  $indexed
     * @return array<string, mixed>|null
     */
    public static function detailForSku(array $indexed, string $sku, string $alternateSku = ''): ?array
    {
        foreach ([$sku, $alternateSku] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($candidate);
            foreach ([$candidate, strtoupper($candidate), $norm] as $key) {
                if ($key === '' || ! isset($indexed[$key]) || ! is_array($indexed[$key])) {
                    continue;
                }
                $row = $indexed[$key];
                $rowSku = trim((string) ($row['sku'] ?? ''));
                if ($rowSku !== '' && self::skuEquals($rowSku, $candidate)) {
                    return $row;
                }
            }
        }

        return null;
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

                return self::availableFromNode($variation);
            }

            return null;
        }

        return self::availableFromNode($item);
    }

    /**
     * Seller Hub "available" for one listing or variation.
     * QuantityAvailable 0 with a positive Quantity is the parent/offer echo, not the stock buyers see.
     *
     * @param  array<string, mixed>  $node
     */
    public static function availableFromNode(array $node): ?int
    {
        $available = isset($node['QuantityAvailable']) && is_numeric($node['QuantityAvailable'])
            ? (int) $node['QuantityAvailable']
            : null;
        $quantity = isset($node['Quantity']) && is_numeric($node['Quantity'])
            ? (int) $node['Quantity']
            : null;
        $sold = (int) ($node['SellingStatus']['QuantitySold'] ?? $node['QuantitySold'] ?? 0);

        if ($available !== null && $available > 0) {
            return $available;
        }
        if ($quantity !== null && $quantity > 0) {
            if ($available === null && $sold > 0 && $quantity >= $sold) {
                return $quantity - $sold;
            }

            return $quantity;
        }
        if ($available !== null) {
            return $available;
        }

        return $quantity;
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

        return $ln !== '' && $ln === $rn;
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
