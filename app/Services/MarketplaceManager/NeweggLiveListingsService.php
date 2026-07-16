<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Newegg listings UI (AliExpress/Reverb parity).
 * Catalog comes from newegg_pricing (active / inactive).
 */
final class NeweggLiveListingsService
{
    public const CACHE_KEY = 'mm.newegg.live_listings.v1';

    public const CACHE_TTL_SECONDS = 7200;

    /** UI state buckets. */
    public const STATUS_TYPES = [
        'active',
        'inactive',
    ];

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function all(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            try {
                Cache::forget(self::CACHE_KEY);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return $this->fetchFromPricing();
            });
        } catch (\Throwable $e) {
            Log::warning('NeweggLiveListingsService: cache unavailable', ['error' => $e->getMessage()]);

            return $this->fetchFromPricing();
        }
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? $cached : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param  array<int, string>  $productIds  Newegg Item #s and/or Seller Part #s
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    public function liveDetailsByProductIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $productIds
        ), static fn ($id) => $id !== '')));

        if ($ids === [] || ! Schema::hasTable('newegg_pricing')) {
            return [];
        }

        $rows = NeweggPricing::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('newegg_item_number', $ids)->orWhereIn('seller_part_number', $ids);
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $parsed = $this->mapPricingRow($row);
            if ($parsed === null) {
                continue;
            }
            $out[$parsed['product_id']] = $parsed;
            $out[$parsed['sku']] = $parsed;
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromPricing(): array
    {
        if (! Schema::hasTable('newegg_pricing')) {
            return [];
        }

        $out = [];
        NeweggPricing::query()
            ->whereNotNull('seller_part_number')
            ->where('seller_part_number', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$out) {
                foreach ($chunk as $row) {
                    $parsed = $this->mapPricingRow($row);
                    if ($parsed !== null) {
                        $out[] = $parsed;
                    }
                }
            });

        return $out;
    }

    /**
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapPricingRow(NeweggPricing $row): ?array
    {
        $sku = trim((string) $row->seller_part_number);
        if ($sku === '') {
            return null;
        }

        $itemNumber = trim((string) ($row->newegg_item_number ?? ''));
        $productId = $itemNumber !== '' ? $itemNumber : $sku;
        $active = $row->active;
        if ($active === null && $row->inventory_active !== null) {
            $active = $row->inventory_active;
        }
        $isActive = $active === true || $active === 1 || $active === '1';

        $price = $row->selling_price !== null ? (float) $row->selling_price : null;
        $inv = $row->available_quantity !== null ? (int) $row->available_quantity : null;

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $isActive ? 'active' : 'inactive',
            'inventory' => $inv,
            'title' => null,
            'price' => $price,
        ];
    }
}
