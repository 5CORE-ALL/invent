<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtDilRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtDilRuleTest extends TestCase
{
    public function test_defaults_are_three_slabs_pink_green_red(): void
    {
        $bands = AmazonAdsBgtDilRule::defaultBands();
        $this->assertCount(3, $bands);
        $this->assertSame('Pink', $bands[0]['label']);
        $this->assertSame('Green', $bands[1]['label']);
        $this->assertSame('Red', $bands[2]['label']);
        $this->assertSame(50, $bands[0]['dil_from']);
        $this->assertSame(25, $bands[1]['dil_from']);
        $this->assertSame(0, $bands[2]['dil_from']);
        $this->assertSame(3, $bands[0]['bgt']);
        $this->assertSame(2, $bands[1]['bgt']);
        $this->assertSame(1, $bands[2]['bgt']);
    }

    public function test_normalize_keeps_custom_slab_count_and_allows_zero_bgt(): void
    {
        $rule = AmazonAdsBgtDilRule::normalizeRule([
            'bands' => [
                ['dil_from' => 40, 'dil_to' => 9999, 'bgt' => 0, 'label' => 'High', 'color' => '#111111'],
                ['dil_from' => 0, 'dil_to' => 40, 'bgt' => 2, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertCount(2, $rule['bands']);
        $this->assertSame(0, $rule['bands'][0]['bgt']);
        $this->assertSame(0.0, $rule['bands'][1]['dil_from']);
        $this->assertSame(2, $rule['bands'][1]['bgt']);
    }

    public function test_apply_uses_first_matching_slab(): void
    {
        $rule = AmazonAdsBgtDilRule::defaults();
        $this->assertSame(3, AmazonAdsBgtDilRule::apply(50.0, $rule)['bgt']);
        $this->assertSame('Pink', AmazonAdsBgtDilRule::apply(80.0, $rule)['label']);
        $this->assertSame(2, AmazonAdsBgtDilRule::apply(25.0, $rule)['bgt']);
        $this->assertSame('Green', AmazonAdsBgtDilRule::apply(40.0, $rule)['label']);
        $this->assertSame(1, AmazonAdsBgtDilRule::apply(0.0, $rule)['bgt']);
        $this->assertSame('Red', AmazonAdsBgtDilRule::apply(24.9, $rule)['label']);
        $this->assertNull(AmazonAdsBgtDilRule::apply(null, $rule)['bgt']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtDilRule::normalizeRule([
            'bands' => [
                ['dil_from' => 80, 'dil_to' => 20, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }

    public function test_normalize_rejects_negative_bgt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtDilRule::normalizeRule([
            'bands' => [
                ['dil_from' => 0, 'dil_to' => 10, 'bgt' => -1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
