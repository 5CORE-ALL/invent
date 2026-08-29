<?php

namespace Tests\Unit;

use App\Support\Marketplace\ChannelMetricDotPair;
use PHPUnit\Framework\TestCase;

class ChannelMetricDotPairTest extends TestCase
{
    public function test_walks_back_past_duplicate_days(): void
    {
        $this->assertSame(
            [8178.8, 8585.0],
            ChannelMetricDotPair::lastTwoDistinct([8585.0, 8585.0, 8178.8, 8000.0], 0.01)
        );
    }

    public function test_flat_history_stays_equal(): void
    {
        $this->assertSame(
            [100.0, 100.0],
            ChannelMetricDotPair::lastTwoDistinct([100.0, 100.0, 100.0], 0.01)
        );
    }

    public function test_pins_live_latest_without_losing_baseline(): void
    {
        $this->assertSame(
            [9179.5, 8585.0],
            ChannelMetricDotPair::pinLatest(9179.5, 9179.5, 8585.0)
        );
    }

    public function test_cvr_epsilon_treats_6_42_as_flat(): void
    {
        $this->assertSame(
            [6.39, 6.42],
            ChannelMetricDotPair::lastTwoDistinct([6.42, 6.42, 6.39], 0.005)
        );
    }
}
