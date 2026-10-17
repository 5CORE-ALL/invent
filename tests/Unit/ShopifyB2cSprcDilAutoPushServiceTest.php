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
            $this->row(['lp' => 60, 'ship' => 5, 'lmp' => 72, 'clearance' => true]),
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

    public function test_amz_cap_raises_a_price_floor_over_the_rule_price(): void
    {
        // Rule price 90, A Price 95 → S PRC never below A Price.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['amz' => 95]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.00, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_capped']);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
    }

    public function test_amz_cap_wins_over_lmp_cap(): void
    {
        // LMP 80 would cap to 80, but A Price 85 is the floor.
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80, 'amz' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(85.00, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_capped']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_amz_below_rule_price_does_not_cap(): void
    {
        $out = (new ShopifyB2cSprcDilAutoPushService)->computeTarget($this->row(['amz' => 70]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_capped']);
    }
}
