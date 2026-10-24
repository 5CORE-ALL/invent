<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtGpftRule;
use App\Support\AmazonAdsSbgt;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtGpftRuleTest extends TestCase
{
    public function test_defaults_cover_negative_gpft_and_start_at_zero_budget(): void
    {
        $bands = AmazonAdsBgtGpftRule::defaultBands();
        $this->assertSame(-9999.0, (float) $bands[2]['gpft_from']);
        $this->assertSame(9999.0, (float) $bands[0]['gpft_to']);
        foreach ($bands as $band) {
            $this->assertSame(0, $band['bgt']);
        }
    }

    public function test_apply_uses_first_matching_slab_including_negative_gpft(): void
    {
        $rule = [
            'bands' => [
                ['gpft_from' => 30, 'gpft_to' => 9999, 'bgt' => 3, 'label' => 'High', 'color' => '#e83e8c'],
                ['gpft_from' => 15, 'gpft_to' => 30, 'bgt' => 2, 'label' => 'Mid', 'color' => '#28a745'],
                ['gpft_from' => -9999, 'gpft_to' => 15, 'bgt' => -1, 'label' => 'Low', 'color' => '#a00211'],
            ],
        ];

        $this->assertSame(3, AmazonAdsBgtGpftRule::apply(42.5, $rule)['bgt']);
        $this->assertSame(3, AmazonAdsBgtGpftRule::apply(30.0, $rule)['bgt']);
        $this->assertSame(2, AmazonAdsBgtGpftRule::apply(20.0, $rule)['bgt']);
        $this->assertSame(-1, AmazonAdsBgtGpftRule::apply(-12.0, $rule)['bgt']);
        $this->assertNull(AmazonAdsBgtGpftRule::apply(null, $rule)['bgt']);
    }

    public function test_flag_bgt_replaces_bgt_only_for_flagged_campaigns_and_blank_falls_back(): void
    {
        $rule = AmazonAdsBgtGpftRule::normalizeRule([
            'bands' => [
                ['gpft_from' => 30, 'gpft_to' => 9999, 'bgt' => 3, 'bgt_flag' => 6, 'label' => 'High', 'color' => '#e83e8c'],
                ['gpft_from' => 15, 'gpft_to' => 30, 'bgt' => 2, 'bgt_flag' => '', 'label' => 'Mid', 'color' => '#28a745'],
                ['gpft_from' => -9999, 'gpft_to' => 15, 'bgt' => 1, 'bgt_flag' => 0, 'label' => 'Low', 'color' => '#a00211'],
            ],
        ]);

        $this->assertNull($rule['bands'][1]['bgt_flag']);
        $this->assertSame(0, $rule['bands'][2]['bgt_flag']);
        $this->assertTrue(AmazonAdsBgtGpftRule::hasFlagBudgets($rule));

        $this->assertSame(3, AmazonAdsBgtGpftRule::apply(40.0, $rule)['bgt']);
        $this->assertSame(6, AmazonAdsBgtGpftRule::apply(40.0, $rule, true)['bgt']);
        $this->assertTrue(AmazonAdsBgtGpftRule::apply(40.0, $rule, true)['flagged']);
        // Blank flag Bgt: a flagged campaign keeps the normal Bgt.
        $this->assertSame(2, AmazonAdsBgtGpftRule::apply(20.0, $rule, true)['bgt']);
        $this->assertFalse(AmazonAdsBgtGpftRule::apply(20.0, $rule, true)['flagged']);
        // An explicit 0 flag Bgt is a real value, not "blank".
        $this->assertSame(0, AmazonAdsBgtGpftRule::apply(5.0, $rule, true)['bgt']);

        $noFlags = AmazonAdsBgtGpftRule::normalizeRule(['bands' => [['gpft_from' => 0, 'gpft_to' => 9999, 'bgt' => 1]]]);
        $this->assertFalse(AmazonAdsBgtGpftRule::hasFlagBudgets($noFlags));
    }

    public function test_is_flagged_needs_spend_and_acos_below_gpft(): void
    {
        $this->assertTrue(AmazonAdsBgtGpftRule::isFlagged(20.0, 35.0, 10.0));
        $this->assertFalse(AmazonAdsBgtGpftRule::isFlagged(40.0, 35.0, 10.0));
        $this->assertFalse(AmazonAdsBgtGpftRule::isFlagged(20.0, 35.0, 0.0));
        $this->assertFalse(AmazonAdsBgtGpftRule::isFlagged(null, 35.0, 10.0));
        $this->assertFalse(AmazonAdsBgtGpftRule::isFlagged(20.0, null, 10.0));
    }

    public function test_bgt_gpft_is_the_eighth_sbgt_part(): void
    {
        $this->assertSame(5, AmazonAdsSbgt::sumFromParts(1, 1, 1, null, null, null, null, 2));
        $this->assertSame(1, AmazonAdsSbgt::sumFromParts(2, 0, 0, null, null, null, null, -1));
    }
}
