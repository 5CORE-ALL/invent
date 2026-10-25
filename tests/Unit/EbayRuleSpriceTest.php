<?php

namespace Tests\Unit;

use App\Support\AmazonDilGroiRule;
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

    public function test_clearance_sku_is_priced_only_from_the_clearance_nroi(): void
    {
        $pricer = $this->pricer(['buss' => [['min' => 0, 'max' => 9999, 'disc' => 50]]]);
        $row = ['lp' => 40, 'ship' => 5, 'clearance' => 'YES', 'percentage' => 0.8, 'ads' => 0.0, 'lmp' => 30];
        // No Clearance NROI% known: nothing is priced.
        $this->assertNull(EbayRuleSprice::price($pricer, $this->input($row)));
        // (40 × 1.10 + 5) ÷ 0.80 = 61.25, in Std prc vs dil mode and in Sprc Dil mode. LMP 30 and Std do not cap it.
        $std = EbayRuleSprice::price($pricer, $this->input($row), ['rule' => 'std', 'dil' => null, 'clearance_nroi' => 10.0]);
        $this->assertSame(61.25, $std['sprice']);
        $this->assertFalse($std['lmp_capped']);
        $dil = ['rules' => [], 'cvr_adj' => [], 'lmp_cap' => null, 'diff_adj' => null];
        $this->assertSame(61.25, EbayRuleSprice::price($pricer, $this->input($row), ['rule' => 'dil', 'dil' => $dil, 'clearance_nroi' => 10.0])['sprice']);
        // Ads 5% comes off the take-home: (40 × 1.10 + 5) ÷ (0.80 − 0.05) = 65.33.
        $this->assertSame(65.33, EbayRuleSprice::price($pricer, $this->input(['ads' => 0.05] + $row), ['rule' => 'std', 'dil' => null, 'clearance_nroi' => 10.0])['sprice']);
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

    public function test_sprc_dil_switch_prices_from_target_nroi_and_lands_on_lmp_x_098(): void
    {
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 0, 40), AmazonDilGroiRule::make(0.1, 100, 40)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null)];
        $context = ['rule' => 'dil', 'dil' => $dil];
        // Target NROI 40%: (50 × 1.4 + 5) ÷ (0.80 − 0.10) = 107.14, over LMP 90 (SGROI at LMP = 34%) → LMP × 0.98.
        $input = $this->input(['lp' => 50, 'ship' => 5, 'lmp' => 90, 'percentage' => 0.8, 'ads' => 0.10, 'inv' => 10, 'dil' => 5, 'cvr' => 8, 'cvr_60' => 8]);
        $out = EbayRuleSprice::price($this->pricer(), $input, $context);
        $this->assertSame(107.14, $out['rule_price']);
        $this->assertSame(88.2, $out['sprice']);
        $this->assertTrue($out['lmp_capped']);
        // The same SKU on Std prc vs dil (switch default) lands on LMP itself.
        $std = EbayRuleSprice::price($this->pricer(), $input, ['rule' => 'std', 'dil' => null]);
        $this->assertSame(90.0, $std['sprice']);
    }

    public function test_sprc_dil_lmp_cap_reduce_pct_follows_the_dil_band(): void
    {
        $lmpCap = ['low_lt' => 25, 'high_gt' => 50, 'low_pct' => 2, 'mid_pct' => 5, 'high_pct' => 10];
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 0, 40), AmazonDilGroiRule::make(0.1, 1000, 40)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null), 'lmp_cap' => $lmpCap];
        $context = ['rule' => 'dil', 'dil' => $dil];
        // Rule price 107.14 is over LMP 90 (SGROI at LMP ≥ 20%) → S PRC = LMP less the band's %.
        $base = ['lp' => 50, 'ship' => 5, 'lmp' => 90, 'percentage' => 0.8, 'ads' => 0.10, 'inv' => 10, 'cvr' => 8, 'cvr_60' => 8];
        $this->assertSame(88.2, EbayRuleSprice::price($this->pricer(), $this->input(['dil' => 10] + $base), $context)['sprice']);
        $this->assertSame(85.5, EbayRuleSprice::price($this->pricer(), $this->input(['dil' => 30] + $base), $context)['sprice']);
        $this->assertSame(81.0, EbayRuleSprice::price($this->pricer(), $this->input(['dil' => 60] + $base), $context)['sprice']);
    }

    public function test_lmp_cap_config_is_normalized_and_defaults_to_2_percent(): void
    {
        $this->assertSame(2.0, AmazonDilGroiRule::lmpCapReducePct(40));
        $cfg = AmazonDilGroiRule::normalizeLmpCap(['low_lt' => 60, 'high_gt' => 10, 'mid_pct' => 500, 'low_pct' => -1]);
        $this->assertSame(60.0, $cfg['high_gt']);
        $this->assertSame(99.0, $cfg['mid_pct']);
        $this->assertSame(2.0, $cfg['low_pct']);
        $this->assertSame(2.0, AmazonDilGroiRule::unpackStored(['rules' => [], 'cvr_adj' => []])['lmp_cap']['high_pct']);
    }

    public function test_sprc_dil_adds_10_to_target_nroi_when_std_vs_price_diff_is_over_20_percent(): void
    {
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 0, 40), AmazonDilGroiRule::make(0.1, 100, 40)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null), 'diff_adj' => true];
        $context = ['rule' => 'dil', 'dil' => $dil];
        $base = ['lp' => 50, 'ship' => 5, 'lmp' => 0, 'percentage' => 0.8, 'ads' => 0.10, 'inv' => 10, 'dil' => 5, 'cvr' => 8, 'cvr_60' => 8, 'std' => 500];

        // Diff = (500 − 100) / 100 = 400% > 20% → Target 50%: (50 × 1.5 + 5) ÷ 0.70 = 114.29
        $over = EbayRuleSprice::price($this->pricer(), $this->input($base + ['price' => 100]), $context);
        $this->assertSame(114.29, $over['rule_price']);
        // Diff = 0% → stays on Target 40%: 107.14
        $flat = EbayRuleSprice::price($this->pricer(), $this->input(['std' => 500, 'price' => 500] + $base), $context);
        $this->assertSame(107.14, $flat['rule_price']);
        // Rule off (not eBay 1) → no boost
        $dil['diff_adj'] = false;
        $off = EbayRuleSprice::price($this->pricer(), $this->input($base + ['price' => 100]), ['rule' => 'dil', 'dil' => $dil]);
        $this->assertSame(107.14, $off['rule_price']);
    }

    public function test_sprc_dil_diff_rule_uses_the_saved_threshold_and_points(): void
    {
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 0, 40), AmazonDilGroiRule::make(0.1, 100, 40)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null)];
        $base = ['lp' => 50, 'ship' => 5, 'lmp' => 0, 'percentage' => 0.8, 'ads' => 0.10, 'inv' => 10, 'dil' => 5, 'cvr' => 8, 'cvr_60' => 8, 'std' => 120];

        // Diff = (120 − 100) / 100 = 20%: not above the default 20%, so no boost.
        $dil['diff_adj'] = AmazonDilGroiRule::defaultDiffAdj();
        $same = EbayRuleSprice::price($this->pricer(), $this->input($base + ['price' => 100]), ['rule' => 'dil', 'dil' => $dil]);
        $this->assertSame(107.14, $same['rule_price']);
        // Saved threshold 10% and +20 points: Target 60% → (50 × 1.6 + 5) ÷ 0.70 = 121.43
        $dil['diff_adj'] = ['gt_pct' => 10, 'points' => 20];
        $custom = EbayRuleSprice::price($this->pricer(), $this->input($base + ['price' => 100]), ['rule' => 'dil', 'dil' => $dil]);
        $this->assertSame(121.43, $custom['rule_price']);
    }

    public function test_sprc_dil_price_is_capped_at_std_prc_even_when_lmp_is_above_std(): void
    {
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 0, 40), AmazonDilGroiRule::make(0.1, 100, 115)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null)];
        $base = ['lp' => 22.3, 'ship' => 8, 'percentage' => 0.83, 'ads' => 0.093, 'inv' => 10, 'dil' => 5, 'cvr' => 8, 'cvr_60' => 8, 'price' => 70];
        // Rule price ~ $75.9, LMP $71.95 is above Std $65.99: the Std Prc maximum wins.
        $out = EbayRuleSprice::price($this->pricer(), $this->input($base + ['lmp' => 71.95, 'std' => 65.99]), ['rule' => 'dil', 'dil' => $dil]);
        $this->assertSame(65.99, $out['sprice']);
        $this->assertTrue($out['std_capped']);
        // A blank Std Prc does not cap.
        $none = EbayRuleSprice::price($this->pricer(), $this->input($base + ['lmp' => 71.95, 'std' => 0]), ['rule' => 'dil', 'dil' => $dil]);
        $this->assertGreaterThan(65.99, $none['sprice']);
    }

    public function test_sprc_dil_switch_skips_a_sku_without_inventory(): void
    {
        $dil = ['rules' => [AmazonDilGroiRule::make(0, 100, 40)], 'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(null)];
        $input = $this->input(['inv' => 0, 'dil' => 5]);
        $this->assertNull(EbayRuleSprice::price($this->pricer(), $input, ['rule' => 'dil', 'dil' => $dil]));
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

    public function test_std_ceiling_still_applies_when_lmp_is_above_std(): void
    {
        $pricer = $this->pricer([]);
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

    public function test_ebay_snroi_cap_prices_from_the_target_with_the_page_ads_and_ship(): void
    {
        foreach (EbayRuleSprice::CHANNELS as $channel) {
            $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 1000, 'cap' => 20.0]]], $channel);
            $input = EbayRuleSprice::inputFromTabulatorRow([
                '(Child) sku' => 'SKU-1', 'INV' => 10, 'L30' => 2, 'STANDARD_PRICE' => 100,
                'LP_productmaster' => 40, 'Ship_productmaster' => 3, 'percentage' => 0.88,
                'AD%' => 3.0, 'SCVR' => 5, 'CVR_45' => 5,
            ], 6.0);

            // Page Ads% (6), not the row AD% (3): (40 x 1.20 + 3) / (0.88 - 0.06) = 62.2 → 62.19 (rounded down).
            $priced = EbayRuleSprice::price($pricer, $input);
            $this->assertSame(62.19, $priced['sprice'], $channel);
            // The SNROI% column of the page at that S PRC is not above the target.
            $snroi = (62.19 * (0.88 - 0.06) - 3 - 40) / 40 * 100;
            $this->assertLessThanOrEqual(20.0 + 1e-9, $snroi, $channel);
            $this->assertGreaterThan(19.9, $snroi, $channel);
        }
    }

    public function test_ebay_snroi_cap_target_above_std_holds_at_std_and_lmp_cap_still_applies(): void
    {
        $pricer = $this->pricer(['snroi_cap' => [['min' => 0, 'max' => 1000, 'cap' => 500.0]]], 'ebay1');
        $row = [
            '(Child) sku' => 'SKU-1', 'INV' => 10, 'L30' => 2, 'STANDARD_PRICE' => 100,
            'LP_productmaster' => 40, 'Ship_productmaster' => 3, 'percentage' => 0.88,
            'SCVR' => 5, 'CVR_45' => 5,
        ];
        // A target that needs more than Std holds at Std Prc.
        $this->assertSame(100.0, EbayRuleSprice::price($pricer, EbayRuleSprice::inputFromTabulatorRow($row, 6.0))['sprice']);
        // Amazon order: SNROI cap first, then the LMP cap can still bring it down (SGROI at LMP stays >= 20%).
        $withLmp = EbayRuleSprice::inputFromTabulatorRow($row + ['lmp_price' => 80], 6.0);
        $this->assertSame(80.0, EbayRuleSprice::price($pricer, $withLmp)['sprice']);
    }

    public function test_ebay_slab_without_a_cap_keeps_the_discount_price(): void
    {
        $pricer = $this->pricer([
            'dil' => [['min' => 0, 'max' => 100, 'disc' => 10]],
            'snroi_cap' => [['min' => 0, 'max' => 1000, 'cap' => null]],
        ], 'ebay3');
        $input = EbayRuleSprice::inputFromTabulatorRow([
            '(Child) sku' => 'SKU-1', 'INV' => 10, 'L30' => 2, 'STANDARD_PRICE' => 100,
            'LP_productmaster' => 40, 'Ship_productmaster' => 3, 'percentage' => 0.88,
            'SCVR' => 5, 'CVR_45' => 5,
        ], 6.0);

        $this->assertSame(90.0, EbayRuleSprice::price($pricer, $input)['sprice']);
    }

    public function test_cvr_trend_override_is_ignored_when_invalid(): void
    {
        $pricer = $this->pricer(['cvr' => ['down2_lt' => 4, 'down2_disc' => 8, 'down_lt' => 7, 'down_disc' => 5,
            'up_gt' => 3, 'up_disc' => 3, 'up2_gt' => 15, 'up2_disc' => 3, 'flat_disc' => 1]]);
        // Bad override → generic trend: CVR 5, CVR 60 5 → flat 1%.
        $this->assertSame(99.0, EbayRuleSprice::price($pricer, $this->input(['cvr_trend' => 'sideways']))['sprice']);
    }
}
