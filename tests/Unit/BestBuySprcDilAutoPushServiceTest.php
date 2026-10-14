<?php

namespace Tests\Unit;

use App\Services\BestBuyApiService;
use App\Services\BestBuySprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class BestBuySprcDilAutoPushServiceTest extends TestCase
{
    private function service(): BestBuySprcDilAutoPushService
    {
        return new BestBuySprcDilAutoPushService(
            (new \ReflectionClass(BestBuyApiService::class))->newInstanceWithoutConstructor()
        );
    }

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
        ], $over), 'bestbuy');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'BB-TEST',
            'inv' => 10,
            'dil' => 20,
            'bb_l30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 0,
            'margin' => 0.80,
            'amz' => 0,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->service()->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_capped']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_zero_sold_uses_bb_l30(): void
    {
        $out = $this->service()->computeTarget($this->row(['bb_l30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_min_npft_raises_the_price_and_includes_ship_bb(): void
    {
        // Raw 90. LP 60 + Ship BB 4 = 64. Min NPFT 10%: 64 / (0.80 − 0.10) = 91.43.
        $out = $this->service()->computeTarget($this->row(['lp' => 60, 'ship' => 4]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(91.43, $out['sprice'], 0.011);
        $npft = ((($out['sprice'] * 0.80) - 64) / $out['sprice']) * 100;
        $this->assertGreaterThanOrEqual(9.99, $npft);
    }

    public function test_clearance_keeps_the_discount_price_below_min_npft(): void
    {
        $out = $this->service()->computeTarget($this->row(['lp' => 60, 'ship' => 4, 'clearance' => true]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_std_is_the_maximum_when_min_npft_is_already_met(): void
    {
        // Raw 90 is under Std 100 and NPFT is healthy, so Std never lifts the price.
        $out = $this->service()->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        $this->assertLessThanOrEqual(100.00, $out['sprice']);
        $this->assertFalse($out['npft_floor']);
    }

    public function test_min_npft_on_snpft_wins_over_the_std_maximum(): void
    {
        // LP 70 + Ship BB 8 = 78. Min NPFT 10%: 78 / (0.80 − 0.10) = 111.43, above Std 100.
        $out = $this->service()->computeTarget($this->row(['lp' => 70, 'ship' => 8]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(111.43, $out['sprice'], 0.011);
        $this->assertTrue($out['npft_floor']);
        $snpft = ((($out['sprice'] * 0.80) - 78) / $out['sprice']) * 100;
        $this->assertGreaterThanOrEqual(9.99, $snpft);
    }

    public function test_min_npft_on_snpft_wins_over_the_amz_rule(): void
    {
        // A Price 5.99 is far under the Min NPFT price, so the floor is the S PRC, not A Price.
        $out = $this->service()->computeTarget($this->row(['lp' => 6, 'ship' => 2, 'standard_price' => 5.99, 'amz' => 5.99]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertGreaterThan(5.99, $out['sprice']);
        $snpft = ((($out['sprice'] * 0.80) - 8) / $out['sprice']) * 100;
        $this->assertGreaterThanOrEqual(9.99, $snpft);
    }

    public function test_clearance_is_exempt_from_the_snpft_floor(): void
    {
        $out = $this->service()->computeTarget($this->row(['lp' => 70, 'ship' => 8, 'clearance' => true]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertFalse($out['npft_floor']);
        // Clearance keeps the discount price (Std 100 − 10%), even though its SNPFT is under the floor.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_lmp_caps_the_price_when_sgroi_at_lmp_is_healthy(): void
    {
        // LP 20, margin 0.80: SGROI at LMP 80 = (64 − 20) / 20 = 220%, so the cap applies.
        $out = $this->service()->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(80.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
    }

    public function test_min_npft_wins_over_the_lmp_cap(): void
    {
        // LP 60 + Ship BB 4: Min NPFT 10% needs 91.43. LMP 80 would give NPFT under 10%, so the cap is skipped.
        $out = $this->service()->computeTarget($this->row(['lp' => 60, 'ship' => 4, 'lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertGreaterThanOrEqual(91.42, $out['sprice']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_amz_rule_raises_price_to_a_price(): void
    {
        $out = $this->service()->computeTarget($this->row(['amz' => 95.49]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.49, $out['sprice'], 0.001);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
        $this->assertTrue($out['amz_capped']);
    }

    public function test_amz_rule_wins_over_the_lmp_cap(): void
    {
        $out = $this->service()->computeTarget($this->row(['lmp' => 80, 'amz' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(85.00, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_capped']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_amz_rule_does_nothing_when_price_is_already_at_or_above_a_price(): void
    {
        $svc = $this->service();

        $above = $svc->computeTarget($this->row(['amz' => 80]), $this->pricer());
        $this->assertEqualsWithDelta(90.00, $above['sprice'], 0.001);
        $this->assertFalse($above['amz_capped']);

        $equal = $svc->computeTarget($this->row(['amz' => 90]), $this->pricer());
        $this->assertEqualsWithDelta(90.00, $equal['sprice'], 0.001);
        $this->assertFalse($equal['amz_capped']);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = $this->service();
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = $this->service()->computeTarget($this->row(['margin' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.80, $out['margin'], 0.0001);
    }
}
