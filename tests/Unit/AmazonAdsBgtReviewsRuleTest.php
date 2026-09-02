<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtReviewsRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtReviewsRuleTest extends TestCase
{
    public function test_default_slabs_match_requested_ranges(): void
    {
        $bands = AmazonAdsBgtReviewsRule::defaultBands();
        $this->assertCount(4, $bands);
        $this->assertSame(2.99, $bands[0]['rev_from']);
        $this->assertSame(3.5, $bands[0]['rev_to']);
        $this->assertSame(3.51, $bands[1]['rev_from']);
        $this->assertSame(4.0, $bands[1]['rev_to']);
        $this->assertSame(4.01, $bands[2]['rev_from']);
        $this->assertSame(4.5, $bands[2]['rev_to']);
        $this->assertSame(4.51, $bands[3]['rev_from']);
        $this->assertSame(5.0, $bands[3]['rev_to']);
    }

    public function test_apply_maps_rating_to_slabs(): void
    {
        $rule = AmazonAdsBgtReviewsRule::defaults();
        $this->assertNull(AmazonAdsBgtReviewsRule::apply(2.98, $rule)['bgt']);
        $this->assertNull(AmazonAdsBgtReviewsRule::apply(null, $rule)['bgt']);
        $this->assertSame(1, AmazonAdsBgtReviewsRule::apply(2.99, $rule)['bgt']);
        $this->assertSame(1, AmazonAdsBgtReviewsRule::apply(3.5, $rule)['bgt']);
        $this->assertSame(2, AmazonAdsBgtReviewsRule::apply(3.51, $rule)['bgt']);
        $this->assertSame(2, AmazonAdsBgtReviewsRule::apply(4.0, $rule)['bgt']);
        $this->assertSame(3, AmazonAdsBgtReviewsRule::apply(4.01, $rule)['bgt']);
        $this->assertSame(3, AmazonAdsBgtReviewsRule::apply(4.5, $rule)['bgt']);
        $this->assertSame(4, AmazonAdsBgtReviewsRule::apply(4.51, $rule)['bgt']);
        $this->assertSame(4, AmazonAdsBgtReviewsRule::apply(5.0, $rule)['bgt']);
    }

    public function test_normalize_keeps_custom_slab_count_and_ranges(): void
    {
        $rule = AmazonAdsBgtReviewsRule::normalizeRule([
            'bands' => [
                ['rev_from' => 1, 'rev_to' => 3, 'bgt' => 8, 'label' => 'Low', 'color' => '#111111'],
                ['rev_from' => 3.01, 'rev_to' => 5, 'bgt' => 12, 'label' => 'High', 'color' => '#222222'],
            ],
        ]);
        $this->assertCount(2, $rule['bands']);
        $this->assertSame(1.0, $rule['bands'][0]['rev_from']);
        $this->assertSame(3.0, $rule['bands'][0]['rev_to']);
        $this->assertSame(8, $rule['bands'][0]['bgt']);
        $this->assertSame('Low', $rule['bands'][0]['label']);
        $this->assertSame(12, $rule['bands'][1]['bgt']);
        $this->assertSame(5.0, $rule['bands'][1]['rev_to']);
    }

    public function test_apply_uses_custom_slabs(): void
    {
        $rule = AmazonAdsBgtReviewsRule::normalizeRule([
            'bands' => [
                ['rev_from' => 1, 'rev_to' => 3, 'bgt' => 8, 'label' => 'Low', 'color' => '#111111'],
                ['rev_from' => 3.01, 'rev_to' => 5, 'bgt' => 12, 'label' => 'High', 'color' => '#222222'],
            ],
        ]);
        $this->assertSame(8, AmazonAdsBgtReviewsRule::apply(2.5, $rule)['bgt']);
        $this->assertSame(12, AmazonAdsBgtReviewsRule::apply(4.2, $rule)['bgt']);
        $this->assertNull(AmazonAdsBgtReviewsRule::apply(0.5, $rule)['bgt']);
    }

    public function test_normalize_rejects_from_greater_than_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AmazonAdsBgtReviewsRule::normalizeRule([
            'bands' => [
                ['rev_from' => 4, 'rev_to' => 2, 'bgt' => 1, 'label' => 'Bad', 'color' => '#111111'],
            ],
        ]);
    }
}
