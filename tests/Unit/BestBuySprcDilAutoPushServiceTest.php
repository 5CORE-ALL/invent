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

    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        // (60 × 1.10 + 4) ÷ 0.80 = 87.50 although A Price is 120 and LMP is 50: no discount, LMP, Std or Amz rule applies.
        $row = $this->row(['lp' => 60, 'ship' => 4, 'amz' => 120, 'lmp' => 50, 'clearance' => true, 'clearance_nroi' => 10]);
        $out = $this->service()->computeTarget($row, $this->pricer());
        $this->assertEqualsWithDelta(87.50, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_capped']);
        $this->assertFalse($out['lmp_capped']);
        $this->assertNull($this->service()->computeTarget($this->row(['lp' => 0, 'clearance' => true, 'clearance_nroi' => 10]), $this->pricer()));
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

    /** @return list<array{min:float,max:float,cap:float|null}> */
    private function capSlabs(?float $cap): array
    {
        return [['min' => 0.0, 'max' => 100.0, 'cap' => $cap]];
    }

    public function test_snroi_cap_prices_s_prc_from_the_target_with_ship_bb_and_no_ads(): void
    {
        // LP 20, Ship BB 5, take-home 0.80. Cap 100% -> (20 x 2 + 5) / 0.80 = 56.25. The 90.00 discount price is not used.
        $out = $this->service()->computeTarget($this->row(['ship' => 5]), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
        $this->assertEqualsWithDelta(100.0, ((56.25 * 0.80) - 5 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_keeps_lmp_cap_amz_floor_and_std_max(): void
    {
        // Cap price 56.25 is below LMP 80: LMP cap does not apply.
        $lmp = $this->service()->computeTarget($this->row(['ship' => 5, 'lmp' => 80]), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));
        $this->assertNotNull($lmp);
        $this->assertSame(56.25, $lmp['sprice']);
        $this->assertFalse($lmp['lmp_capped']);

        // The cap price is below A Price 70: the Amz rule still raises S PRC to A Price.
        $amz = $this->service()->computeTarget($this->row(['ship' => 5, 'amz' => 70]), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));
        $this->assertNotNull($amz);
        $this->assertSame(70.0, $amz['sprice']);
        $this->assertTrue($amz['amz_capped']);

        // A target that needs more than Std holds at Std.
        $high = $this->service()->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));
        $this->assertNotNull($high);
        $this->assertSame(100.0, $high['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = $this->service()->computeTarget($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

        $this->assertNotNull($out);
        $this->assertSame(90.0, $out['sprice']);
    }

    /** @return array<string, mixed> */
    private function dilCtx(): array
    {
        return [
            'rules' => [
                ['key' => '0-0', 'label' => '0%', 'min' => 0.0, 'max' => 0.0, 'groi' => 40.0],
                ['key' => '0.1-50', 'label' => '0.1–50%', 'min' => 0.1, 'max' => 50.0, 'groi' => 60.0],
            ],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
            'lmp_cap' => ['low_lt' => 25, 'high_gt' => 50, 'low_pct' => 5, 'mid_pct' => 5, 'high_pct' => 5],
        ];
    }

    /** @return array<string, mixed> */
    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['ship' => 5, 'price' => 100], $over));
    }

    private function dilTarget(array $row): ?array
    {
        return $this->service()->computeTarget($row, null, $this->dilCtx());
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship_bb(): void
    {
        $out = $this->dilTarget($this->dilRow());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6 + 5) / 0.80 = 46.25. Std 100 is far above.
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
        $this->assertFalse($out['amz_capped']);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs Price 70 -> Diff 42.9% (> 20): Target 70%. (20 x 1.7 + 5) / 0.80 = 48.75.
        $out = $this->dilTarget($this->dilRow(['price' => 70]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(48.75, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_dil_outside_every_slab_has_no_price(): void
    {
        $this->assertNull($this->dilTarget($this->dilRow(['dil' => 80])));
    }

    public function test_sprc_dil_lmp_cap_uses_the_dil_band_and_has_no_std_maximum(): void
    {
        // Price 46.25 is above LMP 40 and SGROI at LMP is 35% (>= 20): S PRC = LMP x (1 - 5%) = 38.00.
        $out = $this->dilTarget($this->dilRow(['lmp' => 40]));
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(38.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);

        // No Std Prc maximum on Best Buy: a low Std Prc does not cap the price.
        $std = $this->dilTarget($this->dilRow(['standard_price' => 30]));
        $this->assertNotNull($std);
        $this->assertEqualsWithDelta(46.25, $std['sprice'], 0.001);
    }

    public function test_sprc_dil_amz_rule_raises_to_a_price_and_wins_over_the_lmp_cap(): void
    {
        // Price 46.25 is below A Price 60: S PRC = A Price.
        $amz = $this->dilTarget($this->dilRow(['amz' => 60]));
        $this->assertNotNull($amz);
        $this->assertEqualsWithDelta(60.0, $amz['sprice'], 0.001);
        $this->assertTrue($amz['amz_capped']);

        // LMP 40 caps it to 38.00, which is below A Price 45: A Price wins.
        $both = $this->dilTarget($this->dilRow(['lmp' => 40, 'amz' => 45]));
        $this->assertNotNull($both);
        $this->assertEqualsWithDelta(45.0, $both['sprice'], 0.001);
        $this->assertTrue($both['amz_capped']);
        $this->assertFalse($both['lmp_capped']);

        // Price above A Price stays.
        $above = $this->dilTarget($this->dilRow(['amz' => 40]));
        $this->assertNotNull($above);
        $this->assertEqualsWithDelta(46.25, $above['sprice'], 0.001);
        $this->assertFalse($above['amz_capped']);
    }

    public function test_sprc_dil_no_inventory_has_no_target(): void
    {
        $this->assertNull($this->dilTarget($this->dilRow(['inv' => 0])));
    }

    public function test_without_a_dil_context_the_std_rule_still_prices(): void
    {
        $out = $this->service()->computeTarget($this->row(), $this->pricer(), null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
