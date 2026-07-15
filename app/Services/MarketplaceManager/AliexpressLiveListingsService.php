<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Services\AliExpressApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live listing helpers for AliExpress listings UI (parity with ReverbLiveListingsService).
 *
 * Full catalog warm pulls each product_status_type into cache — page render only peeks cache
 * and optionally hydrates the current page via getProductInfo.
 */
final class AliexpressLiveListingsService
{
    public const CACHE_KEY = 'mm.aliexpress.live_listings.v1';

    public const CACHE_TTL_SECONDS = 7200;

    /** AE product_status_type values used for catalog warm + filter buckets. */
    public const STATUS_TYPES = [
        'onSelling',
        'auditing',
        'offline',
        'editingRequired',
        'service_delete',
    ];

    /**
     * Full catalog (expensive). Background / Refresh live only — not page renders.
     *
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
                return $this->fetchFromApi();
            });
        } catch (\Throwable $e) {
            Log::warning('AliexpressLiveListingsService: cache unavailable, fetching uncached', [
                'error' => $e->getMessage(),
            ]);

            return $this->fetchFromApi();
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

    /**
     * Live AE details for a page of product IDs (getProductInfo).
     *
     * @param  array<int, string>  $productIds
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

        $api = app(AliExpressApiService::class);
        $out = [];
        foreach ($ids as $id) {
            try {
                $info = $api->getProductInfo($id);
                if (empty($info['success']) || ! is_array($info['data'] ?? null)) {
                    continue;
                }
                $parsed = $this->parseProductInfo($id, $info['data']);
                if ($parsed !== null) {
                    $out[$parsed['product_id']] = $parsed;
                }
                usleep(60000);
            } catch (\Throwable $e) {
                // continue
            }
        }

        return $out;
    }

    /**
     * AE list APIs often omit SKU codes. Resolve SKUs via aliexpress_metric by product_id.
     *
     * @return array<int, array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}>
     */
    protected function fetchFromApi(): array
    {
        $api = app(AliExpressApiService::class);
        $out = [];
        $seenSku = [];
        $metricByProduct = $this->metricSkuMapByProductId();

        foreach (self::STATUS_TYPES as $statusType) {
            $page = 1;
            $guard = 0;
            $statusOk = 0;
            while ($guard < 400) {
                $guard++;
                $result = $api->getInventory($page, 50, ['product_status_type' => $statusType]);
                if (empty($result['success'])) {
                    Log::warning('AliexpressLiveListingsService: list failed', [
                        'status' => $statusType,
                        'page' => $page,
                        'message' => $result['message'] ?? null,
                    ]);
                    break;
                }

                $products = $result['data']['products'] ?? [];
                if (! is_array($products) || $products === []) {
                    break;
                }

                foreach ($products as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $productId = trim((string) ($item['product_id'] ?? $item['id'] ?? ''));
                    if ($productId === '') {
                        $rows = $api->extractSkuRowsFromListItem($item, false);
                        $productId = trim((string) (($rows[0]['product_id'] ?? '') ?: ''));
                    }
                    if ($productId === '') {
                        continue;
                    }

                    $title = $this->extractSubject($item);
                    $price = $this->extractListPrice($item);
                    $metricRows = $metricByProduct[$productId] ?? [];

                    if ($metricRows === []) {
                        $rows = $api->extractSkuRowsFromListItem($item, false);
                        foreach ($rows as $row) {
                            $sku = trim((string) ($row['sku'] ?? ''));
                            if ($sku === '' || $sku === $productId) {
                                continue;
                            }
                            $metricRows[] = [
                                'sku' => $sku,
                                'product_name' => $row['product_name'] ?? $title,
                                'price' => $row['price'] ?? $price,
                                'stock' => $row['stock'] ?? null,
                            ];
                        }
                    }

                    foreach ($metricRows as $row) {
                        $sku = trim((string) ($row['sku'] ?? ''));
                        if ($sku === '' || $sku === $productId) {
                            continue;
                        }
                        $norm = strtoupper($sku);
                        if (isset($seenSku[$norm])) {
                            continue;
                        }
                        $seenSku[$norm] = true;
                        $statusOk++;
                        $out[] = [
                            'product_id' => $productId,
                            'sku' => $sku,
                            'state' => strtolower($statusType),
                            'inventory' => isset($row['stock']) && $row['stock'] !== null ? (int) $row['stock'] : null,
                            'title' => isset($row['product_name']) ? (string) $row['product_name'] : $title,
                            'price' => isset($row['price']) && $row['price'] !== null ? (float) $row['price'] : $price,
                        ];
                    }
                }

                $totalPage = isset($result['data']['total_page']) ? (int) $result['data']['total_page'] : null;
                $itemCount = count($products);
                if ($itemCount < 50) {
                    break;
                }
                if ($totalPage !== null && $totalPage > 0 && $page >= $totalPage) {
                    break;
                }
                $page++;
                usleep(120000);
            }

            Log::info('AliexpressLiveListingsService: status scanned', [
                'status' => $statusType,
                'sku_rows' => $statusOk,
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, array<int, array{sku: string, product_name: ?string, price: mixed, stock: null}>>
     */
    protected function metricSkuMapByProductId(): array
    {
        $map = [];
        if (! Schema::hasTable('aliexpress_metric')) {
            return $map;
        }

        AliexpressMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->select(['sku', 'product_id', 'product_name', 'price'])
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$map) {
                foreach ($rows as $row) {
                    $pid = trim((string) $row->product_id);
                    $sku = trim((string) $row->sku);
                    if ($pid === '' || $sku === '') {
                        continue;
                    }
                    $map[$pid][] = [
                        'sku' => $sku,
                        'product_name' => $row->product_name,
                        'price' => $row->price,
                        'stock' => null,
                    ];
                }
            });

        return $map;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function extractSubject(array $item): ?string
    {
        foreach (['subject', 'product_title', 'title', 'product_name'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return trim($item[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function extractListPrice(array $item): ?float
    {
        foreach (['product_min_price', 'product_max_price', 'price'] as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (float) $item[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float}|null
     */
    protected function parseProductInfo(string $productId, array $data): ?array
    {
        $api = app(AliExpressApiService::class);
        $rows = $api->extractSkuRowsFromProductInfo($data, $productId);
        $first = $rows[0] ?? null;
        $sku = $first ? trim((string) ($first['sku'] ?? '')) : '';
        if ($sku === '') {
            $metric = Schema::hasTable('aliexpress_metric')
                ? AliexpressMetric::query()->where('product_id', $productId)->whereColumn('sku', '!=', 'product_id')->value('sku')
                : null;
            $sku = $metric ? trim((string) $metric) : '';
        }
        $stateRaw = $data['product_status_type']
            ?? $data['product_status']
            ?? $data['status']
            ?? $data['productStatusType']
            ?? '';
        $state = strtolower(trim((string) $stateRaw));

        $inv = null;
        if ($first && isset($first['stock']) && $first['stock'] !== null) {
            $inv = (int) $first['stock'];
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $state,
            'inventory' => $inv,
            'title' => $first['product_name'] ?? null,
            'price' => isset($first['price']) ? (float) $first['price'] : null,
        ];
    }
}
