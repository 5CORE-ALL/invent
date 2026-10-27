<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 1 "Ads Rules": L7 Sold slabs (per PARENT / ad, not per SKU) with a Target ROAS per slab.
 *
 * A "parent" is one Temu ad (goods_id) — its L7 sold is the sum of every SKU under it.
 * Auto run (when ON) is executed daily by `temu:auto-run-ads`:
 *   - slab Target ROAS > 0  -> ad is run (resumed if paused) and its target ROAS is pushed
 *   - slab Target ROAS = 0  -> ad is paused
 *   - slab Target ROAS blank -> ad is left untouched
 */
class TemuSoldRoasRuleService
{
    public const SLABS_KEY = 'temu_ads_sold_roas_slabs';
    public const AUTO_RUN_KEY = 'temu_ads_sold_roas_auto_run';
    public const HISTORY_KEY = 'temu_ads_sold_roas_history';
    public const PUSHED_KEY = 'temu_ads_sold_roas_pushed';

    /** Temu accepts a ROAS multiple up to 12. */
    public const MAX_ROAS = 12.0;

    /**
     * @return array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>
     */
    public function defaultSlabs(): array
    {
        return [
            ['sold_min' => 0, 'sold_max' => 0, 'target_roas' => null],
            ['sold_min' => 1, 'sold_max' => 1, 'target_roas' => null],
            ['sold_min' => 2, 'sold_max' => 2, 'target_roas' => null],
            ['sold_min' => 3, 'sold_max' => 3, 'target_roas' => null],
            ['sold_min' => 4, 'sold_max' => null, 'target_roas' => null],
        ];
    }

    /**
     * @return array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>
     */
    public function slabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::SLABS_KEY)->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];

        return $this->normalizeSlabs($raw) ?: $this->defaultSlabs();
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>
     */
    public function normalizeSlabs($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        if (count($raw) === 1 && is_string($raw[0] ?? null)) {
            $decoded = json_decode((string) $raw[0], true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $min = isset($item['sold_min']) && $item['sold_min'] !== '' && is_numeric($item['sold_min'])
                ? max(0, (int) $item['sold_min'])
                : 0;
            $max = isset($item['sold_max']) && $item['sold_max'] !== '' && is_numeric($item['sold_max'])
                ? max(0, (int) $item['sold_max'])
                : null;
            if ($max !== null && $max < $min) {
                $max = $min;
            }
            $roas = isset($item['target_roas']) && $item['target_roas'] !== '' && is_numeric($item['target_roas'])
                ? round((float) $item['target_roas'], 2)
                : null;
            $out[] = ['sold_min' => $min, 'sold_max' => $max, 'target_roas' => $roas];
        }

        return $out;
    }

    /**
     * @param  array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>  $slabs
     */
    public function saveSlabs(array $slabs): void
    {
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::SLABS_KEY],
            ['column_order' => [json_encode($slabs)]]
        );
    }

    public function autoRunEnabled(): bool
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::AUTO_RUN_KEY)->first();
        $v = is_array($row?->visibility) ? $row->visibility : [];

        return ! empty($v['enabled']);
    }

    public function setAutoRun(bool $enabled): void
    {
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::AUTO_RUN_KEY],
            ['column_order' => [], 'visibility' => ['enabled' => $enabled, 'updated_at' => now()->toDateTimeString()]]
        );
    }

    /**
     * @return array{at: string|null, summary: array<string, mixed>|null}
     */
    public function lastRun(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::AUTO_RUN_KEY)->first();
        $v = is_array($row?->visibility) ? $row->visibility : [];

        return [
            'at' => $v['last_run_at'] ?? null,
            'summary' => is_array($v['last_run'] ?? null) ? $v['last_run'] : null,
        ];
    }

    /**
     * Slab label used for display + as the history key (e.g. "0", "1-3", "4+").
     *
     * @param  array{sold_min: int, sold_max: int|null}  $slab
     */
    public static function slabLabel(array $slab): string
    {
        $min = (int) $slab['sold_min'];
        $max = $slab['sold_max'];
        if ($max === null) {
            return $min.'+';
        }

        return $min === (int) $max ? (string) $min : $min.'-'.$max;
    }

    /**
     * @param  array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>  $slabs
     */
    public static function slabIndexFor(int $sold, array $slabs): ?int
    {
        foreach ($slabs as $i => $slab) {
            if ($sold >= (int) $slab['sold_min'] && ($slab['sold_max'] === null || $sold <= (int) $slab['sold_max'])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Units sold per SKU in the last 7 complete Pacific days (Temu 1 `temu_orders`, cancelled excluded).
     * Keyed by ShopifySku::normalizeSkuForShopifyLookup(sku).
     *
     * @return array<string, int>
     */
    public static function soldL7BySku(): array
    {
        if (! Schema::hasTable('temu_orders')) {
            return [];
        }

        [$start, $end] = TemuShopifySalesService::channelMasterL7Window();
        $appTz = config('app.timezone');
        $start = $start->copy()->setTimezone($appTz);
        $end = $end->copy()->setTimezone($appTz);

        $out = [];
        TemuOrder::query()
            ->whereBetween('parent_order_time', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('order_status_text')
                    ->orWhereRaw('UPPER(order_status_text) NOT IN (?, ?)', ['CANCELED', 'CANCELLED']);
            })
            ->get(['id', 'ext_code', 'display_sku', 'quantity'])
            ->each(function ($o) use (&$out) {
                $ext = trim((string) ($o->ext_code ?? ''));
                $sku = $ext !== '' ? $ext : trim((string) ($o->display_sku ?? ''));
                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($key === '') {
                    return;
                }
                $out[$key] = ($out[$key] ?? 0) + (int) ($o->quantity ?? 0);
            });

        return $out;
    }

    /**
     * One entry per parent (ad / goods_id) with Inv > 0: L7 sold and Inv are the sum over every SKU under that ad.
     *
     * @return array<string, array{goods_id: string, parent: string, skus: int, sold_l7: int, inv: int, status: string}>
     */
    public function parents(): array
    {
        $sold = self::soldL7BySku();

        // Same ad set as the /temu/ads table (default L30 window), so counts match the page.
        $rows = TemuAdsApiReport::query()
            ->inLatestWindow('L30')
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['id', 'goods_id', 'sku', 'ad_status', 'period', 'start_ts']);

        /** @var array<string, array{goods_id: string, skus: array<string, true>, status: string, status_rank: int}> $groups */
        $groups = [];
        $rawSkus = [];
        foreach ($rows as $r) {
            $gid = trim((string) $r->goods_id);
            if ($gid === '') {
                continue;
            }
            if (! isset($groups[$gid])) {
                $groups[$gid] = ['goods_id' => $gid, 'skus' => [], 'status' => '', 'status_rank' => 0];
            }
            $key = ShopifySku::normalizeSkuForShopifyLookup(trim((string) ($r->sku ?? '')));
            if ($key !== '') {
                $groups[$gid]['skus'][$key] = true;
                $rawSku = trim((string) $r->sku);
                $rawSkus[$rawSku] = true;
                $stripped = rtrim($rawSku, '.');
                if ($stripped !== '') {
                    $rawSkus[$stripped] = true;
                }
            }
            $status = trim((string) ($r->ad_status ?? ''));
            $rank = match ($status) {
                'Active' => 3,
                'Inactive', 'Paused' => 2,
                '' => 0,
                default => 1,
            };
            if ($rank > $groups[$gid]['status_rank']) {
                $groups[$gid]['status'] = $status;
                $groups[$gid]['status_rank'] = $rank;
            }
        }

        // Inventory per SKU (Shopify inv) — same source as the Inv column on /temu/ads.
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku(array_keys($rawSkus), false);

        $out = [];
        foreach ($groups as $gid => $g) {
            $total = 0;
            $inv = 0;
            foreach (array_keys($g['skus']) as $k) {
                $total += (int) ($sold[$k] ?? 0);
                $inv += (int) (($shopifyByNorm[$k] ?? null)?->inv ?? 0);
            }
            // Only parents that have stock.
            if ($inv <= 0) {
                continue;
            }
            $out[$gid] = [
                'goods_id' => $gid,
                'parent' => '',
                'skus' => count($g['skus']),
                'sold_l7' => $total,
                'inv' => $inv,
                'status' => $g['status'],
            ];
        }

        return $out;
    }

    /**
     * Parents per slab (+ total), using the SAVED slabs.
     *
     * @param  array<int, array{sold_min: int, sold_max: int|null, target_roas: float|null}>  $slabs
     * @param  array<string, array{sold_l7: int}>  $parents
     * @return array{labels: array<int, string>, counts: array<int, int>, total: int}
     */
    public function counts(array $slabs, array $parents): array
    {
        $counts = array_fill(0, count($slabs), 0);
        foreach ($parents as $p) {
            $i = self::slabIndexFor((int) $p['sold_l7'], $slabs);
            if ($i !== null) {
                $counts[$i]++;
            }
        }

        return [
            'labels' => array_map([self::class, 'slabLabel'], $slabs),
            'counts' => $counts,
            'total' => count($parents),
        ];
    }

    /**
     * Store today's per-slab parent counts for the colour history dot.
     *
     * @param  array{labels: array<int, string>, counts: array<int, int>, total: int}  $counts
     */
    public function snapshot(array $counts): void
    {
        $row = ChannelTabulatorColumnSetting::query()->firstOrNew(['channel_name' => self::HISTORY_KEY]);
        $hist = is_array($row->visibility) ? $row->visibility : [];
        $today = now()->toDateString();
        $day = ['total' => (int) $counts['total']];
        foreach ($counts['labels'] as $i => $label) {
            $day['slab:'.$label] = (int) ($counts['counts'][$i] ?? 0);
        }
        $hist[$today] = $day;
        ksort($hist);
        if (count($hist) > 180) {
            $hist = array_slice($hist, -180, 180, true);
        }
        $row->visibility = $hist;
        if ($row->column_order === null) {
            $row->column_order = [];
        }
        $row->save();
    }

    /**
     * @return array<int, array{date: string, value: float}>
     */
    public function history(string $metric, int $days): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::HISTORY_KEY)->first();
        $hist = is_array($row?->visibility) ? $row->visibility : [];
        ksort($hist);
        $from = now()->subDays($days - 1)->toDateString();
        $data = [];
        foreach ($hist as $date => $metrics) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $from || ! is_array($metrics)) {
                continue;
            }
            $data[] = ['date' => date('M d', strtotime($date)), 'value' => (float) ($metrics[$metric] ?? 0)];
        }

        return $data;
    }

    /**
     * Daily Auto run: check each parent's L7 sold, find its slab and push run/pause/ROAS to Temu.
     *
     * @param  callable(int,int):void|null  $onEach
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false, ?callable $onEach = null): array
    {
        $slabs = $this->slabs();
        $parents = $this->parents();
        $counts = $this->counts($slabs, $parents);
        if (! $dryRun) {
            $this->snapshot($counts);
        }

        $stats = [
            'parents' => count($parents),
            'matched' => 0,
            'resumed' => 0,
            'paused' => 0,
            'roas_set' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'actions' => [],
            'errors' => [],
        ];

        $pushedRow = ChannelTabulatorColumnSetting::query()->firstOrNew(['channel_name' => self::PUSHED_KEY]);
        $pushedMap = is_array($pushedRow->visibility) ? $pushedRow->visibility : [];
        $api = $dryRun ? null : app(TemuApiService::class);

        $work = [];
        foreach ($parents as $p) {
            if (! in_array($p['status'], ['Active', 'Inactive', 'Paused'], true)) {
                $stats['skipped']++;

                continue;
            }
            $i = self::slabIndexFor((int) $p['sold_l7'], $slabs);
            $roas = $i === null ? null : $slabs[$i]['target_roas'];
            if ($i === null || $roas === null) {
                $stats['skipped']++;

                continue;
            }
            $work[] = [$p, $i, (float) $roas];
        }

        $total = count($work);
        $done = 0;
        foreach ($work as [$p, $i, $roas]) {
            $stats['matched']++;
            $gid = $p['goods_id'];
            $isActive = $p['status'] === 'Active';
            $label = self::slabLabel($slabs[$i]);
            $action = [];

            if ($roas <= 0) {
                if ($isActive) {
                    $action[] = 'pause';
                }
            } else {
                $target = min(self::MAX_ROAS, max(0.1, round($roas, 2)));
                if (! $isActive) {
                    $action[] = 'resume';
                }
                if (! isset($pushedMap[$gid]) || abs((float) $pushedMap[$gid] - $target) > 0.0001) {
                    $action[] = 'roas:'.$target;
                }
            }

            if ($action === []) {
                $stats['unchanged']++;
            } elseif ($dryRun) {
                $stats['actions'][] = ['goods_id' => $gid, 'sold_l7' => $p['sold_l7'], 'slab' => $label, 'action' => implode('+', $action)];
            } else {
                $ok = true;
                foreach ($action as $a) {
                    $res = match (true) {
                        $a === 'pause' => $api->pauseAd($gid),
                        $a === 'resume' => $api->resumeAd($gid),
                        default => $api->modifyAdRoas($gid, (float) substr($a, 5)),
                    };
                    if (! ($res['ok'] ?? false)) {
                        $ok = false;
                        $stats['failed']++;
                        $stats['errors'][] = $gid.': '.$a.' — '.($res['error_msg'] ?? 'failed');
                        Log::warning('temu:auto-run-ads failed', ['goods_id' => $gid, 'action' => $a, 'error' => $res['error_msg'] ?? null]);
                        break;
                    }
                    if ($a === 'pause') {
                        $stats['paused']++;
                        TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => 'Inactive']);
                    } elseif ($a === 'resume') {
                        $stats['resumed']++;
                        TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => 'Active']);
                    } else {
                        $stats['roas_set']++;
                        $pushedMap[$gid] = (float) substr($a, 5);
                    }
                }
                $stats['actions'][] = ['goods_id' => $gid, 'sold_l7' => $p['sold_l7'], 'slab' => $label, 'action' => implode('+', $action), 'ok' => $ok];
            }

            $done++;
            if ($onEach) {
                $onEach($done, $total);
            }
        }

        if (! $dryRun) {
            $pushedRow->visibility = $pushedMap;
            if ($pushedRow->column_order === null) {
                $pushedRow->column_order = [];
            }
            $pushedRow->save();

            $row = ChannelTabulatorColumnSetting::query()->firstOrNew(['channel_name' => self::AUTO_RUN_KEY]);
            $v = is_array($row->visibility) ? $row->visibility : [];
            $v['last_run_at'] = now()->toDateTimeString();
            $v['last_run'] = collect($stats)->only(['parents', 'matched', 'resumed', 'paused', 'roas_set', 'unchanged', 'skipped', 'failed'])->all();
            $row->visibility = $v;
            if ($row->column_order === null) {
                $row->column_order = [];
            }
            $row->save();
        }

        return $stats;
    }
}
