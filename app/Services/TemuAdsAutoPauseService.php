<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\TemuAdsApiReport;
use Illuminate\Support\Facades\Log;

/**
 * Pause Active Temu ads that match the shared /temu/ads rule:
 * L7 clicks < threshold AND L30 ROAS < Stop ROAS.
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
     * @return array<int, array{goods_id: string, sku: mixed, clicks_l7: int, roas: float, ad_spend: float, status: string|null}>
     */
    public function matchingAds(): array
    {
        $clickBelow = $this->l7ClicksRedBelow();
        $stopRoas = $this->targetRoasBidding();

        $byGoods = TemuAdsApiReport::query()
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->get()
            ->groupBy(fn (TemuAdsApiReport $r) => (string) $r->goods_id);

        $matches = [];
        foreach ($byGoods as $goodsId => $rows) {
            $status = $rows->first(
                fn (TemuAdsApiReport $r) => in_array($r->displayAdStatus(), ['Active', 'Inactive', 'Deleted'], true)
            )?->displayAdStatus() ?? $rows->first()?->displayAdStatus();
            if (in_array($status, ['Inactive', 'Deleted'], true)) {
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
            if ($l7Clicks >= $clickBelow || $roas >= $stopRoas) {
                continue;
            }
            // Local Status is often Not sync / No ad until Refresh Status.
            // Still pause if the row actually ran ads (clicks or spend).
            if ($status !== 'Active' && $l7Clicks <= 0 && $spend <= 0) {
                continue;
            }

            $matches[] = [
                'goods_id' => (string) $goodsId,
                'sku' => $roasRow->sku,
                'clicks_l7' => $l7Clicks,
                'roas' => $roas,
                'ad_spend' => $spend,
                'status' => $status,
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
    public function pauseMatching(bool $dryRun = false, ?callable $onEach = null): array
    {
        $matches = $this->matchingAds();
        $paused = [];
        $already = 0;
        $failed = [];
        $total = count($matches);
        $liveStatuses = [];

        if (! $dryRun && $matches !== []) {
            $statusQuery = $this->temuApi->queryAdStatuses(array_column($matches, 'goods_id'));
            $liveStatuses = $statusQuery['statuses'] ?? [];
        }

        foreach ($matches as $index => $match) {
            if ($dryRun) {
                $paused[] = $match;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }

            $live = $liveStatuses[$match['goods_id']] ?? null;
            if (in_array($live, ['Inactive', 'Deleted', 'No ad'], true)) {
                TemuAdsApiReport::where('goods_id', $match['goods_id'])
                    ->update(['ad_status' => $live]);
                $already++;
                $paused[] = $match;
                if ($onEach) {
                    $onEach($index + 1, $total, $match, ['ok' => true, 'already' => true]);
                }
                continue;
            }

            $result = $this->temuApi->pauseAd($match['goods_id']);
            if ($result['ok'] ?? false) {
                TemuAdsApiReport::where('goods_id', $match['goods_id'])
                    ->update(['ad_status' => 'Inactive']);
                if ($result['already'] ?? false) {
                    $already++;
                }
                $paused[] = $match;
            } else {
                $failed[] = array_merge($match, [
                    'error' => $result['error_msg'] ?? 'Pause failed',
                ]);
            }
            if ($onEach) {
                $onEach($index + 1, $total, $match, $result);
            }
        }

        $stats = [
            'matched' => count($matches),
            'paused' => $dryRun ? 0 : count($paused),
            'already' => $already,
            'failed' => count($failed),
            'dry_run' => $dryRun,
            'l7_clicks_red_below' => $this->l7ClicksRedBelow(),
            'target_roas_bidding' => $this->targetRoasBidding(),
            'paused_goods' => $paused,
            'failed_goods' => $failed,
        ];

        Log::info('TemuAdsAutoPauseService::pauseMatching', [
            'matched' => $stats['matched'],
            'paused' => $stats['paused'],
            'already' => $already,
            'failed' => $stats['failed'],
            'dry_run' => $dryRun,
            'l7_below' => $stats['l7_clicks_red_below'],
            'stop_roas' => $stats['target_roas_bidding'],
        ]);

        return $stats;
    }
}
