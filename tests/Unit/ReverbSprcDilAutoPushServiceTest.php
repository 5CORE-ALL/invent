<?php

namespace Tests\Unit;

use App\Services\ReverbSprcDilAutoPushService;
use App\Support\AmazonDilGroiRule;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class ReverbSprcDilAutoPushServiceTest extends TestCase
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
            'zero_sold_disc' => 4,
        ], $over), 'reverb');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'RV-TEST',
            'inv' => 10,
            'dil' => 20,
            'rv_l30' => 2,
            'cvr' => 5,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 3,
            'margin' => 0.85,
            'ads_pct' => 0,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_zero_sold_adds_the_zero_sold_disc(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(['rv_l30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new ReverbSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull((new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 8, 'clearance' => true]),
            $this->pricer()
        ));
    }

    public function test_lmp_cap_when_sgroi_at_lmp_is_at_least_20(): void
    {
        // Target 90. LMP 80: SGROI at LMP = ((80 × 0.85) − 3 − 20) / 20 = 225% ≥ 20 → cap to LMP.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(80.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_lmp_below_sgroi_floor_does_not_cap(): void
    {
        // LP 60, ship 5: SGROI at LMP 72 = ((72 × 0.85) − 5 − 60) / 60 = −6.3% < 20 → keep the rule price.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'ship' => 5, 'lmp' => 72]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(['margin' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.85, $out['margin'], 0.0001);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_and_ads(): void
    {
        // LP 20, Ship 3, take-home 0.85, Ads 5%. Cap 100% -> (20 x 2 + 3) / (0.85 - 0.05) = 53.75.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['ads_pct' => 5]),
            $this->pricer(['snroi_cap' => $this->capSlabs(100)])
        );

        $this->assertNotNull($out);
        $this->assertSame(53.75, $out['sprice']);
        // The page SNROI% = (S PRC x (take-home - Ads%) - Ship - LP) / LP lands on the target.
        $this->assertEqualsWithDelta(100.0, ((53.75 * 0.80) - 3 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_then_lmp_cap_then_std_max(): void
    {
        // Cap price 53.75 is below LMP 80, so the LMP cap does not apply.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['ads_pct' => 5, 'lmp' => 80]),
            $this->pricer(['snroi_cap' => $this->capSlabs(100)])
        );
        $this->assertNotNull($out);
        $this->assertSame(53.75, $out['sprice']);

        $high = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));
        $this->assertNotNull($high);
        $this->assertSame(100.0, $high['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
    }

    /** @return array{rules:list<array<string,mixed>>,cvr_adj:?array,diff_adj:?array,lmp_cap:?array} */
    private function dilCtx(): array
    {
        return [
            'rules' => [
                ['key' => '0-0', 'label' => '0%', 'min' => 0.0, 'max' => 0.0, 'groi' => 40.0],
                ['key' => '0.1-50', 'label' => '0.1–50%', 'min' => 0.1, 'max' => 50.0, 'groi' => 60.0],
            ],
            'cvr_adj' => ['down_lt' => 7, 'down_adj' => -5, 'up_gt' => 10, 'up_adj' => 5],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
            'lmp_cap' => null,
        ];
    }

    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['ads_pct' => 5, 'price' => 100, 'cvr' => 8], $over));
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship_and_ads(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->dilRow(), null, $this->dilCtx());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6 + 3) / (0.85 - 0.05) = 43.75. Std 100 is far above, no LMP.
        $this->assertEqualsWithDelta(43.75, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs RV Price 70 -> Diff 42.9% (> 20): Target 60 + 10 = 70%. (20 x 1.7 + 3) / 0.80 = 46.25.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->dilRow(['price' => 70]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_lmp_cap_lands_on_lmp_minus_the_dil_band_pct(): void
    {
        // Dil 20 is in the low band. Price 43.75 is at/above LMP 40 and SGROI at LMP = (40 x 0.85 - 3 - 20) / 20 = 55% >= 20.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->dilRow(['lmp' => 40]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $pct = AmazonDilGroiRule::lmpCapReducePct(20.0, null);
        $this->assertEqualsWithDelta(round(40 * (1 - $pct / 100), 2), $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
    }

    public function test_sprc_dil_lmp_below_the_sgroi_floor_does_not_cap(): void
    {
        // SGROI at LMP 28 = (28 x 0.85 - 3 - 20) / 20 = 4% < 20: keep the Dil price.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->dilRow(['lmp' => 28]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(43.75, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sprc_dil_std_price_is_the_maximum(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->dilRow(['standard_price' => 40]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.00, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_works_without_a_std_price_and_skips_clearance_and_out_of_slab_skus(): void
    {
        $svc = new ReverbSprcDilAutoPushService;
        $noStd = $svc->computeTarget($this->dilRow(['standard_price' => 0]), null, $this->dilCtx());
        $this->assertNotNull($noStd);
        $this->assertEqualsWithDelta(43.75, $noStd['sprice'], 0.001);

        $this->assertNull($svc->computeTarget($this->dilRow(['clearance' => true]), null, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->dilRow(['inv' => 0]), null, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->dilRow(['dil' => 80]), null, $this->dilCtx()));
    }

    public function test_no_dil_context_leaves_the_std_prc_behaviour_unchanged(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
