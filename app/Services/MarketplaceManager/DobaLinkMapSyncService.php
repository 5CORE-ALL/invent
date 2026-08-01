<?php

namespace App\Services\MarketplaceManager;

use App\Models\DobaMetric;
use App\Services\DobaApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh doba_metrics from Doba OpenAPI goods/detail.
 */
class DobaLinkMapSyncService
{
    private const CACHE_KEY = 'doba_link_map_sync';

    public function __construct(
        protected DobaApiService $dobaApi
    ) {}

    /**
     * @return array{success: bool, message: string, page_upserted?: int, total_upserted?: int, done?: bool}
     */
    public function syncAll(): array
    {
        return $this->syncFromApi();
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
        if ($reset || $page === 1) {
            return $this->syncFromApi();
        }

        return array_merge($this->getProgress(), [
            'success' => true,
            'page' => $page,
            'page_upserted' => 0,
            'total_upserted' => (int) ($this->getProgress()['total_upserted'] ?? 0),
            'done' => true,
        ]);
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
        if (! Schema::hasTable('doba_metrics')) {
            return $this->fail('doba_metrics table missing.');
        }

        $this->updateProgress([
            'running' => true,
            'page' => 1,
            'message' => 'Refreshing Doba inventory from OpenAPI…',
        ]);

        if (! $this->dobaApi->isConfigured()) {
            return $this->fail('Doba API credentials missing.');
        }

        try {
            $this->dobaApi->getinventoryData();
        } catch (\Throwable $e) {
            Log::warning('DobaLinkMapSyncService: getinventoryData failed', ['error' => $e->getMessage()]);

            return $this->fail('Doba inventory refresh failed: '.$e->getMessage());
        }

        $linked = (int) DobaMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->count();

        $message = "Refreshed Doba link map — {$linked} listed SKU(s).";
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
