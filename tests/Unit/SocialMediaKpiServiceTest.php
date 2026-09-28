<?php

namespace Tests\Unit;

use App\SocialMedia\SocialMediaKpiService;
use App\SocialMedia\SocialMetric;
use PHPUnit\Framework\TestCase;

class SocialMediaKpiServiceTest extends TestCase
{
    private SocialMediaKpiService $kpi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kpi = new SocialMediaKpiService();
    }

    public function test_engagement_sums_only_metrics_the_api_returned(): void
    {
        $this->assertSame(6.0, $this->kpi->engagement(1, 2, 3, null));
        $this->assertSame(0.0, $this->kpi->engagement(0, 0, null, null));
        $this->assertNull($this->kpi->engagement(null, null, null, null));
    }

    public function test_engagement_rate_uses_impressions_then_reach(): void
    {
        $byImpressions = $this->kpi->engagementRate(25, 100, 50);
        $this->assertSame(0.25, $byImpressions['value']);
        $this->assertSame('impressions', $byImpressions['basis']);

        $byReach = $this->kpi->engagementRate(10, null, 40);
        $this->assertSame(0.25, $byReach['value']);
        $this->assertSame('reach', $byReach['basis']);

        $missing = $this->kpi->engagementRate(10, null, null);
        $this->assertNull($missing['value']);
        $this->assertSame(SocialMetric::NOT_AVAILABLE, $missing['availability']);
    }

    public function test_combined_rate_is_not_an_average_of_rates(): void
    {
        $likes = $this->kpi->sumAvailable([
            ['value' => 10, 'availability' => SocialMetric::AVAILABLE],
            ['value' => 30, 'availability' => SocialMetric::AVAILABLE],
        ]);
        $impressions = $this->kpi->sumAvailable([
            ['value' => 100, 'availability' => SocialMetric::AVAILABLE],
            ['value' => 50, 'availability' => SocialMetric::AVAILABLE],
        ]);
        $rate = $this->kpi->engagementRate($likes, $impressions, null);

        $this->assertEqualsWithDelta(40 / 150, $rate['value'], 0.00001);
        $this->assertNotEqualsWithDelta((0.10 + 0.60) / 2, $rate['value'], 0.00001);
    }

    public function test_missing_values_are_not_treated_as_zero(): void
    {
        $sum = $this->kpi->sumAvailable([
            ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE],
            ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE],
        ]);
        $this->assertNull($sum);
    }

    public function test_follower_growth_needs_two_dates(): void
    {
        $single = $this->kpi->followerGrowth([
            ['date' => '2026-09-01', 'value' => 100, 'availability' => SocialMetric::AVAILABLE],
        ]);
        $this->assertSame(SocialMetric::NOT_AVAILABLE, $single['availability']);
        $this->assertNull($single['change']);

        $growth = $this->kpi->followerGrowth([
            ['date' => '2026-09-02', 'value' => 110, 'availability' => SocialMetric::AVAILABLE],
            ['date' => '2026-09-01', 'value' => 100, 'availability' => SocialMetric::AVAILABLE],
        ]);
        $this->assertSame(10.0, $growth['change']);
        $this->assertSame(10.0, $growth['percent']);
    }

    public function test_achievement_percentage_and_status(): void
    {
        $onTrack = $this->kpi->achievement(32, 40, true);
        $this->assertSame(80.0, $onTrack['percent']);
        $this->assertSame(8.0, $onTrack['remaining']);
        $this->assertSame('In Progress', $onTrack['status']);

        $this->assertSame('On Track', $this->kpi->achievement(36, 40, true)['status']);
        $this->assertSame('Needs Attention', $this->kpi->achievement(10, 40, true)['status']);
        $this->assertSame('Not Available', $this->kpi->achievement(null, 40, false)['status']);
        $this->assertSame('Not Available', $this->kpi->achievement(10, null, true)['status']);
    }

    public function test_change_is_hidden_without_a_previous_value(): void
    {
        $hidden = $this->kpi->changePercent(10, null, true, false);
        $this->assertSame(SocialMetric::NOT_AVAILABLE, $hidden['availability']);

        $change = $this->kpi->changePercent(114, 100, true, true);
        $this->assertEqualsWithDelta(14.0, $change['value'], 0.00001);
    }

    public function test_date_and_platform_filters(): void
    {
        $rows = [
            ['platform' => 'facebook', 'account_id' => 1, 'date' => '2026-09-01', 'metric' => 'reach', 'value' => 5, 'availability' => 'available', 'content_type' => 'post'],
            ['platform' => 'instagram', 'account_id' => 2, 'date' => '2026-09-20', 'metric' => 'reach', 'value' => 9, 'availability' => 'available', 'content_type' => 'video'],
        ];
        $filtered = $this->kpi->filterRows($rows, [
            'platform' => 'instagram',
            'start' => '2026-09-15',
            'end' => '2026-09-30',
            'content_type' => 'video',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame(9, $filtered[0]['value']);
    }
}
