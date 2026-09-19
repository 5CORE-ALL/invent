<?php

namespace Tests\Unit;

use App\Services\Temu3AdsApiReportService;
use App\Services\Temu3ApiService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class Temu3AdsApiReportServiceTest extends TestCase
{
    public function test_period_ranges_match_temu_1_seller_center_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 15:00:00', 'America/Los_Angeles'));
        try {
            $ranges = (new Temu3ApiService())->adsPeriodRanges();
            $today = Carbon::now('America/Los_Angeles');

            $this->assertSame($today->copy()->subDays(6)->startOfDay()->timestamp * 1000, $ranges['L7']['startTs']);
            $this->assertSame($today->copy()->endOfDay()->timestamp * 1000, $ranges['L7']['endTs']);
            $this->assertSame($today->copy()->subDays(29)->startOfDay()->timestamp * 1000, $ranges['L30']['startTs']);
            $this->assertSame($today->copy()->subDays(59)->startOfDay()->timestamp * 1000, $ranges['L60']['startTs']);
            $this->assertSame($today->copy()->subDays(30)->endOfDay()->timestamp * 1000, $ranges['L60']['endTs']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_metrics_prefer_overall_summary_then_ad_only(): void
    {
        $service = new Temu3AdsApiReportService(new Temu3ApiService());
        $metrics = $service->metricsFromApiResult([
            'reportInfo' => [
                'summary' => [
                    'imprCnt' => ['total' => ['val' => 200]],
                    'clkCnt' => ['total' => ['val' => 10]],
                    'spend' => ['total' => ['val' => 2500]],
                    'orderPayAmt' => ['total' => ['val' => 5000]],
                    'orderPayCnt' => ['total' => ['val' => 2]],
                    'cartCnt' => ['total' => ['val' => 3]],
                ],
                'reportsSummary' => [
                    'imprCntAll' => ['val' => 1],
                    'clkCntAll' => ['val' => 1],
                    'adSpendAll' => ['val' => 100],
                ],
            ],
        ]);

        $this->assertSame(200, $metrics['impressions']);
        $this->assertSame(10, $metrics['clicks']);
        $this->assertSame(5.0, $metrics['ctr']);
        $this->assertSame(3, $metrics['cart_cnt']);
        $this->assertSame(2, $metrics['order_pay_cnt']);
        $this->assertSame(50.0, $metrics['order_pay_amt']);
        $this->assertSame(25.0, $metrics['ad_spend']);
        $this->assertSame(2.0, $metrics['roas']);
        $this->assertSame(50.0, $metrics['acos']);
    }

    public function test_merge_keeps_report_and_adds_ad_detail(): void
    {
        $merged = Temu3AdsApiReportService::mergeAdDetailIntoRaw(
            json_encode(['reportInfo' => ['summary' => []]], JSON_UNESCAPED_UNICODE),
            ['goodsId' => 1, 'adShowStatus' => 7]
        );
        $decoded = json_decode((string) $merged, true);

        $this->assertSame(7, $decoded['adDetail']['adShowStatus']);
        $this->assertArrayHasKey('summary', $decoded['reportInfo']);
    }
}
