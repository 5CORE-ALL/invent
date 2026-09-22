<?php

namespace Tests\Unit;

use App\Support\GoogleShoppingLiveSyncStatus;
use PHPUnit\Framework\TestCase;

class GoogleShoppingLiveSyncStatusTest extends TestCase
{
    public function test_green_only_when_that_column_was_fetched_pushed_and_matches(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([
            'live_bid' => 0.35,
            'bid_fetch_ok' => 1,
            'bid_push_ok' => 1,
            'bid_pushed_value' => 0.35,
            'live_bgt' => 12,
            'bgt_fetch_ok' => 1,
            'bgt_push_ok' => 0,
            'bgt_pushed_value' => null,
        ], 0.35, 12);

        $this->assertTrue($cols['lbid_green']);
        $this->assertSame(0.35, $cols['lbid']);
        $this->assertFalse($cols['lbgt_green']);
        $this->assertSame(12.0, $cols['lbgt']);
    }

    public function test_budget_fetch_failure_does_not_turn_off_a_verified_bid(): void
    {
        $cols = GoogleShoppingLiveSyncStatus::columns([
            'live_bid' => 0.40,
            'bid_fetch_ok' => 1,
            'bid_push_ok' => 1,
            'bid_pushed_value' => 0.40,
            'live_bgt' => null,
            'bgt_fetch_ok' => 0,
            'bgt_fetch_error' => 'budget query failed',
            'bgt_push_ok' => 1,
            'bgt_pushed_value' => 8,
        ], 0.40, 8);

        $this->assertTrue($cols['lbid_green']);
        $this->assertFalse($cols['lbgt_green']);
        $this->assertNull($cols['lbgt']);
        $this->assertStringContainsString('could not be fetched', $cols['lbgt_tip']);
    }

    public function test_matching_live_value_without_a_push_is_not_green(): void
    {
        $row = GoogleShoppingLiveSyncStatus::fieldRow('bid', [
            'live_bid' => 0.55,
            'bid_fetch_ok' => true,
            'bid_push_ok' => false,
        ], 0.55);

        $this->assertFalse($row['green']);
        $this->assertSame(0.55, $row['value']);
        $this->assertStringContainsString('not pushed', $row['tip']);
    }

    public function test_green_clears_when_suggested_bid_changes_after_push(): void
    {
        $row = GoogleShoppingLiveSyncStatus::fieldRow('bid', [
            'live_bid' => 0.35,
            'bid_fetch_ok' => 1,
            'bid_push_ok' => 1,
            'bid_pushed_value' => 0.35,
        ], 0.50);

        $this->assertFalse($row['green']);
        $this->assertStringContainsString('does not match', $row['tip']);
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
};
