<?php

namespace Tests\Unit;

use App\Services\SheinSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class SheinSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'shein');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'SHEIN-TEST',
            'inv' => 10,
            'dil' => 20,
            'al30' => 2,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 5,
            'margin' => 0.80,
            'standard_price' => 100,
            'lmp' => 0,
            'clearance' => false,
        ], $over);
    }

    private function target(array $row, ?StdPrcVsDilPricer $pricer = null, float $ads = 0.0): ?array
    {
        return (new SheinSprcDilAutoPushService)->computeTarget($row, $pricer ?? $this->pricer(), $ads);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = $this->target($this->row());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_zero_sold_uses_al30(): void
    {
        $out = $this->target($this->row(['al30' => 0]));

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_clearance_sku_has_no_target_like_amazon(): void
    {
        // Clearance SKUs show "C" in S PRC. Nothing is saved or pushed for them.
        $this->assertNull($this->target($this->row(['standard_price' => 120, 'lp' => 70, 'ship' => 10, 'clearance' => true])));
    }

    public function test_lmp_below_the_price_caps_to_lmp(): void
    {
        $out = $this->target($this->row(['lmp' => 85]));

        $this->assertNotNull($out);
        // SGROI at LMP 85 is 215% (≥ 20%), so the price drops to LMP.
        $this->assertEqualsWithDelta(85.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);
        $this->assertEqualsWithDelta(90.00, $out['uncapped'], 0.001);
    }

    public function test_lmp_above_the_price_does_not_raise_it(): void
    {
        $out = $this->target($this->row(['lmp' => 120]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
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

    public function test_missing_margin_defaults_to_one_like_the_page(): void
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
        // LP 20, Ship 5, margin 0.80. Cap 100% -> (20 x 2 + 5) / 0.80 = 56.25. The 90.00 discount price is not used.
        // A channel Ads% of 10% passed in must not change it: Shein's SNROI% column has no Ads%.
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(100)]), 0.10);

        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);
        $this->assertEqualsWithDelta(100.0, ((56.25 * 0.80) - 5 - 20) / 20 * 100, 0.01);
    }

    public function test_snroi_cap_then_lmp_cap_then_std_max(): void
    {
        // The cap price 56.25 is below LMP 70, so the LMP cap does not apply. Std 100 is the maximum.
        $out = $this->target($this->row(['lmp' => 70]), $this->pricer(['snroi_cap' => $this->capSlabs(100)]));
        $this->assertNotNull($out);
        $this->assertSame(56.25, $out['sprice']);

        $high = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(1000)]));
        $this->assertNotNull($high);
        $this->assertSame(100.0, $high['sprice']);
    }

    public function test_snroi_cap_blank_slab_keeps_the_discount_price(): void
    {
        $out = $this->target($this->row(), $this->pricer(['snroi_cap' => $this->capSlabs(null)]));

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
            'cvr_adj' => ['down_lt' => 7, 'down_adj' => -5, 'up_gt' => 10, 'up_adj' => 5],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
            'lmp_cap' => ['low_lt' => 25, 'high_gt' => 50, 'low_pct' => 5, 'mid_pct' => 5, 'high_pct' => 5],
        ];
    }

    /** @return array<string, mixed> */
    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['price' => 100, 'views' => 0, 'cvr' => 8], $over));
    }

    private function dilTarget(array $row): ?array
    {
        return (new SheinSprcDilAutoPushService)->computeTarget($row, null, null, $this->dilCtx());
    }

    public function test_sprc_dil_price_is_the_slab_target_with_ship(): void
    {
        $out = $this->dilTarget($this->dilRow());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6 + 5) / 0.80 = 46.25. Std 100 is far above.
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs Sp. Price 70 -> Diff 42.9% (> 20): Target 70%. (20 x 1.7 + 5) / 0.80 = 48.75.
        $out = $this->dilTarget($this->dilRow(['price' => 70]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(48.75, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_cvr_overlay_only_applies_with_views(): void
    {
        // CVR 5% < 7% (down): Adj -5 -> Target 55%. (20 x 1.55 + 5) / 0.80 = 45.00.
        $withViews = $this->dilTarget($this->dilRow(['views' => 200, 'cvr' => 5]));
        $this->assertNotNull($withViews);
        $this->assertEqualsWithDelta(45.00, $withViews['sprice'], 0.001);

        $noViews = $this->dilTarget($this->dilRow(['views' => 0, 'cvr' => 5]));
        $this->assertNotNull($noViews);
        $this->assertEqualsWithDelta(46.25, $noViews['sprice'], 0.001);
    }

    public function test_sprc_dil_dil_above_every_slab_uses_the_nearest_slab(): void
    {
        $out = $this->dilTarget($this->dilRow(['dil' => 80]));

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(46.25, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_lmp_cap_uses_the_dil_band_and_std_is_the_maximum(): void
    {
        // Price 46.25 is above LMP 40 and SGROI at LMP is 35% (>= 20): S PRC = LMP x (1 - 5%) = 38.00.
        $out = $this->dilTarget($this->dilRow(['lmp' => 40]));
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(38.00, $out['sprice'], 0.001);
        $this->assertTrue($out['lmp_capped']);

        $std = $this->dilTarget($this->dilRow(['standard_price' => 30]));
        $this->assertNotNull($std);
        $this->assertEqualsWithDelta(30.0, $std['sprice'], 0.001);
    }

    public function test_sprc_dil_clearance_and_no_inventory_have_no_target(): void
    {
        $this->assertNull($this->dilTarget($this->dilRow(['clearance' => true])));
        $this->assertNull($this->dilTarget($this->dilRow(['inv' => 0])));
    }

    public function test_without_a_dil_context_the_std_rule_still_prices(): void
    {
        $out = (new SheinSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer(), 0.0, null);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
