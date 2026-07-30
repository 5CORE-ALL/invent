<?php

namespace App\Services\MarketplaceManager;

use App\Models\SheinMetric;
use App\Models\SheinMmMetric;
use App\Models\SheinPricingPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Build / refresh shein_metric (MM link map) from shein_pricing_prices + shein_metrics (product cache).
 * product_id = shein_sku_code when known; otherwise sku (unlinked placeholder).
 */
class SheinLinkMapSyncService
{
    private const CACHE_KEY = 'shein_link_map_sync';

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     page: int,
     *     total_page: ?int,
     *     page_upserted: int,
     *     total_upserted: int,
     *     done: bool
     * }
     */
    public function syncPage(int $page = 1, int $pageSize = 200, bool $reset = false): array
    {
        if (! Schema::hasTable('shein_metric')) {
            return $this->fail('shein_metric table missing.');
        }

        $hasPricing = Schema::hasTable('shein_pricing_prices');
        $hasCache = Schema::hasTable('shein_metrics');
        if (! $hasPricing && ! $hasCache) {
            return $this->fail('shein_pricing_prices / shein_metrics missing. Sync Shein products first.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
        }

        $state = $this->getProgress();
        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Reading Shein catalog page {$page}…",
        ]);

        // Prefer pricing SKUs; fall back to product-cache SKUs.
        if ($hasPricing) {
            $query = SheinPricingPrice::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id');
            $source = 'shein_pricing_prices';
        } else {
            $query = SheinMetric::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id');
            $source = 'shein_metrics';
        }

        $totalCount = (clone $query)->count();
        $totalPage = max(1, (int) ceil($totalCount / $pageSize));
        $rows = $query->forPage($page, $pageSize)->get();

        // Prefetch shein_sku_code + names from product cache for this page.
        $skus = $rows->pluck('sku')->map(static fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
        $cacheBySku = [];
        if ($hasCache && $skus !== []) {
            SheinMetric::query()
                ->whereIn('sku', $skus)
                ->get(['sku', 'shein_sku_code', 'product_name', 'price'])
                ->each(function (SheinMetric $m) use (&$cacheBySku) {
                    $cacheBySku[trim((string) $m->sku)] = $m;
                });
        }

        $pageUpserted = 0;
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }

            $cache = $cacheBySku[$sku] ?? null;
            $sheinSkuCode = trim((string) ($cache?->shein_sku_code ?? ''));
            // Linked when we have a real Shein skuCode ≠ seller sku.
            $productId = $sheinSkuCode !== '' ? $sheinSkuCode : $sku;

            $price = $row->price ?? $cache?->price ?? null;
            $name = $cache?->product_name ?? null;

            SheinMmMetric::updateOrCreate(
                ['sku' => $sku],
                array_filter([
                    'product_id' => $productId,
                    'price' => $price !== null ? (float) $price : null,
                    'product_name' => $name,
                ], static fn ($v) => $v !== null)
            );
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from {$source} ({$page} page(s), {$totalCount} rows)."
            : "Page {$page} of {$totalPage}: {$pageUpserted} SKU link(s) saved…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'total_upserted' => $totalUpserted,
            'message' => $message,
            'done' => $done,
            'error' => false,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'page' => $page,
            'total_page' => $totalPage,
            'page_upserted' => $pageUpserted,
            'total_upserted' => $totalUpserted,
            'done' => $done,
        ];
    }

    /**
     * @return array{success: bool, message: string, total_upserted: int}
     */
    public function syncAll(): array
    {
        $page = 1;
        $total = 0;
        $reset = true;
        do {
            $result = $this->syncPage($page, 200, $reset);
            $reset = false;
            if (empty($result['success'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Link map sync failed.',
                    'total_upserted' => $total,
                ];
            }
            $total = (int) ($result['total_upserted'] ?? $total);
            if (! empty($result['done'])) {
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'total_upserted' => $total,
                ];
            }
            $page++;
        } while ($page < 5000);

        return [
            'success' => true,
            'message' => "Stopped after page limit; upserted {$total}.",
            'total_upserted' => $total,
        ];
    }

    /**
     * @return array{success: bool, message: string, page: int, total_page: null, page_upserted: int, total_upserted: int, done: bool}
     */
    protected function fail(string $message): array
    {
        Log::warning('SheinLinkMapSyncService: '.$message);
        $this->updateProgress([
            'running' => false,
            'done' => true,
            'error' => true,
            'message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'page' => 1,
            'total_page' => null,
            'page_upserted' => 0,
            'total_upserted' => 0,
            'done' => true,
        ];
    }

    protected function resetProgress(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->updateProgress([
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_count' => null,
            'total_upserted' => 0,
            'done' => false,
            'error' => false,
            'message' => '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        return is_array($state) ? $state : [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_upserted' => 0,
            'message' => '',
            'done' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function updateProgress(array $patch): void
    {
        $state = array_merge($this->getProgress(), $patch);
        Cache::put(self::CACHE_KEY, $state, now()->addHours(6));
    }
}
