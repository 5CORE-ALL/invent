<?php

namespace Tests\Unit;

use App\Support\DilVsSbidRule;
use PHPUnit\Framework\TestCase;

class DilVsSbidRuleTest extends TestCase
{
    public function test_defaults_are_es_bid_then_dynamic_then_auto_off_through_over_100(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $this->assertSame('es_bid', $slabs[0]['mode']);
        $this->assertEquals(0, $slabs[0]['min']);
        $this->assertEquals(0, $slabs[0]['max']);

        $this->assertSame('dynamic', $slabs[1]['mode']);
        $this->assertEquals(0.1, $slabs[1]['min']);
        $this->assertEquals(10, $slabs[1]['max']);

        $this->assertSame('auto_off', $slabs[2]['mode']);
        $this->assertEquals(10, $slabs[2]['min']);
        $this->assertEquals(20, $slabs[2]['max']);

        $last = $slabs[count($slabs) - 1];
        $this->assertSame('auto_off', $last['mode']);
        $this->assertEquals(100, $last['min']);
        $this->assertEquals(9999, $last['max']);
    }

    public function test_resolve_uses_es_bid_dynamic_and_auto_off(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $zero = DilVsSbidRule::resolve(0, 12.5, $slabs);
        $this->assertSame('es_bid', $zero['mode']);
        $this->assertSame(12.5, $zero['bid']);
        $this->assertFalse($zero['off']);

        $mid = DilVsSbidRule::resolve(10, 12.5, $slabs);
        $this->assertSame('dynamic', $mid['mode']);
        $this->assertSame(8.0, $mid['bid']);

        $justOver = DilVsSbidRule::resolve(10.01, 12.5, $slabs);
        $this->assertTrue($justOver['off']);
        $this->assertSame('auto_off', $justOver['mode']);

        $high = DilVsSbidRule::resolve(140, 12.5, $slabs);
        $this->assertTrue($high['off']);
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
