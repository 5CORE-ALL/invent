<?php

namespace App\Services\MarketplaceManager;

use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Build / refresh newegg_metric link map from existing newegg_pricing catalog.
 * (Newegg item list API is report/async — pricing table is the operational source.)
 */
class NeweggLinkMapSyncService
{
    private const CACHE_KEY = 'newegg_link_map_sync';

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
        if (! Schema::hasTable('newegg_metric')) {
            return $this->fail('newegg_metric table missing.');
        }

        if (! Schema::hasTable('newegg_pricing')) {
            return $this->fail('newegg_pricing table missing. Fetch Newegg items/pricing first.');
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
            'message' => "Reading newegg_pricing page {$page}…",
        ]);

        $query = NeweggPricing::query()
            ->whereNotNull('seller_part_number')
            ->where('seller_part_number', '!=', '')
            ->orderBy('id');

        $totalCount = (clone $query)->count();
        $totalPage = max(1, (int) ceil($totalCount / $pageSize));
        $rows = $query->forPage($page, $pageSize)->get();

        $pageUpserted = 0;
        foreach ($rows as $row) {
            $sku = trim((string) $row->seller_part_number);
            if ($sku === '') {
                continue;
            }
            $itemNumber = trim((string) ($row->newegg_item_number ?? ''));
            // Linked when we have a real Newegg Item #; otherwise keep product_id=sku (unlinked placeholder).
            $productId = $itemNumber !== '' ? $itemNumber : $sku;

            NeweggMetric::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $productId,
                    'price' => $row->selling_price,
                ]
            );
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();

        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from newegg_pricing ({$page} page(s), {$totalCount} rows)."
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
     * Run all pages in one call (cli / schedule).
     *
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
        Log::warning('NeweggLinkMapSyncService: '.$message);
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
