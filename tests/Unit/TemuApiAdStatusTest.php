<?php

namespace Tests\Unit;

use App\Services\TemuApiService;
use PHPUnit\Framework\TestCase;

class TemuApiAdStatusTest extends TestCase
{
    public function test_ad_show_status_7_is_paused_not_active(): void
    {
        $this->assertSame('Inactive', TemuApiService::normalizeAdStatus(7));
        $this->assertSame('Inactive', TemuApiService::statusFromAdDetail([
            'goodsId' => 610661316837215,
            'adShowStatus' => 7,
            'adPhase' => 0,
            'roas' => 40000,
            'budget' => -1,
        ]));
    }

    public function test_unknown_numeric_status_with_ad_is_not_forced_active(): void
    {
        $this->assertSame('Inactive', TemuApiService::statusFromAdDetail([
            'adShowStatus' => 12,
            'budget' => -1,
        ]));
    }

    public function test_status_zero_without_campaign_stays_no_ad(): void
    {
        $this->assertSame('No ad', TemuApiService::statusFromAdDetail([
            'goodsId' => 1,
            'adShowStatus' => 0,
        ]));
    }

    public function test_delivering_stays_active(): void
    {
        $this->assertSame('Active', TemuApiService::statusFromAdDetail([
            'adShowStatus' => 1,
            'budget' => -1,
        ]));
    }

    public function test_classic_paused_stays_inactive(): void
    {
        $this->assertSame('Inactive', TemuApiService::statusFromAdDetail([
            'adShowStatus' => 2,
        ]));
    }

    public function test_ad_show_status_8_is_active(): void
    {
        $this->assertSame('Active', TemuApiService::normalizeAdStatus(8));
        $this->assertSame('Active', TemuApiService::statusFromAdDetail([
            'goodsId' => 610964111988643,
            'adShowStatus' => 8,
            'adPhase' => 0,
            'roas' => 40000,
            'budget' => -1,
            'summary' => ['imprCnt' => ['total' => ['val' => 5], 'ad' => ['val' => 0]]],
        ]));
    }

    public function test_merge_keeps_report_and_adds_ad_detail(): void
    {
        $merged = \App\Services\TemuAdsApiReportService::mergeAdDetailIntoRaw(
            json_encode(['reportInfo' => ['summary' => []]], JSON_UNESCAPED_UNICODE),
            ['goodsId' => 610661316837215, 'adShowStatus' => 7]
        );
        $decoded = json_decode((string) $merged, true);

        $this->assertSame(7, $decoded['adDetail']['adShowStatus']);
        $this->assertArrayHasKey('summary', $decoded['reportInfo']);
    }
}
