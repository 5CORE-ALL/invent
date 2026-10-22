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

    public function test_ship_is_reduced_from_sprice_before_the_snroi_cap(): void
    {
        // 90.00 − Ship 8 = 82.00 (no cap rule).
        $out = $this->target($this->row(['ship' => 8]));
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(82.00, $out['sprice'], 0.001);

        // SNROI at 82 = ((82 × 0.80) − 20) / 20 = 128%. Cap 100% → (20 × 2) / 0.80 = 50.00. Ship is not taken off after the cap.
        $capped = $this->target(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]])
        );
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(50.00, $capped['sprice'], 0.001);
    }

    public function test_zero_sold_uses_ab_l30(): void
    {
        $out = $this->target($this->row(['al30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_keeps_the_discount_price(): void
    {
        $out = $this->target($this->row(['standard_price' => 150, 'lp' => 100, 'clearance' => true]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(135.00, $out['sprice'], 0.001);
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
