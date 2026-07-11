<?php

namespace App\Services\MarketplaceManager;

use App\Models\ReverbMetric;
use App\Services\ReverbManagerApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReverbLinkMapSyncService
{
    private const CACHE_KEY = 'reverb_link_map_sync';

    private const MAX_PAGES = 500;

    public function __construct(
        protected ReverbManagerApiService $aliExpressApi
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
        if (! Schema::hasTable('reverb_metric')) {
            return $this->fail('reverb_metric table missing.');
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
            'message' => "Fetching Reverb page {$page}…",
        ]);

        $result = $this->aliExpressApi->getInventory($page, $pageSize);
        if (empty($result['success'])) {
            $message = $result['message'] ?? 'Failed to fetch products from Reverb.';

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
        $pageUpserted = $this->upsertItems($items);

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $itemCount = count($items);
        $done = $this->isLastPage($page, $itemCount, $pageSize, $totalPage);

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from Reverb ({$page} API page(s)".($totalCount ? ", {$totalCount} products on Reverb" : '').'). No new listings were created on Reverb.'
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
            Log::info('Reverb link map sync finished', [
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
            'message' => "Updated {$totalUpserted} SKU link(s) from Reverb ({$page} API page(s)).",
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

            $rows = $this->aliExpressApi->extractSkuRowsFromListItem($item, fetchDetail: false);
            if (! $this->rowsHaveRealSku($rows)) {
                $rows = $this->aliExpressApi->extractSkuRowsFromListItem($item, fetchDetail: true);
                usleep(100000);
            }

            foreach ($rows as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $productId = (string) ($row['product_id'] ?? '');
                if ($productId === '' || $sku === '' || $sku === $productId) {
                    continue;
                }

                ReverbMetric::updateOrCreate(
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
