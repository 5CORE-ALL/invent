<?php

namespace Tests\Unit;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use PHPUnit\Framework\TestCase;

/**
 * Campaign 398493428105822 (PT campaign): 260 targets, 148 archived. Amazon rejects every archived
 * target, and an archived twin of an enabled target sent in the same request got the enabled one
 * rejected as a duplicate, so its bid never changed.
 */
class AmazonSpTargetsToPushTest extends TestCase
{
    public function test_archived_targets_are_not_sent(): void
    {
        $targets = [
            ['targetId' => '1', 'state' => 'ENABLED'],
            ['targetId' => '2', 'state' => 'ARCHIVED'],
            ['targetId' => '3', 'state' => 'PAUSED'],
            ['targetId' => '4', 'state' => 'archived'],
        ];

        $this->assertSame(
            [['bid' => 0.59, 'targetId' => '1'], ['bid' => 0.59, 'targetId' => '3']],
            AmazonSpBudgetController::targetsToPush($targets, 0.59)
        );
    }

    public function test_enabled_target_with_an_archived_twin_is_still_sent_once(): void
    {
        $expr = [['type' => 'ASIN_SAME_AS', 'value' => 'B0DG2MXYVT']];
        $targets = [
            ['targetId' => '467052543041098', 'adGroupId' => 'ag', 'expression' => $expr, 'state' => 'ARCHIVED', 'bid' => 0.64],
            ['targetId' => '553035422833697', 'adGroupId' => 'ag', 'expression' => $expr, 'state' => 'ENABLED', 'bid' => 0.79],
        ];

        $this->assertSame(
            [['bid' => 0.59, 'targetId' => '553035422833697']],
            AmazonSpBudgetController::targetsToPush($targets, 0.59)
        );
    }

    public function test_paused_and_missing_state_targets_are_sent_and_blank_ids_are_not(): void
    {
        $targets = [
            ['targetId' => ' 10 ', 'state' => 'PAUSED'],
            ['targetId' => '11'],
            ['targetId' => '', 'state' => 'ENABLED'],
            ['state' => 'ENABLED'],
            'not-an-array',
        ];

        $this->assertSame(
            [['bid' => 1.25, 'targetId' => '10'], ['bid' => 1.25, 'targetId' => '11']],
            AmazonSpBudgetController::targetsToPush($targets, 1.25)
        );
    }

    public function test_all_archived_campaign_sends_nothing(): void
    {
        $this->assertSame([], AmazonSpBudgetController::targetsToPush([
            ['targetId' => '1', 'state' => 'ARCHIVED'],
            ['targetId' => '2', 'state' => 'ARCHIVED'],
        ], 0.5));
    }
}
