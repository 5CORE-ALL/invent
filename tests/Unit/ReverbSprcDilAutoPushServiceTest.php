<?php

namespace Tests\Unit;

use App\Services\ReverbSprcDilAutoPushService;
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
}
