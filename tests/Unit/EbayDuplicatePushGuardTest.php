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

    public function test_first_push_of_a_price_goes_ahead(): void
    {
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            null, null, $this->at('2026-10-12 01:30'), 66.39, 68.18, $this->at('2026-10-12 04:34')
        ));
        // Previous push was a different price.
        $this->assertNull(ChannelLivePriceSync::decideDuplicateEbayPush(
            68.18, $this->at('2026-10-12 04:34'), $this->at('2026-10-12 01:30'), 66.39, 68.07, $this->at('2026-10-12 08:10')
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
