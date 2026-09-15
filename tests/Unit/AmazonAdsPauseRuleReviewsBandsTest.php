<?php

namespace Tests\Unit;

use App\Support\AmazonAdsCampaignSkuSync;
use App\Support\AmazonAdsPauseRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsPauseRuleReviewsBandsTest extends TestCase
{
    public function test_reviews_and_price_cannot_be_turned_on(): void
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
            'reviews' => ['enabled' => true, 'below' => 3],
        ]);

        $this->assertFalse($rule['reviews']['enabled']);
        $this->assertFalse($rule['pr']['reviews_enabled']);
        $this->assertFalse($rule['pr']['price_enabled']);
        $this->assertFalse(AmazonAdsPauseRule::reviewsEnabled($rule));
        $this->assertFalse(AmazonAdsPauseRule::ratingBelowReviewsThreshold($rule, 1.0));
        $this->assertTrue(AmazonAdsPauseRule::hasCampaignBands($rule));
    }

    public function test_legacy_review_bands_stay_disabled(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'reviews' => [
                ['from' => 1, 'to' => 3, 'action' => 'PAUSED', 'label' => 'Low'],
            ],
        ]);

        $this->assertFalse($rule['reviews']['enabled']);
        $this->assertFalse(AmazonAdsPauseRule::reviewsEnabled($rule));
    }

    public function test_campaign_decide_ignores_reviews_and_price(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'pr' => [
                'enabled' => true,
                'dil_enabled' => true,
                'dil_above' => 100,
                'price_enabled' => true,
                'price_below' => 99,
                'reviews_enabled' => true,
                'reviews_below' => 5,
            ],
            'reviews' => ['enabled' => true, 'below' => 5],
        ]);

        $parent = 'PARENT MX 4CH 2MIC PT';
        $child = 'MX 4CH 2MIC BLK PT';
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_ENABLED,
            AmazonAdsPauseRule::decide($rule, ['rating' => 1.0, 'price' => 1, 'dil' => 10], $parent)['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['rating' => 5.0, 'price' => 99, 'dil' => 100], $parent)['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 120], $child)['status']
        );
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

    public function test_should_auto_enable_only_parent_leftover_rule_pauses(): void
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
        $parent = 'PARENT MS 080 1PK PT';
        $child = 'MS 080 1PK BLK PT';

        $dilReason = 'Pause — PR Dil% 100% ≥ 100%';
        $priceReason = 'Pause — PR Price $15 < $20';

        $this->assertTrue(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now, $parent, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now, $parent, $priceReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now, $parent, ''));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now, $child, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $recent, $now, null, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', null, $now, $parent, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'PAUSED', $oldPinkDil, $now, $parent, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($clear, 'ENABLED', $recent, $now, $parent, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable($pause, 'PAUSED', $recent, $now, $parent, $dilReason));
        $this->assertTrue(AmazonAdsPauseRule::shouldAutoEnable(['status' => ''], 'PAUSED', $recent, $now, $parent, $dilReason));
        $this->assertFalse(AmazonAdsPauseRule::isRecentPauseRuleStamp($oldPinkDil, $now));
        $this->assertTrue(AmazonAdsPauseRule::isRecentPauseRuleStamp($recent, $now));
        $this->assertTrue(AmazonAdsPauseRule::isDilPauseReason($dilReason));
        $this->assertFalse(AmazonAdsPauseRule::isDilPauseReason($priceReason));
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
