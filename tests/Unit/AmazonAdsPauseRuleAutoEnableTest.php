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

    public function test_pr_pause_still_matches_dil_or_price(): void
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

        $this->assertSame(
            AmazonAdsPauseRule::ACTION_PAUSED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 100, 'price' => 50])['status']
        );
        $this->assertSame(
            AmazonAdsPauseRule::ACTION_ENABLED,
            AmazonAdsPauseRule::decide($rule, ['dil' => 3.5, 'price' => 59.99])['status']
        );
    }
}
