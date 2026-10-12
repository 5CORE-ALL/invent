<?php

namespace Tests\Unit;

use App\Support\EbayRuleSprice;
use App\Support\StdPrcVsDilPricer;
use PHPUnit\Framework\TestCase;

class EbayRuleSpriceTest extends TestCase
{
    private function pricer(array $rules = [], string $channel = 'ebay1'): StdPrcVsDilPricer
    {
        return new StdPrcVsDilPricer(array_merge([
            'dil' => [],
            'age' => [],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 0, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [],
            'review_max' => 4,
            'buss' => [],
            'zero_sold_disc' => 0,
            'min_npft' => 0,
        ], $rules), $channel);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SKU-1',
            'inv' => 10,
            'std' => 100,
            'dil' => 0,
            'cvr' => 5,
            'cvr_60' => 5,
            'cvr_trend' => 'flat',
            'ebay_l30' => 3,
            'lp' => 10,
            'ship' => 0,
            'percentage' => 0.85,
            'ads_frac' => 0,
            'lmp' => 0,
            'age_days' => 0,
            'clearance' => false,
        ], $overrides);
    }

    public function test_cvr_trend_is_cvr30_vs_cvr45_like_the_amazon_std_rule(): void
    {
        $this->assertSame('down', EbayRuleSprice::cvrTrend(0, 0));
        $this->assertSame('down', EbayRuleSprice::cvrTrend(0, 3));
        $this->assertSame('down', EbayRuleSprice::cvrTrend(2, 2.5));
        $this->assertSame('up', EbayRuleSprice::cvrTrend(2.5, 2));
        $this->assertSame('up', EbayRuleSprice::cvrTrend(5, 0));
        $this->assertSame('flat', EbayRuleSprice::cvrTrend(4.55, 4.5));
        $this->assertSame('flat', EbayRuleSprice::cvrTrend(4.45, 4.5));
    }

    public function test_input_uses_the_ebay_row_only(): void
    {
        $input = EbayRuleSprice::inputFromTabulatorRow([
            '(Child) sku' => 'ABC 1',
            'Parent' => 'PARENT ABC',
            'INV' => 20,
            'L30' => 5,
            'eBay L30' => 0,
            'SCVR' => 1.5,
            'CVR_45' => 2.0,
            'CVR_60' => 2.5,
            'STANDARD_PRICE' => 49.999,
            'LP_productmaster' => 12,
            'Ship_productmaster' => 4,
            'percentage' => 0.86,
            'AD%' => 7,
            'lmp_price' => 40,
            'lmp_entries' => [
                ['price' => 30, 'ignored' => 'yes'],
                ['landed_price' => 0, 'total_price' => 38.5],
                ['price' => 41],
            ],
        ]);

        $this->assertSame('ABC 1', $input['sku']);
        $this->assertSame(20.0, $input['inv']);
        $this->assertSame(50.0, $input['std']);
        $this->assertSame(25.0, $input['dil']);
        $this->assertSame('down', $input['cvr_trend']);
        $this->assertSame(2.0, $input['cvr_60']);
        $this->assertSame(0.0, $input['ebay_l30']);
        $this->assertEqualsWithDelta(0.07, $input['ads_frac'], 1e-9);
        $this->assertSame(0.86, $input['percentage']);
        $this->assertSame(38.5, $input['lmp']);
    }

    public function test_cvr45_falls_back_to_the_l30_l60_midpoint(): void
    {
        $this->assertSame(3.0, EbayRuleSprice::cvr45(['SCVR' => 2, 'CVR_60' => 4]));
        $this->assertSame(1.2, EbayRuleSprice::cvr45(['SCVR' => 2, 'CVR_45' => 1.2, 'CVR_60' => 4]));
    }

    public function test_lmp_falls_back_to_stored_lmp_price(): void
    {
        $this->assertSame(40.0, EbayRuleSprice::effectiveLmp(['lmp_price' => 40, 'lmp_entries' => [['price' => 20, 'ignored' => true]]]));
        $this->assertSame(0.0, EbayRuleSprice::effectiveLmp([]));
    }

    public function test_parent_rows_are_not_priced(): void
    {
        $this->assertNull(EbayRuleSprice::inputFromTabulatorRow(['(Child) sku' => 'PARENT ABC', 'INV' => 5]));
        $this->assertNull(EbayRuleSprice::inputFromTabulatorRow(['(Child) sku' => 'ABC', 'is_parent_summary' => true]));
        $this->assertNull(EbayRuleSprice::inputFromTabulatorRow(['(Child) sku' => '']));
    }

    public function test_inv_zero_std_zero_are_skipped(): void
    {
        $pricer = $this->pricer();
        $this->assertNull(EbayRuleSprice::price($pricer, $this->input(['inv' => 0])));
        $this->assertNull(EbayRuleSprice::price($pricer, $this->input(['std' => 0])));
    }

    public function test_no_discount_keeps_std(): void
    {
        $out = EbayRuleSprice::price($this->pricer(), $this->input());
        $this->assertSame(100.0, $out['sprice']);
        $this->assertFalse($out['lmp_capped']);
        $this->assertFalse($out['std_capped']);
    }

    public function test_discounts_add_and_under_15_halves_all_but_b_disc(): void
    {
        $pricer = $this->pricer([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 10]],
            'age' => [['min' => 0, 'max' => 30, 'disc' => 4]],
            'buss' => [['min' => 0, 'max' => 15, 'disc' => 20], ['min' => 15, 'max' => 9999, 'disc' => 6]],
            'cvr' => ['down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 0, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 2],
        ]);
        // $100: 10 + 4 + 6 + 2 = 22% → 78.00.
        $this->assertSame(78.0, EbayRuleSprice::price($pricer, $this->input(['dil' => 30]))['sprice']);
        // $10: (10 + 4 + 2) × 0.5 = 8% + B Disc 20% (full) = 28% → 7.20.
        $this->assertSame(7.2, EbayRuleSprice::price($pricer, $this->input(['std' => 10, 'lp' => 1, 'dil' => 30]))['sprice']);
        // $15 is not under $15: 10 + 4 + 6 + 2 = 22% → 11.70.
        $this->assertSame(11.7, EbayRuleSprice::price($pricer, $this->input(['std' => 15, 'lp' => 1, 'dil' => 30]))['sprice']);
    }

    public function test_discount_sum_is_capped_below_100(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 100]], 'dil' => [['min' => 0, 'max' => 100, 'disc' => 50]]]);
        // 150% → 99.99% → $100 × 0.0001 = 0.01.
        $this->assertSame(0.01, EbayRuleSprice::price($pricer, $this->input(['lp' => 0]))['sprice']);
    }

    public function test_zero_sold_uses_ebay_l30(): void
    {
        $pricer = $this->pricer(['zero_sold_disc' => 6]);
        $this->assertSame(94.0, EbayRuleSprice::price($pricer, $this->input(['ebay_l30' => 0]))['sprice']);
        $this->assertSame(100.0, EbayRuleSprice::price($pricer, $this->input(['ebay_l30' => 1]))['sprice']);
        // Amazon / generic sold qty never decides eBay 0 Sold.
        $this->assertSame(94.0, EbayRuleSprice::price($pricer, $this->input(['ebay_l30' => 0, 'a_l30' => 9, 'sold' => 9]))['sprice']);
    }

    public function test_cvr_uses_the_ebay_trend_not_the_generic_one(): void
    {
        $pricer = $this->pricer(['cvr' => ['down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
            'up_gt' => 3, 'up_disc' => 3, 'up2_gt' => 15, 'up2_disc' => 3, 'flat_disc' => 0]]);
        // Comparator 0: generic logic would be flat (0%). eBay trend is Up → up disc 3%.
        $this->assertSame(97.0, EbayRuleSprice::price($pricer, $this->input([
            'cvr' => 5, 'cvr_60' => 0, 'cvr_trend' => EbayRuleSprice::cvrTrend(5, 0),
        ]))['sprice']);
        // CVR 0 → Down, under 4 → 8%.
        $this->assertSame(92.0, EbayRuleSprice::price($pricer, $this->input([
            'cvr' => 0, 'cvr_60' => 2, 'cvr_trend' => EbayRuleSprice::cvrTrend(0, 2),
        ]))['sprice']);
    }

    public function test_min_npft_raises_with_ebay_margin_and_ebay_ads(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 50]], 'min_npft' => 10]);
        // 45 / (0.85 − 0.05 − 0.10) = 64.2857 → 64.29.
        $this->assertSame(64.29, EbayRuleSprice::price($pricer, $this->input(['lp' => 40, 'ship' => 5, 'ads_frac' => 0.05]))['sprice']);
        // Without ads: 45 / 0.75 = 60.00.
        $this->assertSame(60.0, EbayRuleSprice::price($pricer, $this->input(['lp' => 40, 'ship' => 5]))['sprice']);
        // Already above the floor: stays at the discount price.
        $this->assertSame(50.0, EbayRuleSprice::price($pricer, $this->input(['lp' => 10, 'ship' => 2]))['sprice']);
    }

    public function test_clearance_skips_min_npft(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 50]], 'min_npft' => 10]);
        $this->assertSame(50.0, EbayRuleSprice::price($pricer, $this->input(['lp' => 40, 'ship' => 5, 'clearance' => 'YES']))['sprice']);
    }

    public function test_lmp_cap_applies_when_sgroi_at_lmp_is_20_or_more(): void
    {
        $out = EbayRuleSprice::price($this->pricer(), $this->input(['lp' => 40, 'ship' => 5, 'lmp' => 80]));
        $this->assertSame(80.0, $out['sprice']);
        $this->assertTrue($out['lmp_capped']);
        $this->assertSame(100.0, $out['rule_price']);
    }

    public function test_lmp_gate_boundary_at_exactly_20_percent(): void
    {
        // (120 × 0.5 − 50) / 50 = 20% → cap.
        $at = EbayRuleSprice::price($this->pricer(), $this->input(['std' => 150, 'lp' => 50, 'percentage' => 0.5, 'lmp' => 120]));
        $this->assertSame(120.0, $at['sprice']);
        // (119.98 × 0.5 − 50) / 50 = 19.98% → no cap.
        $below = EbayRuleSprice::price($this->pricer(), $this->input(['std' => 150, 'lp' => 50, 'percentage' => 0.5, 'lmp' => 119.98]));
        $this->assertSame(150.0, $below['sprice']);
        $this->assertFalse($below['lmp_capped']);
    }

    public function test_lmp_gate_uses_the_ebay_take_home_margin(): void
    {
        // LMP 70, LP 50, Ship 5: margin 0.95 → 23% (cap); margin 0.85 → 9% (no cap).
        $this->assertSame(70.0, EbayRuleSprice::price($this->pricer(), $this->input(['lp' => 50, 'ship' => 5, 'lmp' => 70, 'percentage' => 0.95]))['sprice']);
        $this->assertSame(100.0, EbayRuleSprice::price($this->pricer(), $this->input(['lp' => 50, 'ship' => 5, 'lmp' => 70, 'percentage' => 85]))['sprice']);
    }

    public function test_lmp_never_raises_a_price_below_it(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 40]]]);
        $out = EbayRuleSprice::price($pricer, $this->input(['lmp' => 80]));
        $this->assertSame(60.0, $out['sprice']);
        $this->assertFalse($out['lmp_capped']);
    }

    public function test_price_equal_to_lmp_is_treated_as_at_lmp(): void
    {
        $out = EbayRuleSprice::price($this->pricer(), $this->input(['lmp' => 100]));
        $this->assertSame(100.0, $out['sprice']);
    }

    public function test_std_is_the_ceiling_after_min_npft(): void
    {
        $pricer = $this->pricer(['min_npft' => 10]);
        // Min NPFT floor = 50 / 0.75 = 66.67, above Std 60 → Std 60.
        $out = EbayRuleSprice::price($pricer, $this->input(['std' => 60, 'lp' => 45, 'ship' => 5]));
        $this->assertSame(66.67, $out['rule_price']);
        $this->assertSame(60.0, $out['sprice']);
        $this->assertTrue($out['std_capped']);
    }

    public function test_std_ceiling_still_applies_when_lmp_is_above_std(): void
    {
        $pricer = $this->pricer(['min_npft' => 10]);
        $out = EbayRuleSprice::price($pricer, $this->input(['std' => 60, 'lp' => 45, 'ship' => 5, 'lmp' => 70]));
        $this->assertSame(60.0, $out['sprice']);
    }

    public function test_result_is_rounded_to_cents(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 33.333]]]);
        $this->assertSame(66.67, EbayRuleSprice::price($pricer, $this->input())['sprice']);
    }

    public function test_only_ebay_channels_use_their_own_metrics(): void
    {
        foreach (EbayRuleSprice::CHANNELS as $channel) {
            $this->assertTrue(StdPrcVsDilPricer::usesOwnChannelMetrics($channel));
        }
        foreach (['amazon', 'macys', 'shopify_b2c', 'temu', ''] as $channel) {
            $this->assertFalse(StdPrcVsDilPricer::usesOwnChannelMetrics($channel));
        }
    }

    public function test_cvr_trend_override_is_ignored_when_invalid(): void
    {
        $pricer = $this->pricer(['cvr' => ['down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
            'up_gt' => 3, 'up_disc' => 3, 'up2_gt' => 15, 'up2_disc' => 3, 'flat_disc' => 1]]);
        // Bad override → generic trend: CVR 5, CVR 60 5 → flat 1%.
        $this->assertSame(99.0, EbayRuleSprice::price($pricer, $this->input(['cvr_trend' => 'sideways']))['sprice']);
    }
}
