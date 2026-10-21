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

    public function test_alibaba_zero_sold_discount_uses_ab_l30_not_ov_l30(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'zero_sold_disc' => 10,
        ], 'alibaba');

        $this->assertSame(90.0, $pricer->priceFromRow([
            'INV' => 4,
            'std' => 100,
            'al30' => 0,
            'L30' => 12,
        ]));
        $this->assertSame(100.0, $pricer->priceFromRow([
            'INV' => 4,
            'std' => 100,
            'al30' => 2,
            'L30' => 0,
        ]));
        $this->assertSame(100.0, $pricer->priceFromRow([
            'INV' => 4,
            'std' => 100,
            'L30' => 0,
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

    public function test_bestbuy_keeps_the_discount_when_npft_is_already_above_the_floor(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 10]],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
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

    /** @return array<string, mixed> */
    private function tiktokRules(): array
    {
        return [
            'dil' => [], 'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [], 'review_max' => 4,
            'zero_sold_disc' => 6,
        ];
    }

    public function test_tiktok_zero_sold_uses_tt_l30(): void
    {
        foreach (['tiktok', 'tiktok2'] as $channel) {
            $pricer = new StdPrcVsDilPricer($this->tiktokRules(), $channel);
            $row = ['inv' => 5, 'std_price' => 100, 'dil' => 0, 'lp' => 10, 'ship' => 0, 'margin' => 0.9];

            // ov_l30 (metric sold) must not decide 0 Sold, only TT L30 does.
            $this->assertSame(94.0, $pricer->priceFromRow($row + ['tt_l30' => 0.0, 'ov_l30' => 9.0]));
            $this->assertSame(100.0, $pricer->priceFromRow($row + ['tt_l30' => 3.0, 'ov_l30' => 0.0]));
            // Unknown sold source is never read as 0 sold.
            $this->assertSame(100.0, $pricer->priceFromRow($row + ['tt_l30' => null, 'ov_l30' => 0.0]));
        }
    }

    public function test_best_buy_skips_cvr_and_other_pages_do_not(): void
    {
        $this->assertTrue(StdPrcVsDilPricer::skipsCvr('bestbuy'));
        $this->assertFalse(StdPrcVsDilPricer::skipsCvr('amazon'));
        $this->assertFalse(StdPrcVsDilPricer::skipsCvr('purchasing_power'));
    }

    public function test_best_buy_does_not_apply_amazon_cvr_when_cvr_is_missing(): void
    {
        $rules = [
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 6, 'down_lt' => 7, 'down_disc' => 4,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
        ];
        $row = ['inv' => 2, 'std' => 100, 'cvr' => 0, 'lp' => 10, 'ship' => 0, 'margin' => 0.9];

        // Same missing CVR on Amazon is "down" → 6% off.
        $amazon = new StdPrcVsDilPricer($rules, 'amazon');
        $this->assertSame(94.0, $amazon->priceFromRow($row));

        // Best Buy has no session CVR, so the slab does not fire.
        $bestbuy = new StdPrcVsDilPricer($rules, 'bestbuy');
        $this->assertSame(100.0, $bestbuy->priceFromRow($row));
    }

    public function test_lmp_disc_applies_only_when_the_sku_has_an_lmp(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'lmp' => [['min' => 0, 'max' => 10, 'disc' => 10]],
        ]);
        $row = ['inv' => 1, 'std_price' => 100, 'dil' => 5, 'cvr' => 8, 'cvr_60' => 8];

        $this->assertSame(90.0, $pricer->priceFromRow($row + ['lmp' => 17.07]));
        $this->assertSame(100.0, $pricer->priceFromRow($row));
    }
}
