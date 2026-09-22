<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingLiveSyncStatus;
use PHPUnit\Framework\TestCase;

class GoogleShoppingLiveSyncStatusTest extends TestCase
{
    public function test_page_shows_only_the_stored_verified_dot(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([
            'live_bid' => 0.35,
            'bid_green' => 1,
            'live_bgt' => 12,
            'bgt_green' => 0,
        ], 0.35, 12);

        $this->assertTrue($cols['lbid_green']);
        $this->assertSame(0.35, $cols['lbid']);
        $this->assertFalse($cols['lbgt_green']);
        $this->assertSame(12.0, $cols['lbgt']);
    }

    public function test_a_changed_sbid_does_not_flip_the_stored_dot(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([
            'live_bid' => 0.35,
            'bid_green' => 1,
        ], 0.50, null);

        $this->assertTrue($cols['lbid_green']);
        $this->assertSame(0.35, $cols['lbid']);
    }

    public function test_failed_fetch_flags_do_not_clear_a_stored_dot(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([
            'live_bid' => 0.40,
            'bid_green' => 1,
            'bid_fetch_ok' => 0,
            'bid_fetch_error' => 'bid query failed',
            'live_bgt' => 8,
            'bgt_green' => 1,
            'bgt_fetch_ok' => 0,
            'bgt_fetch_error' => 'budget query failed',
        ], 0.40, 8);

        $this->assertTrue($cols['lbid_green']);
        $this->assertTrue($cols['lbgt_green']);
        $this->assertSame(8.0, $cols['lbgt']);
    }

    public function test_campaign_budget_is_not_shown_as_a_live_budget(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([], 0.2, 15);

        $this->assertNull($cols['lbid']);
        $this->assertFalse($cols['lbid_green']);
        $this->assertNull($cols['lbgt']);
        $this->assertFalse($cols['lbgt_green']);
        $this->assertStringContainsString('has not been verified', $cols['lbgt_tip']);
    }

    public function test_verified_store_writes_only_when_google_returns_a_value(): void
    {
        $match = GoogleShoppingLiveSyncStatus::verifiedStore('bid', 0.35, null, 0.35);
        $this->assertSame(0.35, $match['value']);
        $this->assertTrue($match['green']);

        $mismatch = GoogleShoppingLiveSyncStatus::verifiedStore('bgt', 9.0, null, 12);
        $this->assertSame(9.0, $mismatch['value']);
        $this->assertFalse($mismatch['green']);

        $this->assertNull(GoogleShoppingLiveSyncStatus::verifiedStore('bid', null, 'missing', 0.35));
        $this->assertNull(GoogleShoppingLiveSyncStatus::verifiedStore('bgt', null, 'budget query failed', 8));
        $this->assertNull(GoogleShoppingLiveSyncStatus::verifiedStore('bid', 0.35, 'mixed', 0.35));
    }

    public function test_uniform_bid_requires_product_groups_to_agree(): void
    {
        $this->assertSame(0.35, GoogleShoppingLiveSyncStatus::uniformPositive([0.35, 0.35], GoogleShoppingLiveSyncStatus::BID_TOLERANCE));
        $this->assertNull(GoogleShoppingLiveSyncStatus::uniformPositive([0.35, 0.80], GoogleShoppingLiveSyncStatus::BID_TOLERANCE));
        $this->assertSame(
            0.35,
            GoogleShoppingLiveSyncStatus::dollarsFromMicros('350000')
        );
    }

    public function test_listing_group_rows_are_grouped_by_campaign(): void
    {
        $grouped = GoogleShoppingLiveSyncStatus::positiveDollarsByCampaign([
            ['campaign' => ['id' => '11'], 'adGroupCriterion' => ['cpcBidMicros' => '350000']],
            ['campaign' => ['id' => '11'], 'adGroupCriterion' => ['cpcBidMicros' => '350000']],
            ['campaign' => ['id' => '22'], 'campaignBudget' => ['amountMicros' => '12000000']],
        ], 'cpc');

        $this->assertSame([0.35, 0.35], $grouped['11']);
        $this->assertArrayNotHasKey('22', $grouped);

        $budgets = GoogleShoppingLiveSyncStatus::positiveDollarsByCampaign([
            ['campaign' => ['id' => 22], 'campaignBudget' => ['amountMicros' => 12000000]],
        ], 'budget');
        $this->assertSame([12.0], $budgets['22']);
    }
}
