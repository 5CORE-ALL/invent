<?php

namespace Tests\Unit;

use App\Http\Controllers\AmazonAdsController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AmazonAdsL30BadgeUniverseTest extends TestCase
{
    private function shouldUseL30SummaryUniverse(string $table, array $input): bool
    {
        $method = new ReflectionMethod(AmazonAdsController::class, 'shouldUseL30SummaryUniverseForBadges');
        $method->setAccessible(true);
        $request = Request::create('/amazon-ads/raw-data/all_reports', 'POST', $input);

        return (bool) $method->invoke(null, $table, $request);
    }

    public function test_calendar_mode_uses_l30_summary_for_sp_and_sb(): void
    {
        $input = [
            'date_from' => '2026-09-06',
            'date_to' => '2026-09-06',
            'summary_report_range' => '',
        ];

        $this->assertTrue($this->shouldUseL30SummaryUniverse('amazon_sp_campaign_reports', $input));
        $this->assertTrue($this->shouldUseL30SummaryUniverse('amazon_sb_campaign_reports', $input));
        $this->assertFalse($this->shouldUseL30SummaryUniverse('amazon_sd_campaign_reports', $input));
    }

    public function test_explicit_summary_range_keeps_filtered_grid_query(): void
    {
        $input = ['summary_report_range' => 'L30'];

        $this->assertFalse($this->shouldUseL30SummaryUniverse('amazon_sp_campaign_reports', $input));
        $this->assertFalse($this->shouldUseL30SummaryUniverse('amazon_sb_campaign_reports', $input));
    }
}
