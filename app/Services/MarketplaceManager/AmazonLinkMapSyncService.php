<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonListingStatus;
use App\Models\ProductStockMapping;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh amazon_listing_statuses SKU ↔ ASIN link map from local rows + optional listings pull.
 */
class AmazonLinkMapSyncService
{
    private const CACHE_KEY = 'amazon_link_map_sync';

    public function __construct(
        protected AmazonSpApiService $amazonApi
    ) {}

    /**
     * @return array{success: bool, message: string, total_upserted?: int, done?: bool}
     */
    public function syncAll(): array
    {
        $page = 1;
        $totalUpserted = 0;
        do {
            $result = $this->syncPage($page, 200, $page === 1);
            if (empty($result['success'])) {
                return $result;
            }
            $totalUpserted = (int) ($result['total_upserted'] ?? $totalUpserted);
            if (! empty($result['done'])) {
                break;
            }
            $page++;
        } while ($page <= 500);

        return [
            'success' => true,
            'message' => "Link map sync finished ({$totalUpserted} row(s) touched).",
            'total_upserted' => $totalUpserted,
            'done' => true,
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
    public function syncPage(int $page = 1, int $pageSize = 200, bool $reset = false): array
    {
        if (! Schema::hasTable('amazon_listing_statuses')) {
            return $this->fail('amazon_listing_statuses table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
            if ($this->amazonApi->isConfigured()) {
                $this->syncFromListingsOrInventory();
            }
        }

        $state = $this->getProgress();
        if (! empty($state['done']) && $page > 1) {
            return array_merge($state, ['success' => true]);
        }

        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Reading amazon_listing_statuses page {$page}…",
        ]);

        $query = AmazonListingStatus::query()
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
            $value = AmazonListingStatusHelper::valueArray($row);
            $asin = AmazonListingStatusHelper::resolveAsin($row);
            if ($asin === '' && Schema::hasTable('product_stock_mappings')) {
                $asin = $this->lookupAsinFromMappings($sku);
            }
            if ($asin !== '' && ($value['asin'] ?? '') !== $asin) {
                $value['asin'] = $asin;
                $row->value = $value;
                $row->save();
            } else {
                $row->touch();
            }
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();
        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from amazon_listing_statuses ({$page} page(s), {$totalCount} rows)."
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

    protected function syncFromListingsOrInventory(): void
    {
        $this->updateProgress([
            'running' => true,
            'page' => 1,
            'message' => 'Refreshing Amazon listings / inventory from SP-API…',
        ]);

        try {
            if (Schema::hasTable('amazon_listings_raw')) {
                Artisan::call('app:fetch-amazon-listings');
            } else {
                $this->amazonApi->getinventory();
            }
        } catch (\Throwable $e) {
            Log::warning('AmazonLinkMapSyncService: listings refresh failed', ['error' => $e->getMessage()]);
        }
    }

    protected function lookupAsinFromMappings(string $sku): string
    {
        if (! Schema::hasTable('amazon_listings_raw')) {
            return '';
        }

        $row = \Illuminate\Support\Facades\DB::table('amazon_listings_raw')
            ->where('seller_sku', $sku)
            ->orWhereRaw('UPPER(TRIM(seller_sku)) = ?', [strtoupper($sku)])
            ->orderByDesc('id')
            ->first(['asin1', 'asin']);

        if (! $row) {
            return '';
        }
        $asin = strtoupper(trim((string) ($row->asin1 ?? $row->asin ?? '')));

        return preg_match('/^[A-Z0-9]{10}$/', $asin) ? $asin : '';
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
