<?php

namespace Tests\Unit;

use App\Support\EbayCampaignAdLiveBid;
use PHPUnit\Framework\TestCase;

class EbayCampaignAdLiveBidTest extends TestCase
{
    public function test_reads_the_listing_bid_and_ignores_a_campaign_default(): void
    {
        $this->assertSame(12.0, EbayCampaignAdLiveBid::fromPayload(['bidPercentage' => '12.0']));
        $this->assertSame(9.14, EbayCampaignAdLiveBid::fromPayload(['bidPercentage' => 9.14]));
        $this->assertNull(EbayCampaignAdLiveBid::fromPayload([]));
        $this->assertNull(EbayCampaignAdLiveBid::fromPayload(['bidPercentage' => '']));
        $this->assertNull(EbayCampaignAdLiveBid::fromPayload(['bidPercentage' => null]));
    }

    public function test_update_keeps_stored_c_bid_when_the_list_payload_omits_listing_bid(): void
    {
        $base = [
            'campaign_id' => 'c1',
            'listing_id' => '123',
            'updated_at' => 'now',
        ];

        $withoutListingBid = EbayCampaignAdLiveBid::applyToRow($base, ['listingId' => '123']);
        $this->assertArrayNotHasKey('bid_percentage', $withoutListingBid);

        $withListingBid = EbayCampaignAdLiveBid::applyToRow($base, [
            'listingId' => '123',
            'bidPercentage' => '9.0',
        ]);
        $this->assertSame(9.0, $withListingBid['bid_percentage']);
    }

    public function test_log_label_does_not_print_the_campaign_default(): void
    {
        $this->assertSame('7%', EbayCampaignAdLiveBid::logLabel(['bidPercentage' => '7']));
        $this->assertSame('listing bid missing (keep stored)', EbayCampaignAdLiveBid::logLabel([]));
    }

    public function test_live_c_bid_matches_s_bid_on_the_same_tenth(): void
    {
        $this->assertTrue(EbayCampaignAdLiveBid::matches(12.0, 12.04));
        $this->assertTrue(EbayCampaignAdLiveBid::matches(9.0, 9));
        $this->assertFalse(EbayCampaignAdLiveBid::matches(7.0, 9.0));
        $this->assertFalse(EbayCampaignAdLiveBid::matches(null, 9.0));
        $this->assertFalse(EbayCampaignAdLiveBid::matches(12.0, 0));
    }
}
