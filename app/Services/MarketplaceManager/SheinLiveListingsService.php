<?php

namespace App\Services\MarketplaceManager;

use App\Models\SheinListingStatus;
use App\Models\SheinMetric;
use App\Models\SheinMmMetric;
use App\Models\SheinPricingPrice;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for Shein listings UI.
 * Stock from shein_pricing_prices.shein_stock; product_id from shein_metric / shein_metrics.shein_sku_code.
 */
final class SheinLiveListingsService
{
    public const CACHE_KEY = 'mm.shein.live_listings.v1';

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
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
                return $this->fetchFromLocal();
            });

            return $this->withListedNullInventoryRows(is_array($rows) ? $rows : []);
        } catch (\Throwable $e) {
            Log::warning('SheinLiveListingsService: cache unavailable', ['error' => $e->getMessage()]);

            return $this->withListedNullInventoryRows($this->fetchFromLocal());
        }
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>|null
     */
    public function peekCached(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            return is_array($cached) ? $this->withListedNullInventoryRows($cached) : null;
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
     * @param  array<int, string>  $productIds  Shein skuCodes and/or seller SKUs
     * @return array<string, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
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

        $all = $this->all();
        $out = [];
        foreach ($all as $row) {
            if (in_array($row['product_id'], $ids, true) || in_array($row['sku'], $ids, true)) {
                $out[$row['product_id']] = $row;
                $out[$row['sku']] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromLocal(): array
    {
        if (! Schema::hasTable('shein_pricing_prices')) {
            return $this->withListedNullInventoryRows([]);
        }

        $mmBySku = [];
        if (Schema::hasTable('shein_metric')) {
            SheinMmMetric::query()
                ->whereNotNull('sku')
                ->get(['sku', 'product_id', 'product_name'])
                ->each(function (SheinMmMetric $m) use (&$mmBySku) {
                    $mmBySku[trim((string) $m->sku)] = $m;
                });
        }

        $cacheBySku = [];
        if (Schema::hasTable('shein_metrics')) {
            SheinMetric::query()
                ->whereNotNull('sku')
                ->get(['sku', 'shein_sku_code', 'product_name', 'status', 'inventory'])
                ->each(function (SheinMetric $m) use (&$cacheBySku) {
                    $cacheBySku[trim((string) $m->sku)] = $m;
                });
        }

        $out = [];
        SheinPricingPrice::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use (&$out, $mmBySku, $cacheBySku) {
                foreach ($chunk as $row) {
                    $parsed = $this->mapPricingRow($row, $mmBySku[$row->sku] ?? null, $cacheBySku[$row->sku] ?? null);
                    if ($parsed !== null) {
                        $out[] = $parsed;
                    }
                }
            });

        return $this->withListedNullInventoryRows($out);
    }

    /**
     * Pending Hub listings often have `--` qty and no shein_pricing_prices row.
     * Keep them in the live map as active + null inventory so they classify as mismatch.
     *
     * @param  array<int, array{product_id?: string, sku?: string, state?: string, inventory?: int|null, title?: ?string, price?: ?float}>  $rows
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function withListedNullInventoryRows(array $rows): array
    {
        $have = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $have[strtoupper($sku)] = true;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm !== '') {
                $have[$norm] = true;
            }
        }

        foreach (SheinListingStatus::listedSellerSkus() as $sku) {
            $upper = strtoupper($sku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if (isset($have[$upper]) || ($norm !== '' && isset($have[$norm]))) {
                continue;
            }
            $have[$upper] = true;
            if ($norm !== '') {
                $have[$norm] = true;
            }
            $rows[] = [
                'product_id' => $sku,
                'sku' => $sku,
                'state' => 'active',
                'inventory' => null,
                'title' => null,
                'price' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function mapPricingRow(SheinPricingPrice $row, ?SheinMmMetric $mm, ?SheinMetric $cache): ?array
    {
        $sku = trim((string) $row->sku);
        if ($sku === '') {
            return null;
        }

        $sheinCode = trim((string) ($mm?->product_id ?? $cache?->shein_sku_code ?? ''));
        if ($sheinCode === '' || $sheinCode === $sku) {
            $sheinCode = trim((string) ($cache?->shein_sku_code ?? ''));
        }
        $productId = $sheinCode !== '' ? $sheinCode : $sku;

        $status = strtolower(trim((string) ($cache?->status ?? '')));
        $isActive = ! in_array($status, ['inactive', 'disabled', 'delisted', 'out_of_stock', 'deleted'], true);
        if ($status === '') {
            $isActive = ($row->shein_stock ?? 0) > 0 || $productId !== $sku;
        }

        $inv = $row->shein_stock !== null
            ? (int) $row->shein_stock
            : ($cache?->inventory !== null ? (int) $cache->inventory : null);

        $price = $row->special_offer_price ?? $row->price ?? null;
        $title = $mm?->product_name ?? $cache?->product_name ?? null;

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $isActive ? 'active' : 'inactive',
            'inventory' => $inv,
            'title' => $title,
            'price' => $price !== null ? (float) $price : null,
        ];
    }
}
