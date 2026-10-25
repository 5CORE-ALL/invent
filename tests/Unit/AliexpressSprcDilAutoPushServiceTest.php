<?php

namespace Tests\Unit;

use App\Services\AliexpressSprcDilAutoPushService;
use App\Support\AmazonDilGroiRule;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class AliexpressSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'aliexpress');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'AE-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'cvr' => 5,
            'review_count' => 0,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 3,
            'margin' => 0.89,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new AliexpressSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_clearance_keeps_the_discount_price(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 8, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_lmp_cap_when_sgroi_at_lmp_is_at_least_20(): void
    {
        // Target 90. LMP 80: SGROI at LMP = ((80 × 0.89) − 3 − 20) / 20 = 241% ≥ 20 → cap to LMP.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(80.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_lmp_below_sgroi_floor_does_not_cap(): void
    {
        // LP 60, ship 5: SGROI at LMP 72 = ((72 × 0.89) − 5 − 60) / 60 = −1.3% < 20 → keep the rule price.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'ship' => 5, 'lmp' => 72, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sgpft_and_sroi_follow_the_saved_price(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // ((90 × 0.89) − 3 − 20) = 57.1 → SGPFT 63%, SROI 285% (rounded).
        $this->assertSame(63, $out['sgpft']);
        $this->assertSame(285, $out['sroi']);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_not_the_discounts(): void
    {
        // LP 20, Ship 3, margin 0.89, no Ads. Cap 100% -> (20 x 2 + 3) / 0.89 = 48.31 (the 10% discount price 90 is not used).
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));

        $this->assertNotNull($out);
        $this->assertSame(48.31, $out['sprice']);
        // The SNROI% column at that price never goes above the target.
        $this->assertLessThanOrEqual(100.0, (48.31 * 0.89 - 3 - 20) / 20 * 100);
    }

    public function test_snroi_cap_above_what_std_can_reach_holds_at_std(): void
    {
        // A 1000% target needs far more than Std, so S PRC is Std and the 10% discount is not taken.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));

        $this->assertNotNull($out);
        $this->assertSame(100.0, $out['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
    }

    public function test_lmp_cap_still_applies_after_the_snroi_cap(): void
    {
        // Cap 1000% holds at Std 100, then LMP 80 (SGROI at LMP is far above 20%) pulls it to 80.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget(
            $this->row(['lmp' => 80]),
            $this->pricer(['snroi_cap' => $this->capSlabs(1000)])
        );

        $this->assertNotNull($out);
        $this->assertSame(80.0, $out['sprice']);
        $this->assertTrue($out['lmp_capped']);
    }

    /** @return array<string, mixed> */
    private function dilCtx(): array
    {
        return [
            'rules' => [
                ['key' => '0-0', 'label' => '0%', 'min' => 0.0, 'max' => 0.0, 'groi' => 40.0],
                ['key' => '0.1-50', 'label' => '0.1–50%', 'min' => 0.1, 'max' => 50.0, 'groi' => 60.0],
            ],
            'cvr_adj' => null,
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
            'lmp_cap' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['price' => 100], $over));
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship_and_no_ads(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->dilRow(), null, $this->dilCtx());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6 + 3) / 0.89 = 39.33. Std 100 is far above, no LMP.
        $this->assertEqualsWithDelta(39.33, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs Price 70 -> Diff 42.9% (> 20): Target 70%. (20 x 1.7 + 3) / 0.89 = 41.57.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->dilRow(['price' => 70]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(41.57, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_lmp_cap_lands_on_lmp_minus_the_dil_band_pct(): void
    {
        // SGROI at LMP 35 = (35 x 0.89 - 3 - 20) / 20 = 40.75% >= 20, and 39.33 is above 35.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->dilRow(['lmp' => 35]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $pct = AmazonDilGroiRule::lmpCapReducePct(20.0, null);
        $this->assertEqualsWithDelta(round(35 * (1 - $pct / 100), 2), $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_sprc_dil_lmp_below_the_sgroi_floor_does_not_cap(): void
    {
        // SGROI at LMP 28 = (28 x 0.89 - 3 - 20) / 20 = 9.6% < 20: keep the Dil price.
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->dilRow(['lmp' => 28]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(39.33, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sprc_dil_std_price_is_the_maximum(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->dilRow(['standard_price' => 30]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(30.0, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_above_every_slab_prices_at_std_and_at_lmp_when_std_is_above_lmp(): void
    {
        $svc = new AliexpressSprcDilAutoPushService;

        $std = $svc->computeTarget($this->dilRow(['dil' => 60]), null, $this->dilCtx());
        $this->assertNotNull($std);
        $this->assertEqualsWithDelta(100.0, $std['sprice'], 0.001);
        $this->assertFalse($std['lmp_capped']);

        // Std 100 > LMP 90: S PRC = LMP, with no SGROI test and no band reduction.
        $lmp = $svc->computeTarget($this->dilRow(['dil' => 60, 'lmp' => 90]), null, $this->dilCtx());
        $this->assertNotNull($lmp);
        $this->assertEqualsWithDelta(90.0, $lmp['sprice'], 0.001);
        $this->assertTrue($lmp['lmp_capped']);

        $this->assertNull($svc->computeTarget($this->dilRow(['dil' => 60, 'standard_price' => 0]), null, $this->dilCtx()));
    }

    public function test_sprc_dil_prices_clearance_and_zero_sold_rows_and_skips_no_inventory(): void
    {
        $svc = new AliexpressSprcDilAutoPushService;

        $clearance = $svc->computeTarget($this->dilRow(['clearance' => true, 'al30' => 0]), null, $this->dilCtx());
        $this->assertNotNull($clearance);
        $this->assertEqualsWithDelta(39.33, $clearance['sprice'], 0.001);

        $this->assertNull($svc->computeTarget($this->dilRow(['inv' => 0]), null, $this->dilCtx()));
    }

    public function test_without_a_dil_context_the_std_rule_still_prices(): void
    {
        $out = (new AliexpressSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
