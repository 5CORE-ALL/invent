<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtPrcRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtPrcRuleTest extends TestCase
{
    public function test_defaults_are_pink_to_red(): void
    {
        $bands = AmazonAdsBgtPrcRule::defaultBands();
        $this->assertCount(5, $bands);
        $this->assertSame('Pink', $bands[0]['label']);
        $this->assertSame('Red', $bands[4]['label']);
        $this->assertSame(0, $bands[4]['prc_from']);
        $this->assertSame(5, $bands[0]['bgt']);
        $this->assertSame(1, $bands[4]['bgt']);
    }

    public function test_normalize_keeps_custom_slabs_and_allows_zero_bgt(): void
    {
        $rule = AmazonAdsBgtPrcRule::normalizeRule([
            'bands' => [
                ['prc_from' => 50, 'prc_to' => 9999, 'bgt' => 0, 'label' => 'High', 'color' => '#111111'],
                ['prc_from' => 0, 'prc_to' => 50, 'bgt' => 2, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertCount(2, $rule['bands']);
        $this->assertSame(0, $rule['bands'][0]['bgt']);
        $this->assertSame(0.0, $rule['bands'][1]['prc_from']);
    }

    public function test_flips_legacy_red_first_five_slabs(): void
    {
        $rule = AmazonAdsBgtPrcRule::normalizeRule([
            'bands' => [
                ['prc_from' => 20, 'prc_to' => 40, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
                ['prc_from' => 41, 'prc_to' => 60, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['prc_from' => 61, 'prc_to' => 100, 'bgt' => 1, 'label' => 'Blue', 'color' => '#2563eb'],
                ['prc_from' => 101, 'prc_to' => 150, 'bgt' => 2, 'label' => 'Green', 'color' => '#28a745'],
                ['prc_from' => 151, 'prc_to' => 9999, 'bgt' => 3, 'label' => 'Pink', 'color' => '#e83e8c'],
            ],
        ]);
        $this->assertSame('Pink', $rule['bands'][0]['label']);
        $this->assertSame(3, $rule['bands'][0]['bgt']);
        $this->assertSame(151.0, $rule['bands'][0]['prc_from']);
        $this->assertSame('Red', $rule['bands'][4]['label']);
        $this->assertSame(20.0, $rule['bands'][4]['prc_from']);
    }

    public function test_apply_matches_first_slab_including_zero_price_and_zero_bgt(): void
    {
        $rule = AmazonAdsBgtPrcRule::normalizeRule([
            'bands' => [
                ['prc_from' => 50, 'prc_to' => 9999, 'bgt' => 8, 'label' => 'High', 'color' => '#111111'],
                ['prc_from' => 0, 'prc_to' => 50, 'bgt' => 0, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertSame(8, AmazonAdsBgtPrcRule::apply(50.0, $rule)['bgt']);
        $this->assertSame(0, AmazonAdsBgtPrcRule::apply(10.0, $rule)['bgt']);
        $this->assertSame('Low', AmazonAdsBgtPrcRule::apply(0.0, $rule)['label']);
        $this->assertNull(AmazonAdsBgtPrcRule::apply(null, $rule)['bgt']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtPrcRule::normalizeRule([
            'bands' => [
                ['prc_from' => 80, 'prc_to' => 20, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
