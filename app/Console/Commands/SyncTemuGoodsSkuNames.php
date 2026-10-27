<?php

namespace App\Console\Commands;

use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use App\Services\TemuApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pull the exact SKU list Temu holds for every goods_id (goods.detail skuList + outGoodsSn)
 * and cache it so /temu/ads can show only SKU names Temu itself reports.
 * Also writes an audit of how our stored SKUs differ from Temu.
 */
class SyncTemuGoodsSkuNames extends Command
{
    public const CACHE_KEY = 'temu_goods_sku_names_v1';
    public const LIVE_GOODS_CACHE_KEY = 'temu_live_goods_ids_v1';

    protected $signature = 'temu:sync-goods-sku-names
                            {--goods-id= : Only this goods ID}
                            {--live-only : Only refresh the list of goods Temu still lists (fast)}';

    protected $description = 'Cache exact Temu SKU names per goods_id for /temu/ads and write an audit of mismatches';

    public function handle(TemuApiService $api): int
    {
        $only = $this->option('goods-id') ? trim((string) $this->option('goods-id')) : null;

        // Goods Temu still lists in Seller Center. Anything else is removed/ended there.
        $live = $api->fetchLiveGoodsIds();
        if (is_array($live) && $live !== []) {
            Cache::forever(self::LIVE_GOODS_CACHE_KEY, $live);
            $this->info('Temu lists '.count($live).' goods in Seller Center.');
        } else {
            $this->warn('Could not read Temu goods list; keeping previous live-goods cache.');
        }
        if ($this->option('live-only')) {
            return is_array($live) && $live !== [] ? 0 : 1;
        }

        $goodsIds = $only
            ? [$only]
            : TemuAdsApiReport::query()->whereNotNull('goods_id')->where('goods_id', '!=', '')
                ->distinct()->pluck('goods_id')
                ->merge(TemuMetric::query()->whereNotNull('goods_id')->where('goods_id', '!=', '')->distinct()->pluck('goods_id'))
                ->map(fn ($g) => (string) $g)->unique()->values()->all();

        if ($goodsIds === []) {
            $this->warn('No goods IDs found.');

            return 1;
        }

        $existing = Cache::get(self::CACHE_KEY, []);
        $map = is_array($existing) ? $existing : [];

        $this->info('Fetching Temu SKU lists for '.count($goodsIds).' goods...');
        $bar = $this->output->createProgressBar(count($goodsIds));
        $bar->start();

        $ok = 0;
        $fail = [];
        foreach ($goodsIds as $i => $gid) {
            $info = $api->fetchGoodsSkuInfo($gid);
            if ($info === null) {
                $fail[] = $gid;
            } else {
                $map[$gid] = $info;
                $ok++;
            }
            $bar->advance();
            if ($ok > 0 && $ok % 50 === 0) {
                Cache::forever(self::CACHE_KEY, $map);
            }
            usleep(150000);
        }
        $bar->finish();
        $this->newLine();

        Cache::forever(self::CACHE_KEY, $map);

        $audit = $this->buildAudit($goodsIds, $map);
        Storage::disk('local')->put('temu_sku_audit.json', json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Done. Temu answered {$ok}/".count($goodsIds).' goods; '.count($fail).' failed.');
        $this->line('Our SKU rows NOT on Temu (stale):        '.count($audit['stale']));
        $this->line('Our SKU rows Temu has with EMPTY name:   '.count($audit['unnamed']));
        $this->line('Our SKU rows whose name differs on Temu: '.count($audit['renamed']));
        $this->line('Audit file: storage/app/temu_sku_audit.json');
        Log::info('temu:sync-goods-sku-names finished', [
            'ok' => $ok, 'fail' => count($fail),
            'stale' => count($audit['stale']), 'unnamed' => count($audit['unnamed']), 'renamed' => count($audit['renamed']),
        ]);

        return $ok > 0 ? 0 : 1;
    }

    /**
     * @param  list<string>  $goodsIds
     * @param  array<string, array{out_goods_sn: string, skus: array<string, string>}>  $map
     * @return array{stale: list<array>, unnamed: list<array>, renamed: list<array>}
     */
    private function buildAudit(array $goodsIds, array $map): array
    {
        $audit = ['stale' => [], 'unnamed' => [], 'renamed' => []];

        $rows = TemuAdsApiReport::query()
            ->where('period', 'L30')
            ->whereIn('goods_id', $goodsIds)
            ->get(['goods_id', 'sku', 'sku_id']);

        foreach ($rows as $r) {
            $gid = (string) $r->goods_id;
            $skuId = trim((string) $r->sku_id);
            if ($skuId === '' || ! isset($map[$gid])) {
                continue;
            }
            $temuSkus = $map[$gid]['skus'] ?? [];
            $entry = ['goods_id' => $gid, 'sku_id' => $skuId, 'our_sku' => (string) $r->sku];
            if (! array_key_exists($skuId, $temuSkus)) {
                $audit['stale'][] = $entry;
            } elseif ($temuSkus[$skuId] === '') {
                $audit['unnamed'][] = $entry;
            } elseif ($temuSkus[$skuId] !== (string) $r->sku) {
                $entry['temu_sku'] = $temuSkus[$skuId];
                $audit['renamed'][] = $entry;
            }
        }

        return $audit;
    }
}
