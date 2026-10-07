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

    public function test_std_under_15_halves_each_rule_discount(): void
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

        // 10% + 5% + 4% = 19%, then 0.5× → 9.5%. $10 × 0.905 = $9.05.
        $this->assertSame(9.05, $pricer->priceFromRow([
            'inv' => 2,
            'std' => 10,
            'dil' => 5,
            'age_days' => 10,
            'review_count' => 1,
            'cvr' => 8,
        ]));
        // $15 is not below $15, so the full 19% still applies: $15 × 0.81 = $12.15.
        $this->assertSame(12.15, $pricer->priceFromRow([
            'inv' => 2,
            'std' => 15,
            'dil' => 5,
            'age_days' => 10,
            'review_count' => 1,
            'cvr' => 8,
        ]));
    }

    public function test_buss_discount_comes_off_std_by_price_range(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 15, 'disc' => 10],
                ['min' => 15, 'max' => 50, 'disc' => 4],
            ],
        ]);

        // $20 is in 15–50 → 4%. $20 × 0.96 = $19.20.
        $this->assertSame(19.2, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 20,
        ]));
        // $10 is under $15. B Disc stays at the full 10%. $10 × 0.90 = $9.00.
        $this->assertSame(9.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 10,
        ]));
    }

    public function test_zero_sold_discount_applies_only_when_sold_qty_is_zero(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'zero_sold_disc' => 10,
        ], 'ebay1');

        $this->assertSame(90.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'ebay_l30' => 0,
        ]));
        $this->assertSame(100.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'ebay_l30' => 3,
        ]));
        $this->assertSame(9.5, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 10,
            'ebay_l30' => 0,
        ]));
    }

    public function test_zero_cvr_is_down_like_amazon(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
        ]);

        $this->assertSame(92.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'cvr' => 0,
        ]));
    }

    public function test_roi_discount_uses_groi_slabs_and_halves_under_15(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'roi' => [
                ['min' => 0, 'max' => 50, 'disc' => 10],
                ['min' => 50, 'max' => 9999, 'disc' => 2],
            ],
        ], 'shopify_b2b');

        // Price $20 × 0.95 − LP $10 = GROI 90% → 2%. $100 × 0.98 = $98.
        $this->assertSame(98.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'live' => 20,
            'lp' => 10,
            'margin' => 0.95,
        ]));
        // Price $12 × 0.95 − $10 = GROI 14% → 10%. $100 × 0.90 = $90.
        $this->assertSame(90.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'live' => 12,
            'lp' => 10,
            'margin' => 0.95,
        ]));
        // Std under $15 halves the 10% ROI disc. $10 × 0.95 = $9.50.
        $this->assertSame(9.5, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 10,
            'live' => 12,
            'lp' => 10,
            'margin' => 0.95,
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
