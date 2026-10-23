<?php

namespace Tests\Unit;

use App\Services\PurchasingPowerApiService;
use App\Services\PurchasingPowerSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class PurchasingPowerSprcDilAutoPushServiceTest extends TestCase
{
    private function service(): PurchasingPowerSprcDilAutoPushService
    {
        return new PurchasingPowerSprcDilAutoPushService(
            (new \ReflectionClass(PurchasingPowerApiService::class))->newInstanceWithoutConstructor()
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
        ], $over), 'purchasing_power');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'PP-TEST',
            'inv' => 10,
            'dil' => 20,
            'pp_l30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 0,
            'margin' => 0.95,
            'amz' => 0,
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
    }

    public function test_zero_sold_uses_pp_l30(): void
    {
        $out = $this->service()->computeTarget($this->row(['pp_l30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull($this->service()->computeTarget($this->row(['lp' => 80, 'ship' => 4, 'clearance' => true]), $this->pricer()));
    }

    public function test_amz_rule_raises_price_to_a_price(): void
    {
        $out = $this->service()->computeTarget($this->row(['amz' => 95.49]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.49, $out['sprice'], 0.001);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
        $this->assertTrue($out['amz_capped']);
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
        $out = $this->service()->computeTarget($this->row(['margin' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.85, $out['margin'], 0.0001);
    }

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_bb_and_no_ads(): void
    {
        // LP 20, Ship BB 5, take-home 0.80. Cap 100% -> (20 x 2 + 5) / 0.80 = 56.25. The 90.00 discount price is not used.
        $out = $this->service()->computeTarget(
            $this->row(['ship' => 5, 'margin' => 0.80]),
            $this->pricer(['snroi_cap' => $this->capSlabs(100)])
        );

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
        $this->assertEqualsWithDelta(100.0, ((56.25 * 0.80) - 5 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_then_std_max_then_amz_floor(): void
    {
        $high = $this->service()->computeTarget($this->row(['margin' => 0.80]), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));
        $this->assertNotNull($high);
        $this->assertSame(100.0, $high['sprice']);

        // The cap price 56.25 is below A Price 70, so the Amz rule raises S PRC to 70.
        $amz = $this->service()->computeTarget(
            $this->row(['ship' => 5, 'margin' => 0.80, 'amz' => 70]),
            $this->pricer(['snroi_cap' => $this->capSlabs(100)])
        );
        $this->assertNotNull($amz);
        $this->assertSame(70.0, $amz['sprice']);
        $this->assertTrue($amz['amz_capped']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = $this->service()->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
    }
}
