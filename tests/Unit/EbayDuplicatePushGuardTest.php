<?php

namespace Tests\Unit;

use App\Services\ChannelLivePriceSync;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class EbayDuplicatePushGuardTest extends TestCase
{
    private function at(string $t): Carbon
    {
        return Carbon::parse($t, 'America/Los_Angeles');
    }

    public function test_never_pushed_and_stale_live_goes_ahead(): void
    {
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            null, null, $this->at('2026-10-12 01:30'), 66.39, 68.18, $this->at('2026-10-12 04:34')
        ));
    }

    public function test_real_price_move_after_an_earlier_push_goes_ahead(): void
    {
        // 68.18 pushed earlier, now a genuine move to 72.00 (> 1% deadband).
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            68.18, $this->at('2026-10-12 04:34'), $this->at('2026-10-12 01:30'), 66.39, 72.00, $this->at('2026-10-12 08:10')
        ));
    }

    public function test_same_price_again_with_stale_live_snapshot_is_skipped(): void
    {
        // CS 04 2W on 11 Oct: 18.39 pushed 05:30, live snapshot still older, hourly runs re-queued it.
        $reason = ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-11 05:30'), $this->at('2026-10-11 01:30'), 19.99, 18.39, $this->at('2026-10-11 06:59')
        );
        $this->assertNotNull($reason);
        $this->assertStringContainsString('not refreshed', $reason);
    }

    public function test_few_cents_of_noise_after_a_push_is_skipped(): void
    {
        // ebay2 CS 04 2W on 12 Oct: 18.41 pushed 06:57, then 18.39 at 08:16; ebay1 68.18 -> 68.07.
        $this->assertNotNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.41, $this->at('2026-10-12 06:57'), $this->at('2026-10-12 01:30'), 18.79, 18.39, $this->at('2026-10-12 08:16')
        ));
        $this->assertNotNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            68.18, $this->at('2026-10-12 04:34'), $this->at('2026-10-12 01:30'), 66.39, 68.07, $this->at('2026-10-12 08:10')
        ));
    }

    public function test_deadband_scales_with_price_and_has_a_floor(): void
    {
        $this->assertEqualsWithDelta(0.10, ChannelLivePriceSync::ebayPushDeadband(5.00), 0.0001);
        $this->assertEqualsWithDelta(0.18, ChannelLivePriceSync::ebayPushDeadband(18.39), 0.0001);
        $this->assertEqualsWithDelta(0.80, ChannelLivePriceSync::ebayPushDeadband(79.99), 0.0001);
    }

    public function test_just_over_the_deadband_is_pushed(): void
    {
        // 18.39 -> 18.60 is +0.21 (> 0.18 deadband).
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-12 04:00'), $this->at('2026-10-12 01:30'), 19.99, 18.60, $this->at('2026-10-12 08:00')
        ));
    }

    public function test_stale_snapshot_skip_expires_after_a_day(): void
    {
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-10 05:30'), $this->at('2026-10-10 01:30'), 19.99, 18.39, $this->at('2026-10-11 06:00')
        ));
    }

    public function test_listing_reverted_after_push_is_repushed_after_cooldown(): void
    {
        // Fetch AFTER the push still shows Std: real revert (12 Oct eBay1 case).
        $pushed = $this->at('2026-10-12 08:10');
        $seen = $this->at('2026-10-12 08:34');

        $this->assertStringContainsString('cooldown', (string) ChannelLivePriceSync::decideDuplicateEbayPush(
            68.07, $pushed, $seen, 79.99, 68.07, $this->at('2026-10-12 09:00')
        ));
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            68.07, $pushed, $seen, 79.99, 68.07, $this->at('2026-10-12 11:20')
        ));
    }

    public function test_revert_with_a_slightly_different_new_price_is_still_pushed(): void
    {
        // Listing went back to Std 79.99 after our push of 68.07; S PRC is now 68.18: far from live, so push.
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            68.07, $this->at('2026-10-12 08:10'), $this->at('2026-10-12 08:34'), 79.99, 68.18, $this->at('2026-10-12 09:00')
        ));
    }

    public function test_fresh_live_within_deadband_is_not_pushed(): void
    {
        $this->assertNotNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.41, $this->at('2026-10-12 06:57'), $this->at('2026-10-12 07:30'), 18.41, 18.39, $this->at('2026-10-12 08:16')
        ));
    }

    public function test_already_live_is_left_to_the_callers(): void
    {
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-11 05:30'), $this->at('2026-10-11 09:00'), 18.39, 18.39, $this->at('2026-10-11 10:00')
        ));
    }

    public function test_future_push_stamp_is_not_trusted(): void
    {
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-11 12:00'), $this->at('2026-10-11 01:00'), 19.99, 18.39, $this->at('2026-10-11 06:00')
        ));
    }

    public function test_cent_rounding_counts_as_the_same_price(): void
    {
        $this->assertNotNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            18.39, $this->at('2026-10-11 05:30'), $this->at('2026-10-11 01:30'), 19.99, 18.394, $this->at('2026-10-11 06:00')
        ));
    }
}
