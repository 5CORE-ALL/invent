<?php

namespace Tests\Unit;

use App\Support\AmazonAdsCampaignSkuSync;
use App\Support\AmazonAdsPauseRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsPauseRuleReviewsBandsTest extends TestCase
{
    public function test_reviews_is_a_below_threshold_not_bands(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'reviews' => ['enabled' => true, 'below' => 3],
        ]);

        $this->assertTrue($rule['reviews']['enabled']);
        $this->assertSame(3.0, $rule['reviews']['below']);
        $this->assertTrue(AmazonAdsPauseRule::reviewsEnabled($rule));
        $this->assertFalse(AmazonAdsPauseRule::hasCampaignBands($rule));
    }

    public function test_legacy_bands_become_below_threshold(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'reviews' => [
                ['from' => 1, 'to' => 3, 'action' => 'PAUSED', 'label' => 'Low'],
            ],
        ]);

        $this->assertTrue($rule['reviews']['enabled']);
        $this->assertSame(3.0, $rule['reviews']['below']);
    }

    public function test_campaign_decide_ignores_reviews_rating(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'reviews' => ['enabled' => true, 'below' => 3],
        ]);

        $decision = AmazonAdsPauseRule::decide($rule, ['rating' => 1.5]);
        $this->assertSame(AmazonAdsPauseRule::ACTION_ENABLED, $decision['status']);
        $this->assertTrue(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 2.5));
        $this->assertFalse(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 4.6));
    }

    public function test_amazon_ad_ref_from_stored_ids(): void
    {
        $this->assertSame(
            ['channel' => 'sp', 'ad_id' => '99'],
            AmazonAdsCampaignSkuSync::amazonAdRef('99')
        );
        $this->assertSame(
            ['channel' => 'sb', 'ad_id' => '88'],
            AmazonAdsCampaignSkuSync::amazonAdRef('sb:88:MUS FLD HD ACC BLU')
        );
        $this->assertSame(
            ['channel' => null, 'ad_id' => ''],
            AmazonAdsCampaignSkuSync::amazonAdRef('name:123:SKU')
        );
    }
}
