<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
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
     * T ROAS for a row: same Spend 1 slabs as the T ROAS column.
     */
    public function targetRoasForSpend(float $spend): float
    {
        $n = is_nan($spend) || is_infinite($spend) ? 0.0 : $spend;
        foreach ($this->roasRuleSlabs() as $slab) {
            $min = $slab['spend_min'];
            $max = $slab['spend_max'];
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
     * @return array<int, array{spend_min: float|null, spend_max: float|null, target_roas: float|null}>
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
     * @return array<int, array{spend_min: float|null, spend_max: float|null, target_roas: float|null}>
     */
    private function defaultRoasRuleSlabs(): array
    {
        return [
            ['spend_min' => 0.0, 'spend_max' => 0.0, 'target_roas' => 4.0],
            ['spend_min' => 0.01, 'spend_max' => 5.99, 'target_roas' => 5.0],
            ['spend_min' => 6.0, 'spend_max' => 9.0, 'target_roas' => 10.0],
            ['spend_min' => 9.01, 'spend_max' => null, 'target_roas' => 12.0],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{spend_min: float|null, spend_max: float|null, target_roas: float|null}>
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
            $spendMin = $this->moneyOrNull($item['spend_min'] ?? $item['min'] ?? null);
            $spendMax = $this->moneyOrNull($item['spend_max'] ?? $item['max'] ?? null);
            $targetRoas = null;
            if (isset($item['target_roas']) && $item['target_roas'] !== '' && is_numeric($item['target_roas'])) {
                $targetRoas = round((float) $item['target_roas'], 2);
            }
            if ($spendMin === null && $spendMax === null && $targetRoas === null) {
                continue;
            }
            if ($spendMax !== null && $spendMin !== null && $spendMax < $spendMin) {
                $spendMax = $spendMin;
            }
            $out[] = [
                'spend_min' => $spendMin,
                'spend_max' => $spendMax,
                'target_roas' => $targetRoas,
            ];
        }

        return $out;
    }

    private function moneyOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = round((float) $v, 2);

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
            $tRoas = $this->targetRoasForSpend($spend);
            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($roasRow->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;

            $desired = $this->actionFromPauseRunSlabs($l7Clicks, $inv);
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
}
