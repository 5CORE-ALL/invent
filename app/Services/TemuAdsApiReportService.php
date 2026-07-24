<?php

namespace App\Services;

use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetch temu.searchrec.ad.reports.goods.query and store full raw + summary fields.
 */
class TemuAdsApiReportService
{
    public function __construct(protected TemuApiService $temuApiService)
    {
    }

    /**
     * Date ranges used for L7 / L30 / L60 snapshots (ms timestamps).
     */
    public function periodRanges(): array
    {
        return [
            'L7' => [
                'startTs' => Carbon::now()->subDays(7)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::yesterday()->endOfDay()->timestamp * 1000,
            ],
            'L30' => [
                'startTs' => Carbon::now()->subDays(30)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::yesterday()->endOfDay()->timestamp * 1000,
            ],
            'L60' => [
                'startTs' => Carbon::now()->subDays(60)->startOfDay()->timestamp * 1000,
                'endTs' => Carbon::now()->subDays(31)->endOfDay()->timestamp * 1000,
            ],
        ];
    }

    /**
     * Goods IDs to fetch (from temu_metrics), optionally filtered.
     *
     * @return array<int, string>
     */
    public function resolveGoodsIds(?string $specificGoodsId = null): array
    {
        if ($specificGoodsId !== null && $specificGoodsId !== '') {
            return [(string) $specificGoodsId];
        }

        return TemuMetric::whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->pluck('goods_id')
            ->unique()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Fetch one goodsId for a period and upsert temu_ads_api_reports.
     * Also mirrors impressions/clicks onto TemuMetric (existing L30/L60 behavior).
     *
     * @return array{ok: bool, goods_id: string, period: string, message?: string}
     */
    public function fetchAndStore(string $goodsId, string $period = 'L30'): array
    {
        $period = strtoupper($period);
        $ranges = $this->periodRanges();
        if (! isset($ranges[$period])) {
            return ['ok' => false, 'goods_id' => $goodsId, 'period' => $period, 'message' => 'Invalid period'];
        }

        $range = $ranges[$period];
        $sku = TemuMetric::where('goods_id', $goodsId)->value('sku');

        try {
            $result = $this->temuApiService->fetchAdsData(
                $goodsId,
                $range['startTs'],
                $range['endTs']
            );

            if (! $result) {
                TemuAdsApiReport::updateOrCreate(
                    ['goods_id' => $goodsId, 'period' => $period],
                    [
                        'sku' => $sku,
                        'start_ts' => $range['startTs'],
                        'end_ts' => $range['endTs'],
                        'raw_response' => null,
                        'success' => false,
                        'error_msg' => 'Empty or failed API response',
                        'fetched_at' => now(),
                    ]
                );

                return ['ok' => false, 'goods_id' => $goodsId, 'period' => $period, 'message' => 'Empty API response'];
            }

            $summary = $result['reportInfo']['reportsSummary'] ?? [];
            $row = [
                'sku' => $sku,
                'start_ts' => $range['startTs'],
                'end_ts' => $range['endTs'],
                'impressions' => $this->val($summary, 'imprCntAll'),
                'clicks' => $this->val($summary, 'clkCntAll'),
                'ctr' => $this->val($summary, 'ctrAll'),
                'cart_cnt' => $this->val($summary, 'cartCntAll'),
                'order_pay_cnt' => $this->val($summary, 'orderPayCntAll'),
                'order_pay_amt' => $this->val($summary, 'orderPayAmtAll'),
                'ad_spend' => $this->val($summary, 'adSpendAll'),
                'roas' => $this->val($summary, 'roasAll'),
                'acos' => $this->val($summary, 'acosAll'),
                'raw_response' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'success' => true,
                'error_msg' => null,
                'fetched_at' => now(),
            ];

            TemuAdsApiReport::updateOrCreate(
                ['goods_id' => $goodsId, 'period' => $period],
                $row
            );

            // Keep TemuMetric L30/L60 impressions & clicks in sync (existing consumers)
            if ($period === 'L30') {
                TemuMetric::where('goods_id', $goodsId)->update([
                    'product_impressions_l30' => (int) ($row['impressions'] ?? 0),
                    'product_clicks_l30' => (int) ($row['clicks'] ?? 0),
                ]);
            } elseif ($period === 'L60') {
                TemuMetric::where('goods_id', $goodsId)->update([
                    'product_impressions_l60' => (int) ($row['impressions'] ?? 0),
                    'product_clicks_l60' => (int) ($row['clicks'] ?? 0),
                ]);
            }

            return ['ok' => true, 'goods_id' => $goodsId, 'period' => $period];
        } catch (\Throwable $e) {
            Log::error('TemuAdsApiReportService::fetchAndStore failed', [
                'goods_id' => $goodsId,
                'period' => $period,
                'error' => $e->getMessage(),
            ]);

            TemuAdsApiReport::updateOrCreate(
                ['goods_id' => $goodsId, 'period' => $period],
                [
                    'sku' => $sku,
                    'start_ts' => $range['startTs'],
                    'end_ts' => $range['endTs'],
                    'success' => false,
                    'error_msg' => substr($e->getMessage(), 0, 500),
                    'fetched_at' => now(),
                ]
            );

            return [
                'ok' => false,
                'goods_id' => $goodsId,
                'period' => $period,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch all (or one) goods for a period.
     *
     * @return array{total: int, ok: int, fail: int}
     */
    public function fetchAll(string $period = 'L30', ?string $specificGoodsId = null, ?callable $onEach = null): array
    {
        $goodsIds = $this->resolveGoodsIds($specificGoodsId);
        $ok = 0;
        $fail = 0;

        foreach ($goodsIds as $goodsId) {
            $result = $this->fetchAndStore($goodsId, $period);
            if ($result['ok']) {
                $ok++;
            } else {
                $fail++;
            }
            if ($onEach) {
                $onEach($result);
            }
            usleep(200000); // 0.2s rate limit
        }

        return [
            'total' => count($goodsIds),
            'ok' => $ok,
            'fail' => $fail,
        ];
    }

    private function val(?array $summary, string $key): mixed
    {
        if (! is_array($summary) || ! isset($summary[$key])) {
            return null;
        }
        $node = $summary[$key];
        if (is_array($node) && array_key_exists('val', $node)) {
            return $node['val'];
        }

        return is_numeric($node) ? $node : null;
    }
}
