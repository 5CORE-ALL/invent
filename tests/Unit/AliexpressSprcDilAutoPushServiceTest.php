<?php

namespace Tests\Unit;

use App\Services\AliexpressSprcDilAutoPushService;
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
}
