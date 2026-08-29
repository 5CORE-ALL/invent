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

    public function test_flat_last_step_stays_gray_when_adjacent(): void
    {
        $this->assertSame(
            [8585.0, 8585.0],
            ChannelMetricDotPair::lastTwoAdjacent([8585.0, 8585.0, 8178.8])
        );
    }

    public function test_distinct_walks_back_past_duplicate_last_days(): void
    {
        $this->assertSame(
            [517.67, 337.31],
            ChannelMetricDotPair::lastTwoDistinct([337.31, 337.31, 517.67, 955.39])
        );
    }

    public function test_distinct_temu2_frozen_y_walks_to_last_move(): void
    {
        $this->assertSame(
            [585.0, 659.44],
            ChannelMetricDotPair::lastTwoDistinct([659.44, 659.44, 659.44, 585.0])
        );
    }

    public function test_distinct_truly_flat_stays_gray(): void
    {
        $this->assertSame(
            [165.0, 165.0],
            ChannelMetricDotPair::lastTwoDistinct([165.0, 165.0, 165.0])
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
