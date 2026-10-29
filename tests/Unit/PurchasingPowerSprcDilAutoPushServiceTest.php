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

    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        // LP 20, Ship BB 4, take-home 0.80, Clearance NROI 10%: (20 x 1.10 + 4) / 0.80 = 32.50.
        // Std rule (90), A Price floor (amz 60) and Std Prc do not touch it. It is saved and pushed like any SKU.
        $out = $this->service()->computeTarget(
            $this->row(['ship' => 4, 'margin' => 0.80, 'amz' => 60, 'clearance' => true, 'clearance_nroi' => 10]),
            $this->pricer()
        );

        $this->assertNotNull($out);
        $this->assertSame(32.5, $out['sprice']);
        $this->assertFalse($out['amz_capped']);
    }

    public function test_clearance_price_is_the_same_in_both_modes_and_needs_lp_and_inventory(): void
    {
        $row = $this->row(['ship' => 4, 'margin' => 0.80, 'amz' => 60, 'clearance' => true, 'clearance_nroi' => 10]);
        $std = $this->service()->computeTarget($row, $this->pricer());
        $dil = $this->service()->computeTarget($row, null, $this->dilCtx());

        $this->assertSame(32.5, $std['sprice']);
        $this->assertSame($std['sprice'], $dil['sprice']);
        $this->assertNull($this->service()->computeTarget(array_merge($row, ['lp' => 0]), $this->pricer()));
        $this->assertNull($this->service()->computeTarget(array_merge($row, ['inv' => 0]), $this->pricer()));
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

    /** @return array{rules:list<array<string,mixed>>,diff_adj:array} */
    private function dilCtx(): array
    {
        return [
            'rules' => [
                ['key' => '0-0', 'label' => '0%', 'min' => 0.0, 'max' => 0.0, 'groi' => 40.0],
                ['key' => '0.1-50', 'label' => '0.1–50%', 'min' => 0.1, 'max' => 50.0, 'groi' => 60.0],
            ],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
        ];
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship_bb_and_no_discounts(): void
    {
        // Dil 20% -> Target 60%. LP 20, Ship BB 5, take-home 0.80: (20 x 1.6 + 5) / 0.80 = 46.25. Std Prc 50 is within the Diff threshold.
        $out = $this->service()->computeTarget(
            $this->row(['ship' => 5, 'margin' => 0.80, 'price' => 100, 'standard_price' => 50]),
            null,
            $this->dilCtx()
        );

        $this->assertNotNull($out);
        $this->assertSame(46.25, $out['sprice']);
        $this->assertFalse($out['amz_capped']);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Rule price 46.25, Std 100 -> Diff 116% (> 20): Target 60 + 10 = 70%. (20 x 1.7 + 5) / 0.80 = 48.75.
        $out = $this->service()->computeTarget(
            $this->row(['ship' => 5, 'margin' => 0.80, 'price' => 70]),
            null,
            $this->dilCtx()
        );

        $this->assertNotNull($out);
        $this->assertSame(48.75, $out['sprice']);
    }

    public function test_sprc_dil_diff_gives_the_same_price_whatever_the_live_price_is(): void
    {
        // The boosted price is pushed, so the live price becomes 48.75. The saved S PRC must not move (no flip-flop).
        $prices = [];
        foreach ([30, 46.25, 48.75, 90] as $live) {
            $out = $this->service()->computeTarget($this->row(['ship' => 5, 'margin' => 0.80, 'price' => $live]), null, $this->dilCtx());
            $prices[] = $out['sprice'];
        }

        $this->assertSame([48.75, 48.75, 48.75, 48.75], $prices);
    }

    public function test_sprc_dil_diff_is_off_when_std_is_within_the_threshold_of_the_rule_price(): void
    {
        // Rule price 46.25, Std 54 -> Diff 16.8% (<= 20): no boost, whatever the live price is.
        foreach ([40, 100] as $live) {
            $out = $this->service()->computeTarget($this->row(['ship' => 5, 'margin' => 0.80, 'price' => $live, 'standard_price' => 54]), null, $this->dilCtx());
            $this->assertSame(46.25, $out['sprice']);
        }
    }

    public function test_sprc_dil_pp_l30_zero_still_uses_the_dil_matching_slab(): void
    {
        // PP L30 = 0 changes nothing, same as the page: Dil 20% -> Target 60%: (20 x 1.6 + 0) / 0.80 = 40.00
        // (Std 40 is within the Diff threshold: no Diff). It must NOT drop to the lowest slab Target.
        $out = $this->service()->computeTarget(
            $this->row(['pp_l30' => 0, 'margin' => 0.80, 'price' => 100, 'standard_price' => 40]),
            null,
            $this->dilCtx()
        );

        $this->assertNotNull($out);
        $this->assertSame(40.0, $out['sprice']);

        // Dil = 0 uses the 0-0 slab (Target 40%): (20 x 1.4) / 0.80 = 35.00
        $zeroDil = $this->service()->computeTarget(
            $this->row(['pp_l30' => 0, 'dil' => 0, 'margin' => 0.80, 'price' => 100, 'standard_price' => 40]),
            null,
            $this->dilCtx()
        );
        $this->assertSame(35.0, $zeroDil['sprice']);
    }

    public function test_sprc_dil_has_no_std_maximum_only_the_amz_floor(): void
    {
        // LP 80 -> (80 x 1.6) / 0.80 = 160 > Std 100 -> NOT held at Std: stays 160.
        $uncapped = $this->service()->computeTarget($this->row(['lp' => 80, 'margin' => 0.80, 'price' => 100]), null, $this->dilCtx());
        $this->assertSame(160.0, $uncapped['sprice']);
        $this->assertFalse($uncapped['amz_capped']);

        // A Price 200 is above it -> Amz floor 200.
        $floored = $this->service()->computeTarget($this->row(['lp' => 80, 'margin' => 0.80, 'price' => 100, 'amz' => 200]), null, $this->dilCtx());
        $this->assertSame(200.0, $floored['sprice']);
        $this->assertTrue($floored['amz_capped']);
    }

    public function test_sprc_dil_has_no_lmp_cap_even_when_a_row_carries_an_lmp(): void
    {
        // Purchasing Power has no LMP: an lmp value on the row (it never has one) must not change the price.
        $with = $this->service()->computeTarget($this->row(['ship' => 5, 'margin' => 0.80, 'price' => 100, 'lmp' => 30]), null, $this->dilCtx());
        $without = $this->service()->computeTarget($this->row(['ship' => 5, 'margin' => 0.80, 'price' => 100]), null, $this->dilCtx());

        $this->assertSame($without['sprice'], $with['sprice']);
    }

    public function test_sprc_dil_no_inventory_has_no_target(): void
    {
        $this->assertNull($this->service()->computeTarget($this->row(['inv' => 0]), null, $this->dilCtx()));
    }

    public function test_std_prc_vs_dil_is_unchanged_without_a_dil_context(): void
    {
        $out = $this->service()->computeTarget($this->row(), $this->pricer());

        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
