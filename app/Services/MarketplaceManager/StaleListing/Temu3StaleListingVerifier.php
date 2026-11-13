<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\Temu3Metric;
use App\Services\MarketplaceManager\ReconcilesTemuStaleGoods;
use App\Services\Temu3ApiService;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 3 "Listed" is temu3_metrics.goods_id (non-empty, not equal to the SKU, listing_status not deleted /
 * missing). A SKU keeps a stale goods_id when Temu deleted the goods or when the SKU was dropped from a
 * multi-variation goods. Two checks, both only on a definite answer:
 *  1. Goods gone: goods.list.retrieve (ALL) is the live Seller Center list. Goods missing from a completely read
 *     list lose their goods_id (same guarded reconcile as Temu 1 / Temu 2, with the 20 goods / 80% guards).
 *  2. SKU absent: goods.detail of each remaining goods lists its SKUs; a stored SKU that is not on its goods
 *     loses the goods_id.
 * Errors, unreadable or empty answers keep the row and count as an error.
 */
class Temu3StaleListingVerifier implements StaleListingVerifier
{
    use ReconcilesTemuStaleGoods;

    private const MAX_GOODS = 400;

    private const MAX_SECONDS = 240;

    private const OFFSET_KEY = 'stale_listing_verify_temu3_offset';

    public function __construct(
        protected Temu3ApiService $temuApi
    ) {}

    protected function reconcileMetricClass(): string
    {
        return Temu3Metric::class;
    }

    protected function reconcileChannelKey(): string
    {
        return 'temu3';
    }

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('temu3_metrics')) {
            $out['message'] = 'Temu 3: temu3_metrics table not found.';

            return $out;
        }
        if (! $this->temuApi->isConfigured()) {
            $out['message'] = 'Temu 3: API credentials missing, nothing checked.';

            return $out;
        }

        $out['checked'] = (int) Temu3Metric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->count();

        // 1. Goods Temu no longer lists.
        $goodsCleared = 0;
        $step1 = ['ok' => false, 'note' => 'Temu goods check did not run.'];
        try {
            $step1 = $this->reconcileStaleGoodsIds();
            $goodsCleared = (int) ($step1['cleared'] ?? 0);
        } catch (\Throwable $e) {
            $step1['note'] = 'Temu goods check failed: '.$e->getMessage();
            Log::warning('Temu3StaleListingVerifier: goods reconcile failed', ['error' => $e->getMessage()]);
        }

        // 2. SKUs dropped from a goods that still exists.
        $skuStep = $this->verifySkusOnGoods();

        $out['cleared'] = $goodsCleared + $skuStep['cleared'];
        $out['errors'] = $skuStep['errors'] + ($step1['ok'] ? 0 : 1);
        $out['kept'] = max(0, $out['checked'] - $out['cleared'] - $skuStep['errors']);
        $out['ok'] = $step1['ok'] && ! $skuStep['aborted'];
        $out['message'] = "Checked {$out['checked']} Temu 3 SKU(s): {$goodsCleared} on goods Temu no longer lists, "
            ."{$skuStep['cleared']} no longer on their goods ".($out['cleared'] > 0 ? '(now Missing L)' : '')
            .", {$out['kept']} still listed, {$out['errors']} could not be verified."
            .(($step1['note'] ?? '') !== '' ? ' '.$step1['note'] : '')
            .($skuStep['aborted'] ? ' Five failures in a row; SKU check stopped, run again later.' : '')
            .($skuStep['not_reached'] > 0 && ! $skuStep['aborted'] ? " {$skuStep['not_reached']} goods are checked on the next run." : '');

        return $out;
    }

    /**
     * @return array{cleared: int, errors: int, aborted: bool, not_reached: int}
     */
    private function verifySkusOnGoods(): array
    {
        $res = ['cleared' => 0, 'errors' => 0, 'aborted' => false, 'not_reached' => 0];

        $hasStatus = Schema::hasColumn('temu3_metrics', 'listing_status');
        $hasSkuId = Schema::hasColumn('temu3_metrics', 'sku_id');
        $columns = ['id', 'sku', 'goods_id'];
        if ($hasStatus) {
            $columns[] = 'listing_status';
        }
        if ($hasSkuId) {
            $columns[] = 'sku_id';
        }

        // goods id => rows
        $byGoods = [];
        Temu3Metric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->whereColumn('sku', '!=', 'goods_id')
            ->orderBy('id')
            ->get($columns)
            ->each(function ($row) use (&$byGoods, $hasStatus, $hasSkuId) {
                $sku = trim((string) $row->sku);
                $goodsId = trim((string) $row->goods_id);
                if ($sku === '' || $goodsId === '' || stripos($sku, 'PARENT') !== false) {
                    return;
                }
                if ($hasStatus && ListingCountsEngine::listingStatusHidesId($row->listing_status ?? null, $goodsId, $sku)) {
                    return;
                }
                $byGoods[$goodsId][] = [
                    'id' => (int) $row->id,
                    'sku' => $sku,
                    'sku_id' => $hasSkuId ? trim((string) ($row->sku_id ?? '')) : '',
                ];
            });
        if ($byGoods === []) {
            return $res;
        }

        $goodsIds = array_map('strval', array_keys($byGoods));
        sort($goodsIds, SORT_STRING);
        $offset = ((int) Cache::get(self::OFFSET_KEY, 0)) % count($goodsIds);
        $goodsIds = array_merge(array_slice($goodsIds, $offset), array_slice($goodsIds, 0, $offset));

        $absent = []; // list of [id, sku, goods_id]
        $verifiedRows = 0;
        $consecutiveErrors = 0;
        $processed = 0;
        $started = time();
        foreach ($goodsIds as $goodsId) {
            if ($processed >= self::MAX_GOODS || (time() - $started) > self::MAX_SECONDS) {
                break;
            }
            $processed++;
            $rows = $byGoods[$goodsId];
            try {
                $info = $this->temuApi->fetchGoodsSkuInfo($goodsId);
            } catch (\Throwable $e) {
                $info = null;
                Log::warning('Temu3StaleListingVerifier: goods detail failed', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);
            }
            $skus = is_array($info) && is_array($info['skus'] ?? null) ? $info['skus'] : [];
            if ($skus === []) {
                // Temu call failed or returned no SKU list: unknown, keep.
                $res['errors'] += count($rows);
                $consecutiveErrors++;
                if ($consecutiveErrors >= 5) {
                    $res['aborted'] = true;
                    break;
                }

                continue;
            }
            $consecutiveErrors = 0;
            $verifiedRows += count($rows);

            $nameKeys = [];
            $hasUnnamed = false;
            foreach ($skus as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    $hasUnnamed = true;

                    continue;
                }
                foreach (ListingCountsEngine::skuIndexKeys($name) as $key) {
                    $nameKeys[$key] = true;
                }
            }

            foreach ($rows as $row) {
                if ($row['sku_id'] !== '' && array_key_exists($row['sku_id'], $skus)) {
                    continue;
                }
                if ($hasUnnamed) {
                    continue; // a SKU Temu has no seller name for could be this one
                }
                $found = false;
                foreach ($this->skuKeys($row['sku']) as $key) {
                    if (isset($nameKeys[$key])) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $absent[] = ['id' => $row['id'], 'sku' => $row['sku'], 'goods_id' => $goodsId];
                }
            }
            usleep(150000);
        }
        Cache::forever(self::OFFSET_KEY, ($offset + $processed) % count($goodsIds));
        $res['not_reached'] = max(0, count($goodsIds) - $processed);

        // Most SKUs "absent" means the goods.detail answer is not shaped as expected, not that they were dropped.
        if (count($absent) >= 20 && count($absent) > (int) floor($verifiedRows * 0.5)) {
            Log::warning('Temu3StaleListingVerifier: too many SKUs reported absent, nothing removed', ['absent' => count($absent), 'verified' => $verifiedRows]);
            $res['errors'] += count($absent);

            return $res;
        }

        foreach ($absent as $item) {
            try {
                Temu3Metric::query()->whereKey($item['id'])->update(['goods_id' => null]);
                $res['cleared']++;
                Log::info('Temu3StaleListingVerifier: SKU is no longer on its Temu goods, goods_id cleared', ['sku' => $item['sku'], 'goods_id' => $item['goods_id']]);
            } catch (\Throwable $e) {
                $res['errors']++;
                Log::warning('Temu3StaleListingVerifier: update failed', ['sku' => $item['sku'], 'goods_id' => $item['goods_id'], 'error' => $e->getMessage()]);
            }
        }

        return $res;
    }

    /**
     * @return list<string>
     */
    private function skuKeys(string $sku): array
    {
        $keys = ListingCountsEngine::skuIndexKeys($sku);
        foreach (ListingCountsEngine::packCountAliasSkus($sku) as $alias) {
            $keys = array_merge($keys, ListingCountsEngine::skuIndexKeys($alias));
        }

        return array_values(array_unique($keys));
    }
}
