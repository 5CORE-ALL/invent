<?php

namespace Tests\Unit;

use App\Services\PlsSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class PlsSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'pls');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'PLS-TEST',
            'inv' => 10,
            'dil' => 20,
            'pls_l30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 5,
            'margin' => 0.80,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null): ?array
    {
        return (new PlsSprcDilAutoPushService)->computeTarget($row, $pricer ?? $this->pricer());
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_zero_sold_uses_pls_l30(): void
    {
        $out = $this->target($this->row(['pls_l30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull($this->target($this->row(['standard_price' => 120, 'lp' => 70, 'ship' => 10, 'clearance' => true])));
    }

    public function test_lmp_has_no_effect_on_pls(): void
    {
        $out = $this->target($this->row(['lmp' => 50]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $this->assertNull($this->target($this->row(['inv' => 0])));
        $this->assertNull($this->target($this->row(['standard_price' => 0])));
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = $this->target($this->row(['margin' => 80]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.80, $out['margin'], 0.0001);
    }

    public function test_invalid_margin_defaults_to_one(): void
    {
        $out = $this->target($this->row(['margin' => 0]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(1.0, $out['margin'], 0.0001);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_and_no_ads(): void
    {
        // LP 20, Ship 5, PLS% 0.80. Cap 100% -> (20 x 2 + 5) / 0.80 = 56.25 (the 90.00 discount price is not used).
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
        // The page SNROI% = (S PRC x PLS% - Ship - LP) / LP lands on the target.
        $this->assertEqualsWithDelta(100.0, ((56.25 * 0.80) - 5 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_above_what_std_can_reach_holds_at_std(): void
    {
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));

        $this->assertNotNull($out);
        $this->assertSame(100.0, $out['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
    }
}
