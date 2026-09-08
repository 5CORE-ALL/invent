<?php

namespace Tests\Unit;

use App\Support\AmazonAdsEnabledCampaignSync;
use PHPUnit\Framework\TestCase;

class AmazonAdsEnabledCampaignSyncTest extends TestCase
{
    public function test_budget_amount_reads_nested_budget(): void
    {
        $this->assertSame(12.5, AmazonAdsEnabledCampaignSync::budgetAmount([
            'budget' => ['budget' => 12.5, 'currencyCode' => 'USD'],
        ]));
        $this->assertSame(8.0, AmazonAdsEnabledCampaignSync::budgetAmount(['budget' => 8]));
        $this->assertNull(AmazonAdsEnabledCampaignSync::budgetAmount([]));
    }

    public function test_l30_window_is_30_days_ending_on_anchor(): void
    {
        $this->assertSame(
            ['start' => '2026-08-08', 'end' => '2026-09-06'],
            AmazonAdsEnabledCampaignSync::l30WindowEnding('2026-09-06')
        );
    }

    public function test_zero_metric_payload_marks_enabled(): void
    {
        $row = AmazonAdsEnabledCampaignSync::zeroMetricPayload(
            ['name' => 'PARENT MEGA WP KW', 'budget' => ['budget' => 5, 'currencyCode' => 'USD']],
            'profile-1',
            '57622558126952',
            'SPONSORED_PRODUCTS',
            ['start' => '2026-08-08', 'end' => '2026-09-06']
        );

        $this->assertSame('ENABLED', $row['campaignStatus']);
        $this->assertSame('PARENT MEGA WP KW', $row['campaignName']);
        $this->assertSame(0, $row['cost']);
        $this->assertSame(5.0, $row['campaignBudgetAmount']);
        $this->assertSame('USD', $row['campaignBudgetCurrencyCode']);
    }

    public function test_filter_to_columns_drops_spend_when_table_has_only_cost(): void
    {
        $filtered = AmazonAdsEnabledCampaignSync::filterToColumns(
            ['cost' => 0, 'spend' => 0, 'clicks' => 0],
            ['cost', 'clicks']
        );

        $this->assertSame(['cost' => 0, 'clicks' => 0], $filtered);
    }
}
