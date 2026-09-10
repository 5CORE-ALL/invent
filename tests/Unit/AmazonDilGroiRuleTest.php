<?php

namespace Tests\Unit;

use App\Support\AmazonDilGroiRule;
use PHPUnit\Framework\TestCase;

class AmazonDilGroiRuleTest extends TestCase
{
    public function test_defaults_are_five_slabs_from_0_1_to_25(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertCount(5, $rules);
        $this->assertSame('0.1-5', $rules[0]['key']);
        $this->assertSame('0.1–5%', $rules[0]['label']);
        $this->assertSame(0.1, $rules[0]['min']);
        $this->assertSame(5.0, $rules[0]['max']);
        $this->assertSame(50.0, $rules[0]['groi']);
        $this->assertSame('20-25', $rules[4]['key']);
        $this->assertSame('20–25%', $rules[4]['label']);
        $this->assertSame(70.0, $rules[4]['groi']);
    }

    public function test_slab_key_boundaries(): void
    {
        $this->assertNull(AmazonDilGroiRule::slabKey(0));
        $this->assertNull(AmazonDilGroiRule::slabKey(0.09));
        $this->assertSame('0.1-5', AmazonDilGroiRule::slabKey(0.1));
        $this->assertSame('0.1-5', AmazonDilGroiRule::slabKey(4.99));
        $this->assertSame('5-10', AmazonDilGroiRule::slabKey(5));
        $this->assertSame('10-15', AmazonDilGroiRule::slabKey(10));
        $this->assertSame('15-20', AmazonDilGroiRule::slabKey(15));
        $this->assertSame('20-25', AmazonDilGroiRule::slabKey(20));
        $this->assertSame('20-25', AmazonDilGroiRule::slabKey(25));
        $this->assertNull(AmazonDilGroiRule::slabKey(25.01));
        $this->assertNull(AmazonDilGroiRule::slabKey(40));
    }

    public function test_groi_for_dil_uses_matching_slab(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertSame(50.0, AmazonDilGroiRule::groiForDil(2.5, $rules));
        $this->assertSame(55.0, AmazonDilGroiRule::groiForDil(7, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(0, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(30, $rules));
    }

    public function test_match_or_nearest_clamps_below_and_above_slabs(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertSame('0.1-5', AmazonDilGroiRule::matchOrNearest(0, $rules)['key']);
        $this->assertSame(50.0, AmazonDilGroiRule::matchOrNearest(0, $rules)['groi']);
        $this->assertSame('20-25', AmazonDilGroiRule::matchOrNearest(40, $rules)['key']);
        $this->assertSame(70.0, AmazonDilGroiRule::matchOrNearest(40, $rules)['groi']);
        $this->assertSame('5-10', AmazonDilGroiRule::matchOrNearest(7, $rules)['key']);
    }

    public function test_normalize_list_keeps_custom_slab_count(): void
    {
        $rules = AmazonDilGroiRule::normalizeList([
            ['min' => 0.1, 'max' => 10, 'groi' => 80],
            ['key' => '10-25', 'groi' => 40],
        ]);
        $this->assertCount(2, $rules);
        $this->assertSame('0.1-10', $rules[0]['key']);
        $this->assertSame(80.0, $rules[0]['groi']);
        $this->assertSame('10-25', $rules[1]['key']);
        $this->assertSame(40.0, AmazonDilGroiRule::groiForDil(12, $rules));
        $this->assertSame(40.0, AmazonDilGroiRule::groiForDil(25, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(26, $rules));
    }

    public function test_min_target_is_lowest_groi_in_table(): void
    {
        $rules = AmazonDilGroiRule::normalizeList([
            ['min' => 0.1, 'max' => 5, 'groi' => 50],
            ['min' => 5, 'max' => 10, 'groi' => 55],
            ['min' => 50, 'max' => 60, 'groi' => 100],
        ]);
        $this->assertSame(50.0, AmazonDilGroiRule::minTarget($rules));
        $this->assertNull(AmazonDilGroiRule::minTarget([]));
    }

    public function test_suggested_price_matches_amazon_groi_formula(): void
    {
        $lp = 40.0;
        $ship = 8.0;
        $groi = 70.0;
        $expected = round(($lp * (1 + $groi / 100) + $ship) / 0.80, 2);
        $this->assertSame($expected, AmazonDilGroiRule::suggestedPrice($lp, $ship, $groi));
        $this->assertNull(AmazonDilGroiRule::suggestedPrice(0, $ship, $groi));
    }

    public function test_cvr_trend_matches_tabulator_l30_vs_l45(): void
    {
        $this->assertSame('down', AmazonDilGroiRule::cvrTrend(0, 8));
        $this->assertSame('down', AmazonDilGroiRule::cvrTrend(5, 6));
        $this->assertSame('up', AmazonDilGroiRule::cvrTrend(12, 9));
        $this->assertSame('flat', AmazonDilGroiRule::cvrTrend(8, 8));
        $this->assertSame('flat', AmazonDilGroiRule::cvrTrend(8.05, 8));
    }

    public function test_adjust_groi_for_cvr_down_below_7_and_up_above_10(): void
    {
        $this->assertSame(40.0, AmazonDilGroiRule::adjustGroiForCvr(50, 6.9, 'down'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 7, 'down'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 5, 'flat'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12, 'down'));
        $this->assertSame(60.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10.1, 'up'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10, 'up'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 6, 'up'));
        $this->assertSame(0.0, AmazonDilGroiRule::adjustGroiForCvr(5, 1, 'down'));
    }

    public function test_adjust_groi_uses_saved_cvr_overlay_thresholds(): void
    {
        $cfg = ['down_lt' => 5, 'down_adj' => -8, 'up_gt' => 12, 'up_adj' => 6];
        $this->assertSame(42.0, AmazonDilGroiRule::adjustGroiForCvr(50, 4.9, 'down', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 5, 'down', $cfg));
        $this->assertSame(56.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12.1, 'up', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12, 'up', $cfg));
    }

    public function test_unpack_stored_keeps_legacy_slab_list_and_cvr_adj_wrapper(): void
    {
        $legacy = AmazonDilGroiRule::unpackStored([
            ['min' => 0.1, 'max' => 5, 'groi' => 50],
        ]);
        $this->assertCount(1, $legacy['rules']);
        $this->assertSame(7.0, $legacy['cvr_adj']['down_lt']);
        $this->assertSame(-10.0, $legacy['cvr_adj']['down_adj']);

        $wrapped = AmazonDilGroiRule::unpackStored([
            'rules' => [['min' => 0.1, 'max' => 5, 'groi' => 50]],
            'cvr_adj' => ['down_lt' => 6, 'down_adj' => -12, 'up_gt' => 11, 'up_adj' => 9],
        ]);
        $this->assertSame(50.0, $wrapped['rules'][0]['groi']);
        $this->assertSame(6.0, $wrapped['cvr_adj']['down_lt']);
        $this->assertSame(-12.0, $wrapped['cvr_adj']['down_adj']);
        $this->assertSame(11.0, $wrapped['cvr_adj']['up_gt']);
        $this->assertSame(9.0, $wrapped['cvr_adj']['up_adj']);
    }

    public function test_adjust_groi_level_only_ignores_trend(): void
    {
        $this->assertSame(40.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 6.9));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 7));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 8.5));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 10));
        $this->assertSame(60.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 10.1));
        $this->assertSame(0.0, AmazonDilGroiRule::adjustGroiForCvrLevel(5, 1));
    }
}
