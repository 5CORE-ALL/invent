<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto Cron for /temu/ads: only push rows whose Active/Pause status
 * changes from the L7 click limit (and T ROAS for pause).
 */
class TemuAdsAutoPauseService
{
    public function __construct(protected TemuApiService $temuApi)
    {
    }

    public function l7ClicksRedBelow(): int
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_l7_clicks_red_below')
            ->first();
        $n = isset($row->column_order[0]) ? (int) $row->column_order[0] : 70;

        return $n >= 0 ? $n : 70;
    }

    public function targetRoasBidding(): float
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_target_roas_bidding')
            ->first();
        $n = isset($row->column_order[0]) ? (float) $row->column_order[0] : 8.0;

        return $n >= 0.1 ? round($n, 1) : 8.0;
    }

    /**
     * T ROAS for a row: same clicks slabs as the T ROAS column.
     */
    public function targetRoasForClicks(int $clicks): float
    {
        $n = max(0, $clicks);
        foreach ($this->roasRuleSlabs() as $slab) {
            $min = $slab['clicks_min'];
            $max = $slab['clicks_max'];
            if ($min === null && $max === null) {
                continue;
            }
            if ($min !== null && $n < $min) {
                continue;
            }
            if ($max !== null && $n > $max) {
                continue;
            }
            if ($slab['target_roas'] !== null) {
                return (float) $slab['target_roas'];
            }
        }

        return $this->targetRoasBidding();
    }

    /**
     * @return array<int, array{clicks_min: int|null, clicks_max: int|null, target_roas: float|null}>
     */
    public function roasRuleSlabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_roas_rule_slabs')
            ->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];
        $slabs = $this->normalizeRoasRuleSlabs($raw);

        return $slabs !== [] ? $slabs : $this->defaultRoasRuleSlabs();
    }

    /**
     * @return array<int, array{clicks_min: int|null, clicks_max: int|null, target_roas: float|null}>
     */
    private function defaultRoasRuleSlabs(): array
    {
        return [
            ['clicks_min' => 0, 'clicks_max' => 0, 'target_roas' => 4.0],
            ['clicks_min' => 1, 'clicks_max' => 5, 'target_roas' => 5.0],
            ['clicks_min' => 6, 'clicks_max' => 9, 'target_roas' => 10.0],
            ['clicks_min' => 10, 'clicks_max' => null, 'target_roas' => 12.0],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{clicks_min: int|null, clicks_max: int|null, target_roas: float|null}>
     */
    private function normalizeRoasRuleSlabs($raw): array
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
            $clicksMin = $this->clicksOrNull($item['clicks_min'] ?? $item['spend_min'] ?? $item['min'] ?? null);
            $clicksMax = $this->clicksOrNull($item['clicks_max'] ?? $item['spend_max'] ?? $item['max'] ?? null);
            $targetRoas = null;
            if (isset($item['target_roas']) && $item['target_roas'] !== '' && is_numeric($item['target_roas'])) {
                $targetRoas = round((float) $item['target_roas'], 2);
            }
            if ($clicksMin === null && $clicksMax === null && $targetRoas === null) {
                continue;
            }
            if ($clicksMax !== null && $clicksMin !== null && $clicksMax < $clicksMin) {
                $clicksMax = $clicksMin;
            }
            $out[] = [
                'clicks_min' => $clicksMin,
                'clicks_max' => $clicksMax,
                'target_roas' => $targetRoas,
            ];
        }

        return $out;
    }

    private function clicksOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return $n < 0 ? null : $n;
    }

    /**
     * Daily auto-pause cron (after L7 fetch + 16:10 IST). Default ON.
     */
    public function cronEnabled(): bool
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_auto_pause_cron')
            ->first();
        if (! $row) {
            return true;
        }
        $raw = is_array($row->column_order) ? ($row->column_order[0] ?? '1') : '1';

        return ! in_array(strtolower((string) $raw), ['0', 'false', 'off', 'paused'], true);
    }

    public function setCronEnabled(bool $enabled): bool
    {
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => 'temu_ads_auto_pause_cron'],
            ['column_order' => [$enabled ? '1' : '0']]
        );

        Log::info('TemuAdsAutoPauseService::setCronEnabled', ['enabled' => $enabled]);

        return $enabled;
    }

    /**
     * @return array<int, array{min: int, max: int|null, action: string}>
     */
    public function pauseRunSlabs(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_pause_run_slabs')
            ->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];
        $slabs = $this->normalizePauseRunSlabs($raw);

        return $slabs !== [] ? $slabs : [
            ['min' => 0, 'max' => 69, 'action' => 'run'],
            ['min' => 70, 'max' => null, 'action' => 'pause'],
        ];
    }

    public function pauseRunInvZero(): bool
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu_ads_pause_run_inv_zero')
            ->first();
        if (! $row) {
            return true;
        }
        $raw = is_array($row->column_order) ? ($row->column_order[0] ?? '1') : '1';

        return ! in_array(strtolower((string) $raw), ['0', 'false', 'off'], true);
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{min: int, max: int|null, action: string}>
     */
    private function normalizePauseRunSlabs($raw): array
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
            $min = max(0, (int) ($item['min'] ?? 0));
            $max = array_key_exists('max', $item) && $item['max'] !== null && $item['max'] !== ''
                ? (int) $item['max']
                : null;
            if ($max !== null && $max < $min) {
                $max = $min;
            }
            $out[] = [
                'min' => $min,
                'max' => $max,
                'action' => (($item['action'] ?? '') === 'run') ? 'run' : 'pause',
            ];
        }

        usort($out, static fn (array $a, array $b) => $a['min'] <=> $b['min']);

        return $out;
    }

    public function actionFromPauseRunSlabs(int $clicksL7, int $inv = 0): string
    {
        if ($this->pauseRunInvZero() && $inv <= 0) {
            return 'pause';
        }
        foreach ($this->pauseRunSlabs() as $slab) {
            $min = (int) ($slab['min'] ?? 0);
            $max = $slab['max'] ?? null;
            if ($clicksL7 >= $min && ($max === null || $clicksL7 <= (int) $max)) {
                return (($slab['action'] ?? '') === 'run') ? 'run' : 'pause';
            }
        }

        return $clicksL7 < $this->l7ClicksRedBelow() ? 'run' : 'pause';
    }

    /**
     * Persist last Pause/Run push and a short history on every period row for the goods.
     *
     * @return array<int, array{at: string, action: string, ok: bool, message: string}>
     */
    public function recordPauseRunPush(string $goodsId, string $action, bool $ok, ?string $message = null): array
    {
        if ($goodsId === '' || ! Schema::hasColumn('temu_ads_api_reports', 'pause_run_ok')) {
            return [];
        }

        $entry = [
            'at' => now()->toDateTimeString(),
            'action' => $action === 'run' ? 'run' : 'pause',
            'ok' => $ok,
            'message' => substr(trim((string) $message), 0, 240),
        ];

        $history = [];
        TemuAdsApiReport::query()->where('goods_id', $goodsId)->orderBy('id')->each(function (TemuAdsApiReport $row) use ($entry, $ok, &$history) {
            $hist = is_array($row->pause_run_history) ? $row->pause_run_history : [];
            array_unshift($hist, $entry);
            $hist = array_slice($hist, 0, 20);
            $history = $hist;
            $row->pause_run_ok = $ok;
            $row->pause_run_error = $ok ? null : ($entry['message'] !== '' ? $entry['message'] : 'Temu update failed');
            $row->pause_run_at = now();
            $row->pause_run_history = $hist;
            $row->save();
        });

        return $history;
    }

    /**
     * Rows whose Pause/Run Rule desired status differs from current Active/Pause.
     * Already-correct rows are omitted so cron does not push them.
     *
     * @return array<int, array{goods_id: string, sku: mixed, clicks_l7: int, roas: float, t_roas: float, ad_spend: float, status: string|null, action: string}>
     */
    public function matchingAds(): array
    {
        $byGoods = TemuAdsApiReport::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['id', 'goods_id', 'sku', 'period', 'clicks', 'roas', 'ad_spend', 'ad_status'])
            ->groupBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);

        $skus = $byGoods->map(function ($rows) {
            $row = $rows->firstWhere('period', 'L30') ?: $rows->first();

            return (string) ($row->sku ?? '');
        })->filter(fn ($s) => $s !== '')->unique()->values()->all();
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);

        $matches = [];
        foreach ($byGoods as $goodsId => $rows) {
            $status = $rows->first(
                fn (TemuAdsApiReport $r) => in_array($r->displayAdStatus(), ['Active', 'Inactive'], true)
            )?->displayAdStatus();
            if (! in_array($status, ['Active', 'Inactive'], true)) {
                continue;
            }

            $l7 = $rows->firstWhere('period', 'L7');
            if (! $l7) {
                continue;
            }

            $l30 = $rows->firstWhere('period', 'L30');
            $roasRow = $l30 ?: $l7;
            $l7Clicks = (int) ($l7->clicks ?? 0);
            $roas = (float) ($roasRow->roas ?? 0);
            $spend = (float) ($roasRow->ad_spend ?? 0);
            $clicks = (int) ($roasRow->clicks ?? 0);
            $tRoas = $this->targetRoasForClicks($clicks);
            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($roasRow->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;

            $desired = $this->actionFromPauseRunSlabs($l7Clicks, $inv);
            if ($tRoas <= 0) {
                $desired = 'pause';
            }
            if ($desired === 'run' && $status === 'Active') {
                continue;
            }
            if ($desired === 'pause' && $status === 'Inactive') {
                continue;
            }

            $matches[] = [
                'goods_id' => (string) $goodsId,
                'sku' => $roasRow->sku,
                'clicks_l7' => $l7Clicks,
                'roas' => $roas,
                't_roas' => $tRoas,
                'ad_spend' => $spend,
                'status' => $status,
                'action' => $desired,
            ];
        }

        return $matches;
    }

    /**
     * @return array{
     *   matched: int,
     *   paused: int,
     *   already: int,
     *   failed: int,
     *   dry_run: bool,
     *   l7_clicks_red_below: int,
     *   target_roas_bidding: float,
     *   paused_goods: array<int, array<string, mixed>>,
     *   failed_goods: array<int, array<string, mixed>>
     * }
     */
    public function pauseMatching(bool $dryRun = false, ?callable $onEach = null, ?array $onlyGoodsIds = null): array
    {
        $matches = $this->matchingAds();
        if ($onlyGoodsIds !== null && $onlyGoodsIds !== []) {
            $want = array_fill_keys(array_map('strval', $onlyGoodsIds), true);
            $matches = array_values(array_filter(
                $matches,
                static fn (array $row) => isset($want[(string) $row['goods_id']])
            ));
        }
        $paused = [];
        $resumed = [];
        $already = 0;
        $failed = [];
        $results = [];
        $total = count($matches);
        $liveStatuses = [];

        if (! $dryRun && $matches !== []) {
            $statusQuery = $this->temuApi->queryAdStatuses(array_column($matches, 'goods_id'));
            $liveStatuses = $statusQuery['statuses'] ?? [];
        }

        foreach ($matches as $index => $match) {
            $action = $match['action'] ?? 'pause';
            $wantActive = $action === 'run';
            if ($dryRun) {
                if ($wantActive) {
                    $resumed[] = $match;
                } else {
                    $paused[] = $match;
                }
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }

            $live = $liveStatuses[$match['goods_id']] ?? $match['status'];
            if (in_array($live, ['Deleted', 'No ad'], true)) {
                TemuAdsApiReport::where('goods_id', $match['goods_id'])
                    ->update(['ad_status' => $live]);
                $already++;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }
            if ($wantActive && $live === 'Active') {
                $already++;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }
            if (! $wantActive && $live === 'Inactive') {
                TemuAdsApiReport::where('goods_id', $match['goods_id'])
                    ->update(['ad_status' => 'Inactive']);
                $already++;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }

            $result = $wantActive
                ? $this->temuApi->resumeAd($match['goods_id'])
                : $this->temuApi->pauseAd($match['goods_id']);
            $ok = (bool) ($result['ok'] ?? false);
            $message = $ok
                ? (($action === 'run' ? 'Run' : 'Pause').' sent to Temu')
                : (string) ($result['error_msg'] ?? ($wantActive ? 'Run failed' : 'Pause failed'));
            $history = $this->recordPauseRunPush($match['goods_id'], $action, $ok, $message);
            $results[] = [
                'goods_id' => $match['goods_id'],
                'action' => $action,
                'ok' => $ok,
                'message' => $message,
                'pause_run_ok' => $ok,
                'pause_run_error' => $ok ? '' : $message,
                'pause_run_history' => $history,
            ];
            if ($ok) {
                TemuAdsApiReport::where('goods_id', $match['goods_id'])
                    ->update(['ad_status' => $wantActive ? 'Active' : 'Inactive']);
                if ($result['already'] ?? false) {
                    $already++;
                } elseif ($wantActive) {
                    $resumed[] = $match;
                } else {
                    $paused[] = $match;
                }
            } else {
                $failed[] = array_merge($match, [
                    'error' => $message,
                ]);
            }
            if ($onEach) {
                $onEach($index + 1, $total, $match, $result);
            }
        }

        $stats = [
            'matched' => count($matches),
            'paused' => count($paused),
            'resumed' => count($resumed),
            'already' => $already,
            'failed' => count($failed),
            'dry_run' => $dryRun,
            'l7_clicks_red_below' => $this->l7ClicksRedBelow(),
            'target_roas_bidding' => $this->targetRoasBidding(),
            'paused_goods' => $paused,
            'resumed_goods' => $resumed,
            'failed_goods' => $failed,
            'results' => $results,
        ];

        Log::info('TemuAdsAutoPauseService::pauseMatching', [
            'matched' => $stats['matched'],
            'paused' => $stats['paused'],
            'resumed' => $stats['resumed'],
            'already' => $already,
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
            'l7_at_or_above' => $stats['l7_clicks_red_below'],
        ]);

        return $stats;
    }

    /**
     * Ads whose Temu target differs from the T ROAS click slab.
     * Target 0 pauses an Active ad. Others get temu.searchrec.ad.modify status 5.
     *
     * @return array{
     *   checked: int,
     *   matched: int,
     *   pushed: int,
     *   paused: int,
     *   already: int,
     *   failed: int,
     *   dry_run: bool,
     *   skipped_lock: bool,
     *   pushed_goods: array<int, array<string, mixed>>,
     *   paused_goods: array<int, array<string, mixed>>,
     *   failed_goods: array<int, array<string, mixed>>
     * }
     */
    public function pushTargetRoas(bool $dryRun = false, ?callable $onEach = null, ?array $onlyGoodsIds = null): array
    {
        $empty = [
            'checked' => 0,
            'matched' => 0,
            'pushed' => 0,
            'paused' => 0,
            'already' => 0,
            'failed' => 0,
            'dry_run' => $dryRun,
            'skipped_lock' => false,
            'pushed_goods' => [],
            'paused_goods' => [],
            'failed_goods' => [],
        ];

        $lockKey = 'temu_push_target_roas_lock';
        if (! $dryRun && ! Cache::add($lockKey, 1, 1800)) {
            $empty['skipped_lock'] = true;
            Log::info('TemuAdsAutoPauseService::pushTargetRoas skipped — already running');

            return $empty;
        }

        try {
            return $this->pushTargetRoasUnlocked($dryRun, $onEach, $onlyGoodsIds);
        } finally {
            if (! $dryRun) {
                Cache::forget($lockKey);
            }
        }
    }

    /**
     * @param  array<int, string>|null  $onlyGoodsIds
     * @return array{
     *   checked: int,
     *   matched: int,
     *   pushed: int,
     *   paused: int,
     *   already: int,
     *   failed: int,
     *   dry_run: bool,
     *   skipped_lock: bool,
     *   pushed_goods: array<int, array<string, mixed>>,
     *   paused_goods: array<int, array<string, mixed>>,
     *   failed_goods: array<int, array<string, mixed>>
     * }
     */
    private function pushTargetRoasUnlocked(bool $dryRun, ?callable $onEach, ?array $onlyGoodsIds): array
    {
        $want = null;
        if ($onlyGoodsIds !== null && $onlyGoodsIds !== []) {
            $want = array_fill_keys(array_map('strval', $onlyGoodsIds), true);
        }

        $rows = TemuAdsApiReport::query()
            ->inLatestWindow('L30')
            ->whereIn('ad_status', ['Active', 'Inactive'])
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->orderBy('id')
            ->get(['id', 'goods_id', 'sku', 'clicks', 'ad_status', 'raw_response']);

        $byGoods = [];
        foreach ($rows as $row) {
            $gid = (string) $row->goods_id;
            if ($want !== null && ! isset($want[$gid])) {
                continue;
            }
            if (! isset($byGoods[$gid])) {
                $byGoods[$gid] = $row;
            }
        }

        $pending = [];
        $already = 0;
        foreach ($byGoods as $gid => $row) {
            $clicks = (int) ($row->clicks ?? 0);
            $target = round($this->targetRoasForClicks($clicks), 2);
            $stored = $this->storedTargetRoas($row);
            $status = (string) $row->ad_status;
            $action = $this->targetRoasAction($target, $stored, $status);
            if ($action === null) {
                $already++;
                continue;
            }
            $pending[] = [
                'goods_id' => $gid,
                'sku' => $row->sku,
                'clicks' => $clicks,
                'target_roas' => $action === 'pause' ? 0.0 : min(12.0, $target),
                'stored_roas' => $stored,
                'status' => $status,
                'action' => $action,
            ];
        }

        $pushed = [];
        $paused = [];
        $failed = [];
        $total = count($pending);

        foreach ($pending as $index => $item) {
            if ($dryRun) {
                if ($item['action'] === 'pause') {
                    $paused[] = $item;
                } else {
                    $pushed[] = $item;
                }
                if ($onEach) {
                    $onEach($index + 1, $total, $item, ['ok' => true]);
                }
                continue;
            }

            if ($index > 0) {
                usleep(200000);
            }

            if ($item['action'] === 'pause') {
                $result = $this->temuApi->pauseAd($item['goods_id']);
                $ok = (bool) ($result['ok'] ?? false);
                if ($ok) {
                    TemuAdsApiReport::where('goods_id', $item['goods_id'])
                        ->update(['ad_status' => 'Inactive']);
                    $paused[] = $item;
                } else {
                    $failed[] = array_merge($item, [
                        'error' => (string) ($result['error_msg'] ?? 'Pause failed'),
                    ]);
                }
            } else {
                $result = $this->temuApi->modifyAdRoas($item['goods_id'], (float) $item['target_roas']);
                $ok = (bool) ($result['ok'] ?? false);
                if ($ok) {
                    $this->rememberPushedTarget($item['goods_id'], (float) $item['target_roas']);
                    $pushed[] = $item;
                } else {
                    $failed[] = array_merge($item, [
                        'error' => (string) ($result['error_msg'] ?? 'ROAS update failed'),
                    ]);
                }
            }

            if ($onEach) {
                $onEach($index + 1, $total, $item, $result);
            }
        }

        $stats = [
            'checked' => count($byGoods),
            'matched' => $total,
            'pushed' => count($pushed),
            'paused' => count($paused),
            'already' => $already,
            'failed' => count($failed),
            'dry_run' => $dryRun,
            'skipped_lock' => false,
            'pushed_goods' => $dryRun ? $pushed : [],
            'paused_goods' => $dryRun ? $paused : [],
            'failed_goods' => $failed,
        ];

        Log::info('TemuAdsAutoPauseService::pushTargetRoas', [
            'checked' => $stats['checked'],
            'matched' => $stats['matched'],
            'pushed' => $stats['pushed'],
            'paused' => $stats['paused'],
            'already' => $stats['already'],
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
        ]);

        return $stats;
    }

    private function targetRoasAction(float $target, ?float $stored, string $status): ?string
    {
        if ($target <= 0) {
            return $status === 'Active' ? 'pause' : null;
        }
        $target = min(12.0, $target);
        if ($target < 0.1) {
            return null;
        }
        if ($stored !== null && abs($stored - $target) < 0.05) {
            return null;
        }

        return 'roas';
    }

    private function storedTargetRoas(TemuAdsApiReport $row): ?float
    {
        $raw = $row->raw_response;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return null;
        }
        $ad = $raw['adDetail'] ?? ($raw['result']['adDetail'] ?? null);
        if (! is_array($ad) || ! isset($ad['roas']) || ! is_numeric($ad['roas'])) {
            return null;
        }

        return round(((float) $ad['roas']) / 10000, 2);
    }

    private function rememberPushedTarget(string $goodsId, float $target): void
    {
        $api = (int) round($target * 10000);
        $rows = TemuAdsApiReport::query()
            ->inLatestWindow('L30')
            ->where('goods_id', $goodsId)
            ->get(['id', 'raw_response']);

        foreach ($rows as $row) {
            $raw = $row->raw_response;
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (! is_array($raw)) {
                continue;
            }
            if (! isset($raw['adDetail']) || ! is_array($raw['adDetail'])) {
                $raw['adDetail'] = [];
            }
            $raw['adDetail']['roas'] = $api;
            TemuAdsApiReport::where('id', $row->id)->update([
                'raw_response' => json_encode($raw),
            ]);
        }
    }
}
