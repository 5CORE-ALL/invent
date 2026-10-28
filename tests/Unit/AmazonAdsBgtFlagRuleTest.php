<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtFlagRule;
use App\Support\AmazonAdsSbgt;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtFlagRuleTest extends TestCase
{
    public function test_gap_exists_only_for_flagged_campaigns(): void
    {
        $this->assertSame(15.0, AmazonAdsBgtFlagRule::flagGap(10.0, 25.0, 40.0));
        $this->assertNull(AmazonAdsBgtFlagRule::flagGap(30.0, 25.0, 40.0), 'ACOS above GPFT is not flagged');
        $this->assertNull(AmazonAdsBgtFlagRule::flagGap(25.0, 25.0, 40.0), 'ACOS equal to GPFT is not flagged');
        $this->assertNull(AmazonAdsBgtFlagRule::flagGap(10.0, 25.0, 0.0), 'No spend is not flagged');
        $this->assertNull(AmazonAdsBgtFlagRule::flagGap(null, 25.0, 40.0));
        $this->assertNull(AmazonAdsBgtFlagRule::flagGap(10.0, null, 40.0));
    }

    public function test_apply_uses_first_matching_slab_and_skips_unflagged(): void
    {
        $rule = [
            'bands' => [
                ['flag_from' => 25, 'flag_to' => 9999, 'bgt' => 3, 'label' => 'High', 'color' => '#e83e8c'],
                ['flag_from' => 10, 'flag_to' => 25, 'bgt' => 2, 'label' => 'Mid', 'color' => '#28a745'],
                ['flag_from' => 0, 'flag_to' => 10, 'bgt' => 1, 'label' => 'Low', 'color' => '#2563eb'],
            ],
        ];

        $this->assertSame(3, AmazonAdsBgtFlagRule::apply(30.0, $rule)['bgt']);
        $this->assertSame(3, AmazonAdsBgtFlagRule::apply(25.0, $rule)['bgt']);
        $this->assertSame(2, AmazonAdsBgtFlagRule::apply(12.5, $rule)['bgt']);
        $this->assertSame(1, AmazonAdsBgtFlagRule::apply(4.0, $rule)['bgt']);
        $this->assertNull(AmazonAdsBgtFlagRule::apply(null, $rule)['bgt']);
    }

    public function test_flag_budget_is_the_ninth_sbgt_part(): void
    {
        $this->assertSame(6, AmazonAdsSbgt::sumFromParts(1, 1, 1, null, null, null, 2, 1));
        $this->assertSame(5, AmazonAdsSbgt::sumFromParts(1, 1, 1, null, null, null, 2, null));
    }
}
