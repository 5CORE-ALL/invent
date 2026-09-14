<?php

namespace Tests\Unit;

use App\Services\DilRuleSpriceApplyService;
use App\Support\AliexpressPushGuard;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class DilRuleSpriceApplyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AliexpressPushGuard::setStopLowSgroi(false, 30);
    }

    public function test_dil_in_slab_uses_target_groi_and_takehome(): void
    {
        $out = $this->compute('bestbuy', [
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

    public function test_zero_sold_uses_min_target_groi(): void
    {
        $rules = [
            AmazonDilGroiRule::make(0.1, 5.0, 80),
            AmazonDilGroiRule::make(5.0, 10.0, 40),
        ];
        $out = DilRuleSpriceApplyService::for('bestbuy')->computeTarget(
            [
                'inv' => 8,
                'dil' => 0,
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
        // min slab GROI is 40, not the 0.1–5 / 80 slab: (20*1.4)/0.80 = 35.00
        $this->assertEqualsWithDelta(35.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
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

    public function test_bestbuy_raises_to_amz_floor(): void
    {
        $out = $this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
            'amz_price' => 45,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(45.0, $out['sprice'], 0.01);
    }

    public function test_bestbuy_caps_at_lmp_after_amz_floor(): void
    {
        $out = $this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 40,
            'amz_price' => 45,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['sprice'], 0.01);
    }

    public function test_bestbuy_keeps_dil_when_at_or_above_amz(): void
    {
        $out = $this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 2,
            'cvr' => 8,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
            'amz_price' => 30,
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
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
        $this->assertNull($this->compute('bestbuy', [
            'inv' => 0,
            'dil' => 3,
            'ov_l30' => 2,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_sold_out_of_slab_skips_on_min_groi_channels(): void
    {
        $this->assertNull($this->compute('bestbuy', [
            'inv' => 10,
            'dil' => 80,
            'ov_l30' => 5,
            'lp' => 20,
            'ship' => 0,
        ]));
    }

    public function test_shein_zero_sold_uses_dil_slab_not_min_groi(): void
    {
        $rules = [
            AmazonDilGroiRule::make(0.1, 5.0, 80),
            AmazonDilGroiRule::make(5.0, 10.0, 40),
        ];
        $out = DilRuleSpriceApplyService::for('shein')->computeTarget(
            [
                'inv' => 8,
                'dil' => 0,
                'ov_l30' => 0,
                'al30' => 0,
                'cvr' => 0,
                'views' => 0,
                'lp' => 20,
                'ship' => 0,
                'lmp' => 0,
            ],
            $rules,
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );

        $this->assertNotNull($out);
        // Dil 0 clamps to first slab GROI 80, not min GROI 40. No views → no CVR adj.
        // (20 * 1.80) / 0.80 = 45.00
        $this->assertEqualsWithDelta(45.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(80.0, $out['groi'], 0.01);
    }

    public function test_shein_high_dil_clamps_to_last_slab(): void
    {
        $out = $this->compute('shein', [
            'inv' => 10,
            'dil' => 80,
            'ov_l30' => 5,
            'al30' => 5,
            'cvr' => 8,
            'views' => 100,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // last default slab 20–25 GROI 70; CVR 8 is mid-band so no adj.
        // (20 * 1.70) / 0.80 = 42.50
        $this->assertEqualsWithDelta(42.50, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(70.0, $out['groi'], 0.01);
    }

    public function test_shein_cvr_overlay_skips_without_views(): void
    {
        $out = $this->compute('shein', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 0,
            'al30' => 0,
            'cvr' => 0,
            'views' => 0,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);

        $this->assertNotNull($out);
        // 0.1–5 slab GROI 50. CVR 0 would be −10 if views existed.
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
    }

    public function test_shein_cvr_overlay_applies_when_views_exist(): void
    {
        $down = $this->compute('shein', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 1,
            'al30' => 1,
            'cvr' => 3,
            'views' => 40,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);
        $this->assertNotNull($down);
        $this->assertEqualsWithDelta(40.0, $down['groi'], 0.01);
        $this->assertEqualsWithDelta(35.00, $down['sprice'], 0.01);

        $up = $this->compute('shein', [
            'inv' => 10,
            'dil' => 3,
            'ov_l30' => 6,
            'al30' => 6,
            'cvr' => 15,
            'views' => 40,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 0,
        ]);
        $this->assertNotNull($up);
        $this->assertEqualsWithDelta(60.0, $up['groi'], 0.01);
        $this->assertEqualsWithDelta(40.00, $up['sprice'], 0.01);
    }

    public function test_aliexpress_sold_uses_dil_slab(): void
    {
        $out = $this->compute('aliexpress', [
            'inv' => 9,
            'dil' => 22,
            'al30' => 2,
            'lp' => 10,
            'ship' => 6,
            'lmp' => 40,
            'std_price' => 36.99,
        ]);

        $this->assertNotNull($out);
        // AL30 > 0 → 20–25 slab NROI 70. No LMP cap (28.75 < 40).
        // (10*1.70 + 6) / 0.80 = 28.75
        $this->assertEqualsWithDelta(28.75, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(70.0, $out['groi'], 0.01);
    }

    public function test_aliexpress_zero_sold_uses_min_nroi_not_std(): void
    {
        $out = $this->compute('aliexpress', [
            'inv' => 10,
            'dil' => 0,
            'al30' => 0,
            'lp' => 20,
            'ship' => 0,
            'lmp' => 100,
            'std_price' => 99,
        ]);

        $this->assertNotNull($out);
        // AL30 = 0 → min Target NROI 50, not Std $99.
        // (20 × 1.50) / 0.80 = 37.50
        $this->assertEqualsWithDelta(37.50, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
    }

    public function test_aliexpress_zero_sold_uses_min_nroi_not_dil_slab(): void
    {
        $rules = [
            AmazonDilGroiRule::make(0.1, 5.0, 40),
            AmazonDilGroiRule::make(5.0, 25.0, 80),
        ];
        $out = DilRuleSpriceApplyService::for('aliexpress')->computeTarget(
            [
                'inv' => 9,
                'dil' => 22,
                'ov_l30' => 2,
                'al30' => 0,
                'lp' => 10,
                'ship' => 6,
                'lmp' => 40,
                'std_price' => 36.99,
            ],
            $rules,
            AmazonDilGroiRule::defaultCvrAdj(),
            0.80
        );

        $this->assertNotNull($out);
        // min Target NROI is 40, not the Dil 22 / 80 slab. No LMP cap on 0 Sold.
        // (10*1.40 + 6) / 0.80 = 20.00
        $this->assertEqualsWithDelta(20.00, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(40.0, $out['groi'], 0.01);
    }

    public function test_aliexpress_zero_sold_high_dil_uses_min_nroi_not_std(): void
    {
        $out = $this->compute('aliexpress', [
            'inv' => 10,
            'dil' => 55,
            'al30' => 0,
            'lp' => 10,
            'ship' => 6,
            'lmp' => 15,
            'std_price' => 19.99,
        ]);

        $this->assertNotNull($out);
        // AL30 = 0 even when Dil is above last To: min NROI 50, not Std, and no LMP cap.
        // (10*1.50 + 6) / 0.80 = 21.25
        $this->assertEqualsWithDelta(21.25, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(50.0, $out['groi'], 0.01);
    }

    public function test_aliexpress_out_of_slab_uses_std_then_lmp_cap(): void
    {
        $std = $this->compute('aliexpress', [
            'inv' => 10,
            'dil' => 55,
            'al30' => 3,
            'lp' => 10,
            'ship' => 6,
            'lmp' => 23.08,
            'std_price' => 19.99,
        ]);
        $this->assertNotNull($std);
        $this->assertEqualsWithDelta(19.99, $std['sprice'], 0.01);

        $capped = $this->compute('aliexpress', [
            'inv' => 10,
            'dil' => 80,
            'al30' => 3,
            'lp' => 5,
            'ship' => 1,
            'lmp' => 12,
            'std_price' => 20,
        ]);
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(12.0, $capped['sprice'], 0.01);
    }

    public function test_aliexpress_stop_skips_low_sgroi_std(): void
    {
        AliexpressPushGuard::setStopLowSgroi(true, 30);
        try {
            $this->assertNull($this->compute('aliexpress', [
                'inv' => 10,
                'dil' => 80,
                'al30' => 2,
                'lp' => 20,
                'ship' => 10,
                'lmp' => 0,
                'std_price' => 25,
            ]));
        } finally {
            AliexpressPushGuard::setStopLowSgroi(false, 30);
        }
    }

    public function test_aliexpress_out_of_slab_without_std_skips(): void
    {
        $this->assertNull($this->compute('aliexpress', [
            'inv' => 10,
            'dil' => 80,
            'al30' => 2,
            'lp' => 10,
            'ship' => 0,
            'std_price' => 0,
        ]));
    }

    public function test_channels_from_arg(): void
    {
        $this->assertSame(DilRuleSpriceApplyService::CHANNELS, DilRuleSpriceApplyService::channelsFromArg('all'));
        $this->assertSame(['bestbuy'], DilRuleSpriceApplyService::channelsFromArg('bb'));
        $this->assertSame(['fb_marketplace'], DilRuleSpriceApplyService::channelsFromArg('fb'));
        $this->assertSame(['walmart'], DilRuleSpriceApplyService::channelsFromArg('wm'));
        $this->assertSame(['pls'], DilRuleSpriceApplyService::channelsFromArg('pls'));
        $this->assertSame([], DilRuleSpriceApplyService::channelsFromArg('ebay3'));
        $this->assertNotContains('fb_marketplace', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertContains('walmart', DilRuleSpriceApplyService::PUSH_CHANNELS);
        $this->assertContains('pls', DilRuleSpriceApplyService::PUSH_CHANNELS);
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
