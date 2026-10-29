<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 1 "Ads Rules": L7 Sold slabs (per PARENT / ad, not per SKU) with a Target ROAS per slab.
 *
 * A "parent" is one Temu ad (goods_id) with 2+ different SKU IDs — its L7 sold is the sum of every SKU under it.
 * Single-SKU goods are NOT parents: they are ignored by the counts and by Auto run.
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
    public const RUN_LOCK_KEY = 'temu_ads_sold_roas_run_lock';

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
     * @return array{at: string|null, summary: array<string, mixed>|null, errors: array<int, string>}
     */
    public function lastRun(): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::AUTO_RUN_KEY)->first();
        $v = is_array($row?->visibility) ? $row->visibility : [];

        return [
            'at' => $v['last_run_at'] ?? null,
            'summary' => is_array($v['last_run'] ?? null) ? $v['last_run'] : null,
            'errors' => is_array($v['last_run_errors'] ?? null) ? $v['last_run_errors'] : [],
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
        return self::soldBySkuForWindow(TemuShopifySalesService::channelMasterL7Window());
    }

    /**
     * Units sold per SKU in the Temu Channel Master L30 window (same source as {@see soldL7BySku()}).
     *
     * @return array<string, int>
     */
    public static function soldL30BySku(): array
    {
        return self::soldBySkuForWindow(TemuShopifySalesService::channelMasterL30Window());
    }

    /**
     * @param  array{0: \Carbon\Carbon, 1: \Carbon\Carbon}  $window
     * @return array<string, int>
     */
    private static function soldBySkuForWindow(array $window): array
    {
        if (! Schema::hasTable('temu_orders')) {
            return [];
        }

        [$start, $end] = $window;
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
     * One entry per PARENT (ad / goods_id with 2+ SKU IDs) with Inv > 0: L7 sold and Inv are the sum over every SKU under that ad.
     *
     * @return array<string, array{goods_id: string, parent: string, skus: int, sold_l7: int, inv: int, status: string}>
     */
    public function parents(): array
    {
        $sold = self::soldL7BySku();

        // Same ad set as the /temu/ads table (default L30 window), so counts match the page.
        $hasSkuId = Schema::hasColumn((new TemuAdsApiReport)->getTable(), 'sku_id');
        $columns = ['id', 'goods_id', 'sku', 'ad_status', 'period', 'start_ts'];
        if ($hasSkuId) {
            $columns[] = 'sku_id';
        }
        $rows = TemuAdsApiReport::query()
            ->inLatestWindow('L30')
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get($columns);

        /** @var array<string, array{goods_id: string, skus: array<string, true>, sku_ids: array<string, true>, status: string, status_rank: int}> $groups */
        $groups = [];
        $rawSkus = [];
        foreach ($rows as $r) {
            $gid = trim((string) $r->goods_id);
            if ($gid === '') {
                continue;
            }
            if (! isset($groups[$gid])) {
                $groups[$gid] = ['goods_id' => $gid, 'skus' => [], 'sku_ids' => [], 'status' => '', 'status_rank' => 0];
            }
            $skuId = trim((string) ($r->sku_id ?? ''));
            if ($skuId !== '') {
                $groups[$gid]['sku_ids'][$skuId] = true;
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

        // Parent = what Temu Seller Center shows (all variations the goods lists), not only the SKUs that had ad traffic.
        $temuMap = \App\Console\Commands\SyncTemuGoodsSkuNames::getSkuNames();
        if (is_array($temuMap)) {
            foreach ($groups as $gid => &$g) {
                $temuSkus = $temuMap[$gid]['skus'] ?? null;
                if (! is_array($temuSkus) || $g['sku_ids'] === []) {
                    continue;
                }
                foreach ($temuSkus as $skuId => $name) {
                    $name = trim((string) $name);
                    if ($name === '') {
                        continue;
                    }
                    $g['sku_ids'][(string) $skuId] = true;
                    $key = ShopifySku::normalizeSkuForShopifyLookup($name);
                    if ($key !== '') {
                        $g['skus'][$key] = true;
                        $rawSkus[$name] = true;
                        $stripped = rtrim($name, '.');
                        if ($stripped !== '') {
                            $rawSkus[$stripped] = true;
                        }
                    }
                }
            }
            unset($g);
        }

        // Inventory per SKU (Shopify inv) — same source as the Inv column on /temu/ads.
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku(array_keys($rawSkus), false);

        $out = [];
        foreach ($groups as $gid => $g) {
            // Rules apply to PARENT goods only (one goods_id with 2+ different SKU IDs = the "Multi SKU" rows on
            // /temu/ads). A goods with a single SKU is never touched by Ads Rules / Auto run.
            if (count($g['sku_ids']) < 2) {
                continue;
            }
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
     * Create the missing Temu ad for every PARENT that matches a slab with Target ROAS > 0 but has no ad yet.
     * The ad is created with that slab's Target ROAS (so nothing else needs pushing for it).
     *
     * Safety rules:
     *  - live check with Temu first: only goods Temu says have "No ad" are created (stale statuses are fixed instead)
     *  - goods Temu rejected earlier are skipped for $retryDays days (the listing must be fixed first);
     *    a "daily budget must be between N and ..." reject is retried at N, but only if N <= $maxBudget
     *  - at most $limit ads per run (each ad is a live daily budget)
     *
     * @param  callable(int,int):void|null  $onEach
     * @return array<string, mixed>
     */
    public function createMissingAds(float $budget = 10.0, float $maxBudget = 10.0, int $limit = 50, int $retryDays = 7, bool $dryRun = false, ?callable $onEach = null): array
    {
        $maxBudget = max($budget, $maxBudget);
        $slabs = $this->slabs();
        $parents = $this->parents();

        $stats = [
            'parents' => count($parents),
            'candidates' => 0,
            'created' => 0,
            'failed' => 0,
            'skipped' => 0,
            'skipped_rejected' => 0,
            'skipped_budget' => 0,
            'already_has_ad' => 0,
            'unverified' => 0,
            'actions' => [],
            'errors' => [],
        ];

        $rejects = [];
        if (Schema::hasColumn((new TemuAdsApiReport)->getTable(), 'ad_create_reject')) {
            TemuAdsApiReport::query()
                ->whereNotNull('ad_create_reject')->where('ad_create_reject', '!=', '')
                ->get(['goods_id', 'ad_create_reject', 'ad_create_reject_at'])
                ->each(function ($r) use (&$rejects) {
                    $rejects[(string) $r->goods_id] = ['msg' => (string) $r->ad_create_reject, 'at' => $r->ad_create_reject_at];
                });
        }

        $candidates = [];
        foreach ($parents as $p) {
            if (($p['status'] ?? '') !== 'No ad') {
                continue;
            }
            $i = self::slabIndexFor((int) $p['sold_l7'], $slabs);
            $roas = $i === null ? null : $slabs[$i]['target_roas'];
            if ($i === null || $roas === null || (float) $roas <= 0) {
                $stats['skipped']++;

                continue;
            }
            $gid = (string) $p['goods_id'];
            $useBudget = $budget;
            if (isset($rejects[$gid])) {
                $msg = $rejects[$gid]['msg'];
                if (preg_match('/between\s+(\d+(?:\.\d+)?)\s+and/i', $msg, $m)) {
                    if ((float) $m[1] > $maxBudget) {
                        $stats['skipped_budget']++;

                        continue;
                    }
                    $useBudget = max($budget, (float) $m[1]);
                } else {
                    $at = $rejects[$gid]['at'];
                    if ($at !== null && $at->greaterThan(now()->subDays(max(0, $retryDays)))) {
                        $stats['skipped_rejected']++;

                        continue;
                    }
                }
            }
            $candidates[] = ['p' => $p, 'roas' => min(self::MAX_ROAS, max(0.1, round((float) $roas, 2))), 'budget' => $useBudget, 'slab' => self::slabLabel($slabs[$i])];
        }

        usort($candidates, fn ($a, $b) => $b['p']['inv'] <=> $a['p']['inv']);
        $stats['candidates'] = count($candidates);
        if ($candidates === []) {
            return $stats;
        }

        $api = app(TemuApiService::class);
        $rejectSvc = $dryRun ? null : app(TemuAdCreateRejectService::class);

        // Ask Temu which of these really have no ad right now.
        $live = $api->queryAdStatuses(array_map(fn ($c) => (string) $c['p']['goods_id'], $candidates));
        $todo = [];
        foreach ($candidates as $c) {
            $gid = (string) $c['p']['goods_id'];
            $ls = $live['statuses'][$gid] ?? null;
            if ($ls === null) {
                $stats['unverified']++;

                continue;
            }
            if ($ls !== 'No ad') {
                $stats['already_has_ad']++;
                if (! $dryRun) {
                    TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => $ls]);
                }

                continue;
            }
            $todo[] = $c;
        }
        $todo = array_slice($todo, 0, max(1, $limit));

        $pushedRow = ChannelTabulatorColumnSetting::query()->firstOrNew(['channel_name' => self::PUSHED_KEY]);
        $pushedMap = is_array($pushedRow->visibility) ? $pushedRow->visibility : [];
        $created = [];
        $total = count($todo);
        foreach ($todo as $n => $c) {
            $gid = (string) $c['p']['goods_id'];
            $label = $c['slab'];
            if ($dryRun) {
                $stats['actions'][] = ['goods_id' => $gid, 'sold_l7' => $c['p']['sold_l7'], 'slab' => $label, 'action' => 'create budget $'.$c['budget'].' ROAS '.$c['roas']];

                continue;
            }
            if ($n > 0) {
                usleep(250000);
            }
            $useBudget = $c['budget'];
            $res = $api->createAd($gid, $useBudget, $c['roas']);
            if (! ($res['ok'] ?? false) && preg_match('/between\s+(\d+(?:\.\d+)?)\s+and/i', (string) ($res['error_msg'] ?? ''), $m)
                && (float) $m[1] <= $maxBudget) {
                $useBudget = max($useBudget, (float) $m[1]);
                $res = $api->createAd($gid, $useBudget, $c['roas']);
            }
            if ($res['ok'] ?? false) {
                $rejectSvc->clearReject($gid);
                $created[] = $gid;
                $pushedMap[$gid] = $c['roas'];
                $stats['created']++;
                $stats['actions'][] = ['goods_id' => $gid, 'sold_l7' => $c['p']['sold_l7'], 'slab' => $label, 'action' => 'created $'.$useBudget.' ROAS '.$c['roas'], 'ok' => true];
            } else {
                $msg = (string) ($res['error_msg'] ?? 'unknown error');
                $rejectSvc->handleFailedCreate($gid, null, $msg, $res['error_code'] ?? null);
                $stats['failed']++;
                $stats['errors'][] = $gid.': create — '.$msg;
                $stats['actions'][] = ['goods_id' => $gid, 'sold_l7' => $c['p']['sold_l7'], 'slab' => $label, 'action' => 'create', 'ok' => false];
                Log::warning('temu:auto-create-ads failed', ['goods_id' => $gid, 'error' => $msg]);
            }
            if ($onEach) {
                $onEach($n + 1, $total);
            }
        }

        if (! $dryRun && $created !== []) {
            $after = $api->queryAdStatuses($created);
            foreach ($created as $gid) {
                TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => $after['statuses'][$gid] ?? 'Inactive']);
            }
            \App\Http\Controllers\Campaigns\Temu1MissingAdsController::forgetMissingTotalCache();
            $pushedRow->visibility = $pushedMap;
            if ($pushedRow->column_order === null) {
                $pushedRow->column_order = [];
            }
            $pushedRow->save();
        }

        return $stats;
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
            'no_ad' => 0,
            'unverified' => 0,
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

        // Never trust the stored status: ask Temu which of these goods really have an ad right now.
        // No ad / deleted -> nothing to run, pause or modify (Temu answers "bad query params"). Fix the stale status instead.
        if ($work !== []) {
            $liveQuery = app(TemuApiService::class)->queryAdStatuses(array_map(fn ($w) => $w[0]['goods_id'], $work));
            $verified = [];
            foreach ($work as [$p, $i, $roas]) {
                $gid = $p['goods_id'];
                $ls = $liveQuery['statuses'][$gid] ?? null;
                if ($ls === null) {
                    $stats['unverified']++; // Temu lookup failed: do nothing for this goods
                    $stats['skipped']++;

                    continue;
                }
                if (! in_array($ls, ['Active', 'Inactive', 'Paused'], true)) {
                    $stats['no_ad']++;
                    $stats['skipped']++;
                    if (! $dryRun && $ls !== $p['status']) {
                        TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => $ls]);
                    }

                    continue;
                }
                if (! $dryRun && $ls !== $p['status']) {
                    TemuAdsApiReport::query()->where('goods_id', $gid)->update(['ad_status' => $ls]);
                }
                $p['status'] = $ls;
                $verified[] = [$p, $i, $roas];
            }
            $work = $verified;
        }

        $touched = [];
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
                        $touched[$gid] = true;
                    } elseif ($a === 'resume') {
                        $stats['resumed']++;
                        $touched[$gid] = true;
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

        if (! $dryRun && $touched !== []) {
            // Store what Temu reports after our calls — not what we assume.
            $after = app(TemuApiService::class)->queryAdStatuses(array_keys($touched));
            foreach ($after['statuses'] as $gid => $st) {
                TemuAdsApiReport::query()->where('goods_id', (string) $gid)->update(['ad_status' => $st]);
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
            $v['last_run'] = collect($stats)->only(['parents', 'matched', 'resumed', 'paused', 'roas_set', 'unchanged', 'skipped', 'no_ad', 'unverified', 'failed'])->all();
            $v['last_run_errors'] = array_slice($stats['errors'], 0, 10);
            $row->visibility = $v;
            if ($row->column_order === null) {
                $row->column_order = [];
            }
            $row->save();
        }

        return $stats;
    }
}
