<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ProductStockMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Amazon listings UI (Reverb/Temu parity).
 * Active / Inactive come from Amazon seller status (amazon_datsheets + listing JSON), not qty.
 */
class AmazonLiveListingsService
{
    private const CACHE_KEY = 'mm.amazon.live_listings.v2';

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('mm.amazon.live_listings.v1');
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cached = $this->peekCached();
            if ($cached !== null) {
                return $cached;
            }
        }

        $rows = $this->fetchFromLocal();
        if ($this->rowsHavePortalStatus($rows)) {
            Cache::put(self::CACHE_KEY, $rows, now()->addHours(6));
        } else {
            Cache::forget(self::CACHE_KEY);
        }

        return $rows;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (! is_array($cached) || $cached === []) {
            return null;
        }
        if (! $this->rowsHavePortalStatus($cached)) {
            Cache::forget(self::CACHE_KEY);

            return null;
        }

        return $cached;
    }

    /**
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function indexedBySku(bool $forceRefresh = false): array
    {
        $out = [];
        foreach ($this->all($forceRefresh) as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $out[$sku] = $row;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function liveDetailsByProductIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $productIds
        ), static fn ($id) => $id !== '')));

        if ($ids === [] || ! Schema::hasTable('amazon_listing_statuses')) {
            return [];
        }

        $datasheet = $this->datasheetStatusBySku();
        $rows = AmazonListingStatus::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('sku', $ids);
                foreach ($ids as $id) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($id)]);
                }
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->mapProduct($row, [], $datasheet);
            if ($parsed === null) {
                continue;
            }
            $out[$parsed['product_id']] = $parsed;
            $out[$parsed['sku']] = $parsed;
            $out[strtoupper($parsed['sku'])] = $parsed;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function rowsHavePortalStatus(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (in_array(strtolower((string) ($row['state'] ?? '')), ['active', 'inactive'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}>
     */
    protected function fetchFromLocal(): array
    {
        $inventoryMap = $this->amazonInventoryBySku();
        $datasheet = $this->datasheetStatusBySku();
        $seen = [];
        $out = [];

        if (Schema::hasTable('amazon_listing_statuses')) {
            AmazonListingStatus::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$out, &$seen, $inventoryMap, $datasheet) {
                    foreach ($rows as $row) {
                        $mapped = $this->mapProduct($row, $inventoryMap, $datasheet);
                        if ($mapped === null) {
                            continue;
                        }
                        $key = strtoupper($mapped['sku']);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $out[] = $mapped;
                    }
                });
        }

        if (Schema::hasTable('amazon_listings_raw')) {
            $rawCols = ['id', 'seller_sku', 'asin1'];
            foreach (['your_price', 'quantity', 'item_name'] as $col) {
                if (Schema::hasColumn('amazon_listings_raw', $col)) {
                    $rawCols[] = $col;
                }
            }
            DB::table('amazon_listings_raw')
                ->whereNotNull('seller_sku')
                ->where('seller_sku', '!=', '')
                ->select($rawCols)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$out, &$seen, $inventoryMap, $datasheet) {
                    foreach ($rows as $row) {
                        $sku = trim((string) ($row->seller_sku ?? ''));
                        if ($sku === '') {
                            continue;
                        }
                        $key = strtoupper($sku);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $asin = strtoupper(trim((string) ($row->asin1 ?? '')));
                        $ds = $datasheet[$key] ?? null;
                        $state = $this->normalizeAmazonPortalStatus((string) ($ds['status'] ?? ''));
                        if ($state !== 'active' && $state !== 'inactive') {
                            continue;
                        }
                        $seen[$key] = true;
                        $out[] = [
                            'product_id' => preg_match('/^[A-Z0-9]{10}$/', $asin) ? $asin : 'AMZ:'.$sku,
                            'sku' => $sku,
                            'state' => $state,
                            'inventory' => $inventoryMap[$key] ?? (isset($row->quantity) ? (int) $row->quantity : null),
                            'title' => $ds['title'] ?? ($row->item_name !== null ? (string) $row->item_name : null),
                            'price' => isset($ds['price']) && is_numeric($ds['price'])
                                ? (float) $ds['price']
                                : (isset($row->your_price) && is_numeric($row->your_price) ? (float) $row->your_price : null),
                            'inactive_reason' => $state === 'inactive'
                                ? ($ds['status'] !== '' ? (string) $ds['status'] : 'Inactive on Amazon')
                                : null,
                        ];
                    }
                });
        }

        foreach ($datasheet as $key => $ds) {
            if (isset($seen[$key])) {
                continue;
            }
            $state = $this->normalizeAmazonPortalStatus((string) ($ds['status'] ?? ''));
            if ($state !== 'inactive' && $state !== 'active') {
                continue;
            }
            $sku = (string) ($ds['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $asin = strtoupper(trim((string) ($ds['asin'] ?? '')));
            $seen[$key] = true;
            $out[] = [
                'product_id' => preg_match('/^[A-Z0-9]{10}$/', $asin) ? $asin : 'AMZ:'.$sku,
                'sku' => $sku,
                'state' => $state,
                'inventory' => $inventoryMap[$key] ?? null,
                'title' => $ds['title'] !== '' ? $ds['title'] : null,
                'price' => isset($ds['price']) && is_numeric($ds['price']) ? (float) $ds['price'] : null,
                'inactive_reason' => $state === 'inactive'
                    ? ((string) ($ds['status'] ?? '') ?: 'Inactive on Amazon')
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $inventoryMap
     * @param  array<string, array{sku: string, status: string, title: ?string, price: mixed, asin: ?string}>  $datasheet
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}|null
     */
    protected function mapProduct(AmazonListingStatus $row, array $inventoryMap = [], array $datasheet = []): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '' || ! AmazonListingStatusHelper::isLinked($row)) {
            return null;
        }

        $productId = AmazonListingStatusHelper::resolveProductId($row);
        $value = AmazonListingStatusHelper::valueArray($row);
        $upper = strtoupper($sku);
        $inventory = $inventoryMap[$upper] ?? null;
        if ($inventory === null && isset($value['quantity']) && is_numeric($value['quantity'])) {
            $inventory = (int) $value['quantity'];
        }

        $jsonState = $this->normalizeAmazonPortalStatus(AmazonListingStatusHelper::resolveListingState($row));
        $ds = $datasheet[$upper] ?? null;
        $dsState = $this->normalizeAmazonPortalStatus((string) ($ds['status'] ?? ''));
        $state = $dsState !== 'other' ? $dsState : $jsonState;
        if ($state === '') {
            $state = 'other';
        }

        $title = $ds['title'] ?? (isset($value['title']) ? (string) $value['title'] : null);
        $price = isset($ds['price']) && is_numeric($ds['price'])
            ? (float) $ds['price']
            : (isset($value['price']) && is_numeric($value['price']) ? (float) $value['price'] : null);

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $state,
            'inventory' => $inventory,
            'title' => $title !== '' ? $title : null,
            'price' => $price,
            'inactive_reason' => $state === 'inactive'
                ? ((string) ($ds['status'] ?? '') ?: 'Inactive on Amazon')
                : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function amazonInventoryBySku(): array
    {
        $inventoryMap = [];
        if (! Schema::hasTable('product_stock_mappings') || ! Schema::hasColumn('product_stock_mappings', 'inventory_amazon')) {
            return $inventoryMap;
        }

        ProductStockMapping::query()
            ->whereNotNull('inventory_amazon')
            ->select(['id', 'sku', 'inventory_amazon'])
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$inventoryMap) {
                foreach ($chunk as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku !== '') {
                        $inventoryMap[strtoupper($sku)] = (int) $row->inventory_amazon;
                    }
                }
            });

        return $inventoryMap;
    }

    /**
     * @return array<string, array{sku: string, status: string, title: ?string, price: mixed, asin: ?string}>
     */
    protected function datasheetStatusBySku(): array
    {
        $map = [];
        if (! Schema::hasTable('amazon_datsheets') || ! Schema::hasColumn('amazon_datsheets', 'listing_status')) {
            return $map;
        }

        $cols = ['id', 'sku', 'listing_status'];
        foreach (['amazon_title', 'price', 'asin'] as $col) {
            if (Schema::hasColumn('amazon_datsheets', $col)) {
                $cols[] = $col;
            }
        }

        DB::table('amazon_datsheets')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select($cols)
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$map) {
                foreach ($chunk as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '') {
                        continue;
                    }
                    $map[strtoupper($sku)] = [
                        'sku' => $sku,
                        'status' => strtoupper(trim((string) ($row->listing_status ?? ''))),
                        'title' => isset($row->amazon_title) ? trim((string) $row->amazon_title) : null,
                        'price' => $row->price ?? null,
                        'asin' => isset($row->asin) ? (string) $row->asin : null,
                    ];
                }
            });

        return $map;
    }

    protected function normalizeAmazonPortalStatus(string $raw): string
    {
        $state = strtolower(trim($raw));
        $state = str_replace([' ', '-'], '_', $state);

        if (in_array($state, ['active', 'buyable', 'buyable_by_quantity', 'listed', '1', 'true', 'live'], true)) {
            return 'active';
        }
        if (in_array($state, ['inactive', 'incomplete', 'suppressed', 'blocked', 'disabled', '0', 'false'], true)) {
            return 'inactive';
        }

        return 'other';
    }
}
