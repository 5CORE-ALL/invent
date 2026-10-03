<?php

namespace Tests\Unit;

use App\Support\AmazonAdsLiveSyncFollowUp;
use PHPUnit\Framework\TestCase;

class AmazonAdsLiveSyncFollowUpTest extends TestCase
{
    public function test_matching_bid_and_budget_do_not_need_another_pull(): void
    {
        $this->assertFalse(AmazonAdsLiveSyncFollowUp::rowsNeedSync([
            ['sbid' => 0.72, 'last_sbid' => 0.72, 'sbgt' => 4, 'bgt' => 4, 'bid_sync_color' => 'green', 'bgt_sync_color' => 'green'],
        ]));
    }

    public function test_new_sbid_after_a_verified_pull_needs_another_pull(): void
    {
        $this->assertTrue(AmazonAdsLiveSyncFollowUp::rowsNeedSync([
            ['sbid' => 0.44, 'last_sbid' => 0.72, 'bid_sync_color' => 'yellow', 'bgt_sync_color' => 'green', 'sbgt' => 4, 'bgt' => 4],
        ]));
    }

    public function test_new_sbgt_after_a_verified_pull_needs_another_pull(): void
    {
        $this->assertTrue(AmazonAdsLiveSyncFollowUp::rowsNeedSync([
            ['sbid' => 0.72, 'last_sbid' => 0.72, 'sbgt' => 5, 'bgt' => 4, 'bid_sync_color' => 'green', 'bgt_sync_color' => 'yellow'],
        ]));
    }

    public function test_recent_failure_is_left_for_the_nightly_retry(): void
    {
        $this->assertFalse(AmazonAdsLiveSyncFollowUp::rowsNeedSync([
            ['sbid' => 0.44, 'last_sbid' => 0.72, 'bid_sync_color' => 'red', 'sbgt' => 5, 'bgt' => 4, 'bgt_sync_color' => 'red'],
        ]));
    }

    public function test_grid_lbid_sbid_gap_is_queued_for_match_push(): void
    {
        $rows = AmazonAdsLiveSyncFollowUp::dirtySyncRows([
            [
                'campaign_id' => '531',
                'campaignName' => 'PARENT SS HD 2 Pk WOB KW',
                'sbid' => 0.73,
                'last_sbid' => 0.83,
                'bid_sync_color' => 'yellow',
                'sbgt' => 4,
                'bgt' => 4,
                'bgt_sync_color' => 'green',
            ],
        ], 'sp');

        $this->assertCount(1, $rows);
        $this->assertSame('531', $rows[0]['campaign_id']);
        $this->assertSame('sp', $rows[0]['channel']);
        $this->assertSame(0.73, $rows[0]['sbid']);
        $this->assertArrayNotHasKey('sbgt', $rows[0]);
    }

    public function test_blank_lbid_is_queued_for_a_pull_even_without_sbid(): void
    {
        $rows = AmazonAdsLiveSyncFollowUp::dirtySyncRows([
            [
                'campaign_id' => '88',
                'campaignName' => 'CAPO BLUE FBA KW',
                'sbid' => null,
                'last_sbid' => null,
                'bid_sync_color' => 'yellow',
            ],
        ], 'sp');

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['pull_bid']);
        $this->assertArrayNotHasKey('sbid', $rows[0]);
    }

    public function test_failed_bid_gap_is_not_queued_again(): void
    {
        $this->assertSame([], AmazonAdsLiveSyncFollowUp::dirtySyncRows([
            ['campaign_id' => '531', 'sbid' => 0.73, 'last_sbid' => 0.83, 'bid_sync_color' => 'red', 'sbgt' => 4, 'bgt' => 4, 'bgt_sync_color' => 'green'],
        ], 'sp'));
    }
}
