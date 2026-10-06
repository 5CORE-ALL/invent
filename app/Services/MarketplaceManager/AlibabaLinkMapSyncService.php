<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AlibabaPricingPrice;
use App\Models\AlibabaSheetPrice;
use App\Services\AlibabaApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AlibabaLinkMapSyncService
{
    private const CACHE_KEY = 'alibaba_link_map_sync';

    private const MAX_PAGES = 500;

    public function __construct(
        protected AlibabaApiService $aliExpressApi
    ) {}

    /**
     * Sync one API page (for UI progress). Pass page=1 with reset=true to start.
     *
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
    public function syncPage(int $page = 1, int $pageSize = 50, bool $reset = false): array
    {
        if (! Schema::hasTable('alibaba_metrics')) {
            return $this->fail('alibaba_metrics table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
        }

        $state = $this->getProgress();
        if (($state['running'] ?? false) && ! $reset && $page === 1 && ($state['page'] ?? 0) > 1) {
            $this->resetProgress();
            $state = $this->getProgress();
        }

        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Fetching Alibaba page {$page}…",
        ]);

        $result = $this->aliExpressApi->getInventory($page, $pageSize);
        if (empty($result['success'])) {
            $message = $result['message'] ?? 'Failed to fetch products from Alibaba.';

            $this->updateProgress([
                'running' => false,
                'message' => $message,
                'error' => true,
            ]);

            return [
                'success' => false,
                'message' => $message,
                'page' => $page,
                'total_page' => null,
                'page_upserted' => 0,
                'total_upserted' => (int) ($state['total_upserted'] ?? 0),
                'done' => true,
            ];
        }

        $items = $result['data']['products'] ?? [];
        $totalPage = $this->intOrNull($result['data']['total_page'] ?? null);
        $totalCount = $this->intOrNull($result['data']['total_count'] ?? null);
        $pageUpserted = $this->upsertItems($items, $page === 1);

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $itemCount = count($items);
        $done = $this->isLastPage($page, $itemCount, $pageSize, $totalPage);
        $removed = 0;
        if ($done && $page < self::MAX_PAGES) {
            $removed = MarketplaceLinkMapPruner::prune(AlibabaMetric::class, 'alibaba');
        }

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from Alibaba ({$page} API page(s)".($totalCount ? ", {$totalCount} products on AE" : '')
                .($removed > 0 ? ", removed {$removed} missing" : '').'). No new listings were created on Alibaba.'
            : "Page {$page}".($totalPage ? " of {$totalPage}" : '').": {$pageUpserted} SKU link(s) saved…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'total_upserted' => $totalUpserted,
            'message' => $message,
            'done' => $done,
        ]);

        if ($done) {
            Log::info('Alibaba link map sync finished', [
                'pages_synced' => $page,
                'upserted' => $totalUpserted,
                'total_page' => $totalPage,
            ]);
        }

        return [
            'success' => true,
            'message' => $message,
            'page' => $page,
            'total_page' => $totalPage,
            'total_count' => $totalCount,
            'page_upserted' => $pageUpserted,
            'total_upserted' => $totalUpserted,
            'done' => $done,
        ];
    }

    /**
     * Sync all pages in one request (CLI / background).
     *
     * @return array{success: bool, message: string, upserted: int, pages: int}
     */
    public function syncAll(int $pageSize = 50): array
    {
        $this->resetProgress();
        $page = 1;
        $totalUpserted = 0;

        while ($page <= self::MAX_PAGES) {
            $result = $this->syncPage($page, $pageSize, $page === 1);
            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'upserted' => $totalUpserted,
                    'pages' => max(0, $page - 1),
                ];
            }

            $totalUpserted = $result['total_upserted'];
            if ($result['done']) {
                break;
            }

            $page++;
            usleep(150000);
        }

        return [
            'success' => true,
            'message' => "Updated {$totalUpserted} SKU link(s) from Alibaba ({$page} API page(s)).",
            'upserted' => $totalUpserted,
            'pages' => $page,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_upserted' => 0,
            'message' => '',
            'done' => false,
        ]);
    }

    public function resetProgress(): void
    {
        Cache::put(self::CACHE_KEY, [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_upserted' => 0,
            'message' => '',
            'done' => false,
            'error' => false,
        ], 3600);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function upsertItems(array $items, bool $resetSeen = false): int
    {
        $upserted = 0;
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rows = $this->aliExpressApi->extractSkuRowsFromListItem($item, fetchDetail: false);
            if (! $this->rowsHaveRealSku($rows) || ! $this->rowsHavePrice($rows)) {
                $detailRows = $this->aliExpressApi->extractSkuRowsFromListItem($item, fetchDetail: true);
                usleep(100000);
                if ($this->rowsHavePrice($detailRows) || ! $this->rowsHaveRealSku($rows)) {
                    $rows = $detailRows;
                }
            }

            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $productId = (string) ($row['product_id'] ?? '');
                if ($productId === '' || $sku === '' || $sku === $productId) {
                    continue;
                }

                $price = is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0.0;
                $fill = [
                    'product_id' => $productId,
                    'product_name' => $row['product_name'] ?? null,
                ];
                if ($price > 0) {
                    $fill['price'] = $price;
                }
                AlibabaMetric::updateOrCreate(['sku' => $sku], $fill);
                $this->persistApiPrice($productId, $sku, $price, $row['stock'] ?? null, $row['status'] ?? null);
                $seen[] = $sku;
                $upserted++;
            }
        }

        MarketplaceLinkMapPruner::remember('alibaba', $seen, $resetSeen);

        return $upserted;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function persistApiPrice(string $productId, string $sku, float $price, mixed $stock, mixed $status): void
    {
        if ($price <= 0) {
            return;
        }

        $soh = is_numeric($stock) ? (int) $stock : null;
        $statusText = is_string($status) && trim($status) !== '' ? trim($status) : null;

        if (Schema::hasTable('alibaba_sheet_prices')) {
            $existing = AlibabaSheetPrice::query()->where('product_id', $productId)->first();
            if ($existing === null || strcasecmp((string) $existing->sku, $sku) === 0) {
                $sheet = [
                    'sku' => $sku,
                    'sku_price' => $price,
                ];
                if ($soh !== null) {
                    $sheet['soh'] = $soh;
                }
                if ($existing === null && $statusText !== null) {
                    $sheet['status'] = $statusText;
                }
                AlibabaSheetPrice::updateOrCreate(['product_id' => $productId], $sheet);
            }
        }

        if (Schema::hasTable('alibaba_pricing_prices')) {
            $pricing = ['price' => $price];
            if ($soh !== null) {
                $pricing['ab_stock'] = $soh;
            }
            AlibabaPricingPrice::updateOrCreate(['sku' => $sku], $pricing);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function rowsHaveRealSku(array $rows): bool
    {
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $productId = (string) ($row['product_id'] ?? '');
            if ($sku !== '' && $productId !== '' && $sku !== $productId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function rowsHavePrice(array $rows): bool
    {
        foreach ($rows as $row) {
            if (is_numeric($row['price'] ?? null) && (float) $row['price'] > 0) {
                return true;
            }
        }

        return false;
    }

    protected function isLastPage(int $page, int $itemCount, int $pageSize, ?int $totalPage): bool
    {
        if ($itemCount === 0) {
            return true;
        }

        if ($page >= self::MAX_PAGES) {
            return true;
        }

        if ($totalPage !== null && $totalPage > 0 && $page >= $totalPage) {
            return true;
        }

        return $itemCount < $pageSize;
    }

  /**
     * @param  array<string, mixed>  $patch
     */
    protected function updateProgress(array $patch): void
    {
        $state = array_merge($this->getProgress(), $patch);
        Cache::put(self::CACHE_KEY, $state, 3600);
    }

    /**
     * @return array{success: bool, message: string, page: int, total_page: ?int, page_upserted: int, total_upserted: int, done: bool}
     */
    protected function fail(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'page' => 0,
            'total_page' => null,
            'page_upserted' => 0,
            'total_upserted' => 0,
            'done' => true,
        ];
    }

    protected function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
