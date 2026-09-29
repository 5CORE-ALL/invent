<?php

namespace Tests\Unit;

use App\Support\DilVsSbidRule;
use PHPUnit\Framework\TestCase;

class DilVsSbidRuleTest extends TestCase
{
    public function test_defaults_are_es_bid_then_one_sbid_slab(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $this->assertCount(2, $slabs);
        $this->assertSame('es_bid', $slabs[0]['mode']);
        $this->assertEquals(0, $slabs[0]['min']);
        $this->assertEquals(0, $slabs[0]['max']);

        $this->assertSame('dynamic', $slabs[1]['mode']);
        $this->assertEquals(0.1, $slabs[1]['min']);
        $this->assertEquals(10, $slabs[1]['max']);
    }

    public function test_resolve_uses_es_bid_or_sbid_and_does_not_pause(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $zero = DilVsSbidRule::resolve(0, 12.5, $slabs);
        $this->assertSame('es_bid', $zero['mode']);
        $this->assertSame(12.5, $zero['bid']);
        $this->assertFalse($zero['off']);

        $mid = DilVsSbidRule::resolve(10, 12.5, $slabs);
        $this->assertSame('dynamic', $mid['mode']);
        $this->assertSame(8.0, $mid['bid']);
        $this->assertFalse($mid['off']);

        $above = DilVsSbidRule::resolve(10.01, 12.5, $slabs);
        $this->assertSame('none', $above['mode']);
        $this->assertFalse($above['off']);
    }

    public function test_saved_auto_off_slabs_are_dropped(): void
    {
        $slabs = [
            ['min' => 0, 'max' => 0, 'mode' => 'es_bid', 'bid' => null],
            ['min' => 0.1, 'max' => 10, 'mode' => 'dynamic', 'bid' => 8],
            ['min' => 10, 'max' => 20, 'mode' => 'auto_off', 'bid' => null],
            ['min' => 100, 'max' => 9999, 'mode' => 'auto_off', 'bid' => null],
        ];

        $normalized = DilVsSbidRule::normalize($slabs);
        $this->assertCount(2, $normalized);
        $this->assertSame('es_bid', $normalized[0]['mode']);
        $this->assertSame('dynamic', $normalized[1]['mode']);
        $this->assertFalse(DilVsSbidRule::resolve(55, 0, $slabs)['off']);
    }

    public function test_second_slab_keeps_a_custom_dynamic_bid(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();
        $slabs[1]['bid'] = 6.5;

        $mid = DilVsSbidRule::resolve(4, 9, $slabs);
        $this->assertSame(6.5, $mid['bid']);
        $this->assertSame('dynamic', $mid['mode']);
    }

    public function test_switch_is_off_until_the_account_turns_it_on(): void
    {
        $this->assertFalse(DilVsSbidRule::isEnabled(null));
        $this->assertFalse(DilVsSbidRule::isEnabled(['slabs' => []]));
        $this->assertTrue(DilVsSbidRule::isEnabled(['enabled' => true]));
    }

    public function test_missing_es_bid_does_not_invent_a_percent(): void
    {
        $zero = DilVsSbidRule::resolve(0, 0, DilVsSbidRule::defaultSlabs());
        $this->assertSame('none', $zero['mode']);
        $this->assertSame(0.0, $zero['bid']);
    }
}
