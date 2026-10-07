<?php

namespace Tests\Unit;

use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class StdPrcVsDilPricerTest extends TestCase
{
    public function test_price_is_std_minus_age_dil_and_review(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [['min' => 0, 'max' => 10, 'disc' => 5]],
            'age' => [['min' => 0, 'max' => 30, 'disc' => 10]],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [['min' => 1, 'max' => 2, 'disc' => 4]],
            'review_max' => 4,
        ]);

        $this->assertSame(81.0, $pricer->priceFromRow([
            'inv' => 2,
            'std' => 100,
            'dil' => 5,
            'age_days' => 10,
            'review_count' => 1,
            'cvr' => 8,
        ]));
    }

    public function test_down_cvr_uses_the_tighter_slab(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 1,
            ],
            'reviews' => [],
            'review_max' => 4,
        ]);

        $this->assertSame(92.0, $pricer->priceFromRow([
            'inv' => 1,
            'std_price' => 100,
            'dil' => 20,
            'cvr' => 3,
            'cvr_60' => 12,
        ]));
    }

    public function test_zero_inventory_skips(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
        ]);

        $this->assertNull($pricer->priceFromRow(['inv' => 0, 'std' => 50]));
        $this->assertNull($pricer->priceFromRow(['inv' => 3, 'std' => 0]));
    }
}
