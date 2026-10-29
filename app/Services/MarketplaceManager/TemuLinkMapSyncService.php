<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh temu_metrics SKU ↔ goods_id link map from Temu API or local rows.
 */
class TemuLinkMapSyncService
{
    private const CACHE_KEY = 'temu_link_map_sync';

    public function __construct(
        protected TemuApiService $temuApi
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
        if (! Schema::hasTable('temu_metrics')) {
            return $this->fail('temu_metrics table missing.');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(500, $pageSize));

        if ($reset || $page === 1) {
            $this->resetProgress();
            if ($this->temuApi->isConfigured()) {
                return $this->syncFromApiOrMetrics();
            }
        }

        $state = $this->getProgress();
        if (! empty($state['done']) && $page > 1) {
            return array_merge($state, ['success' => true]);
        }

        $this->updateProgress([
            'running' => true,
            'page' => $page,
            'message' => "Reading temu_metrics page {$page}…",
        ]);

        $query = TemuMetric::query()
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
            $goodsId = trim((string) ($row->goods_id ?? ''));
            if ($goodsId === '' || $goodsId === $sku) {
                continue;
            }
            $row->touch();
            $pageUpserted++;
        }

        $totalUpserted = (int) ($state['total_upserted'] ?? 0) + $pageUpserted;
        $done = $page >= $totalPage || $rows->isEmpty();
        $message = $done
            ? "Updated {$totalUpserted} SKU link(s) from temu_metrics ({$page} page(s), {$totalCount} rows)."
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
    protected function syncFromApiOrMetrics(): array
    {
        $this->updateProgress([
            'running' => true,
            'page' => 1,
            'message' => 'Fetching Temu metrics from API…',
        ]);

        try {
            // Only the two steps that build the link map: SKUs (seller SKU + skuId), then goods (goods_id).
            // The full command also pulls order quantities, stock, prices and a per-product ads report
            // (thousands of API calls) - that belongs to the daily metrics run, not to this refresh.
            foreach (['skus', 'goods'] as $step) {
                Artisan::call('app:fetch-temu-metrics', ['--only' => $step]);
                Log::info('TemuLinkMapSyncService: fetch-temu-metrics', ['step' => $step, 'output' => trim(Artisan::output())]);
            }
        } catch (\Throwable $e) {
            Log::warning('TemuLinkMapSyncService: fetch-temu-metrics failed, trying getInventory', [
                'error' => $e->getMessage(),
            ]);
            try {
                $this->temuApi->getInventory();
            } catch (\Throwable $e2) {
                Log::warning('TemuLinkMapSyncService: getInventory failed', ['error' => $e2->getMessage()]);
            }
        }

        // Missing L must follow the marketplace: drop goods_id values Temu no longer lists.
        $reconcile = ['ok' => false, 'cleared' => 0, 'note' => ''];
        try {
            $reconcile = $this->reconcileStaleGoodsIds();
        } catch (\Throwable $e) {
            Log::warning('TemuLinkMapSyncService: reconcile failed', ['error' => $e->getMessage()]);
        }

        $linked = TemuMetric::query()
            ->whereNotNull('sku')
            ->whereNotNull('goods_id')
            ->where('sku', '!=', '')
            ->where('goods_id', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
            ->count();

        TemuMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) {
                $q->whereNull('goods_id')->orWhere('goods_id', '');
            })
            ->limit(5000)
            ->get()
            ->each(fn (TemuMetric $row) => $row->touch());

        $message = "Refreshed Temu link map — {$linked} linked SKU(s) (sku + goods_id)."
            .(($reconcile['cleared'] ?? 0) > 0 ? " Removed {$reconcile['cleared']} stale link(s) Temu no longer lists." : '')
            .(($reconcile['note'] ?? '') !== '' ? ' '.$reconcile['note'] : '');
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

    /**
     * A SKU only counts as Listed (not Missing L) while its goods_id is a goods Temu still lists in
     * Seller Center. goods.list.retrieve never reports deleted / removed goods as live, but the goods
     * step above only ever ADDS goods_id values, so those SKUs stayed "Listed" forever.
     *
     * Safety: nothing changes when the live list could not be read completely, looks too small, or
     * would remove more than half of the stored goods (unless $force). Every cleared SKU/goods_id pair
     * is written to storage/app/temu_cache/temu_cleared_goods_ids_*.json so it can be restored.
     *
     * @return array{ok: bool, live: int, stale_skus: int, cleared: int, note: string, sample: list<array{sku: string, goods_id: string}>}
     */
    public function reconcileStaleGoodsIds(bool $dryRun = false, bool $force = false): array
    {
        $result = ['ok' => false, 'live' => 0, 'stale_skus' => 0, 'cleared' => 0, 'note' => '', 'sample' => []];

        $live = $this->temuApi->fetchLiveGoodsIds();
        if (! is_array($live) || $live === []) {
            $result['note'] = 'Temu goods list could not be read completely - no link was removed.';

            return $result;
        }
        $liveSet = [];
        foreach (array_keys($live) as $goodsId) {
            $liveSet[(string) $goodsId] = true;
        }
        $result['live'] = count($liveSet);

        $rows = TemuMetric::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['id', 'sku', 'goods_id']);
        $storedGoods = $rows->pluck('goods_id')->map(fn ($g) => (string) $g)->unique();
        $stale = $rows->filter(fn ($r) => ! isset($liveSet[(string) $r->goods_id]))->values();
        $staleGoods = $stale->pluck('goods_id')->map(fn ($g) => (string) $g)->unique();

        $result['ok'] = true;
        $result['stale_skus'] = $stale->count();
        $result['sample'] = $stale->take(40)->map(fn ($r) => ['sku' => (string) $r->sku, 'goods_id' => (string) $r->goods_id])->all();

        if ($stale->isEmpty()) {
            return $result;
        }
        if (! $force && ($result['live'] < 20 || $staleGoods->count() > max(25, (int) floor($storedGoods->count() / 2)))) {
            $result['ok'] = false;
            $result['note'] = "Temu's live list ({$result['live']} goods) disagrees with {$staleGoods->count()} of {$storedGoods->count()} stored goods - nothing removed (run temu:reconcile-goods --force to override).";

            return $result;
        }
        if ($dryRun) {
            return $result;
        }

        try {
            \Illuminate\Support\Facades\Storage::disk('local')->put(
                'temu_cache/temu_cleared_goods_ids_'.now()->format('Ymd_His').'.json',
                json_encode($stale->map(fn ($r) => ['id' => $r->id, 'sku' => $r->sku, 'goods_id' => (string) $r->goods_id])->all(), JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['note'] = 'Could not write the restore file - nothing removed: '.$e->getMessage();

            return $result;
        }

        foreach ($stale->pluck('id')->chunk(500) as $ids) {
            $result['cleared'] += TemuMetric::query()->whereIn('id', $ids->all())->update(['goods_id' => null]);
        }
        Log::info('TemuLinkMapSyncService: cleared stale goods_id', ['rows' => $result['cleared'], 'goods' => $staleGoods->count()]);

        return $result;
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
