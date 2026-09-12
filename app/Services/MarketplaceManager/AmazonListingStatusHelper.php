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
            return '';
        }
        $normalized = self::normalizePortalStatus($state);
        if ($normalized !== 'other') {
            return $normalized;
        }
        if (in_array(str_replace([' ', '-'], '_', $state), ['not_listed', 'missing'], true)) {
            return '';
        }

        return $state;
    }

    /**
     * Seller Central "Out of stock" / SP-API DISCOVERABLE is a live listing (qty 0),
     * not an inactive/closed offer. Map those to active so MM tabs match Amazon.
     */
    public static function normalizePortalStatus(string $raw): string
    {
        $state = strtolower(trim($raw));
        $state = str_replace([' ', '-'], '_', $state);

        if (in_array($state, [
            'active', 'buyable', 'buyable_by_quantity', 'listed', '1', 'true', 'live',
            'published', 'out_of_stock', 'oos', 'discoverable',
        ], true)) {
            return 'active';
        }
        if (in_array($state, [
            'inactive', 'incomplete', 'suppressed', 'blocked', 'disabled', '0', 'false',
            'stopped', 'ineligible', 'invalid', 'closed',
        ], true)) {
            return 'inactive';
        }

        return 'other';
    }

    /**
     * Persist amazon_datsheets.listing_status from an SP-API listings-item status.
     * OUT_OF_STOCK / DISCOVERABLE stay live (ACTIVE), not INACTIVE.
     */
    public static function mapAmazonApiStatusToSheet(string $statusValue): string
    {
        $statusValue = strtoupper(trim($statusValue));
        if (in_array($statusValue, ['INCOMPLETE', 'DRAFT', 'PENDING'], true)) {
            return 'INCOMPLETE';
        }
        $normalized = self::normalizePortalStatus($statusValue);
        if ($normalized === 'active') {
            return 'ACTIVE';
        }
        if ($normalized === 'inactive') {
            return 'INACTIVE';
        }
        if (stripos($statusValue, 'BUY') !== false || stripos($statusValue, 'ACTIVE') !== false) {
            return 'ACTIVE';
        }
        if (stripos($statusValue, 'OUT_OF_STOCK') !== false || stripos($statusValue, 'DISCOVERABLE') !== false) {
            return 'ACTIVE';
        }
        if (stripos($statusValue, 'INACTIVE') !== false
            || stripos($statusValue, 'INVALID') !== false
            || stripos($statusValue, 'STOP') !== false
            || stripos($statusValue, 'SUPPRESS') !== false) {
            return 'INACTIVE';
        }

        return $statusValue;
    }

    /**
     * Merchant listings report qty/status. Prefer raw_data (report snapshot) over
     * the quantity column — the column is often overwritten by a Shopify inventory push.
     *
     * @return array{quantity: int|null, state: string}
     */
    public static function metaFromListingsRawRow(object $row): array
    {
        $raw = $row->raw_data ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $quantity = null;
        foreach (['quantity', 'Quantity'] as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && is_numeric($raw[$key])) {
                $quantity = (int) $raw[$key];
                break;
            }
        }
        if ($quantity === null && isset($row->quantity) && $row->quantity !== null && $row->quantity !== '' && is_numeric($row->quantity)) {
            $quantity = (int) $row->quantity;
        }

        $status = '';
        foreach (['status', 'Status', 'item-status', 'listing-status', 'listing_status'] as $key) {
            $candidate = trim((string) ($raw[$key] ?? ''));
            if ($candidate !== '') {
                $status = $candidate;
                break;
            }
        }

        $fulfillment = self::fulfillmentFromRaw($raw, (string) ($row->seller_sku ?? ''));

        return [
            'quantity' => $quantity,
            'state' => $status !== '' ? self::normalizePortalStatus($status) : 'other',
            'fulfillment' => $fulfillment,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fulfillmentFromRaw(array $raw, string $sku = ''): string
    {
        foreach ($raw as $key => $value) {
            $norm = strtolower(trim((string) $key));
            $norm = str_replace([' ', '_'], '-', $norm);
            $val = strtoupper(trim((string) (is_scalar($value) ? $value : '')));
            if ($val === '') {
                continue;
            }
            $looksLikeChannel = str_contains($norm, 'fulfill') || str_contains($norm, 'fulfil') || $norm === 'channel';
            if (! $looksLikeChannel && ! in_array($val, ['AMAZON', 'AFN', 'FBA', 'DEFAULT', 'MFN', 'FBM', 'MERCHANT'], true)) {
                continue;
            }
            if (in_array($val, ['AMAZON', 'AFN', 'FBA', 'AMAZON_NA'], true) || str_contains($val, 'AFN') || $val === 'FBA') {
                return 'fba';
            }
            if (in_array($val, ['DEFAULT', 'MFN', 'FBM', 'MERCHANT'], true)) {
                return 'fbm';
            }
        }
        if (preg_match('/\bFBA\b/i', $sku)) {
            return 'fba';
        }

        return '';
    }

    public static function reportRowIsFba(object $row): bool
    {
        return self::metaFromListingsRawRow($row)['fulfillment'] === 'fba';
    }

    /**
     * FBA rows are never Inactive Listing. Closed FBA leftovers stay off this page.
     */
    public static function reportRowIsClosedFba(object $row): bool
    {
        $meta = self::metaFromListingsRawRow($row);
        if (($meta['fulfillment'] ?? '') === 'fba') {
            return true;
        }
        if (($meta['state'] ?? '') !== 'inactive') {
            return false;
        }

        return ($meta['fulfillment'] ?? '') !== 'fbm';
    }

    /**
     * Live unless this row is a closed offer with no stock.
     * Qty > 0 means Seller Central still has an available offer.
     */
    public static function reportRowIsLive(object $row): bool
    {
        if (self::reportRowIsClosedFba($row)) {
            return false;
        }
        $meta = self::metaFromListingsRawRow($row);
        if (($meta['quantity'] ?? null) !== null && (int) $meta['quantity'] > 0) {
            return true;
        }

        return ($meta['state'] ?? '') !== 'inactive';
    }

    /**
     * @param  array<string, true>  $keys
     */
    public static function rememberSkuLookupKeys(array &$keys, string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        $keys[strtoupper($sku)] = true;
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '') {
            $keys[$norm] = true;
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '') {
            $keys[strtoupper($compact)] = true;
        }
        $base = trim((string) preg_replace('/\s+(FBA|FBM)$/i', '', $sku));
        if ($base !== '' && strcasecmp($base, $sku) !== 0) {
            $keys[strtoupper($base)] = true;
            $baseNorm = ShopifySku::normalizeSkuForShopifyLookup($base);
            if ($baseNorm !== '') {
                $keys[$baseNorm] = true;
            }
        }
    }

    /**
     * One seller SKU can have an Active FBM offer and a Closed FBA offer.
     * If any report row is live, the SKU is not Inactive Listing.
     *
     * @param  list<array{sku: string, live: bool, ignore?: bool, fba?: bool}>  $rows
     * @return array{active: array<string, true>, inactive: list<string>}
     */
    public static function classifyReportSkus(array $rows): array
    {
        $liveByKey = [];
        $deadByKey = [];
        $hasFba = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $key = strtoupper($sku);
            if (! empty($row['fba'])) {
                $hasFba[$key] = true;
                continue;
            }
            if (! empty($row['ignore'])) {
                continue;
            }
            if (! empty($row['live'])) {
                $liveByKey[$key] = $sku;
            } else {
                $deadByKey[$key] = $sku;
            }
        }

        $active = [];
        foreach ($liveByKey as $sku) {
            self::rememberSkuLookupKeys($active, $sku);
        }

        $inactive = [];
        foreach ($deadByKey as $key => $sku) {
            if (isset($hasFba[$key]) || isset($liveByKey[$key]) || isset($active[$key])) {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '' && isset($active[$norm])) {
                continue;
            }
            $compact = strtoupper(ShopifySku::compactSkuForLookup($sku) ?: '');
            if ($compact !== '' && isset($active[$compact])) {
                continue;
            }
            $inactive[] = $sku;
        }

        return [
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    /**
     * SP-API listings-item qty from fulfillmentAvailability (any offer > 0 is live).
     *
     * @param  array<string, mixed>  $body
     */
    public static function quantityFromListingsItemBody(array $body): ?int
    {
        $qty = null;
        $rows = $body['fulfillmentAvailability'] ?? [];
        if (! is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['quantity']) || ! is_numeric($row['quantity'])) {
                continue;
            }
            $value = (int) $row['quantity'];
            $qty = $qty === null ? $value : max($qty, $value);
        }

        return $qty;
    }

    /**
     * Live Seller Central offer vs a closed/inactive listing.
     *
     * @param  string|list<mixed>|null  $status
     * @return 'live'|'inactive'
     */
    public static function sellerCentralStateFromApi(string|array|null $status, ?int $qty): string
    {
        if ($qty !== null && $qty > 0) {
            return 'live';
        }

        $statuses = is_array($status) ? $status : [$status];
        $sawInactive = false;
        foreach ($statuses as $one) {
            $one = trim((string) $one);
            if ($one === '') {
                continue;
            }
            $normalized = self::normalizePortalStatus($one);
            if ($normalized === 'active') {
                return 'live';
            }
            if ($normalized === 'inactive') {
                $sawInactive = true;
            }
        }

        return $sawInactive ? 'inactive' : 'live';
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return 'live'|'inactive'|'missing'|'unknown'
     */
    public static function sellerCentralListingState(?array $body, int $httpStatus): string
    {
        if ($httpStatus === 404) {
            return 'missing';
        }
        if ($httpStatus < 200 || $httpStatus >= 300 || ! is_array($body) || ! empty($body['errors'])) {
            return 'unknown';
        }

        $summary = is_array($body['summaries'][0] ?? null) ? $body['summaries'][0] : [];
        $statusList = $summary['status'] ?? [];

        return self::sellerCentralStateFromApi($statusList, self::quantityFromListingsItemBody($body));
    }

    /**
     * GET_MERCHANT_LISTINGS_ALL_DATA Inactive is only a candidate list.
     * Keep a SKU on Inactive Listing only when Seller Central still says inactive.
     *
     * @param  list<string>  $candidates
     * @param  array<string, string>  $states
     * @return list<string>
     */
    public static function keepSellerCentralInactiveSkus(array $candidates, array $states): array
    {
        $keep = [];
        foreach ($candidates as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            if (($states[$sku] ?? 'unknown') === 'inactive') {
                $keep[] = $sku;
            }
        }

        return $keep;
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
