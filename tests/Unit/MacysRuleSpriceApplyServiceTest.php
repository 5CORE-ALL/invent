<?php

namespace Tests\Unit;

use App\Services\MacysRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class MacysRuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_match_uses_slab_then_floors_to_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 2,
            'lp' => 10,
            'ship' => 0,
            'amz' => 40,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['sprice'], 0.001);
    }

    public function test_keeps_dil_when_at_or_above_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'amz' => 10,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(37.5, $out['sprice'], 0.01);
    }

    public function test_zero_sold_dil_zero_uses_the_zero_slab_then_amazon_floor(): void
    {
        $out = $this->compute([
            'inv' => 41,
            'dil' => 0,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 39.60,
        ], [
            AmazonDilGroiRule::make(0, 0, 30),
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(39.60, $out['sprice'], 0.001);
    }

    public function test_zero_sold_uses_the_dil_matching_slab_not_the_min_groi(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 0,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 50),
            AmazonDilGroiRule::make(5, 10, 40),
        ]);

        $this->assertNotNull($out);
        // Dil 3 -> 50% slab: (10 x 1.5) / 0.80 = 18.75
        $this->assertEqualsWithDelta(18.75, $out['sprice'], 0.01);
    }

    public function test_zero_sold_uses_the_dil_matching_slab_when_at_or_above_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 8,
            'mc_l30' => 0,
            'lp' => 20,
            'ship' => 0,
            'amz' => 10,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        // Dil 8 -> 70% slab: (20 x 1.7) / 0.80 = 42.50
        $this->assertEqualsWithDelta(42.50, $out['sprice'], 0.01);
    }

    public function test_out_of_box_with_sales_uses_std_then_amazon_floor(): void
    {
        $out = $this->compute([
            'inv' => 2,
            'dil' => 300,
            'mc_l30' => 4,
            'lp' => 10,
            'ship' => 0,
            'std' => 95.99,
            'amz' => 94.07,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.99, $out['sprice'], 0.001);
    }

    public function test_out_of_box_std_below_amazon_uses_a_price(): void
    {
        $out = $this->compute([
            'inv' => 2,
            'dil' => 300,
            'mc_l30' => 1,
            'std' => 10.00,
            'amz' => 13.99,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(13.99, $out['sprice'], 0.001);
    }

    public function test_skips_when_inventory_is_zero(): void
    {
        $out = $this->compute([
            'inv' => 0,
            'dil' => 10,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 20,
        ], AmazonDilGroiRule::defaults());

        $this->assertNull($out);
    }

    public function test_sprc_dil_diff_rule_adds_points_to_target(): void
    {
        $out = $this->compute($this->dilRow(['std' => 40, 'mc_price' => 20]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        // Rule price 25, Std 40 → Diff 60% > 20 → target 110 → (10 × 2.1) ÷ 0.8
        $this->assertEqualsWithDelta(26.25, $out['sprice'], 0.01);
    }

    public function test_diff_rule_gives_the_same_price_whatever_the_live_price_is(): void
    {
        $rules = [AmazonDilGroiRule::make(0.1, 25, 100)];
        // Before the push the live price is low, after the push it is the boosted price: the saved S PRC must not move.
        $before = $this->compute($this->dilRow(['std' => 40, 'mc_price' => 20]), $rules, $this->dilCtx());
        $after = $this->compute($this->dilRow(['std' => 40, 'mc_price' => 26.25]), $rules, $this->dilCtx());
        $high = $this->compute($this->dilRow(['std' => 40, 'mc_price' => 39]), $rules, $this->dilCtx());

        $this->assertEqualsWithDelta(26.25, $before['sprice'], 0.001);
        $this->assertEqualsWithDelta($before['sprice'], $after['sprice'], 0.001);
        $this->assertEqualsWithDelta($before['sprice'], $high['sprice'], 0.001);
    }

    public function test_diff_rule_is_off_when_std_is_within_the_threshold_of_the_rule_price(): void
    {
        // Rule price 25, Std 29 → Diff 16% ≤ 20 → no boost.
        $out = $this->compute($this->dilRow(['std' => 29, 'mc_price' => 10]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        $this->assertEqualsWithDelta(25.0, $out['sprice'], 0.01);
    }

    public function test_diff_rule_needs_a_listed_sku_and_a_std_prc(): void
    {
        $rules = [AmazonDilGroiRule::make(0.1, 25, 100)];
        $noLive = $this->compute($this->dilRow(['std' => 40, 'mc_price' => 0]), $rules, $this->dilCtx());
        $noStd = $this->compute($this->dilRow(['std' => 0, 'mc_price' => 20]), $rules, $this->dilCtx());

        $this->assertEqualsWithDelta(25.0, $noLive['sprice'], 0.01);
        $this->assertEqualsWithDelta(25.0, $noStd['sprice'], 0.01);
    }

    public function test_sprc_dil_lmp_cap_uses_lmp_times_dil_band(): void
    {
        $out = $this->compute($this->dilRow(['lmp' => 24]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        // Rule price 25 ≥ LMP 24, SGROI at LMP 92% → LMP × 0.98
        $this->assertEqualsWithDelta(23.52, $out['sprice'], 0.01);
    }

    public function test_sprc_dil_lmp_cap_skipped_when_sgroi_at_lmp_is_low(): void
    {
        $out = $this->compute($this->dilRow(['lmp' => 12.4]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        // SGROI at LMP = (12.4 × 0.8 − 10) ÷ 10 = −8% → no LMP cap, rule price stays
        $this->assertEqualsWithDelta(25.0, $out['sprice'], 0.01);
    }

    public function test_sprc_dil_std_prc_is_the_maximum(): void
    {
        $out = $this->compute($this->dilRow(['std' => 20, 'mc_price' => 20]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        $this->assertEqualsWithDelta(20.0, $out['sprice'], 0.01);
    }

    public function test_sprc_dil_amazon_floor_still_runs_last(): void
    {
        $out = $this->compute($this->dilRow(['lmp' => 24, 'amz' => 30]), [AmazonDilGroiRule::make(0.1, 25, 100)], $this->dilCtx());
        $this->assertEqualsWithDelta(30.0, $out['sprice'], 0.01);
    }

    public function test_without_dil_context_nothing_changes(): void
    {
        $out = $this->compute($this->dilRow(['lmp' => 24, 'std' => 20, 'mc_price' => 10]), [AmazonDilGroiRule::make(0.1, 25, 100)]);
        $this->assertEqualsWithDelta(25.0, $out['sprice'], 0.01);
    }

    /** @return array<string, mixed> */
    private function dilRow(array $extra = []): array
    {
        return array_merge(['inv' => 10, 'dil' => 3, 'mc_l30' => 2, 'lp' => 10, 'ship' => 0, 'amz' => 0], $extra);
    }

    /** @return array{lmp_cap:array,diff_adj:array} */
    private function dilCtx(): array
    {
        return [
            'lmp_cap' => ['low_lt' => 25, 'high_gt' => 50, 'low_pct' => 2, 'mid_pct' => 2, 'high_pct' => 2],
            'diff_adj' => ['gt_pct' => 20, 'points' => 10],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @param  array{lmp_cap:array,diff_adj:array}|null  $dilContext
     * @return array{sprice:float}|null
     */
    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        // LP 10, Ship 2, take-home 0.80, Clearance NROI 10%: (10 × 1.10 + 2) ÷ 0.80 = 16.25.
        // No slab, Std max, LMP cap or A Price floor (amz 40, lmp 12) touches it.
        $out = $this->compute([
            'inv' => 10, 'dil' => 3, 'mc_l30' => 2, 'lp' => 10, 'ship' => 2, 'amz' => 40, 'lmp' => 12, 'std' => 14,
            'clearance' => true, 'clearance_nroi' => 10,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)], ['lmp_cap' => [], 'diff_adj' => []]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(16.25, $out['sprice'], 0.001);
        $this->assertNull($this->compute(['inv' => 10, 'lp' => 0, 'clearance' => true, 'clearance_nroi' => 10], []));
    }

    private function compute(array $row, array $dilRules, ?array $dilContext = null): ?array
    {
        return app(MacysRuleSpriceApplyService::class)->computeTarget($row, $dilRules, 0.80, $dilContext);
    }
}
