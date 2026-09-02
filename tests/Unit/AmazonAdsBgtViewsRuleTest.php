<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtViewsRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtViewsRuleTest extends TestCase
{
    public function test_defaults_are_purple_to_red(): void
    {
        $bands = AmazonAdsBgtViewsRule::defaultBands();
        $this->assertCount(6, $bands);
        $this->assertSame('Purple', $bands[0]['label']);
        $this->assertSame('Red', $bands[5]['label']);
        $this->assertSame(0, $bands[5]['views_from']);
        $this->assertSame(6, $bands[0]['bgt']);
        $this->assertSame(1, $bands[5]['bgt']);
    }

    public function test_normalize_keeps_custom_slab_count_and_allows_zero_bgt(): void
    {
        $rule = AmazonAdsBgtViewsRule::normalizeRule([
            'bands' => [
                ['views_from' => 100, 'views_to' => 9999, 'bgt' => 0, 'label' => 'High', 'color' => '#111111'],
                ['views_from' => 0, 'views_to' => 99, 'bgt' => 3, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertCount(2, $rule['bands']);
        $this->assertSame(0, $rule['bands'][0]['bgt']);
        $this->assertSame(0.0, $rule['bands'][1]['views_from']);
        $this->assertSame(3, $rule['bands'][1]['bgt']);
    }

    public function test_flips_legacy_red_first_six_slabs(): void
    {
        $rule = AmazonAdsBgtViewsRule::normalizeRule([
            'bands' => [
                ['views_from' => 0, 'views_to' => 70, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
                ['views_from' => 71, 'views_to' => 140, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['views_from' => 141, 'views_to' => 210, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
                ['views_from' => 211, 'views_to' => 280, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
                ['views_from' => 281, 'views_to' => 350, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
                ['views_from' => 351, 'views_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ],
        ]);
        $this->assertSame('Purple', $rule['bands'][0]['label']);
        $this->assertSame(6, $rule['bands'][0]['bgt']);
        $this->assertSame(351.0, $rule['bands'][0]['views_from']);
        $this->assertSame('Red', $rule['bands'][5]['label']);
        $this->assertSame(0.0, $rule['bands'][5]['views_from']);
        $this->assertSame(1, $rule['bands'][5]['bgt']);
    }

    public function test_apply_uses_first_matching_slab_including_zero_views_and_zero_bgt(): void
    {
        $rule = AmazonAdsBgtViewsRule::normalizeRule([
            'bands' => [
                ['views_from' => 100, 'views_to' => 9999, 'bgt' => 8, 'label' => 'High', 'color' => '#111111'],
                ['views_from' => 0, 'views_to' => 99, 'bgt' => 0, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertSame(8, AmazonAdsBgtViewsRule::apply(100.0, $rule)['bgt']);
        $this->assertSame(0, AmazonAdsBgtViewsRule::apply(0.0, $rule)['bgt']);
        $this->assertSame(0, AmazonAdsBgtViewsRule::apply(50.0, $rule)['bgt']);
        $this->assertSame('Low', AmazonAdsBgtViewsRule::apply(0.0, $rule)['label']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtViewsRule::normalizeRule([
            'bands' => [
                ['views_from' => 80, 'views_to' => 20, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }

    public function test_normalize_rejects_negative_bgt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtViewsRule::normalizeRule([
            'bands' => [
                ['views_from' => 0, 'views_to' => 10, 'bgt' => -1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
