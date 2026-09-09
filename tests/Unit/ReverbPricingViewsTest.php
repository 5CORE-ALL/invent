<?php

namespace Tests\Unit;

use App\Support\ReverbPricingViews;
use PHPUnit\Framework\TestCase;

class ReverbPricingViewsTest extends TestCase
{
    public function test_views_are_divided_by_1000(): void
    {
        $this->assertSame(364.685, ReverbPricingViews::scale(364685));
        $this->assertSame(0.0, ReverbPricingViews::scale(0));
    }

    public function test_cvr_uses_views_after_divide_by_1000(): void
    {
        // 10 sold ÷ (364685 ÷ 1000) × 100 ≈ 2.74 → 3
        $this->assertSame(3.0, ReverbPricingViews::cvrPercent(10, 364685));
        $this->assertSame(2.74, ReverbPricingViews::cvrPercent(10, 364685, 2));
        $this->assertSame(0.0, ReverbPricingViews::cvrPercent(10, 0));
    }

    public function test_scaled_views_round_trip_to_raw_impressions(): void
    {
        $this->assertSame(364685, ReverbPricingViews::toRawImpressions(ReverbPricingViews::scale(364685)));
    }
}
