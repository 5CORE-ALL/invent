<?php

namespace Tests\Unit;

use App\Services\DilRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class DilRuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_in_slab_uses_target_groi_and_takehome(): void
    {
        $out = $this->compute('newegg', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // GROI 50% (0.1–5 slab): (20*1.5)/0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
    }

    public function test_zero_sold_still_uses_the_dil_matching_slab(): void
    {
        $rules = [
            AmazonDilGroiRule::make(0.1, 5.0, 80),
            AmazonDilGroiRule::make(5.0, 10.0, 40),
        ];
        $out = DilRuleSpriceApplyService::for('newegg')->computeTarget(
            [
                'inv' => 8,
                'dil' => 3,
                'ov_l30' => 0,
                'cvr' => 8,
                'lp' => 20,
                'ship' => 0,
                'lmp' => 0,
            ],
            $rules,
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );

        $this->assertNotNull($out);
        // 0 sold changes nothing: Dil 3 sits in the 0.1–5 / 80 slab, not the lowest slab: (20*1.8)/0.80 = 45.00
        $this->assertEqualsWithDelta(45.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(80.0, $out['groi'], 0.01);
    }

    public function test_temu2_zero_dil_uses_first_slab_not_min_skip(): void
    {
        $out = $this->compute('temu2', [
            'inv' => 5,
            'dil' => 0,
            'ov_l30' => 0,
            'cvr' => 8,
            'lp' => 10,
            'ship' => 2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // first slab GROI 50, no CVR adj (8 is between 7 and 10). Temu SGROI back-solve.
        $this->assertEqualsWithDelta(19.92, $out['sprice'], 0.01);
    }

    public function test_exclude_ship_on_wayfair(): void
    {
        $out = $this->compute('wayfair', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 8,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // ship ignored: (20*1.5)/0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
    }

    public function test_wayfair_diff_above_threshold_adds_points_to_target(): void
    {
        $out = $this->compute('wayfair', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 30, 'std_price' => 45,
        ]);

        $this->assertNotNull($out);
        // Diff = (45 - 30) / 30 = 50% > 20%: GROI 50 + 10 = 60, (20 * 1.6) / 0.80 = 40.00, under Std 45.
        $this->assertEqualsWithDelta(60.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(40.00, $out['sprice'], 0.01);
    }

    public function test_wayfair_diff_below_threshold_adds_nothing(): void
    {
        $out = $this->compute('wayfair', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 39, 'std_price' => 40,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
    }

    public function test_wayfair_std_price_is_the_maximum(): void
    {
        $out = $this->compute('wayfair', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 30, 'std_price' => 38,
        ]);

        $this->assertNotNull($out);
        // Diff 26.7% adds +10 -> 40.00, held at Std Prc 38.00.
        $this->assertEqualsWithDelta(38.00, $out['sprice'], 0.01);
    }

    public function test_doba_diff_above_threshold_adds_points_to_target_with_ship(): void
    {
        $out = $this->compute('doba', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 30, 'std_price' => 70,
        ]);

        $this->assertNotNull($out);
        // Diff = (70 - 30) / 30 = 133% > 20%: GROI 50 + 10 = 60, (20 * 1.6 + 8) / 0.80 = 50.00, under Std 70. Ship is in the price on Doba.
        $this->assertEqualsWithDelta(60.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(50.00, $out['sprice'], 0.01);
    }

    public function test_doba_diff_below_threshold_adds_nothing_and_std_price_is_the_maximum(): void
    {
        $below = $this->compute('doba', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 55, 'std_price' => 60,
        ]);
        $this->assertNotNull($below);
        $this->assertEqualsWithDelta(50.0, $below['groi'], 0.01);
        $this->assertEqualsWithDelta(47.50, $below['sprice'], 0.01);

        $capped = $this->compute('doba', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'cvr' => 8, 'lp' => 20, 'ship' => 8, 'lmp' => 0,
            'live' => 30, 'std_price' => 45,
        ]);
        $this->assertNotNull($capped);
        // Diff 50% adds +10 -> 50.00, held at Std Prc 45.00.
        $this->assertEqualsWithDelta(45.00, $capped['sprice'], 0.01);
    }

    public function test_temu_zero_sold_uses_order_l30_not_shopify_ov(): void
    {
        $out = $this->compute('temu', [
            'inv' => 58,
            'dil' => 26,
            'ov_l30' => 15,
            'temu_l30' => 0,
            'cvr' => 0,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // Temu L30 = 0 → min GROI 50, CVR 0 → −10 → 40. Dil slab is skipped.
        // S PRC is back-solved so Temu SGROI (S R × 0.95) equals 40%.
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(33.49, $out['sprice'], 0.01);
    }

    public function test_temu_zero_sold_formula_matches_on_page_sgroi(): void
    {
        $out = $this->compute('temu', [
            'inv' => 58,
            'dil' => 26,
            'ov_l30' => 15,
            'temu_l30' => 0,
            'cvr' => 0,
            'lp' => 9.22,
            'ship' => 7.2,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(23.65, $out['sprice'], 0.01);
        $sgroi = \App\Services\TemuShopifySalesService::sgroiAtSprice($out['sprice'], 9.22, 7.2, 0.0);
        $this->assertNotNull($sgroi);
        $this->assertEqualsWithDelta(40.0, $sgroi, 0.1);
    }

    public function test_temu_sold_uses_dil_slab(): void
    {
        $out = $this->compute('temu', [
            'inv' => 58,
            'dil' => 22,
            'ov_l30' => 15,
            'temu_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // Temu L30 > 0 → Dil 22% uses 20–25 slab GROI 70 (not 0 Sold min).
        $this->assertEqualsWithDelta(70.0, $out['groi'], 0.01);
    }

    public function test_cvr_down_lowers_groi_on_temu(): void
    {
        $out = $this->compute('temu', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 5,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // 50 + (−10) = 40. Temu S PRC so SGROI = 40%.
        $this->assertEqualsWithDelta(33.49, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
    }

    public function test_newegg_raises_to_amz_floor(): void
    {
        $out = $this->compute('newegg', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
            'amz_price' => 40,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['sprice'], 0.01);
    }

    public function test_inv_zero_skips(): void
    {
        $this->assertNull($this->compute('newegg', [
            'inv' => 0,
            'dil' => 3,
            'ov_l30' => 2,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_sold_out_of_slab_skips_on_min_groi_channels(): void
    {
        $this->assertNull($this->compute('newegg', [
            'inv' => 10,
            'dil' => 80,
            'ov_l30' => 5,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_channels_from_arg(): void
    {
        $this->assertSame(DilRuleSpriceApplyService::CHANNELS, DilRuleSpriceApplyService::channelsFromArg('all'));
        $this->assertSame(['fb_marketplace'], DilRuleSpriceApplyService::channelsFromArg('fb'));
        $this->assertSame(['walmart'], DilRuleSpriceApplyService::channelsFromArg('wm'));
        $this->assertSame([], DilRuleSpriceApplyService::channelsFromArg('pls'));
        $this->assertSame([], DilRuleSpriceApplyService::channelsFromArg('ebay3'));
        $this->assertNotContains('fb_marketplace', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertNotContains('doba', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertNotContains('aliexpress', DilRuleSpriceApplyService::CHANNELS);
        $this->assertNotContains('aliexpress', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertNotContains('reverb', DilRuleSpriceApplyService::CHANNELS);
        $this->assertNotContains('reverb', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertNotContains('topdawg', DilRuleSpriceApplyService::CHANNELS);
        $this->assertNotContains('topdawg', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertNotContains('bestbuy', DilRuleSpriceApplyService::CHANNELS);
        $this->assertNotContains('bestbuy', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertContains('walmart', DilRuleSpriceApplyService::PUSH_CHANNELS);
        foreach (['pls', 'tiktok', 'tiktok2', 'shein'] as $moved) {
            $this->assertNotContains($moved, DilRuleSpriceApplyService::CHANNELS);
            $this->assertNotContains($moved, DilRuleSpriceApplyService::PUSH_CHANNELS);
        }
    }

    public function test_wayfair_clearance_is_priced_only_from_the_clearance_nroi(): void
    {
        // LP 20, Ship excluded on Wayfair, take-home 0.80, Clearance NROI 10%: (20 × 1.10) ÷ 0.80 = 27.50.
        // The slab, Diff rule and Std max (std 25) do not touch it.
        $out = $this->compute('wayfair', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'lp' => 20, 'ship' => 6, 'live' => 40, 'std_price' => 25,
            'clearance' => true, 'clearance_nroi' => 10,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(27.50, $out['sprice'], 0.001);
        $this->assertNull($this->compute('wayfair', ['inv' => 10, 'lp' => 0, 'clearance' => true, 'clearance_nroi' => 10]));
    }

    public function test_temu3_clearance_is_priced_only_from_the_clearance_nroi(): void
    {
        // Same Temu price formula as the Sprc Dil column: the S PRC that lands SGROI on the 10% clearance target. No slab or LMP cap.
        $out = $this->compute('temu3', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'lp' => 20, 'ship' => 3, 'lmp' => 25, 'live' => 40,
            'clearance' => true, 'clearance_nroi' => 10,
        ]);

        $this->assertNotNull($out);
        $expected = round(\App\Services\TemuShopifySalesService::spriceFromTargetSgroi(20.0, 3.0, 10.0, 0.0), 2);
        $this->assertEqualsWithDelta($expected, $out['sprice'], 0.001);
        $this->assertGreaterThan(25.0, $out['sprice']);
    }

    public function test_newegg_clearance_is_priced_only_from_the_clearance_nroi_without_the_a_price_floor(): void
    {
        // LP 20, Ship 3, take-home 0.80, Clearance NROI 10%: (20 × 1.10 + 3) ÷ 0.80 = 31.25. Same as the page's C price.
        $out = $this->compute('newegg', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'lp' => 20, 'ship' => 3, 'amz' => 50,
            'clearance' => true, 'clearance_nroi' => 10,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(31.25, $out['sprice'], 0.001);
    }

    public function test_other_channels_still_give_clearance_no_price(): void
    {
        $this->assertNull($this->compute('doba', [
            'inv' => 10, 'dil' => 3, 'ov_l30' => 2, 'lp' => 20, 'ship' => 0, 'clearance' => true, 'clearance_nroi' => 10,
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{sprice: float, groi: float}|null
     */
    private function compute(string $channel, array $row): ?array
    {
        return DilRuleSpriceApplyService::for($channel)->computeTarget(
            $row,
            AmazonDilGroiRule::defaults(),
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );
    }
}
