<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingBgtCvrRule;
use App\Support\GoogleShoppingBgtPrcRule;
use App\Support\GoogleShoppingBgtReviewsRule;
use App\Support\GoogleShoppingBgtViewsRule;
use App\Support\GoogleShoppingSbgt;
use PHPUnit\Framework\TestCase;

class GoogleShoppingSbgtTest extends TestCase
{
    public function test_sum_adds_present_parts(): void
    {
        $this->assertSame(15, GoogleShoppingSbgt::sumFromParts(2, 3, 4, 1, 5));
        $this->assertSame(8, GoogleShoppingSbgt::sumFromParts(2, 3, 3, null, null));
    }

    public function test_explicit_bgt_acos_zero_zeros_the_total(): void
    {
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(5, 4, 0, 3, 2));
        $this->assertSame(0, GoogleShoppingSbgt::sumFromParts(null, null, 0, null, null));
    }

    public function test_all_missing_is_null(): void
    {
        $this->assertNull(GoogleShoppingSbgt::sumFromParts(null, null, null, null, null));
    }

    public function test_default_views_band_matches_amazon_logic(): void
    {
        $this->assertSame(6, GoogleShoppingBgtViewsRule::apply(400.0, GoogleShoppingBgtViewsRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtViewsRule::apply(10.0, GoogleShoppingBgtViewsRule::defaults())['bgt']);
    }

    public function test_default_cvr_band_matches_amazon_logic(): void
    {
        $this->assertSame(6, GoogleShoppingBgtCvrRule::apply(22.0, GoogleShoppingBgtCvrRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtCvrRule::apply(1.0, GoogleShoppingBgtCvrRule::defaults())['bgt']);
    }

    public function test_default_prc_band_matches_amazon_logic(): void
    {
        $this->assertSame(5, GoogleShoppingBgtPrcRule::apply(200.0, GoogleShoppingBgtPrcRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtPrcRule::apply(30.0, GoogleShoppingBgtPrcRule::defaults())['bgt']);
        $this->assertNull(GoogleShoppingBgtPrcRule::apply(null, GoogleShoppingBgtPrcRule::defaults())['bgt']);
    }

    public function test_default_reviews_band_matches_amazon_logic(): void
    {
        $this->assertSame(4, GoogleShoppingBgtReviewsRule::apply(4.8, GoogleShoppingBgtReviewsRule::defaults())['bgt']);
        $this->assertSame(1, GoogleShoppingBgtReviewsRule::apply(3.2, GoogleShoppingBgtReviewsRule::defaults())['bgt']);
        $this->assertNull(GoogleShoppingBgtReviewsRule::apply(null, GoogleShoppingBgtReviewsRule::defaults())['bgt']);
    }
}
