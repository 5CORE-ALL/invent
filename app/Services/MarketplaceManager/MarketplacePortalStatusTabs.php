<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Inv SKU Match / Mismatch stay qty buckets.
 * Active SKU / Inactive SKU are seller-portal status (query keys mismatch_inactive / matched_inactive).
 */
final class MarketplacePortalStatusTabs
{
    /**
     * Seller-portal Active vs Inactive from listing state only (not Shopify qty).
     */
    public static function bucket(?string $state): string
    {
        $state = strtolower(trim((string) $state));
        $state = str_replace([' ', '-'], '_', $state);
        if (in_array($state, [
            'active', '1', 'true', 'live', 'onselling', 'on_selling',
            'published', 'enabled', 'buyable', 'buyable_by_quantity', 'listed',
            'activate', 'out_of_stock', 'oos', 'approved', 'available', 'visible',
            'for_sale',
        ], true)) {
            return 'active';
        }
        if (in_array($state, [
            'inactive', '0', 'false', 'offline', 'ended', 'draft', 'disabled',
            'delisted', 'deleted', 'unpublished', 'auditing', 'editingrequired',
            'editing_required', 'service_delete', 'pending', 'under_review',
            'seller_deactivated', 'platform_deactivated', 'freeze', 'failed',
            'incomplete', 'suppressed', 'blocked', 'unsold', 'archived',
            'retired', 'rejected', 'suspended', 'hidden',
        ], true)) {
            return 'inactive';
        }
        if (in_array($state, ['', 'other', 'missing', 'not_listed'], true)) {
            return 'other';
        }

        return 'inactive';
    }

    /**
     * @param  array<int, mixed>|null  $liveRows
     * @return array{active: list<string>, inactive: list<string>}
     */
    public static function skuLists(?array $liveRows): array
    {
        $active = [];
        $inactive = [];
        $seen = [];
        foreach ($liveRows ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $bucket = self::bucket((string) ($row['state'] ?? ''));
            if ($bucket === 'inactive') {
                $inactive[] = $sku;
            } elseif ($bucket === 'active') {
                $active[] = $sku;
            }
        }

        return ['active' => $active, 'inactive' => $inactive];
    }

    /**
     * @param  array<string, mixed>  $counts
     * @param  list<string>  $matchedQty
     * @param  list<string>  $mismatchQty
     * @param  list<string>  $zeroQty
     * @param  array<int, mixed>|null  $liveRows
     * @return array{
     *   counts: array<string, mixed>,
     *   matchedActive: list<string>,
     *   mismatchActive: list<string>,
     *   matchedInactive: list<string>,
     *   mismatchInactive: list<string>
     * }
     */
    public static function overlayQtyAndPortal(
        array $counts,
        array $matchedQty,
        array $mismatchQty,
        array $zeroQty,
        ?array $liveRows
    ): array {
        $portal = self::skuLists($liveRows);
        $counts['matched'] = count($matchedQty);
        $counts['mismatch'] = count($mismatchQty);
        $counts['zero'] = count($zeroQty);
        $counts['matched_inactive'] = count($portal['inactive']);
        $counts['mismatch_inactive'] = count($portal['active']);
        $counts['linked'] = $counts['matched'] + $counts['mismatch'] + $counts['zero'];
        $counts['linked_with_inv'] = $counts['matched'];
        $counts['linked_zero_inv'] = $counts['zero'];

        return [
            'counts' => $counts,
            'matchedActive' => $matchedQty,
            'mismatchActive' => $mismatchQty,
            'matchedInactive' => $portal['inactive'],
            'mismatchInactive' => $portal['active'],
        ];
    }

    /**
     * @param  array<int, mixed>|null  $liveRows
     */
    public static function paginate(
        string $status,
        array $skus,
        ?array $liveRows,
        string $searchSku,
        string $searchName,
        int $page,
        int $perPage,
        string $stateProperty,
        string $titleProperty
    ): LengthAwarePaginator {
        $searchSkuU = strtoupper($searchSku);
        $searchNameU = strtoupper($searchName);
        $bySku = [];
        foreach ($liveRows ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $bySku[strtoupper($sku)] = $row;
            $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku);
            if ($norm !== '') {
                $bySku[$norm] = $row;
            }
        }

        $filtered = [];
        foreach ($skus as $sku) {
            $sku = (string) $sku;
            $row = $bySku[strtoupper($sku)]
                ?? $bySku[strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku)]
                ?? [];
            $title = (string) ($row['title'] ?? $sku);
            if ($searchSkuU !== '' && ! str_contains(strtoupper($sku), $searchSkuU)) {
                continue;
            }
            if ($searchNameU !== '' && ! str_contains(strtoupper($title.' '.$sku), $searchNameU)) {
                continue;
            }
            $filtered[] = $sku;
        }

        $total = count($filtered);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        $shopifyRows = [];
        if ($slice !== []) {
            foreach (ShopifySku::query()->whereIn('sku', $slice)->get() as $row) {
                $key = strtoupper(trim((string) $row->sku));
                $shopifyRows[$key] = $row;
                $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku) ?: (string) $row->sku);
                if ($norm !== '' && ! isset($shopifyRows[$norm])) {
                    $shopifyRows[$norm] = $row;
                }
            }
        }
        $liveShopifyQty = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($slice);

        $items = [];
        foreach ($slice as $sku) {
            $cachedRow = $bySku[strtoupper($sku)]
                ?? $bySku[strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku)]
                ?? [];
            $shopify = $shopifyRows[strtoupper($sku)]
                ?? $shopifyRows[strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku)]
                ?? null;
            $shopifyQty = $shopify
                ? MarketplaceListingStockResolver::shopifyQtyFromLiveMapOrRow($liveShopifyQty, $shopify, $sku)
                : null;
            $aeQty = array_key_exists('inventory', $cachedRow) && $cachedRow['inventory'] !== null
                ? (int) $cachedRow['inventory']
                : null;
            $state = (string) ($cachedRow['state'] ?? $status);
            $shopifyTitle = $shopify
                ? (trim(($shopify->goods_summary ?? $shopify->product_title ?? '').($shopify->variant_title ? ' — '.$shopify->variant_title : '')) ?: $sku)
                : ($cachedRow['title'] ?? $sku);
            $item = [
                'shopify_sku_id' => $shopify->id ?? null,
                'product_id' => $cachedRow['product_id'] ?? null,
                'sku_id' => $cachedRow['sku_id'] ?? null,
                'sku' => $sku,
                'title' => $shopifyTitle,
                $titleProperty => $cachedRow['title'] ?? null,
                'image_src' => $shopify->image_src ?? null,
                'price' => $cachedRow['price'] ?? null,
                'shopify_price' => $shopify->b2c_price ?? $shopify->price ?? null,
                'quantity' => $aeQty,
                'ae_quantity' => $aeQty,
                'shopify_quantity' => $shopifyQty,
                'linked' => $shopify !== null,
                'listing_status' => $shopify ? 'linked' : 'not_in_shopify',
                $stateProperty => $state !== '' ? $state : $status,
                'mp_state' => $state !== '' ? $state : $status,
                'inactive_reason' => $status === 'inactive'
                    ? self::inactiveReason($cachedRow, $aeQty)
                    : null,
            ];
            $items[] = (object) $item;
        }

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function inactiveReason(array $row, mixed $inventory): ?string
    {
        $named = trim((string) ($row['inactive_reason'] ?? $row['status_reason'] ?? ''));
        if ($named !== '') {
            return $named;
        }
        $state = strtolower(trim((string) ($row['state'] ?? '')));
        if ($state !== '' && ! in_array($state, ['active', 'inactive'], true)) {
            return str_replace('_', ' ', $state);
        }
        if ($inventory !== null && (int) $inventory <= 0) {
            return 'Out of stock';
        }

        return null;
    }
}
