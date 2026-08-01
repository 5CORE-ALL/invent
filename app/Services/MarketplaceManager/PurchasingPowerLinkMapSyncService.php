<?php

namespace App\Services\MarketplaceManager;

use App\Models\PurchasingPowerProduct;
use App\Services\PurchasingPowerApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh purchasing_power_products from MCM OF21 (prices/stock) or paginate local rows.
 */
class PurchasingPowerLinkMapSyncService
{
    private const CACHE_KEY = 'purchasingpower_link_map_sync';

    public function __construct(
        protected PurchasingPowerApiService $ppApi
    ) {}

    /**
     * @return array{success: bool, message: string, page_upserted?: int, total_upserted?: int, done?: bool}
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
        if (! Schema::hasTable('purchasing_power_products')) {
            return $this->fail('purchasing_power_products table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
            if ($this->ppApi->isConfigured()) {
                return $this->syncFromApi();
            }
        }

        $state = $this->getProgress();
        if (! empty($state['done']) && $page > 1) {
            return array_merge($state, ['success' => true]);
        }

        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Reading purchasing_power_products page {$page}…",
        ]);

        $query = PurchasingPowerProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id');

        $totalCount = (clone $query)->count();
        $totalPage = max(1, (int) ceil($totalCount / $pageSize));
        $rows = $query->forPage($page, $pageSize)->get();

        $pageUpserted = 0;
        foreach ($rows as $row) {
            if (trim((string) $row->sku) === '') {
                continue;
            }
            $row->touch();
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();
        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from purchasing_power_products ({$page} page(s), {$totalCount} rows)."
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
            'message' => 'Fetching Purchasing Power offers from MCM (OF21)…',
        ]);

        try {
            Artisan::call('purchasing-power:sync', ['--prices' => true]);
            $output = trim(Artisan::output());
            Log::info('PurchasingPowerLinkMapSyncService: purchasing-power:sync --prices', ['output' => $output]);
        } catch (\Throwable $e) {
            Log::warning('PurchasingPowerLinkMapSyncService: price sync failed', ['error' => $e->getMessage()]);

            return $this->fail('Purchasing Power price sync failed: '.$e->getMessage());
        }

        $linked = (int) PurchasingPowerProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->count();

        $message = "Refreshed Purchasing Power link map — {$linked} listed SKU(s).";
        $this->updateProgress([
            'running' => false,
            'page' => 1,
            'total_page' => 1,
            'total_count' => $linked,
            'total_upserted' => $linked,
            'message' => $message,
            'done' => true,
            'error' => false,
        ]);

        return [
            'success' => true,
            'message' => $message,
            'page' => 1,
            'total_page' => 1,
            'page_upserted' => $linked,
            'total_upserted' => $linked,
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
