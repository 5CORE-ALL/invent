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
        $this->assertSame(70.0, $rules[0]['groi']);
        $this->assertSame('20-25', $rules[4]['key']);
        $this->assertSame('20–25%', $rules[4]['label']);
        $this->assertSame(50.0, $rules[4]['groi']);
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
        $this->assertSame(70.0, AmazonDilGroiRule::groiForDil(2.5, $rules));
        $this->assertSame(65.0, AmazonDilGroiRule::groiForDil(7, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(0, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(30, $rules));
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

    public function test_suggested_price_matches_amazon_groi_formula(): void
    {
        $lp = 40.0;
        $ship = 8.0;
        $groi = 70.0;
        $expected = round(($lp * (1 + $groi / 100) + $ship) / 0.80, 2);
        $this->assertSame($expected, AmazonDilGroiRule::suggestedPrice($lp, $ship, $groi));
        $this->assertNull(AmazonDilGroiRule::suggestedPrice(0, $ship, $groi));
    }
}
