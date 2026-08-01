<?php

namespace App\Services\MarketplaceManager;

use App\Models\TopDawgProduct;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh topdawg_products SKU ↔ topdawg_listing_id link map from TopDawg API
 * (or local table pages when API unavailable).
 */
class TopDawgLinkMapSyncService
{
    private const CACHE_KEY = 'topdawg_link_map_sync';

    public function __construct(
        protected TopDawgApiService $topdawgApi
    ) {}

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
        if (! Schema::hasTable('topdawg_products')) {
            return $this->fail('topdawg_products table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
            if ($this->topdawgApi->isConfigured()) {
                return $this->syncFromApi();
            }
        }

        $state = $this->getProgress();
        if (! empty($state['done']) && $page > 1) {
            return array_merge($state, ['success' => true]);
        }

        // Local pagination fallback / continuation after API sync stored everything.
        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Reading topdawg_products page {$page}…",
        ]);

        $query = TopDawgProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id');

        $totalCount = (clone $query)->count();
        $totalPage = max(1, (int) ceil($totalCount / $pageSize));
        $rows = $query->forPage($page, $pageSize)->get();

        $pageUpserted = 0;
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $listingId = trim((string) ($row->topdawg_listing_id ?? ''));
            if ($listingId === '') {
                $row->topdawg_listing_id = $sku;
                $row->save();
            } else {
                $row->touch();
            }
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();
        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from topdawg_products ({$page} page(s), {$totalCount} rows)."
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
    protected function syncFromApi(): array
    {
        $this->updateProgress([
            'running' => true,
            'page' => 1,
            'message' => 'Fetching TopDawg products from API…',
        ]);

        try {
            $result = $this->topdawgApi->fetchProducts();
            $products = $result['data'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('TopDawgLinkMapSyncService: API fetch failed', ['error' => $e->getMessage()]);

            return $this->fail('TopDawg product fetch failed: '.$e->getMessage());
        }

        $upserted = 0;
        foreach ($products as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $listingId = trim((string) ($item['id'] ?? $item['tdid'] ?? ''));
            $payload = [
                'topdawg_listing_id' => $listingId !== '' ? $listingId : $sku,
                'tdid' => $item['tdid'] ?? null,
                'product_title' => $item['product_name'] ?? $item['product_title'] ?? $item['title'] ?? null,
                'listing_state' => isset($item['status']) ? strtolower((string) $item['status']) : 'active',
                'price' => $item['cost'] ?? $item['price'] ?? null,
                'msrp' => $item['msrp'] ?? null,
                'remaining_inventory' => $item['qty_available'] ?? $item['remaining_inventory'] ?? $item['inventory'] ?? null,
            ];
            if (! empty($item['picture_url'])) {
                $urls = explode(',', (string) $item['picture_url']);
                $first = trim($urls[0] ?? '');
                if ($first !== '') {
                    $payload['image_src'] = $first;
                }
            }

            TopDawgProduct::updateOrCreate(['sku' => $sku], $payload);
            $upserted++;
        }

        $message = "Updated {$upserted} SKU link(s) from TopDawg API.";
        $this->updateProgress([
            'running' => false,
            'page' => 1,
            'total_page' => 1,
            'total_count' => $upserted,
            'total_upserted' => $upserted,
            'message' => $message,
            'done' => true,
            'error' => false,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'page' => 1,
            'total_page' => 1,
            'page_upserted' => $upserted,
            'total_upserted' => $upserted,
            'done' => true,
        ];
    }

    public function getProgress(): array
    {
        return Cache::get(self::CACHE_KEY, [
            'running' => false,
            'page' => 0,
            'total_page' => null,
            'total_count' => 0,
            'total_upserted' => 0,
            'message' => '',
            'done' => false,
            'error' => false,
        ]);
    }

    protected function updateProgress(array $patch): void
    {
        Cache::put(self::CACHE_KEY, array_merge($this->getProgress(), $patch), now()->addHours(2));
    }

    protected function resetProgress(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

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
    protected function fail(string $message): array
    {
        $this->updateProgress([
            'running' => false,
            'done' => true,
            'error' => true,
            'message' => $message,
        ]);

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
}
