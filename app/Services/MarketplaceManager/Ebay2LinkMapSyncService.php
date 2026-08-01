<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh / ensure ebay_2_metrics link map rows (sku + item_id).
 * item_id is the MM product_id. Linked when item_id is present and ≠ sku.
 */
class Ebay2LinkMapSyncService
{
    private const CACHE_KEY = 'ebay2_link_map_sync';

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
        if (! Schema::hasTable('ebay_2_metrics')) {
            return $this->fail('ebay_2_metrics table missing.');
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
            'message' => "Reading eBay 2 metrics page {$page}…",
        ]);

        $query = Ebay2Metric::query()
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

            $itemId = trim((string) ($row->item_id ?? ''));
            // Keep existing item_id; if blank, leave as sku placeholder (unlinked).
            $payload = [];
            if ($itemId === '') {
                $payload['item_id'] = $sku;
            }
            // Touch updated_at so sync progress is visible.
            if ($payload !== []) {
                $row->fill($payload);
                $row->save();
            } else {
                $row->touch();
            }
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();

        $linked = Ebay2Metric::query()
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->whereColumn('item_id', '!=', 'sku')
            ->count();

        $message = $done
            ? "Validated {$totalUpserted} ebay_2_metrics row(s) ({$page} page(s), {$totalCount} rows). Linked listings: {$linked}."
            : "Page {$page} of {$totalPage}: {$pageUpserted} SKU link(s) checked…";

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
        Log::warning('Ebay2LinkMapSyncService: '.$message);
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
