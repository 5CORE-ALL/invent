<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtCvrRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtCvrRuleTest extends TestCase
{
    public function test_defaults_are_purple_to_red(): void
    {
        $bands = AmazonAdsBgtCvrRule::defaultBands();
        $this->assertCount(6, $bands);
        $this->assertSame('Purple', $bands[0]['label']);
        $this->assertSame('Red', $bands[5]['label']);
        $this->assertSame(0, $bands[5]['cvr_from']);
        $this->assertSame(6, $bands[0]['bgt']);
        $this->assertSame(1, $bands[5]['bgt']);
    }

    public function test_normalize_keeps_custom_slab_count_and_allows_zero_bgt(): void
    {
        $rule = AmazonAdsBgtCvrRule::normalizeRule([
            'bands' => [
                ['cvr_from' => 5, 'cvr_to' => 9999, 'bgt' => 0, 'label' => 'High', 'color' => '#111111'],
                ['cvr_from' => 0, 'cvr_to' => 5, 'bgt' => 3, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertCount(2, $rule['bands']);
        $this->assertSame(0, $rule['bands'][0]['bgt']);
        $this->assertSame(0.0, $rule['bands'][1]['cvr_from']);
        $this->assertSame(3, $rule['bands'][1]['bgt']);
    }

    public function test_flips_legacy_red_first_six_slabs(): void
    {
        $rule = AmazonAdsBgtCvrRule::normalizeRule([
            'bands' => [
                ['cvr_from' => 0.1, 'cvr_to' => 2, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
                ['cvr_from' => 2, 'cvr_to' => 3.9, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
                ['cvr_from' => 3.9, 'cvr_to' => 5.8, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
                ['cvr_from' => 5.8, 'cvr_to' => 7.7, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
                ['cvr_from' => 7.7, 'cvr_to' => 9.6, 'bgt' => 2, 'label' => 'Pink', 'color' => '#e83e8c'],
                ['cvr_from' => 9.6, 'cvr_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ],
        ]);
        $this->assertSame('Purple', $rule['bands'][0]['label']);
        $this->assertSame(6, $rule['bands'][0]['bgt']);
        $this->assertSame(9.6, $rule['bands'][0]['cvr_from']);
        $this->assertSame('Red', $rule['bands'][5]['label']);
        $this->assertSame(0.1, $rule['bands'][5]['cvr_from']);
        $this->assertSame(1, $rule['bands'][5]['bgt']);
    }

    public function test_apply_uses_first_matching_slab_including_zero_cvr_and_zero_bgt(): void
    {
        $rule = AmazonAdsBgtCvrRule::normalizeRule([
            'bands' => [
                ['cvr_from' => 5, 'cvr_to' => 9999, 'bgt' => 8, 'label' => 'High', 'color' => '#111111'],
                ['cvr_from' => 0, 'cvr_to' => 5, 'bgt' => 0, 'label' => 'Low', 'color' => '#222222'],
            ],
        ]);
        $this->assertSame(8, AmazonAdsBgtCvrRule::apply(5.0, $rule)['bgt']);
        $this->assertSame(0, AmazonAdsBgtCvrRule::apply(0.0, $rule)['bgt']);
        $this->assertSame(0, AmazonAdsBgtCvrRule::apply(2.5, $rule)['bgt']);
        $this->assertSame('Low', AmazonAdsBgtCvrRule::apply(0.0, $rule)['label']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtCvrRule::normalizeRule([
            'bands' => [
                ['cvr_from' => 8, 'cvr_to' => 2, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }

    public function test_normalize_rejects_negative_bgt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtCvrRule::normalizeRule([
            'bands' => [
                ['cvr_from' => 0, 'cvr_to' => 10, 'bgt' => -1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
