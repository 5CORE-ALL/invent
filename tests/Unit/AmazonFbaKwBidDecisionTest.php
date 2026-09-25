<?php

namespace Tests\Unit;

use App\Support\AmazonFbaKwBidDecision;
use PHPUnit\Framework\TestCase;

class AmazonFbaKwBidDecisionTest extends TestCase
{
    public function test_enabled_out_of_budget_pushes_cell_sbid_when_live_differs(): void
    {
        $this->assertTrue(AmazonFbaKwBidDecision::shouldPush('ENABLED', 'OUT_OF_BUDGET', 0.83, 0.79));
        $this->assertTrue(AmazonFbaKwBidDecision::allowsBidPush('ENABLED', 'CAMPAIGN_OUT_OF_BUDGET'));
        $this->assertTrue(AmazonFbaKwBidDecision::allowsBidPush('OUT_OF_BUDGET'));
    }

    public function test_enabled_with_budget_remaining_pushes_when_live_differs(): void
    {
        $this->assertTrue(AmazonFbaKwBidDecision::shouldPush('ENABLED', null, 0.83, 0.50));
    }

    public function test_enabled_matching_live_bid_does_not_push(): void
    {
        $this->assertFalse(AmazonFbaKwBidDecision::shouldPush('ENABLED', null, 0.83, 0.83));
        $this->assertFalse(AmazonFbaKwBidDecision::shouldPush('ENABLED', 'OUT_OF_BUDGET', 0.79, 0.79));
    }

    public function test_paused_mismatch_does_not_push(): void
    {
        $this->assertTrue(AmazonFbaKwBidDecision::isRealPause('PAUSED'));
        $this->assertFalse(AmazonFbaKwBidDecision::allowsBidPush('PAUSED', 'OUT_OF_BUDGET'));
        $this->assertFalse(AmazonFbaKwBidDecision::shouldPush('PAUSED', null, 0.83, 0.79));
    }

    public function test_historical_paused_row_stays_paused_and_current_enabled_still_pushes(): void
    {
        $historicalStatus = 'PAUSED';
        $currentStatus = 'ENABLED';

        $this->assertSame('PAUSED', $historicalStatus);
        $this->assertFalse(AmazonFbaKwBidDecision::shouldPush($historicalStatus, null, 0.83, 0.79));
        $this->assertTrue(AmazonFbaKwBidDecision::shouldPush($currentStatus, 'OUT_OF_BUDGET', 0.83, 0.79));
        $this->assertSame(0.79, AmazonFbaKwBidDecision::recordedLiveBid(
            (object) ['last_sbid' => 0.79, 'sbid' => 0.83],
            (object) ['last_sbid' => 0.79, 'sbid' => 0.78]
        ));
    }
}
