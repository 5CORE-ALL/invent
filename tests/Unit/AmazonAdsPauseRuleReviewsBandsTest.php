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

    public function test_reviews_below_default_is_dynamic_2_99(): void
    {
        $defaults = AmazonAdsPauseRule::defaultReviews();
        $this->assertSame(2.99, $defaults['below']);

        $rule = AmazonAdsPauseRule::normalizeRule([
            'reviews' => ['enabled' => true, 'below' => 2.99],
        ]);
        $this->assertTrue(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 2.98));
        $this->assertFalse(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 2.99));
        $this->assertFalse(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 3.0));
    }

    public function test_pr_normalize_keeps_reviews_threshold(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'pr' => [
                'enabled' => true,
                'dil_enabled' => true,
                'dil_above' => 100,
                'price_enabled' => true,
                'price_below' => 20,
                'reviews_enabled' => true,
                'reviews_below' => 2.99,
            ],
            'reviews' => ['enabled' => true, 'below' => 2.99],
        ]);

        $this->assertTrue($rule['pr']['reviews_enabled']);
        $this->assertSame(2.99, $rule['pr']['reviews_below']);
        $this->assertTrue($rule['reviews']['enabled']);
        $this->assertSame(2.99, $rule['reviews']['below']);
        $this->assertSame(AmazonAdsPauseRule::ACTION_ENABLED, AmazonAdsPauseRule::decide($rule, ['rating' => 1.0])['status']);
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

    public function test_should_auto_enable_only_leftover_rule_pauses(): void
    {
        $clear = [
            'status' => AmazonAdsPauseRule::ACTION_ENABLED,
            'reason' => 'Active — no pause rule matched',
            'hits' => [],
        ];
        $pause = [
            'status' => AmazonAdsPauseRule::ACTION_PAUSED,
            'reason' => 'Pause — PR Dil% 100% ≥ 100%',
            'hits' => ['PR Dil% 100% ≥ 100%'],
        ];

        $now = new \DateTimeImmutable('2026-09-12 18:00:00');
        $recent = '2026-08-27 13:09:13';
        $oldPinkDil = '2026-02-25 12:40:51';

        $this->assertTrue(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', null, $now));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $oldPinkDil, $now));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'ENABLED', $recent, $now));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($pause, 'PAUSED', $recent, $now));
        $this->assertTrue(AmazonAdsPauseRule::shouldAutoEnable(['status' => ''], 'PAUSED', $recent, $now));
        $this->assertFalse(AmazonAdsPauseRule::isRecentPauseRuleStamp($oldPinkDil, $now));
        $this->assertTrue(AmazonAdsPauseRule::isRecentPauseRuleStamp($recent, $now));
    }

    public function test_active_again_display_uses_pause_reason_on_hover(): void
    {
        $empty = AmazonAdsPauseRule::activeAgainDisplay(null);
        $this->assertSame('', $empty['label']);
        $this->assertSame('', $empty['reason']);
        $this->assertSame('', $empty['tip']);

        $shown = AmazonAdsPauseRule::activeAgainDisplay([
            'paused_reason' => 'Pause — PR Dil% 100% ≥ 100%',
            'reactivated_at' => '12 Sep 2026 18:25',
        ]);
        $this->assertSame('Active Again', $shown['label']);
        $this->assertSame('Pause — PR Dil% 100% ≥ 100%', $shown['reason']);
        $this->assertStringContainsString('PR Dil% 100% ≥ 100%', $shown['tip']);
        $this->assertStringContainsString('turned back on 12 Sep 2026 18:25', $shown['tip']);
    }
}
