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
            'min_npft' => 10,
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

    public function test_min_npft_raises_the_discounted_price_and_counts_ads(): void
    {
        // Ads 5%: NPFT = ((P × 0.85 − 3 − 60) / P) × 100 − 5. At 90 that is 25% − 5% = 20%... use a heavier cost.
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 5, 'ads_pct' => 5]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        // Raised to NPFT 10%: (70 + 5) / (0.85 − 0.05 − 0.10) = 107.14 → capped by Std 100.
        $this->assertEqualsWithDelta(100.00, $out['sprice'], 0.001);

        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'ship' => 5, 'ads_pct' => 5]),
            $this->pricer()
        );
        $this->assertNotNull($out);
        // Raw 90 → NPFT = ((90 × 0.85 − 5 − 60) / 90) × 100 − 5 = 9.4%. Raised: 65 / 0.70 = 92.86.
        $this->assertEqualsWithDelta(92.86, $out['sprice'], 0.011);
        $npft = ((($out['sprice'] * 0.85) - 5 - 60) / $out['sprice']) * 100 - 5;
        $this->assertGreaterThanOrEqual(9.99, $npft);
    }

    public function test_clearance_keeps_the_discount_price_below_min_npft(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 8, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
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
            $this->row(['lp' => 60, 'ship' => 5, 'lmp' => 72, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_std_is_the_maximum(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 90, 'ship' => 8]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(100.00, $out['sprice'], 0.001);
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new ReverbSprcDilAutoPushService)->computeTarget($this->row(['margin' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.85, $out['margin'], 0.0001);
    }
}
