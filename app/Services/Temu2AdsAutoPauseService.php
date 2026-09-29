<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ShopifySku;
use App\Models\Temu2CampaignReport;
use App\Support\CpMasterDil;
use Illuminate\Support\Facades\Log;

/**
 * Auto Cron for /temu2/ads: only push rows whose Active/Pause status
 * changes from the L7 click limit.
 */
class Temu2AdsAutoPauseService
{
    public function __construct(protected Temu2ApiService $temuApi)
    {
    }

    public function l7ClicksRedBelow(): int
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu2_ads_l7_clicks_red_below')
            ->first();
        $n = isset($row->column_order[0]) ? (int) $row->column_order[0] : 70;

        return $n >= 0 ? $n : 70;
    }

    public function targetRoasBidding(): float
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu2_ads_target_roas_bidding')
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

    public function targetRoasForClicksAndDil(int $clicks, ?float $dil): float
    {
        $base = $this->targetRoasForClicks($clicks);
        if ($dil === null) {
            return $base;
        }
        foreach ($this->dilSlabs() as $slab) {
            $min = $slab['dil_min'];
            $max = $slab['dil_max'];
            if ($min === null && $max === null) {
                continue;
            }
            if ($min !== null && $dil < $min) {
                continue;
            }
            if ($max !== null && $dil > $max) {
                continue;
            }
            $n = round($base + (float) $slab['add_roas'], 2);

            return $n < 0.1 ? 0.1 : $n;
        }

        return $base;
    }

    /**
     * @return array<int, array{clicks_min: int|null, clicks_max: int|null, target_roas: float|null}>
     */
    public function roasRuleSlabs(): array
    {
        $decoded = $this->decodeStoredRoasRule();
        $raw = array_key_exists('slabs', $decoded) || array_key_exists('dil', $decoded)
            ? ($decoded['slabs'] ?? [])
            : $decoded;
        $slabs = $this->normalizeRoasRuleSlabs($raw);

        return $slabs !== [] ? $slabs : $this->defaultRoasRuleSlabs();
    }

    /**
     * @return array<int, array{dil_min: float|null, dil_max: float|null, add_roas: float}>
     */
    public function dilSlabs(): array
    {
        $decoded = $this->decodeStoredRoasRule();
        if (! array_key_exists('dil', $decoded)) {
            return $this->defaultDilSlabs();
        }

        return $this->normalizeDilSlabs(is_array($decoded['dil']) ? $decoded['dil'] : []);
    }

    /**
     * @return array<mixed>
     */
    private function decodeStoredRoasRule(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'temu2_ads_roas_rule_slabs')
            ->first();
        $raw = $row && is_array($row->column_order) ? $row->column_order : [];
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

        return $raw;
    }

    /**
     * @return array<int, array{dil_min: float|null, dil_max: float|null, add_roas: float}>
     */
    private function defaultDilSlabs(): array
    {
        return [
            ['dil_min' => 0.0, 'dil_max' => 0.0, 'add_roas' => 0.0],
            ['dil_min' => 1.0, 'dil_max' => 24.0, 'add_roas' => 0.0],
            ['dil_min' => 25.0, 'dil_max' => 49.0, 'add_roas' => 0.0],
            ['dil_min' => 50.0, 'dil_max' => null, 'add_roas' => 0.0],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{dil_min: float|null, dil_max: float|null, add_roas: float}>
     */
    private function normalizeDilSlabs($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $min = $this->dilBound($item['dil_min'] ?? $item['min'] ?? null);
            $max = $this->dilBound($item['dil_max'] ?? $item['max'] ?? null);
            $add = null;
            if (isset($item['add_roas']) && $item['add_roas'] !== '' && is_numeric($item['add_roas'])) {
                $add = round((float) $item['add_roas'], 2);
            }
            if ($min === null && $max === null && $add === null) {
                continue;
            }
            if ($add === null) {
                $add = 0.0;
            }
            if ($max !== null && $min !== null && $max < $min) {
                $max = $min;
            }
            $out[] = ['dil_min' => $min, 'dil_max' => $max, 'add_roas' => $add];
        }

        return $out;
    }

    private function dilBound(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $n = round((float) $v, 2);

        return $n < 0 ? null : $n;
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
            ->where('channel_name', 'temu2_ads_auto_pause_cron')
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
            ['channel_name' => 'temu2_ads_auto_pause_cron'],
            ['column_order' => [$enabled ? '1' : '0']]
        );

        Log::info('Temu2AdsAutoPauseService::setCronEnabled', ['enabled' => $enabled]);

        return $enabled;
    }

    /**
     * Rows whose click-limit desired status differs from current Active/Pause.
     * Clicks < threshold → Run. Clicks >= threshold → Pause.
     * Already-correct rows are omitted so cron does not push them.
     *
     * @return array<int, array{goods_id: string, sku: mixed, clicks_l7: int, roas: float, t_roas: float, ad_spend: float, status: string|null, action: string}>
     */
    public function matchingAds(): array
    {
        $threshold = $this->l7ClicksRedBelow();

        $byGoods = Temu2CampaignReport::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get(['id', 'goods_id', 'sku', 'report_range', 'clicks', 'roas', 'spend', 'status'])
            ->groupBy(fn (Temu2CampaignReport $r) => (string) $r->goods_id);

        $skus = $byGoods->map(function ($rows) {
            $row = $rows->firstWhere('report_range', 'L30') ?: $rows->first();

            return (string) ($row->sku ?? '');
        })->filter(fn ($s) => $s !== '')->unique()->values()->all();
        $shopifyByNorm = ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);

        $matches = [];
        foreach ($byGoods as $goodsId => $rows) {
            $status = $rows->first(
                fn (Temu2CampaignReport $r) => in_array($r->displayAdStatus(), ['Active', 'Inactive'], true)
            )?->displayAdStatus();
            if (! in_array($status, ['Active', 'Inactive'], true)) {
                continue;
            }

            $l7 = $rows->firstWhere('report_range', 'L7');
            if (! $l7) {
                continue;
            }

            $l30 = $rows->firstWhere('report_range', 'L30');
            $roasRow = $l30 ?: $l7;
            $l7Clicks = (int) ($l7->clicks ?? 0);
            $roas = (float) ($roasRow->roas ?? 0);
            $spend = (float) ($roasRow->spend ?? 0);
            $clicks = (int) ($roasRow->clicks ?? 0);
            $skuKey = ShopifySku::normalizeSkuForShopifyLookup((string) ($roasRow->sku ?? ''));
            $shopify = $skuKey !== '' ? ($shopifyByNorm[$skuKey] ?? null) : null;
            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $sold = $shopify ? (float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0) : 0;
            $tRoas = $this->targetRoasForClicksAndDil($clicks, CpMasterDil::percent($sold, $inv));
            $desired = $l7Clicks < $threshold ? 'run' : 'pause';
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
                Temu2CampaignReport::where('goods_id', $match['goods_id'])
                    ->update(['status' => $live]);
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
                Temu2CampaignReport::where('goods_id', $match['goods_id'])
                    ->update(['status' => 'Inactive']);
                $already++;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }

            $result = $wantActive
                ? $this->temuApi->resumeAd($match['goods_id'])
                : $this->temuApi->pauseAd($match['goods_id']);
            if ($result['ok'] ?? false) {
                Temu2CampaignReport::where('goods_id', $match['goods_id'])
                    ->update(['status' => $wantActive ? 'Active' : 'Inactive']);
                if ($result['already'] ?? false) {
                    $already++;
                } elseif ($wantActive) {
                    $resumed[] = $match;
                } else {
                    $paused[] = $match;
                }
            } else {
                $failed[] = array_merge($match, [
                    'error' => $result['error_msg'] ?? ($wantActive ? 'Run failed' : 'Pause failed'),
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
        ];

        Log::info('Temu2AdsAutoPauseService::pauseMatching', [
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
