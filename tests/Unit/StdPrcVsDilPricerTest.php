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

    public function test_pricing_discount_is_added_to_the_sum_by_std_price_range(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 50, 'disc' => 4],
            ],
            'pricing' => [
                ['min' => 0, 'max' => 15, 'disc' => 6],
                ['min' => 15, 'max' => 50, 'disc' => 2],
            ],
        ]);

        // $20: B Disc 4% + Pricing 2% = 6%. $20 × 0.94 = $18.80.
        $this->assertSame(18.8, $pricer->priceFromRow(['inv' => 1, 'std' => 20]));
        // $10: B Disc 4% + Pricing 6% (full, not halved under $15) = 10%. $10 × 0.90 = $9.00.
        $this->assertSame(9.0, $pricer->priceFromRow(['inv' => 1, 'std' => 10]));
    }

    public function test_negative_discounts_offset_the_other_discounts(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 9999, 'disc' => 10],
            ],
            'pricing' => [
                ['min' => 0, 'max' => 9999, 'disc' => -4],
            ],
            'roi' => [
                ['min' => -9999, 'max' => 0, 'dil_min' => 0, 'dil_max' => 9999, 'disc' => -3],
            ],
        ]);

        // B Disc 10% + Pricing -4% = 6%. $20 × 0.94 = $18.80.
        $this->assertSame(18.8, $pricer->priceFromRow(['inv' => 1, 'std' => 20]));
        // A negative GROI% (price 10, LP 20) also takes its slab: 10 − 4 − 3 = 3%. $20 × 0.97 = $19.40.
        $this->assertSame(19.4, $pricer->priceFromRow(['inv' => 1, 'std' => 20, 'price' => 10, 'lp' => 20]));
    }

    public function test_negative_net_discount_raises_the_price_above_std(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 9999, 'disc' => -8],
            ],
        ]);

        // Net −8%: Std Prc is not a cap. $20 × 1.08 = $21.60.
        $this->assertSame(21.6, $pricer->priceFromRow(['inv' => 1, 'std' => 20]));
    }

    public function test_roi_discount_needs_both_the_groi_slab_and_the_dil_slab(): void
    {
        $pricer = new StdPrcVsDilPricer([
            'dil' => [],
            'age' => [],
            'cvr' => ['flat_disc' => 0],
            'reviews' => [],
            'review_max' => 4,
            'roi' => [
                ['min' => 50, 'max' => 75, 'dil_min' => 10, 'dil_max' => 25, 'disc' => 5],
                ['min' => 75, 'max' => 125, 'dil_min' => 0, 'dil_max' => 9999, 'disc' => 4],
            ],
        ]);
        // GROI% = (20 × 0.8 − 0 − 10) ÷ 10 = 60%.
        $row = ['inv' => 1, 'std' => 20, 'price' => 20, 'lp' => 10, 'margin' => 0.8, 'ship' => 0];

        // Dil 15 is inside 10–25 → 5%. $20 × 0.95 = $19.00.
        $this->assertSame(19.0, $pricer->priceFromRow($row + ['dil' => 15]));
        // Dil 5 is outside the slab's Dil range → no ROI disc.
        $this->assertSame(20.0, $pricer->priceFromRow($row + ['dil' => 5]));
        // No listing price → no GROI% → no ROI disc.
        $this->assertSame(20.0, $pricer->priceFromRow(['inv' => 1, 'std' => 20, 'dil' => 15]));
        // $12 Std is under $15: ROI disc is halved. GROI% = (12 × 0.8 − 5) ÷ 5 = 92% → 4% → 2%. $12 × 0.98 = $11.76.
        $this->assertSame(11.76, $pricer->priceFromRow(['inv' => 1, 'std' => 12, 'price' => 12, 'lp' => 5, 'margin' => 0.8, 'dil' => 0]));
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

    public function test_moq_m_disc_uses_dil_moq_percent(): void
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
            'moq_m' => [['min' => 200, 'max' => 9999, 'disc' => 10]],
        ]);
        $row = ['inv' => 1, 'std_price' => 100, 'dil' => 0, 'cvr' => 8, 'cvr_60' => 8, 'sold' => 1];

        // MOQ 12 → Req M 3. OV L30 7 → Dil MOQ 233%, inside 200–9999.
        $this->assertSame(90.0, $pricer->priceFromRow($row + ['moq' => 12, 'ov_l30' => 7]));
        $this->assertSame(100.0, $pricer->priceFromRow($row + ['ov_l30' => 7]));
    }

    public function test_cap_price_to_snroi_lowers_only_and_floors_to_the_cent(): void
    {
        // LP 50, margin 0.95: price 100 → SNROI (95 − 50) / 50 = 90%. Target 60% → (50 × 1.6) / 0.95 = 84.2105… → 84.21.
        $this->assertSame(84.21, StdPrcVsDilPricer::capPriceToSnroi(100.0, 60.0, 50.0, 0.95));
        // SNROI at 84.21 is 59.99…%, never above the target.
        $this->assertLessThanOrEqual(60.0, ((84.21 * 0.95) - 50) / 50 * 100);
        // Already under the target: unchanged. A cap never raises a price.
        $this->assertSame(70.0, StdPrcVsDilPricer::capPriceToSnroi(70.0, 60.0, 50.0, 0.95));
        // Exactly on target: unchanged.
        $this->assertSame(80.0, StdPrcVsDilPricer::capPriceToSnroi(80.0, 52.0, 50.0, 0.95));
        // No LP: nothing to cap against.
        $this->assertSame(100.0, StdPrcVsDilPricer::capPriceToSnroi(100.0, 60.0, 0.0, 0.95));
        // Ship counts when the channel pays it: (LP × 1.6 + 5) / 0.95.
        $this->assertSame(89.47, StdPrcVsDilPricer::capPriceToSnroi(100.0, 60.0, 50.0, 0.95, 5.0));
    }

    public function test_snroi_cap_rules_are_normalized(): void
    {
        $rules = StdPrcVsDilPricer::normalizeSnroiCapRules([
            ['min' => '0', 'max' => '10', 'cap' => '60'],
            ['min' => '30', 'max' => '20', 'cap' => ''],
            ['min' => 'x', 'max' => '5', 'cap' => '1'],
            'nope',
        ]);

        $this->assertSame([
            ['min' => 0.0, 'max' => 10.0, 'cap' => 60.0],
            ['min' => 20.0, 'max' => 30.0, 'cap' => null],
        ], $rules);
        $this->assertSame([], StdPrcVsDilPricer::normalizeSnroiCapRules(null));
    }

    public function test_snroi_cap_lowers_the_price_by_dil_slab_on_every_channel(): void
    {
        $rules = [
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'snroi_cap' => [
                ['min' => 0, 'max' => 20, 'cap' => 60],
                ['min' => 20, 'max' => 100, 'cap' => null],
            ],
        ];
        $row = ['inv' => 1, 'std_price' => 100, 'cvr' => 8, 'cvr_60' => 8, 'sold' => 1, 'lp' => 50, 'margin' => 0.95];

        $wayfair = new StdPrcVsDilPricer($rules, 'wayfair');
        // Dil 10 is in the 60% cap slab: 100 → 84.21.
        $this->assertSame(84.21, $wayfair->priceFromRow($row + ['dil' => 10]));
        // Dil 50 is in a slab with no cap.
        $this->assertSame(100.0, $wayfair->priceFromRow($row + ['dil' => 50]));
        // Every other channel page has the same SNROI Cap.
        $this->assertSame(84.21, (new StdPrcVsDilPricer($rules, 'faire'))->priceFromRow($row + ['dil' => 10]));
        // Only Amazon and the no-Ship pages have the SNROI Cap. Pages with Ship (eBay, Reverb, Best Buy, Temu ...) price from
        // their discounts only, so the same Dil slab leaves the Std price alone.
        foreach (['reverb', 'ebay2', 'bestbuy', 'newtemuone', 'shopify_b2c'] as $channel) {
            $this->assertSame(100.0, (new StdPrcVsDilPricer($rules, $channel))->priceFromRow($row + ['dil' => 10, 'ship' => 5, 'ads' => 0.10]), $channel);
            $this->assertNull((new StdPrcVsDilPricer($rules, $channel))->snroiCapTargetForDil(10.0), $channel);
        }
        // The no-Ship pages keep it.
        foreach (['wayfair', 'faire', 'topdawg', 'alibaba', 'shopify_b2b', 'amazon'] as $channel) {
            $this->assertTrue(StdPrcVsDilPricer::supportsSnroiCap($channel), $channel);
        }
    }

    public function test_snroi_cap_slab_prices_from_the_target_not_the_discounts_and_std_is_the_maximum(): void
    {
        $rules = [
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 10]],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'snroi_cap' => [
                ['min' => 0, 'max' => 20, 'cap' => 60],
                ['min' => 20, 'max' => 50, 'cap' => 500],
                ['min' => 50, 'max' => 100, 'cap' => null],
            ],
        ];
        $row = ['inv' => 1, 'std_price' => 100, 'cvr' => 8, 'cvr_60' => 8, 'sold' => 1, 'lp' => 50, 'margin' => 0.95];
        $pricer = new StdPrcVsDilPricer($rules, 'faire');

        // Capped slab: S PRC = (50 x 1.6) / 0.95 = 84.21 even though the 10% discount price (90) is higher.
        $this->assertSame(84.21, $pricer->priceFromRow($row + ['dil' => 10]));
        // The discount price is BELOW the target price: S PRC goes up to the target, not down to the discount.
        $this->assertSame(84.21, $pricer->priceFromRow($row + ['dil' => 10, 'std_price' => 100, 'lp' => 50]));
        // A target that needs more than Std holds at Std Prc.
        $this->assertSame(100.0, $pricer->priceFromRow($row + ['dil' => 30]));
        // Slab with no cap: the discounts apply.
        $this->assertSame(90.0, $pricer->priceFromRow($row + ['dil' => 70]));
    }
}
