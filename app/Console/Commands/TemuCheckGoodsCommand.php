<?php

namespace App\Console\Commands;

use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Illuminate\Console\Command;

/**
 * Read-only: shows what Temu's API says about one goods_id and why /temu/ads does (not) list it.
 * Nothing is written to Temu or to the database (use --purge-cache-only to refresh just the live-goods cache).
 */
class TemuCheckGoodsCommand extends Command
{
    protected $signature = 'temu:check-goods {goods_id : Temu goods ID, e.g. 602294351406404}
                            {--refresh-live : Re-read the live goods list from Temu and refresh the /temu/ads cache}';

    protected $description = 'Check a Temu goods_id against the live API and explain whether /temu/ads shows or hides it (read-only).';

    public function handle(TemuApiService $temu): int
    {
        $gid = trim((string) $this->argument('goods_id'));
        if (! $temu->isConfigured()) {
            $this->error('Temu credentials are not configured on this environment (run this on the server).');

            return self::FAILURE;
        }

        // 1) Live goods list (includes removed ones here so we can see their raw status).
        $this->info('1) Seller Center goods list (temu.local.goods.list.retrieve, ALL)');
        $all = $temu->fetchLiveGoodsIds(true);
        if ($all === null) {
            $this->warn('   Could not read the full goods list from Temu.');
        } else {
            $present = array_key_exists($gid, $all);
            $this->line('   Goods in list: '.count($all).' | this goods listed: '.($present ? 'YES, status='.json_encode($all[$gid]) : 'NO'));
            $this->line('   Status values seen: '.json_encode(array_count_values(array_map(fn ($v) => json_encode($v), $all))));
            if ($present && TemuApiService::isRemovedGoodsStatus($all[$gid])) {
                $this->warn('   Status means REMOVED -> now hidden from /temu/ads.');
            }
        }

        // 2) goods.detail
        $this->info('2) goods.detail');
        $detail = $temu->callOpenApi('temu.local.goods.detail.retrieve', ['goodsId' => (int) $gid], 30);
        if (! ($detail['success'] ?? false)) {
            $detail = $temu->callOpenApi('bg.local.goods.detail.query', ['goodsId' => (int) $gid], 30);
        }
        if ($detail['success'] ?? false) {
            $r = (array) ($detail['result'] ?? []);
            $this->line('   answered: goodsName='.json_encode($r['goodsName'] ?? null).' outGoodsSn='.json_encode($r['outGoodsSn'] ?? null));
            $this->line('   status-like fields: '.json_encode(array_intersect_key($r, array_flip(['goodsStatus', 'status', 'subStatus', 'onSale', 'goodsOnSale']))));
        } else {
            $this->line('   NOT answered: '.($detail['errorMsg'] ?? $detail['message'] ?? 'unknown'));
        }

        // 3) lifecycle search (deleted / draft / incomplete / active)
        $this->info('3) bg.local.goods.list.query by search type');
        foreach ([6 => 'deleted', 5 => 'draft', 4 => 'incomplete', 1 => 'active'] as $type => $label) {
            $res = $temu->callOpenApi('bg.local.goods.list.query', [
                'goodsSearchType' => $type,
                'goodsStatusFilterType' => $type,
                'goodsIdList' => [(int) $gid],
                'pageSize' => 5,
                'pageNumber' => 1,
            ], 30);
            $hit = false;
            foreach ((array) ($res['result']['goodsList'] ?? []) as $g) {
                if (is_array($g) && (string) ($g['goodsId'] ?? '') === $gid) {
                    $hit = true;
                }
            }
            $this->line(sprintf('   %-11s %s', $label, $hit ? 'FOUND' : ($res['success'] ?? false ? '-' : 'call failed')));
        }

        // 4) what the app holds
        $this->info('4) App data');
        $rep = TemuAdsApiReport::query()->where('goods_id', $gid)->get(['period', 'sku', 'ad_status', 'fetched_at']);
        $this->line('   temu_ads_api_reports rows: '.$rep->count().($rep->isNotEmpty() ? ' | '.$rep->map(fn ($r) => $r->period.':'.$r->sku)->implode(', ') : ''));
        $this->line('   temu_metrics rows: '.TemuMetric::query()->where('goods_id', $gid)->count());
        $live = SyncTemuGoodsSkuNames::getLiveGoods();
        $this->line('   live-goods cache: '.(is_array($live) ? count($live).' goods; contains this goods: '.(array_key_exists($gid, $live) ? 'YES' : 'NO') : 'MISSING'));
        if (! is_array($live) || count($live) < 50) {
            $this->warn('   Cache missing/too small -> /temu/ads hides nothing. Run: php artisan temu:sync-goods-sku-names --live-only');
        }

        if ($this->option('refresh-live')) {
            $fresh = $temu->fetchLiveGoodsIds();
            if (is_array($fresh) && $fresh !== []) {
                SyncTemuGoodsSkuNames::putLiveGoods($fresh);
                $this->info('Live-goods cache refreshed: '.count($fresh).' goods; contains this goods: '.(array_key_exists($gid, $fresh) ? 'YES' : 'NO'));
            } else {
                $this->warn('Could not refresh the live-goods cache.');
            }
        }

        return self::SUCCESS;
    }
}
