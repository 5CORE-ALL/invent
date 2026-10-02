<?php

namespace Tests\Unit;

use App\Services\TemuAdsApiReportService;
use App\Services\Temu2AdsApiReportService;
use App\Services\Temu2ApiService;
use App\Services\TemuApiService;
use App\Support\TemuAdsBadgeHistory;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TemuAdsLastDaySpendTest extends TestCase
{
    public function test_last_day_spend_converts_cents_to_dollars(): void
    {
        $service = new TemuAdsApiReportService(new TemuApiService());
        $spend = $service->lastDaySpendFromResult($this->centsPayload());

        $this->assertSame(0.7, $spend);
    }

    public function test_last_day_spend_keeps_dollars_when_daily_already_matches_overall(): void
    {
        $service = new TemuAdsApiReportService(new TemuApiService());
        $spend = $service->lastDaySpendFromResult($this->dollarsPayload());

        $this->assertSame(0.7, $spend);
    }

    public function test_last_day_spend_returns_null_without_daily_rows(): void
    {
        $service = new TemuAdsApiReportService(new TemuApiService());

        $this->assertNull($service->lastDaySpendFromResult(['reportInfo' => []]));
    }

    public function test_temu2_last_day_spend_converts_cents_to_dollars(): void
    {
        $service = new Temu2AdsApiReportService(new Temu2ApiService());
        $spend = $service->lastDaySpendFromResult($this->centsPayload());

        $this->assertSame(0.7, $spend);
    }

    public function test_mall_summary_spend_converts_cents_to_dollars(): void
    {
        $service = new TemuAdsApiReportService(new TemuApiService());
        $metrics = $service->metricsFromMallResult([
            'summary' => [
                'spend' => ['total' => ['val' => 198098]],
                'imprCnt' => ['total' => ['val' => 1388831]],
                'clkCnt' => ['total' => ['val' => 52197]],
                'orderPayCnt' => ['total' => ['val' => 1714]],
                'orderPayAmt' => ['total' => ['val' => 2897175]],
            ],
        ]);

        $this->assertSame(1980.98, $metrics['spend']);
        $this->assertSame(1388831, $metrics['impressions']);
        $this->assertSame(52197, $metrics['clicks']);
        $this->assertSame(1714, $metrics['sold']);
        $this->assertSame(28971.75, $metrics['sales']);
    }

    public function test_seller_center_l30_ends_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 13:16:00', 'America/Los_Angeles'));
        try {
            $range = (new TemuApiService())->sellerCenterPeriodRange('L30');
            $start = Carbon::createFromTimestampMs($range['startTs'], 'America/Los_Angeles')->toDateString();
            $end = Carbon::createFromTimestampMs($range['endTs'], 'America/Los_Angeles')->toDateString();
            $this->assertSame('2026-09-02', $start);
            $this->assertSame('2026-10-01', $end);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_badge_history_converts_cents_once(): void
    {
        $hist = [
            'L30' => [
                '2026-09-21' => ['spend' => 582.47, 'y_spend' => 70],
                '2026-09-06' => ['spend' => 1656.3, 'y_spend' => 2916],
            ],
        ];

        $once = TemuAdsBadgeHistory::ensureYSpendInDollars($hist);
        $this->assertTrue($once[TemuAdsBadgeHistory::Y_SPEND_DOLLARS_FLAG]);
        $this->assertSame(0.7, $once['L30']['2026-09-21']['y_spend']);
        $this->assertSame(29.16, $once['L30']['2026-09-06']['y_spend']);

        $twice = TemuAdsBadgeHistory::ensureYSpendInDollars($once);
        $this->assertSame(0.7, $twice['L30']['2026-09-21']['y_spend']);
        $this->assertSame(29.16, $twice['L30']['2026-09-06']['y_spend']);
    }

    /**
     * @return array<string, mixed>
     */
    private function centsPayload(): array
    {
        return [
            'reportInfo' => [
                'summary' => [
                    'spend' => ['total' => ['val' => 1995]],
                ],
                'reportsItemList' => [
                    ['ts' => 1789887600000, 'adSpend' => ['val' => 1925]],
                    ['ts' => 1789974000000, 'adSpend' => ['val' => 70]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dollarsPayload(): array
    {
        return [
            'reportInfo' => [
                'summary' => [
                    'spend' => ['total' => ['val' => 1995]],
                ],
                'reportsItemList' => [
                    ['ts' => 1789887600000, 'adSpend' => ['val' => 19.25]],
                    ['ts' => 1789974000000, 'adSpend' => ['val' => 0.7]],
                ],
            ],
        ];
    }
}
