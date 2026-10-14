<?php

namespace Tests\Unit;

use App\Services\TopDawgSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class TopDawgSprcDilAutoPushServiceTest extends TestCase
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
            'min_npft' => 10,
        ], $over), 'topdawg');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'TD-TEST',
            'inv' => 10,
            'dil' => 20,
            'td_l30' => 2,
            'cvr' => 5,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 0,
            'margin' => 0.95,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_zero_sold_adds_the_zero_sold_disc(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['td_l30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_amz_rule_raises_price_to_a_price(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['amz' => 95.49]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.49, $out['sprice'], 0.001);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
        $this->assertTrue($out['amz_capped']);
    }

    public function test_amz_rule_does_nothing_at_or_above_a_price(): void
    {
        $svc = new TopDawgSprcDilAutoPushService;

        $above = $svc->computeTarget($this->row(['amz' => 80]), $this->pricer());
        $this->assertEqualsWithDelta(90.00, $above['sprice'], 0.001);
        $this->assertFalse($above['amz_capped']);

        $equal = $svc->computeTarget($this->row(['amz' => 90]), $this->pricer());
        $this->assertEqualsWithDelta(90.00, $equal['sprice'], 0.001);
        $this->assertFalse($equal['amz_capped']);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new TopDawgSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_min_npft_raises_the_discounted_price_without_ship(): void
    {
        // Ship is ignored on TopDawg: NPFT = ((P × 0.95 − LP) / P) × 100. LP 85 at 90 → 0.6%.
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 80, 'ship' => 50, 'margin' => 0.95]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        // Raw 90 → NPFT = ((90 × 0.95 − 80) / 90) × 100 = 6.1%. Raised to 10%: 80 / (0.95 − 0.10) = 94.12.
        $this->assertEqualsWithDelta(94.12, $out['sprice'], 0.011);
        $npft = ((($out['sprice'] * 0.95) - 80) / $out['sprice']) * 100;
        $this->assertGreaterThanOrEqual(9.99, $npft);
    }

    public function test_clearance_keeps_the_discount_price_below_min_npft(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 8, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_lmp_is_ignored(): void
    {
        // TopDawg has no LMP cap: even a low LMP on the row does not lower the target.
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertArrayNotHasKey('lmp_capped', $out);
    }

    public function test_ads_percent_is_part_of_min_npft(): void
    {
        // LP 80, margin 0.95. At 90: NPFT = 0.95 − 80/90 − ads. With ads 5% the floor must be higher than without.
        $svc = new TopDawgSprcDilAutoPushService;
        $without = $svc->computeTarget($this->row(['lp' => 80]), $this->pricer());
        $with = $svc->computeTarget($this->row(['lp' => 80, 'ads_pct' => 5]), $this->pricer());

        $this->assertNotNull($without);
        $this->assertNotNull($with);
        $this->assertGreaterThan($without['sprice'], $with['sprice']);
    }

    public function test_std_is_the_maximum(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 90, 'ship' => 8]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(100.00, $out['sprice'], 0.001);
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['margin' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.85, $out['margin'], 0.0001);
    }
}
