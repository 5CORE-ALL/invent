<?php

namespace Tests\Unit;

use App\Support\Marketplace\ChannelMetricDotPair;
use PHPUnit\Framework\TestCase;

class ChannelMetricDotPairTest extends TestCase
{
    public function test_adjacent_days_not_older_different_day(): void
    {
        $this->assertSame(
            [8585.0, 8589.0],
            ChannelMetricDotPair::lastTwoAdjacent([8589.0, 8585.0, 9294.47])
        );
    }

    public function test_flat_last_step_stays_gray(): void
    {
        $this->assertSame(
            [8585.0, 8585.0],
            ChannelMetricDotPair::lastTwoAdjacent([8585.0, 8585.0, 8178.8])
        );
    }

    public function test_pins_saved_table_value_as_latest(): void
    {
        $this->assertSame(
            [8585.0, 8585.0],
            ChannelMetricDotPair::pinLatest(8585.0, 8589.0, 8585.0)
        );
    }

    public function test_pins_live_when_it_is_the_new_latest(): void
    {
        $this->assertSame(
            [9179.5, 8585.0],
            ChannelMetricDotPair::pinLatest(9179.5, 9179.5, 8585.0)
        );
    }
}
