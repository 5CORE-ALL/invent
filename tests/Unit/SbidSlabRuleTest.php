<?php

namespace Tests\Unit;

use App\Support\SbidSlabRule;
use PHPUnit\Framework\TestCase;

class SbidSlabRuleTest extends TestCase
{
    public function test_paused_slab_pauses_a_matching_listing_and_is_left_out_of_the_zero_sold_max(): void
    {
        $slabs = [
            ['l7_views_min' => 0, 'l7_views_max' => 30, 'sbid' => 9, 'paused' => false],
            ['l7_views_min' => 31, 'l7_views_max' => 60, 'sbid' => 8, 'paused' => true],
            ['l7_views_min' => 61, 'l7_views_max' => 90, 'sbid' => 7, 'paused' => false],
        ];

        $paused = SbidSlabRule::match(3, 40, $slabs);
        $this->assertTrue($paused['pause']);
        $this->assertSame(0.0, $paused['bid']);
        $this->assertSame(0.0, SbidSlabRule::resolve(3, 40, $slabs));

        $open = SbidSlabRule::match(3, 10, $slabs);
        $this->assertFalse($open['pause']);
        $this->assertSame(9.0, $open['bid']);

        $zeroSold = SbidSlabRule::match(0, 40, $slabs);
        $this->assertFalse($zeroSold['pause']);
        $this->assertSame(9.0, $zeroSold['bid']);
    }
}
