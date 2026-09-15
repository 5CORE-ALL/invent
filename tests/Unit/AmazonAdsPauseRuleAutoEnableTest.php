<?php

namespace Tests\Unit;

use App\Support\AmazonAdsPauseRule;
use PHPUnit\Framework\TestCase;

class AmazonAdsPauseRuleAutoEnableTest extends TestCase
{
    public function test_normalize_campaign_name_ignores_case_and_trailing_dot(): void
    {
        $this->assertSame(
            'PARENT MS 080 1PK PT',
            AmazonAdsPauseRule::normalizeCampaignName('PARENT MS 080 1Pk PT.')
        );
        $this->assertSame(
            'PARENT MX 4CH 2MIC PT',
            AmazonAdsPauseRule::normalizeCampaignName('PARENT MX 4CH 2MIC pt')
        );
    }

    public function test_parent_campaign_detection_ignores_kw_pt_suffix(): void
    {
        $this->assertTrue(AmazonAdsPauseRule::isParentCampaign('PARENT MS 080 1PK PT'));
        $this->assertTrue(AmazonAdsPauseRule::isParentCampaign('PARENT MS 080 1PK KW'));
        $this->assertFalse(AmazonAdsPauseRule::isParentCampaign('MS 080 1PK BLK PT'));
        $this->assertFalse(AmazonAdsPauseRule::isParentCampaign(''));
        $this->assertFalse(AmazonAdsPauseRule::isParentCampaign(null));
    }

    public function test_pr_pause_matches_parent_dil_only_never_price(): void
    {
        $rule = AmazonAdsPauseRule::normalizeRule([
            'pr' => [
                'enabled' => true,
                'dil_enabled' => true,
                'dil_above' => 100,
                'price_enabled' => true,
                'price_below' => 20,
            ],
        ]);

        $this->assertFalse($rule['pr']['price_enabled']);
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 100, 'price' => 50], 'PARENT MS 080 1PK PT')['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_ENABLED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 3.5, 'price' => 5], 'PARENT MS 080 1PK PT')['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 150, 'price' => 5], 'MS 080 1PK BLK PT')['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 10, 'parent_dil' => 120], 'SS HD 1 PK 4.5 FT WH WOB KW')['status']
        );
        $this->assertStringContainsString(
            'PARENT family',
            AmazonAdsPauseRule::decide($rule, ['dil' => 10, 'parent_dil' => 120], 'SS HD 1 PK 4.5 FT WH WOB KW')['reason']
        );
        $this->assertFalse(AmazonAdsPauseRule::shouldAutoEnable(
            ['status' => AmazonAdsPauseRule::ACTION_ENABLED, 'reason' => '', 'hits' => []],
            'PAUSED',
            '2026-08-27 13:09:13',
            new \DateTimeImmutable('2026-09-12 18:00:00'),
            'MS 080 1PK BLK PT'
        ));
    }
}
