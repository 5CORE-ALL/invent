<?php

namespace Tests\Unit;

use App\Support\MissedPeriodMetrics;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MissedPeriodMetricsTest extends TestCase
{
    public function test_bucket_splits_last_30_from_prior_30(): void
    {
        $now = Carbon::parse('2026-09-18 12:00:00');

        $this->assertSame('l30', MissedPeriodMetrics::bucket($now->copy()->subDays(0), $now));
        $this->assertSame('l30', MissedPeriodMetrics::bucket($now->copy()->subDays(30), $now));
        $this->assertSame('p30', MissedPeriodMetrics::bucket($now->copy()->subDays(31), $now));
        $this->assertSame('p30', MissedPeriodMetrics::bucket($now->copy()->subDays(60), $now));
        $this->assertNull(MissedPeriodMetrics::bucket($now->copy()->subDays(61), $now));
        $this->assertNull(MissedPeriodMetrics::bucket(null, $now));
    }
}
