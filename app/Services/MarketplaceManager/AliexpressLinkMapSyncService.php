<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressMetric;
use App\Services\AliExpressApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AliexpressLinkMapSyncService
{
    private const CACHE_KEY = 'aliexpress_link_map_sync';

    private const MAX_PAGES = 500;

    public function __construct(
        protected AliExpressApiService $aliExpressApi
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
        if (! Schema::hasTable('aliexpress_metric')) {
            return $this->fail('aliexpress_metric table missing.');
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
            'message' => "Fetching AliExpress page {$page}…",
        ]);

        $result = $this->aliExpressApi->getInventory($page, $pageSize);
        if (empty($result['success'])) {
            $message = $result['message'] ?? 'Failed to fetch products from AliExpress.';

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
        $pageUpserted = $this->upsertItems($items);

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $itemCount = count($items);
        $done = $this->isLastPage($page, $itemCount, $pageSize, $totalPage);

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from AliExpress ({$page} API page(s)). No new listings were created on AliExpress."
            : "Page {$page}".($totalPage ? " of {$totalPage}" : '').": {$pageUpserted} SKU link(s) saved…";

        $this->updateProgress([
            'running' => ! $done,
            'page' => $page,
            'total_page' => $totalPage,
            'total_upserted' => $totalUpserted,
            'message' => $message,
            'done' => $done,
        ]);

        if ($done) {
            Log::info('Aliexpress link map sync finished', [
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
            'message' => "Updated {$totalUpserted} SKU link(s) from AliExpress ({$page} API page(s)).",
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
    protected function upsertItems(array $items): int
    {
        $upserted = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($this->aliExpressApi->extractSkuRowsFromListItem($item, fetchDetail: false) as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $productId = (string) ($row['product_id'] ?? '');
                if ($productId === '' || $sku === '' || $sku === $productId) {
                    continue;
                }

                AliexpressMetric::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $productId,
                        'product_name' => $row['product_name'] ?? null,
                        'price' => $row['price'] ?? 0,
                    ]
                );
                $upserted++;
            }
        }

        return $upserted;
    }

    protected function isLastPage(int $page, int $itemCount, int $pageSize, ?int $totalPage): bool
    {
        if ($itemCount === 0) {
            return true;
        }

        if ($page >= self::MAX_PAGES) {
            return true;
        }

        // Partial page = last page. Full pages always continue (API total_page is often wrong).
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
