<?php

namespace Tests\Unit;

use App\Services\ShopifyB2cSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class ShopifyB2cSprcDilAutoPushServiceTest extends TestCase
{
    private function pricer(array $over = []): StdPrcVsDilPricer
    {
        return new StdPrcVsDilPricer(array_merge([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 5]],
            'age' => [['min' => 0, 'max' => 9999, 'disc' => 3]],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [['min' => 0, 'max' => 9999, 'disc' => 2]],
            'zero_sold_disc' => 0,
        ], $over), 'shopify_b2c');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'SH-TEST',
            'inv' => 10,
            'dil' => 20,
            'b2c_l30' => 2,
            'cvr' => 5,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 3,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new ShopifyB2cSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_lmp_cap_when_sgroi_at_lmp_is_at_least_20(): void
    {
        // Target 90. LMP 80: SGROI at LMP = ((80 × 0.95) − 3 − 20) / 20 = 265% ≥ 20 → cap to LMP.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(80.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_lmp_below_sgroi_floor_does_not_cap(): void
    {
        // LP 60, ship 5: SGROI at LMP 72 = ((72 × 0.95) − 5 − 60) / 60 = 5% < 20 → keep the rule price.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'ship' => 5, 'lmp' => 72]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sgpft_sroi_and_snroi_follow_the_saved_price(): void
    {
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), 10.0);

        $this->assertNotNull($out);
        // gross = (90 × 0.95) − 3 − 20 = 62.5 → SGPFT 69.44%, SROI 312.5%.
        $this->assertEqualsWithDelta(69.44, $out['sgpft'], 0.01);
        $this->assertEqualsWithDelta(312.5, $out['sroi'], 0.01);
        // Ads 10% of 90 = 9 → SNROI = (62.5 − 9) / 20 = 267.5%, SNPFT = SGPFT − 10.
        $this->assertEqualsWithDelta(267.5, $out['snroi'], 0.01);
        $this->assertEqualsWithDelta(59.44, $out['snpft'], 0.01);
    }

    public function test_rule_price_below_a_price_uses_a_price(): void
    {
        // Rule price 90, A Price 95.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['amz' => 95]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.00, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_capped']);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
    }

    public function test_lmp_below_a_price_uses_a_price(): void
    {
        // LMP 80 is below A Price 85 → S PRC = A Price, even though the rule price (90) is above it.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80, 'amz' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(85.00, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_capped']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_lmp_cap_still_applies_when_price_stays_above_a_price(): void
    {
        // A Price 70 < LMP 80 < rule price 90 → LMP cap to 80 (SGROI at LMP ≥ 20%), still above A Price.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80, 'amz' => 70]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(80.00, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_capped']);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull((new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['clearance' => true]), $this->pricer()));
    }

    /**
     * Sprc Dil modal settings: slab 0–100 → Target SNROI 50, default CVR overlay, Diff rule above 20% adds +10,
     * LMP cap 2% / 3% / 5% by Dil band (<25 / 25–50 / >50).
     *
     * @return array{rules:array,cvr_adj:array,lmp_cap:array,diff_adj:array}
     */
    private function dilCtx(array $over = []): array
    {
        return array_merge([
            'rules' => [['key' => '0-100', 'label' => '0-100', 'min' => 0.0, 'max' => 100.0, 'groi' => 50.0, 'nroi' => 50.0]],
            'cvr_adj' => ['down_lt' => 7.0, 'down_adj' => -10.0, 'up_gt' => 10.0, 'up_adj' => 10.0],
            'lmp_cap' => ['low_lt' => 25.0, 'high_gt' => 50.0, 'low_pct' => 2.0, 'mid_pct' => 3.0, 'high_pct' => 5.0],
            'diff_adj' => ['gt_pct' => 20.0, 'points' => 10.0],
        ], $over);
    }

    public function test_sprc_dil_prices_from_the_dil_slab_target_snroi(): void
    {
        // CVR 8% is inside 7–10 (no overlay), no Price → no Diff. Target 50: (20 × 1.5 + 3) / 0.95 = 34.74.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['cvr' => 8, 'views' => 100]), null, 0.0, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(34.74, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_ignores_std_prc_vs_dil_discounts_and_works_without_std(): void
    {
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget(
            $this->row(['cvr' => 8, 'views' => 100, 'standard_price' => 0]),
            $this->pricer(['snroi_cap' => $this->capSlabs(1000)]),
            0.0,
            $this->dilCtx()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(34.74, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_cvr_overlay_and_diff_rule_order(): void
    {
        $svc = new ShopifyB2cSprcDilAutoPushService;
        // CVR 5% (< 7) takes 10 off: 40 → (20 × 1.4 + 3) / 0.95 = 32.63.
        $down = $svc->computeTarget($this->row(['cvr' => 5, 'views' => 100]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(32.63, $down['sprice'], 0.001);

        // Diff: Std 100 vs Price 70 = 42.9% (> 20) adds +10 after the CVR overlay: 40 + 10 = 50 → 34.74.
        $diff = $svc->computeTarget($this->row(['cvr' => 5, 'views' => 100, 'price' => 70]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(34.74, $diff['sprice'], 0.001);

        // Slab 5 + CVR −10 floors at 0 first, then +10 → 10. The Diff +10 must not be eaten by the CVR −10.
        $floor = $svc->computeTarget(
            $this->row(['cvr' => 5, 'views' => 100, 'price' => 70]),
            null,
            0.0,
            $this->dilCtx(['rules' => [['key' => '0-100', 'label' => '0-100', 'min' => 0.0, 'max' => 100.0, 'groi' => 5.0, 'nroi' => 5.0]]])
        );
        $this->assertEqualsWithDelta((20 * 1.10 + 3) / 0.95, $floor['sprice'], 0.01);

        // Diff 11.1% is below the 20% threshold: nothing is added.
        $none = $svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'price' => 90]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(34.74, $none['sprice'], 0.001);
    }

    public function test_sprc_dil_skips_cvr_overlay_with_no_views_or_zero_sold(): void
    {
        $svc = new ShopifyB2cSprcDilAutoPushService;
        $noViews = $svc->computeTarget($this->row(['cvr' => 0, 'views' => 0]), null, 0.0, $this->dilCtx());
        $zeroSold = $svc->computeTarget($this->row(['cvr' => 0, 'views' => 100, 'b2c_l30' => 0]), null, 0.0, $this->dilCtx());

        $this->assertEqualsWithDelta(34.74, $noViews['sprice'], 0.001);
        $this->assertEqualsWithDelta(34.74, $zeroSold['sprice'], 0.001);
    }

    public function test_sprc_dil_lmp_cap_uses_the_dil_band_reduce_percent_and_std_maximum(): void
    {
        $svc = new ShopifyB2cSprcDilAutoPushService;
        // Dil 20 (< 25) → 2%. Dil price 34.74 ≥ LMP 30, SGROI at LMP is high → 30 × 0.98 = 29.40.
        $low = $svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'lmp' => 30]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(29.40, $low['sprice'], 0.001);
        $this->assertTrue($low['lmp_capped']);

        // Dil 30 (25–50) → 3%; Dil 60 (> 50) → 5%.
        $mid = $svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'lmp' => 30, 'dil' => 30]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(29.10, $mid['sprice'], 0.001);
        $high = $svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'lmp' => 30, 'dil' => 60]), null, 0.0, $this->dilCtx());
        $this->assertEqualsWithDelta(28.50, $high['sprice'], 0.001);

        // Std Prc is the maximum: Target 500 wants (20 × 6 + 3) / 0.95 = 129.47 but Std is 100.
        $std = $svc->computeTarget(
            $this->row(['cvr' => 8, 'views' => 100]),
            null,
            0.0,
            $this->dilCtx(['rules' => [['key' => '0-100', 'label' => '0-100', 'min' => 0.0, 'max' => 100.0, 'groi' => 500.0, 'nroi' => 500.0]]])
        );
        $this->assertSame(100.0, $std['sprice']);
    }

    public function test_sprc_dil_keeps_the_amz_cap_and_skips_clearance(): void
    {
        $svc = new ShopifyB2cSprcDilAutoPushService;
        // Dil price 34.74 is below A Price 60 → the Amz cap raises S PRC to A Price, like Std prc vs dil.
        $amz = $svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'amz' => 60]), null, 0.0, $this->dilCtx());
        $this->assertSame(60.0, $amz['sprice']);
        $this->assertTrue($amz['amz_capped']);

        $this->assertNull($svc->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'clearance' => true]), null, 0.0, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->row(['lp' => 0, 'cvr' => 8, 'views' => 100]), null, 0.0, $this->dilCtx()));
    }

    public function test_sprc_dil_uses_the_nearest_slab_outside_the_table(): void
    {
        // Dil 150 is above the last slab (0–100): the nearest slab still gives a target.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['cvr' => 8, 'views' => 100, 'dil' => 150]), null, 0.0, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(34.74, $out['sprice'], 0.001);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_and_ads(): void
    {
        // LP 20, Ship 3, take-home 0.95, Ads 10%. Cap 100% -> (20 x 2 + 3) / (0.95 - 0.10) = 50.58.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(100)]), 10.0);

        $this->assertNotNull($out);
        $this->assertSame(50.58, $out['sprice']);
        // The saved SNROI% (the page column) lands on the target, never above it.
        $this->assertEqualsWithDelta(100.0, $out['snroi'], 0.5);
        $this->assertLessThanOrEqual(100.0 + 1e-9, $out['snroi']);
    }

    public function test_snroi_cap_keeps_lmp_cap_amz_cap_and_std_max(): void
    {
        $cap = $this->pricer(['snroi_cap' => $this->capSlabs(100)]);

        // The cap price 50.58 is below LMP 80: the LMP cap does not apply.
        $lmp = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $cap, 10.0);
        $this->assertNotNull($lmp);
        $this->assertSame(50.58, $lmp['sprice']);
        $this->assertFalse($lmp['lmp_capped']);

        // The cap price is below A Price 70: the Amz cap still raises S PRC to A Price.
        $amz = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['amz' => 70]), $cap, 10.0);
        $this->assertNotNull($amz);
        $this->assertSame(70.0, $amz['sprice']);
        $this->assertTrue($amz['amz_capped']);

        // A target that needs more than Std holds at Std.
        $high = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]), 10.0);
        $this->assertNotNull($high);
        $this->assertSame(100.0, $high['sprice']);
    }
}
