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

    public function test_saved_roi_slabs_do_not_change_the_price(): void
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
        ], 'bestbuy');

        $this->assertSame(100.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'live' => 12,
            'lp' => 10,
            'ship' => 8,
            'margin' => 0.80,
        ]));
    }

    public function test_bestbuy_raises_price_to_the_saved_min_npft_and_skips_clearance(): void
    {
        $rules = [
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 30]],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'min_npft' => 10,
        ];
        $row = [
            'inv' => 1,
            'std' => 100,
            'dil' => 5,
            'lp' => 40,
            'ship' => 10,
            'margin' => 0.80,
            'clearance' => false,
        ];

        // 30% off $100 is $70, NPFT 8.57%. Floor for 10% is (40+10)/(0.80−0.10) = $71.43.
        $price = (new StdPrcVsDilPricer($rules, 'bestbuy'))->priceFromRow($row);
        $this->assertSame(71.43, $price);
        $this->assertGreaterThanOrEqual(10, (($price * 0.80 - 10 - 40) / $price) * 100);

        $row['clearance'] = 'YES';
        $this->assertSame(70.0, (new StdPrcVsDilPricer($rules, 'bestbuy'))->priceFromRow($row));

        $rules['min_npft'] = 15;
        // (40+10)/(0.80−0.15) = $76.93.
        $row['clearance'] = false;
        $this->assertSame(76.93, (new StdPrcVsDilPricer($rules, 'bestbuy'))->priceFromRow($row));

        $rules['min_npft'] = 0;
        $this->assertSame(70.0, (new StdPrcVsDilPricer($rules, 'bestbuy'))->priceFromRow($row));

        $rules['min_npft'] = 10;
        $this->assertSame(70.0, (new StdPrcVsDilPricer($rules, 'ebay1'))->priceFromRow($row));
    }

    public function test_bestbuy_keeps_the_discount_when_npft_is_already_above_the_floor(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 10]],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'min_npft' => 10,
        ], 'bestbuy');

        $this->assertSame(90.0, $pricer->priceFromRow([
            'inv' => 1,
            'std' => 100,
            'dil' => 5,
            'lp' => 10,
            'ship' => 5,
            'margin' => 0.80,
            'clearance' => false,
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
