<?php

namespace Tests\Unit;

use App\Support\Marketplace\EbaySpricePush;
use PHPUnit\Framework\TestCase;

class EbaySpricePushUnpushableTest extends TestCase
{
    public function test_listings_without_a_live_item_cannot_take_a_push(): void
    {
        $this->assertSame('No eBay item_id', EbaySpricePush::unpushableReason(null));
        $this->assertSame('No eBay item_id', EbaySpricePush::unpushableReason((object) ['item_id' => '', 'listing_status' => 'ACTIVE']));
        $this->assertSame('Ended listing', EbaySpricePush::unpushableReason((object) ['item_id' => '1', 'listing_status' => 'ENDED']));
        $this->assertStringContainsString('Not listed on eBay', (string) EbaySpricePush::unpushableReason((object) ['item_id' => '1', 'listing_status' => 'MISSING']));
        $this->assertStringContainsString('Not listed on eBay', (string) EbaySpricePush::unpushableReason((object) ['item_id' => '1', 'listing_status' => 'not_listed']));
    }

    public function test_active_and_unknown_status_listings_can_be_pushed(): void
    {
        $this->assertNull(EbaySpricePush::unpushableReason((object) ['item_id' => '1', 'listing_status' => 'ACTIVE']));
        $this->assertNull(EbaySpricePush::unpushableReason((object) ['item_id' => '1', 'listing_status' => null]));
    }
}
