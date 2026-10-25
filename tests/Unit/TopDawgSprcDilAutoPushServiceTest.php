<?php

namespace Tests\Unit;

use App\Services\TopDawgSprcDilAutoPushService;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class TopDawgSprcDilAutoPushServiceTest extends TestCase
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
        ], $over), 'topdawg');
    }

    /** @return array<string, mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'sku' => 'TD-TEST',
            'inv' => 10,
            'dil' => 20,
            'td_l30' => 2,
            'cvr' => 5,
            'age_days' => 10,
            'lp' => 20,
            'ship' => 0,
            'margin' => 0.95,
            'lmp' => 0,
            'standard_price' => 100,
            'clearance' => false,
        ], $over);
    }

    public function test_price_is_std_minus_age_dil_and_b_disc(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2) / 100) = 90.00. NPFT is far above 10%.
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }

    public function test_zero_sold_adds_the_zero_sold_disc(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['td_l30' => 0]), $this->pricer());

        $this->assertNotNull($out);
        // 100 × (1 − (3 + 5 + 2 + 4) / 100) = 86.00
        $this->assertEqualsWithDelta(86.00, $out['sprice'], 0.001);
    }

    public function test_amazon_price_does_not_raise_sprice(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['amz' => 95.49]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_capped']);
    }

    public function test_no_inventory_or_std_has_no_target(): void
    {
        $svc = new TopDawgSprcDilAutoPushService;
        $this->assertNull($svc->computeTarget($this->row(['inv' => 0]), $this->pricer()));
        $this->assertNull($svc->computeTarget($this->row(['standard_price' => 0]), $this->pricer()));
    }

    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        // LP 70, take-home 0.95, Clearance NROI 10%: 70 × 1.10 ÷ 0.95 = 81.05. No Ship, discount or Std max (std 50) applies.
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 70, 'ship' => 8, 'standard_price' => 50, 'clearance' => true, 'clearance_nroi' => 10]),
            $this->pricer()
        );
        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(81.05, $out['sprice'], 0.001);
        $this->assertNull((new TopDawgSprcDilAutoPushService)->computeTarget(
            $this->row(['lp' => 0, 'clearance' => true, 'clearance_nroi' => 10]),
            $this->pricer()
        ));
    }

    public function test_ship_is_reduced_from_sprice_after_the_calculation(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['ship' => 8]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(82.00, $out['sprice'], 0.001);
    }

    public function test_snroi_cap_judges_the_price_after_ship_is_reduced(): void
    {
        // 90.00 − Ship 8 = 82.00. SNROI at 82 = ((82 × 0.95) − 20) / 20 = 194%. Cap 100% → price (20 × 2) / 0.95 = 42.10.
        $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 100, 'cap' => 100.0]]]);
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['ship' => 8]), $pricer);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(42.10, $out['sprice'], 0.001);

        // Ship must never be taken off AFTER the cap: that would push SNROI below the target (even negative).
        $this->assertGreaterThan(20 / 0.95, $out['sprice']);
    }

    public function test_lmp_is_ignored(): void
    {
        // TopDawg has no LMP cap: even a low LMP on the row does not lower the target.
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['lmp' => 80]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
        $this->assertArrayNotHasKey('lmp_capped', $out);
    }

    public function test_margin_given_as_percent_is_normalised(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(['margin' => 85]), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(0.85, $out['margin'], 0.0001);
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
        ];
    }

    /** @return array<string, mixed> */
    private function dilRow(array $over = []): array
    {
        return $this->row(array_merge(['ads_pct' => 5, 'ship' => 3, 'price' => 100, 'cvr' => 8], $over));
    }

    public function test_sprc_dil_price_is_the_slab_target_without_ship_or_ads(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->dilRow(), null, $this->dilCtx());

        $this->assertNotNull($out);
        // Target 60%: (20 x 1.6) / 0.95 = 33.68. Ship and Ads are not in the TopDawg price. Std 100 is far above.
        $this->assertEqualsWithDelta(33.68, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_diff_adds_points_when_std_is_above_price_by_more_than_the_threshold(): void
    {
        // Std 100 vs TD Price 70 -> Diff 42.9% (> 20): Target 60 + 10 = 70%. (20 x 1.7) / 0.95 = 35.79.
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->dilRow(['price' => 70]), null, $this->dilCtx());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(35.79, $out['sprice'], 0.001);
    }

    public function test_sprc_dil_has_no_lmp_cap_and_std_price_is_the_maximum(): void
    {
        $svc = new TopDawgSprcDilAutoPushService;
        $withLmp = $svc->computeTarget($this->dilRow(['lmp' => 20]), null, $this->dilCtx());
        $this->assertNotNull($withLmp);
        $this->assertEqualsWithDelta(33.68, $withLmp['sprice'], 0.001);

        $capped = $svc->computeTarget($this->dilRow(['standard_price' => 30]), null, $this->dilCtx());
        $this->assertNotNull($capped);
        $this->assertEqualsWithDelta(30.00, $capped['sprice'], 0.001);
    }

    public function test_sprc_dil_prices_clearance_and_skips_no_inv_and_out_of_slab(): void
    {
        $svc = new TopDawgSprcDilAutoPushService;
        // Clearance ignores the slab and Std max: the Clearance NROI% prices it in Sprc Dil mode too (20 × 1.10 ÷ 0.95 = 23.16).
        $clr = $svc->computeTarget($this->dilRow(['clearance' => true, 'clearance_nroi' => 10, 'standard_price' => 20]), null, $this->dilCtx());
        $this->assertNotNull($clr);
        $this->assertEqualsWithDelta(23.16, $clr['sprice'], 0.001);

        $this->assertNull($svc->computeTarget($this->dilRow(['inv' => 0]), null, $this->dilCtx()));
        $this->assertNull($svc->computeTarget($this->dilRow(['dil' => 80]), null, $this->dilCtx()));
    }

    public function test_no_dil_context_leaves_the_std_prc_behaviour_unchanged(): void
    {
        $out = (new TopDawgSprcDilAutoPushService)->computeTarget($this->row(), $this->pricer());

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(90.00, $out['sprice'], 0.001);
    }
}
