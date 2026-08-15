<?php

namespace App\Services;

use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetch temu.searchrec.ad.reports.goods.query and store full raw + Overall summary fields.
 * Uses reportInfo.summary.*.total (Seller Center Overall), not ad-only reportsSummary.*All.
 */
class TemuAdsApiReportService
{
    public function __construct(protected TemuApiService $temuApiService)
    {
    }

    /**
     * Date ranges used for L7 / L30 / L60 snapshots (ms timestamps).
     * Same windows as Temu Seller Center Data Report (Last 7 / Last 30 / prior 30).
     */
    public function periodRanges(): array
    {
        return $this->temuApiService->adsPeriodRanges();
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

            $metrics = $this->metricsFromApiResult($result);
            $row = array_merge($metrics, [
                'sku' => $sku,
                'start_ts' => $range['startTs'],
                'end_ts' => $range['endTs'],
                'raw_response' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'success' => true,
                'error_msg' => null,
                'fetched_at' => now(),
            ]);

            TemuAdsApiReport::updateOrCreate(
                ['goods_id' => $goodsId, 'period' => $period],
                $row
            );

            $this->syncTemuMetricClicks($goodsId, $period, $row);

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

    /**
     * Re-extract Overall metrics from stored raw_response (no API call).
     * Fixes rows that saved ad-only reportsSummary or overflowed on raw ROAS.
     *
     * @return array{total: int, ok: int, fail: int}
     */
    public function reparseStored(?string $period = null, ?string $specificGoodsId = null): array
    {
        $query = TemuAdsApiReport::query()->whereNotNull('raw_response');
        if ($period) {
            $query->where('period', strtoupper($period));
        }
        if ($specificGoodsId) {
            $query->where('goods_id', (string) $specificGoodsId);
        }

        $ok = 0;
        $fail = 0;
        $total = 0;

        foreach ($query->cursor() as $report) {
            $total++;
            $raw = json_decode((string) $report->raw_response, true);
            if (! is_array($raw)) {
                $fail++;
                continue;
            }

            try {
                $metrics = $this->metricsFromApiResult($raw);
                $report->fill(array_merge($metrics, [
                    'success' => true,
                    'error_msg' => null,
                ]));
                $report->save();
                $this->syncTemuMetricClicks((string) $report->goods_id, (string) $report->period, $metrics);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::error('TemuAdsApiReportService::reparseStored failed', [
                    'goods_id' => $report->goods_id,
                    'period' => $report->period,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['total' => $total, 'ok' => $ok, 'fail' => $fail];
    }

    /**
     * Seller Center "Overall" totals live in reportInfo.summary.*.total.
     * reportInfo.reportsSummary.*All is ad-only (often much smaller than L7 Overall).
     * Money is cents; CTR/ACOS are percent*100; raw ROAS can overflow decimal(12,4).
     *
     * @return array{impressions: ?int, clicks: ?int, ctr: float, cart_cnt: ?int, order_pay_cnt: ?int, order_pay_amt: ?float, ad_spend: ?float, roas: float, acos: float}
     */
    public function metricsFromApiResult(array $result): array
    {
        $overall = is_array($result['reportInfo']['summary'] ?? null) ? $result['reportInfo']['summary'] : [];
        $adOnly = is_array($result['reportInfo']['reportsSummary'] ?? null) ? $result['reportInfo']['reportsSummary'] : [];

        $impressions = $this->nestedVal($overall, ['imprCnt', 'total']) ?? $this->val($adOnly, 'imprCntAll');
        $clicks = $this->nestedVal($overall, ['clkCnt', 'total']) ?? $this->val($adOnly, 'clkCntAll');
        $cart = $this->nestedVal($overall, ['cartCnt', 'total']) ?? $this->val($adOnly, 'cartCntAll');
        $orders = $this->nestedVal($overall, ['orderPayCnt', 'total']) ?? $this->val($adOnly, 'orderPayCntAll');
        $orderAmt = $this->centsToDollars(
            $this->nestedVal($overall, ['orderPayAmt', 'total']) ?? $this->val($adOnly, 'orderPayAmtAll')
        );
        $spend = $this->centsToDollars(
            $this->nestedVal($overall, ['spend', 'total']) ?? $this->val($adOnly, 'adSpendAll')
        );

        $impr = (int) ($impressions ?? 0);
        $clk = (int) ($clicks ?? 0);
        $orderAmtF = (float) ($orderAmt ?? 0);
        $spendF = (float) ($spend ?? 0);

        $ctr = $impr > 0 ? round($clk / $impr * 100, 4) : 0.0;
        $roas = $spendF > 0 ? round($orderAmtF / $spendF, 4) : 0.0;
        $acos = $orderAmtF > 0 ? round($spendF / $orderAmtF * 100, 4) : 0.0;
        if ($roas > 99999999.9999) {
            $roas = 99999999.9999;
        }

        return [
            'impressions' => $impressions !== null ? $impr : null,
            'clicks' => $clicks !== null ? $clk : null,
            'ctr' => $ctr,
            'cart_cnt' => $cart !== null ? (int) $cart : null,
            'order_pay_cnt' => $orders !== null ? (int) $orders : null,
            'order_pay_amt' => $orderAmt,
            'ad_spend' => $spend,
            'roas' => $roas,
            'acos' => $acos,
        ];
    }

    private function syncTemuMetricClicks(string $goodsId, string $period, array $row): void
    {
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
    }

    private function centsToDollars(mixed $val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (! is_numeric($val)) {
            return null;
        }

        return round(((float) $val) / 100, 4);
    }

    private function nestedVal(?array $root, array $path): mixed
    {
        $node = $root;
        foreach ($path as $key) {
            if (! is_array($node) || ! isset($node[$key])) {
                return null;
            }
            $node = $node[$key];
        }
        if (is_array($node) && array_key_exists('val', $node)) {
            return $node['val'];
        }

        return is_numeric($node) ? $node : null;
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
