<?php

namespace Tests\Feature;

use App\Models\Temu3AdsApiReport;
use App\Models\Temu3Metric;
use App\Services\Temu3AdsApiReportService;
use App\Services\Temu3ApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FetchTemu3AdsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);
    }

    public function test_fetch_and_store_uses_temu_1_report_and_status_logic(): void
    {
        if (! Schema::hasTable('temu3_metrics') || ! Schema::hasTable('temu3_ads_api_reports')) {
            $this->markTestSkipped('temu3 ads tables missing');
        }

        Temu3Metric::query()->where('sku', 'TEMU3 ADS TEST')->delete();
        Temu3AdsApiReport::query()->where('goods_id', '610000000000001')->delete();

        Temu3Metric::query()->create([
            'sku' => 'TEMU3 ADS TEST',
            'sku_id' => '999',
            'goods_id' => '610000000000001',
        ]);

        $api = Mockery::mock(Temu3ApiService::class);
        $api->shouldReceive('adsPeriodRanges')->andReturn([
            'L30' => ['startTs' => 1000, 'endTs' => 2000],
        ]);
        $api->shouldReceive('fetchAdsDataDetailed')->once()->andReturn([
            'ok' => true,
            'result' => [
                'reportInfo' => [
                    'summary' => [
                        'imprCnt' => ['total' => ['val' => 40]],
                        'clkCnt' => ['total' => ['val' => 4]],
                    ],
                ],
            ],
            'error_msg' => null,
        ]);
        $api->shouldReceive('queryAdStatuses')->once()->andReturn([
            'statuses' => ['610000000000001' => 'No ad'],
            'details' => ['610000000000001' => ['goodsId' => 610000000000001, 'adShowStatus' => 0]],
            'failed' => [],
            'error' => null,
        ]);

        $this->app->instance(Temu3ApiService::class, $api);

        $result = app(Temu3AdsApiReportService::class)->fetchAndStore('610000000000001', 'L30');
        $this->assertTrue($result['ok']);

        $report = Temu3AdsApiReport::query()
            ->where('goods_id', '610000000000001')
            ->where('period', 'L30')
            ->first();
        $this->assertNotNull($report);
        $this->assertSame(40, (int) $report->impressions);
        $this->assertSame(4, (int) $report->clicks);
        $this->assertSame('No ad', $report->ad_status);
        $this->assertSame('TEMU3 ADS TEST', $report->sku);
        $this->assertTrue($report->success);

        $metric = Temu3Metric::query()->where('sku', 'TEMU3 ADS TEST')->first();
        $this->assertSame(40, (int) $metric->product_impressions_l30);
        $this->assertSame(4, (int) $metric->product_clicks_l30);
    }
}
