<?php

namespace Tests\Unit;

use App\Support\AttL30Metrics;
use PHPUnit\Framework\TestCase;

class AttL30MetricsTest extends TestCase
{
    public function test_window_is_last_30_days_against_200_hours(): void
    {
        $this->assertSame(30, AttL30Metrics::WINDOW_DAYS);
        $this->assertSame(200, AttL30Metrics::TARGET_HOURS);
        $this->assertSame(12, AttL30Metrics::MAX_DAY_HOURS);
        $this->assertSame(300, AttL30Metrics::MAX_WINDOW_HOURS);
    }

    public function test_day_hours_prefer_active_and_cap_at_12(): void
    {
        $this->assertSame(0.0, AttL30Metrics::dayHoursFromSeconds(0, 0));
        $this->assertSame(8.0, AttL30Metrics::dayHoursFromSeconds(8 * 3600, 16 * 3600));
        $this->assertSame(9.0, AttL30Metrics::dayHoursFromSeconds(0, 9 * 3600));
        $this->assertSame(12.0, AttL30Metrics::dayHoursFromSeconds(20 * 3600, 20 * 3600));
    }

    public function test_window_hours_never_exceed_300(): void
    {
        $this->assertSame(220.0, AttL30Metrics::clampWindowHours(220));
        $this->assertSame(300.0, AttL30Metrics::clampWindowHours(435));
        $this->assertSame(300.0, AttL30Metrics::forHours(435)['att_l30_hours']);
        $this->assertSame(150, AttL30Metrics::forHours(435)['att_l30_pct']);
    }

    public function test_percent_is_hours_over_target_of_200(): void
    {
        $this->assertSame(0, AttL30Metrics::percent(0));
        $this->assertSame(20, AttL30Metrics::percent(40));
        $this->assertSame(80, AttL30Metrics::percent(160));
        $this->assertSame(90, AttL30Metrics::percent(180));
        $this->assertSame(91, AttL30Metrics::percent(181.6));
        $this->assertSame(100, AttL30Metrics::percent(200));
        $this->assertSame(110, AttL30Metrics::percent(220));
    }

    public function test_band_matches_dar_color_thresholds(): void
    {
        $this->assertSame('low', AttL30Metrics::band(79));
        $this->assertSame('mid', AttL30Metrics::band(80));
        $this->assertSame('mid', AttL30Metrics::band(90));
        $this->assertSame('high', AttL30Metrics::band(91));
    }

    public function test_for_hours_returns_display_fields(): void
    {
        $metrics = AttL30Metrics::forHours(160);

        $this->assertSame(160.0, $metrics['att_l30_hours']);
        $this->assertSame(200, $metrics['att_l30_target']);
        $this->assertSame(80, $metrics['att_l30_pct']);
        $this->assertSame('mid', $metrics['att_l30_band']);
    }

    public function test_only_shobha_and_mariya_use_team_logger(): void
    {
        $this->assertTrue(AttL30Metrics::usesTeamLogger('Shobha', 'shobha@5core.com'));
        $this->assertTrue(AttL30Metrics::usesTeamLogger('Mariya K'));
        $this->assertFalse(AttL30Metrics::usesTeamLogger('Archana'));
        $this->assertFalse(AttL30Metrics::usesTeamLogger('John Smith', 'john@5core.com'));
    }
}
