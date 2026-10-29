<?php

namespace App\Services\MarketplaceManager;

use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Temu 1 / Temu 2: Missing L must follow the marketplace.
 *
 * A SKU only counts as Listed while its goods_id is a goods Temu still lists in Seller Center.
 * goods.list.retrieve never reports deleted / removed goods as live, but the link-map refresh only ever
 * ADDED goods_id values, so SKUs of deleted listings stayed "Listed" forever (and a relist was treated as an
 * update of the dead goods). This removes the stale goods_id permanently - from the metrics table and from the
 * saved buyer / seller links - so the SKU shows as Missing L and is published as a brand-new listing.
 *
 * Classes using this trait need: $this->temuApi (TemuApiService / Temu2ApiService),
 * reconcileMetricClass() and reconcileChannelKey().
 */
trait ReconcilesTemuStaleGoods
{
    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    abstract protected function reconcileMetricClass(): string;

    /** ChannelListingRegistry key, e.g. 'temu' or 'temu2' */
    abstract protected function reconcileChannelKey(): string;

    /**
     * Safety: nothing changes when the live list could not be read completely, has fewer than 20 goods,
     * or would remove more than 80% of the stored goods (unless $force). Every cleared SKU / goods_id pair
     * is written to storage/app/temu_cache/temu_cleared_goods_ids_*.json so it can be restored.
     *
     * @return array{ok: bool, live: int, stale_skus: int, stale_goods: int, cleared: int, links_cleared: int, note: string, sample: list<array{sku: string, goods_id: string}>}
     */
    public function reconcileStaleGoodsIds(bool $dryRun = false, bool $force = false): array
    {
        $result = ['ok' => false, 'live' => 0, 'stale_skus' => 0, 'stale_goods' => 0, 'cleared' => 0, 'links_cleared' => 0, 'note' => '', 'sample' => []];

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

        $metric = $this->reconcileMetricClass();
        $rows = $metric::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['id', 'sku', 'goods_id']);
        $storedGoods = $rows->pluck('goods_id')->map(fn ($g) => (string) $g)->unique();
        $stale = $rows->filter(fn ($r) => ! isset($liveSet[(string) $r->goods_id]))->values();
        $staleGoods = $stale->pluck('goods_id')->map(fn ($g) => (string) $g)->unique()->values();

        $result['ok'] = true;
        $result['stale_skus'] = $stale->count();
        $result['stale_goods'] = $staleGoods->count();
        $result['sample'] = $stale->take(40)->map(fn ($r) => ['sku' => (string) $r->sku, 'goods_id' => (string) $r->goods_id])->all();

        if ($stale->isEmpty()) {
            // Metrics are clean, but saved links may still point at goods Temu dropped earlier.
            return $result;
        }
        if (! $force && ($result['live'] < 20 || $staleGoods->count() > max(25, (int) floor($storedGoods->count() * 0.8)))) {
            $result['ok'] = false;
            $result['note'] = "Temu's live list ({$result['live']} goods) disagrees with {$staleGoods->count()} of {$storedGoods->count()} stored goods - nothing removed (run temu:reconcile-goods --force to override).";

            return $result;
        }

        $staleIds = $staleGoods->all();
        if ($dryRun) {
            $result['links_cleared'] = $this->clearStaleLinks($staleIds, true);

            return $result;
        }

        try {
            Storage::disk('local')->put(
                'temu_cache/temu_cleared_goods_ids_'.$this->reconcileChannelKey().'_'.now()->format('Ymd_His').'.json',
                json_encode($stale->map(fn ($r) => ['id' => $r->id, 'sku' => $r->sku, 'goods_id' => (string) $r->goods_id])->all(), JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            $result['ok'] = false;
            $result['note'] = 'Could not write the restore file - nothing removed: '.$e->getMessage();

            return $result;
        }

        foreach ($stale->pluck('id')->chunk(500) as $ids) {
            $result['cleared'] += $metric::query()->whereIn('id', $ids->all())->update(['goods_id' => null]);
        }
        $result['links_cleared'] = $this->clearStaleLinks($staleIds, false);

        Log::info('Temu stale goods removed', [
            'channel' => $this->reconcileChannelKey(),
            'rows' => $result['cleared'],
            'goods' => $staleGoods->count(),
            'links' => $result['links_cleared'],
        ]);

        return $result;
    }

    /**
     * Saved buyer / seller links (listing status + DataView) still carry the dead goods_id and would keep the
     * SKU Listed, so they are removed too.
     *
     * @param  list<string>  $staleGoodsIds
     */
    protected function clearStaleLinks(array $staleGoodsIds, bool $dryRun): int
    {
        $cfg = ChannelListingRegistry::get($this->reconcileChannelKey()) ?? [];
        $stale = array_flip(array_map('strval', $staleGoodsIds));
        $count = 0;

        foreach (['status', 'dataView'] as $key) {
            $class = $cfg[$key] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            $table = (new $class)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'value')) {
                continue;
            }

            $class::query()->whereNotNull('value')->orderBy('id')->chunkById(500, function ($rows) use ($stale, $dryRun, &$count) {
                foreach ($rows as $row) {
                    $raw = $row->value;
                    $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
                    if (! is_array($data)) {
                        continue;
                    }
                    $changed = false;
                    foreach (['buyer_link', 'seller_link'] as $field) {
                        $link = (string) ($data[$field] ?? '');
                        if ($link === '' || preg_match_all('/\d{9,}/', $link, $m) < 1) {
                            continue;
                        }
                        foreach ($m[0] as $digits) {
                            if (isset($stale[$digits])) {
                                unset($data[$field]);
                                $changed = true;
                                break;
                            }
                        }
                    }
                    if (! $changed) {
                        continue;
                    }
                    $count++;
                    if (! $dryRun) {
                        $row->value = is_array($raw) ? $data : json_encode($data);
                        $row->save();
                    }
                }
            });
        }

        return $count;
    }
}
