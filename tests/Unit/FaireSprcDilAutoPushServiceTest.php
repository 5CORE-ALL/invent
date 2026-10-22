<?php

namespace Tests\Unit;

use App\Services\FaireSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class FaireSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'faire');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'FA-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'margin' => 0.75,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_ship_is_reduced_from_sprice_before_the_snroi_cap(): void
    {
        $svc = new FaireSprcDilAutoPushService;

        // 90.00 − Ship 8 = 82.00 (no cap rule).
        $out = $svc->computeTarget($this->row(['ship' => 8]), $this->pricer());
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(82.00, $out['sprice'], 0.001);

        // SNROI at 82 = ((82 × 0.75) − 20) / 20 = 206%. Cap 100% → (20 × 2) / 0.75 = 53.33. Ship is NOT taken off after the cap.
        $capped = $svc->computeTarget(
            $this->row(['ship' => 8]),
            $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]])
        );
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(53.33, $capped['sprice'], 0.001);
    }

    public function test_zero_sold_uses_al30(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(['al30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_keeps_the_discount_price(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 60, 'clearance' => true]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_no_lmp_or_amz_rule(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget(
            $this->row(['lmp' => 50, 'amz' => 95]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertArrayNotHasKey('amz_capped', $out);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new FaireSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new FaireSprcDilAutoPushService)->computeTarget($this->row(['margin' => 75]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.75, $out['margin'], 0.0001);
    }
}
