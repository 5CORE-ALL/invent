<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bProduct;
use App\Services\Business5CoreB2bApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bLiveListingsService
{
    public const CACHE_KEY = 'mm.b5cb2b.live_listings.v1';

    public const CACHE_GEN_KEY = 'mm.b5cb2b.live_listings.gen';

    public function __construct(protected Business5CoreB2bApiService $api)
    {
    }

    public function clearCache(): void
    {
        Cache::increment(self::CACHE_GEN_KEY);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{success: bool, message: string, stored: int}
     */
    public function refresh(): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.', 'stored' => 0];
        }
        if (! Schema::hasTable('b5c_b2b_products')) {
            return ['success' => false, 'message' => 'Run migrations for b5c_b2b_products.', 'stored' => 0];
        }

        try {
            $listings = $this->api->fetchAllListings();
            $inventory = $this->api->fetchAllInventoryRows();
        } catch (\Throwable $e) {
            Log::warning('B5C B2B listings refresh failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'stored' => 0];
        }

        $invBySku = [];
        foreach ($inventory as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $invBySku[strtoupper($sku)] = $row;
            }
            foreach (is_array($row['variants'] ?? null) ? $row['variants'] : [] as $variant) {
                $vSku = trim((string) ($variant['sku'] ?? ''));
                if ($vSku !== '') {
                    $invBySku[strtoupper($vSku)] = array_merge($row, $variant);
                }
            }
        }

        $stored = 0;
        foreach ($listings as $listing) {
            $sku = trim((string) ($listing['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $inv = $invBySku[strtoupper($sku)] ?? [];
            $this->upsertLocalRow($listing, $inv);
            $stored++;
        }

        $this->clearCache();
        $this->all(false);

        return [
            'success' => true,
            'message' => 'Stored '.$stored.' Business 5 Core B2B listing(s).',
            'stored' => $stored,
        ];
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->refreshFromApiIntoLocal();
        } else {
            $cached = $this->peekCached();
            if ($cached !== null) {
                return $cached;
            }
        }

        $gen = (int) Cache::get(self::CACHE_GEN_KEY, 0);
        $rows = $this->fetchFromLocal();
        if ((int) Cache::get(self::CACHE_GEN_KEY, 0) === $gen) {
            Cache::put(self::CACHE_KEY, $rows, now()->addHours(6));
        }

        return MarketplacePortalInactiveCount::applyToLiveRows('b5cb2b', $rows);
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>|null
     */
    public function peekCached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? MarketplacePortalInactiveCount::applyToLiveRows('b5cb2b', $cached) : null;
    }

    /**
     * @param  array<int, string>  $productIds
     * @return array<string, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>
     */
    public function liveDetailsByProductIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $productIds
        ), static fn ($id) => $id !== '')));

        if ($ids === []) {
            return [];
        }

        $source = $this->peekCached();
        if ($source === null) {
            $source = $this->all(false);
        }

        $out = [];
        foreach ($source as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pid = trim((string) ($row['product_id'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $skuId = trim((string) ($row['sku_id'] ?? ''));
            $hit = in_array($pid, $ids, true)
                || in_array($sku, $ids, true)
                || in_array(strtoupper($sku), $ids, true)
                || ($skuId !== '' && in_array($skuId, $ids, true));
            if (! $hit) {
                continue;
            }
            if ($pid !== '') {
                $out[$pid] = $row;
            }
            if ($sku !== '') {
                $out[$sku] = $row;
                $out[strtoupper($sku)] = $row;
            }
            if ($skuId !== '') {
                $out[$skuId] = $row;
            }
        }

        return $out;
    }

    /**
     * Pull one SKU from the Laravel store API into b5c_b2b_products.
     *
     * @return array{success: bool, message: string}
     */
    public function refreshSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is empty.'];
        }
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.'];
        }

        try {
            $listings = $this->api->fetchAllListings(['sku' => $sku]);
            $inventory = $this->api->fetchAllInventoryRows(['sku' => $sku]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $want = strtoupper($sku);
        $listing = null;
        foreach ($listings as $row) {
            if (strtoupper(trim((string) ($row['sku'] ?? ''))) === $want) {
                $listing = $row;
                break;
            }
        }
        $inv = [];
        foreach ($inventory as $row) {
            if (strtoupper(trim((string) ($row['sku'] ?? ''))) === $want) {
                $inv = $row;
                break;
            }
        }
        if ($listing === null && $inv === []) {
            $local = B5cB2bProduct::query()->where('sku', $sku)->first();
            if ($local) {
                return ['success' => false, 'message' => 'SKU was not returned by the B2B listings API.'];
            }

            return ['success' => false, 'message' => 'SKU is not on Business 5 Core B2B.'];
        }

        $this->upsertLocalRow($listing ?? ['sku' => $sku], $inv);
        $this->clearCache();

        return ['success' => true, 'message' => 'Pulled '.$sku.' from Business 5 Core B2B.'];
    }

    protected function refreshFromApiIntoLocal(): void
    {
        $result = $this->refresh();
        if (! ($result['success'] ?? false)) {
            Log::warning('B5cB2bLiveListingsService: force refresh failed', [
                'message' => $result['message'] ?? '',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $listing
     * @param  array<string, mixed>  $inv
     */
    protected function upsertLocalRow(array $listing, array $inv): void
    {
        $sku = trim((string) ($listing['sku'] ?? $inv['sku'] ?? ''));
        if ($sku === '') {
            return;
        }

        B5cB2bProduct::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'listing_id' => $listing['id'] ?? $inv['id'] ?? null,
                'slug' => $listing['slug'] ?? $inv['slug'] ?? null,
                'title' => $listing['name'] ?? $listing['title'] ?? $inv['name'] ?? $inv['title'] ?? $sku,
                'qty' => $inv['qty'] ?? $listing['qty'] ?? null,
                'price' => $this->scalarPrice($listing['price'] ?? $listing['regular_price'] ?? $inv['price'] ?? $inv['regular_price'] ?? null),
                'special_price' => $this->scalarPrice($listing['special_price'] ?? $listing['sale_price'] ?? $inv['special_price'] ?? null),
                'in_stock' => $inv['in_stock'] ?? $listing['in_stock'] ?? null,
                'is_active' => $inv['is_active'] ?? $listing['is_active'] ?? true,
                'payload' => $listing !== [] ? $listing : $inv,
            ]
        );
    }

    /**
     * @return array<int, array{product_id: string, sku: string, sku_id: ?string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason: ?string}>
     */
    protected function fetchFromLocal(): array
    {
        if (! Schema::hasTable('b5c_b2b_products')) {
            return [];
        }

        $rows = [];
        B5cB2bProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->each(function (B5cB2bProduct $row) use (&$rows) {
                $sku = trim((string) $row->sku);
                if ($sku === '') {
                    return;
                }
                $active = $row->is_active !== false;
                $payload = is_array($row->payload) ? $row->payload : [];
                $reason = $active ? null : trim((string) ($payload['inactive_reason'] ?? $payload['status'] ?? 'Inactive listing'));
                $rows[] = [
                    'product_id' => $row->listing_id !== null ? (string) $row->listing_id : '',
                    'sku' => $sku,
                    'sku_id' => $sku,
                    'state' => $active ? 'active' : 'inactive',
                    'inventory' => $row->qty !== null ? (int) $row->qty : null,
                    'title' => $row->title,
                    'price' => $row->price !== null ? (float) $row->price : $this->scalarPrice($payload['price'] ?? null),
                    'inactive_reason' => $reason !== '' ? $reason : null,
                ];
            });

        return $rows;
    }

    protected function scalarPrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['value'] ?? $value['price'] ?? null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
