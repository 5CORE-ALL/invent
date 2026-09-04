<?php

namespace App\Services;

use App\Models\Temu2CampaignReport;
use App\Models\Temu2Metric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fetch temu.searchrec.ad.reports.goods.query and store full raw + Overall summary fields.
 * Uses reportInfo.summary.*.total (Seller Center Overall), not ad-only reportsSummary.*All.
 */
class Temu2AdsApiReportService
{
    public function __construct(protected Temu2ApiService $temuApiService)
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
     * Goods IDs to fetch (from temu2_metrics), optionally filtered.
     *
     * @return array<int, string>
     */
    public function resolveGoodsIds(?string $specificGoodsId = null): array
    {
        if ($specificGoodsId !== null && $specificGoodsId !== '') {
            return [(string) $specificGoodsId];
        }

        return Temu2Metric::whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->pluck('goods_id')
            ->unique()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Fetch one goodsId for a period and upsert temu2_campaign_reports.
     * Also mirrors impressions/clicks onto Temu2Metric (existing L30/L60 behavior).
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
        $sku = $this->resolveSku($goodsId, $period);

        try {
            $result = $this->temuApiService->fetchAdsData(
                $goodsId,
                $range['startTs'],
                $range['endTs']
            );

            if (! $result) {
                return ['ok' => false, 'goods_id' => $goodsId, 'period' => $period, 'message' => 'Empty API response'];
            }

            $metrics = $this->metricsFromApiResult($result);
            $row = $this->campaignRowFromApiMetrics($metrics, $sku);
            $statusQuery = $this->temuApiService->queryAdStatuses([$goodsId]);
            if (isset($statusQuery['statuses'][$goodsId])) {
                $row['status'] = $statusQuery['statuses'][$goodsId];
            }

            Temu2CampaignReport::updateOrCreate(
                ['goods_id' => $goodsId, 'report_range' => $period],
                $row
            );

            $this->syncTemu2MetricClicks($goodsId, $period, $metrics);

            return ['ok' => true, 'goods_id' => $goodsId, 'period' => $period];
        } catch (\Throwable $e) {
            Log::error('Temu2AdsApiReportService::fetchAndStore failed', [
                'goods_id' => $goodsId,
                'period' => $period,
                'error' => $e->getMessage(),
            ]);

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
        return ['total' => 0, 'ok' => 0, 'fail' => 0];
    }

    /**
     * Refresh Active/Inactive from temu.searchrec.ad.detail.query onto all period rows.
     *
     * @return array{total: int, ok: int, fail: int, error: ?string}
     */
    public function refreshAdStatuses(?string $specificGoodsId = null): array
    {
        $goodsIds = $specificGoodsId
            ? [(string) $specificGoodsId]
            : Temu2CampaignReport::query()
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->distinct()
                ->pluck('goods_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

        if ($goodsIds === []) {
            return ['total' => 0, 'ok' => 0, 'fail' => 0, 'error' => null];
        }

        $query = $this->temuApiService->queryAdStatuses($goodsIds);
        $ok = 0;
        $fail = 0;

        foreach ($goodsIds as $goodsId) {
            $status = $query['statuses'][$goodsId] ?? null;
            if ($status === null) {
                $fail++;
                continue;
            }
            Temu2CampaignReport::where('goods_id', $goodsId)->update(['status' => $status]);
            $ok++;
        }

        return [
            'total' => count($goodsIds),
            'ok' => $ok,
            'fail' => $fail,
            'error' => $query['error'] ?? null,
        ];
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

    /**
     * @param  array{impressions: ?int, clicks: ?int, ctr: float, cart_cnt: ?int, order_pay_cnt: ?int, order_pay_amt: ?float, ad_spend: ?float, roas: float, acos: float}  $metrics
     * @return array<string, mixed>
     */
    private function campaignRowFromApiMetrics(array $metrics, mixed $sku): array
    {
        $clicks = (int) ($metrics['clicks'] ?? 0);
        $orders = (int) ($metrics['order_pay_cnt'] ?? 0);

        $row = [
            'spend' => $metrics['ad_spend'] ?? null,
            'base_price_sales' => $metrics['order_pay_amt'] ?? null,
            'roas' => $metrics['roas'] ?? null,
            'acos_ad' => $metrics['acos'] ?? null,
            'sub_orders' => $metrics['order_pay_cnt'] ?? null,
            'impressions' => $metrics['impressions'] ?? null,
            'clicks' => $metrics['clicks'] ?? null,
            'ctr' => $metrics['ctr'] ?? null,
            'cvr' => $clicks > 0 ? round($orders / $clicks * 100, 2) : 0,
            'add_to_cart_number' => $metrics['cart_cnt'] ?? null,
        ];
        $sku = is_string($sku) ? trim($sku) : '';
        if ($sku !== '') {
            $row['sku'] = $sku;
        }

        return $row;
    }

    /**
     * Keep the listing SKU even when temu2_metrics has no row for this goods.
     * A null write would blank Image / Inv / Dil% on the next fetch.
     */
    private function resolveSku(string $goodsId, string $period): ?string
    {
        $sku = Temu2Metric::where('goods_id', $goodsId)->value('sku');
        $sku = is_string($sku) ? trim($sku) : '';
        if ($sku !== '') {
            return $sku;
        }

        $existing = Temu2CampaignReport::query()
            ->where('goods_id', $goodsId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderByRaw('CASE WHEN report_range = ? THEN 0 ELSE 1 END', [$period])
            ->value('sku');
        $existing = is_string($existing) ? trim($existing) : '';

        return $existing !== '' ? $existing : null;
    }

    /**
     * Last calendar day ad spend from reportInfo.reportsItemList (max ts).
     * Daily adSpend.val is in the same units as stored ad_spend.
     */
    public function lastDaySpendFromResult(?array $result): ?float
    {
        $items = is_array($result['reportInfo']['reportsItemList'] ?? null)
            ? $result['reportInfo']['reportsItemList']
            : [];
        $latest = null;
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['ts'])) {
                continue;
            }
            if ($latest === null || (int) $item['ts'] > (int) $latest['ts']) {
                $latest = $item;
            }
        }
        if ($latest === null) {
            return null;
        }

        $val = $this->nestedVal($latest, ['adSpend'])
            ?? $this->nestedVal($latest, ['netAdSpend'])
            ?? $this->nestedVal($latest, ['spend']);

        return $val === null ? null : round((float) $val, 4);
    }

    private function syncTemu2MetricClicks(string $goodsId, string $period, array $row): void
    {
        if ($period === 'L30') {
            Temu2Metric::where('goods_id', $goodsId)->update([
                'product_impressions_l30' => (int) ($row['impressions'] ?? 0),
                'product_clicks_l30' => (int) ($row['clicks'] ?? 0),
            ]);
        } elseif ($period === 'L60') {
            Temu2Metric::where('goods_id', $goodsId)->update([
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
