<?php

namespace Tests\Unit;

use App\Support\DarL30Metrics;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DarL30MetricsTest extends TestCase
{
    public function test_percent_is_submitted_days_over_target_of_25(): void
    {
        $this->assertSame(0, DarL30Metrics::percent(0));
        $this->assertSame(80, DarL30Metrics::percent(20));
        $this->assertSame(92, DarL30Metrics::percent(23));
        $this->assertSame(100, DarL30Metrics::percent(25));
        $this->assertSame(120, DarL30Metrics::percent(30));
    }

    public function test_band_matches_requested_thresholds(): void
    {
        $this->assertSame('low', DarL30Metrics::band(0));
        $this->assertSame('low', DarL30Metrics::band(79));
        $this->assertSame('mid', DarL30Metrics::band(80));
        $this->assertSame('mid', DarL30Metrics::band(90));
        $this->assertSame('high', DarL30Metrics::band(91));
        $this->assertSame('high', DarL30Metrics::band(120));
    }

    public function test_series_covers_rolling_30_days_and_counts_unique_dates(): void
    {
        $end = Carbon::parse('2026-09-06');
        $metrics = DarL30Metrics::forUser([
            '2026-09-06',
            '2026-09-06',
            '2026-09-05',
            '2026-08-07',
            '2026-08-06',
        ], $end);

        $this->assertCount(30, $metrics['dar_l30_series']);
        $this->assertSame('2026-08-08', $metrics['dar_l30_series'][0]['date']);
        $this->assertSame('2026-09-06', $metrics['dar_l30_series'][29]['date']);
        $this->assertSame(2, $metrics['dar_l30_count']);
        $this->assertSame(8, $metrics['dar_l30_pct']);
        $this->assertSame('low', $metrics['dar_l30_band']);
        $this->assertTrue($metrics['dar_l30_series'][0]['weekend']);
        $this->assertSame(1, $metrics['dar_l30_series'][28]['submitted']);
        $this->assertSame(1, $metrics['dar_l30_series'][29]['submitted']);
    }

    public function test_sparkline_renders_one_bar_per_day(): void
    {
        $series = DarL30Metrics::series(['2026-09-06'], Carbon::parse('2026-09-06'));
        $svg = DarL30Metrics::sparklineSvg($series);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertSame(30, substr_count($svg, '<rect'));
    }
}
