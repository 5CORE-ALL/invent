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
}
