<?php

namespace Tests\Unit;

use App\Support\AmazonAdsBgtReviewsRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsBgtReviewsRuleTest extends TestCase
{
    public function test_locked_slabs_match_requested_ranges(): void
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

    public function test_normalize_keeps_ranges_and_allows_custom_bgt(): void
    {
        $rule = AmazonAdsBgtReviewsRule::normalizeRule([
            'bands' => [
                ['bgt' => 8, 'label' => 'Low', 'color' => '#111111'],
            ],
        ]);
        $this->assertSame(2.99, $rule['bands'][0]['rev_from']);
        $this->assertSame(8, $rule['bands'][0]['bgt']);
        $this->assertSame('Low', $rule['bands'][0]['label']);
        $this->assertSame(2, $rule['bands'][1]['bgt']);
        $this->assertCount(4, $rule['bands']);
    }
}
