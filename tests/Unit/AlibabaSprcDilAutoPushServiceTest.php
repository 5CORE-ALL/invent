<?php

namespace Tests\Unit;

use App\Services\AlibabaSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class AlibabaSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'alibaba');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'AB-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'margin' => 0.80,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null, float $ads = 0.0): ?array
    {
        return (new AlibabaSprcDilAutoPushService)->computeTarget($row, $pricer ?? $this->pricer(), $ads);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_zero_sold_uses_ab_l30(): void
    {
        $out = $this->target($this->row(['al30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_min_npft_ignores_ship(): void
    {
        // Std 120: discount price 108. LP 85: NPFT = (108 × 0.8 − 85) / 108 = 1.2%. Raised to 10%: 85 / 0.70 = 121.43,
        // so Std 120 caps it. A Ship value in the row must not change anything.
        $with = $this->target($this->row(['standard_price' => 120, 'lp' => 85, 'ship' => 30]));
        $without = $this->target($this->row(['standard_price' => 120, 'lp' => 85]));

        $this->assertNotNull($with);
        $this->assertEqualsWithDelta($without['sprice'], $with['sprice'], 0.001);
        $this->assertEqualsWithDelta(120.00, $with['sprice'], 0.001);
    }

    public function test_min_npft_raises_the_price(): void
    {
        // Std 150: discount price 135. LP 100: NPFT = (135 × 0.8 − 100) / 135 = 6%. Raised to 10%: 100 / 0.70 = 142.86.
        $out = $this->target($this->row(['standard_price' => 150, 'lp' => 100]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(142.86, $out['sprice'], 0.011);
    }

    public function test_min_npft_includes_channel_ads(): void
    {
        $row = $this->row(['standard_price' => 120, 'lp' => 70]);

        // No ads: NPFT at 108 is 15.2%, so the discount price stays.
        $this->assertEqualsWithDelta(108.00, $this->target($row, null, 0.0)['sprice'], 0.001);
        // Ads 10%: NPFT at 108 is 5.2%. Raised to 10%: 70 / (0.80 − 0.10 − 0.10) = 116.67.
        $this->assertEqualsWithDelta(116.67, $this->target($row, null, 0.10)['sprice'], 0.011);
    }

    public function test_clearance_keeps_the_discount_price_below_min_npft(): void
    {
        $out = $this->target($this->row(['standard_price' => 150, 'lp' => 100, 'clearance' => true]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(135.00, $out['sprice'], 0.001);
    }

    public function test_std_is_the_maximum(): void
    {
        $out = $this->target($this->row(['lp' => 80]));

        $this->assertNotNull($out);
        // LP 80 needs 80 / 0.70 = 114.29 for 10%, but Std 100 is the maximum.
        $this->assertEqualsWithDelta(100.00, $out['sprice'], 0.001);
    }

    public function test_no_std_or_inventory_has_no_target(): void
    {
        $this->assertNull($this->target($this->row(['standard_price' => 0])));
        $this->assertNull($this->target($this->row(['standard_price' => null])));
        $this->assertNull($this->target($this->row(['inv' => 0])));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = $this->target($this->row(['margin' => 80]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.80, $out['margin'], 0.0001);
    }

    public function test_missing_margin_defaults_to_one(): void
    {
        $out = $this->target($this->row(['margin' => 0]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(1.0, $out['margin'], 0.0001);
    }
}
