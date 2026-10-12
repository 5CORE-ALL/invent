<?php

namespace Tests\Unit;

use App\Support\Marketplace\EbaySpricePush;
use PHPUnit\Framework\TestCase;

class EbaySpricePushTest extends TestCase
{
    private function metric(array $attrs = []): object
    {
        return (object) array_merge(['item_id' => '123456789', 'listing_status' => 'ACTIVE', 'ebay_price' => 20.00], $attrs);
    }

    public function test_supports_only_ebay_1_2_3(): void
    {
        $this->assertTrue(EbaySpricePush::supports('ebay1'));
        $this->assertTrue(EbaySpricePush::supports('ebay2'));
        $this->assertTrue(EbaySpricePush::supports('ebay3'));
        $this->assertFalse(EbaySpricePush::supports('ebay2op'));
        $this->assertFalse(EbaySpricePush::supports('amazon'));
    }

    public function test_allows_push_when_price_equals_saved_sprice(): void
    {
        $this->assertNull(EbaySpricePush::blockReason(24.99, 24.99, $this->metric()));
        $this->assertNull(EbaySpricePush::blockReason(24.99, 24.994, $this->metric()));
    }

    public function test_blocks_price_different_from_saved_sprice(): void
    {
        $reason = EbaySpricePush::blockReason(24.99, 26.24, $this->metric());
        $this->assertNotNull($reason);
        $this->assertStringContainsString('saved S PRC', $reason);
    }

    public function test_blocks_when_no_saved_sprice(): void
    {
        $this->assertSame('No saved S PRC', EbaySpricePush::blockReason(null, 24.99, $this->metric()));
        $this->assertSame('No saved S PRC', EbaySpricePush::blockReason(0.0, 24.99, $this->metric()));
    }

    public function test_blocks_invalid_price(): void
    {
        $this->assertSame('Invalid price', EbaySpricePush::blockReason(24.99, 0.0, $this->metric()));
    }

    public function test_blocks_missing_item_id(): void
    {
        $this->assertSame('No eBay item_id', EbaySpricePush::blockReason(24.99, 24.99, null));
        $this->assertSame('No eBay item_id', EbaySpricePush::blockReason(24.99, 24.99, $this->metric(['item_id' => ' '])));
    }

    public function test_blocks_ended_listing(): void
    {
        $this->assertSame('Ended listing', EbaySpricePush::blockReason(24.99, 24.99, $this->metric(['listing_status' => 'ENDED'])));
    }

    public function test_blocks_inv_zero_like_the_blue_badge(): void
    {
        $this->assertSame('INV 0', EbaySpricePush::blockReason(24.99, 24.99, $this->metric(), 0.0));
        $this->assertSame('INV 0', EbaySpricePush::blockReason(24.99, 24.99, $this->metric(), -2.0));
        $this->assertNull(EbaySpricePush::blockReason(24.99, 24.99, $this->metric(), 3.0));
    }

    public function test_detects_item_level_price_ignored_on_variation_listing(): void
    {
        $this->assertTrue(EbaySpricePush::looksMultiVariation([
            'success' => false,
            'message' => 'eBay ignored the price update because this is a multi-variation listing. Retry with the variation SKU.',
            'errors' => [['code' => '21916618', 'message' => 'Item level start price will be ignored on variation listings.']],
        ]));
        $this->assertFalse(EbaySpricePush::looksMultiVariation(['success' => false, 'message' => 'Auction ended']));
        $this->assertFalse(EbaySpricePush::looksMultiVariation(null));
    }

    public function test_live_matches(): void
    {
        $this->assertTrue(EbaySpricePush::liveMatches($this->metric(['ebay_price' => 24.99]), 24.99));
        $this->assertFalse(EbaySpricePush::liveMatches($this->metric(['ebay_price' => 22.00]), 24.99));
        $this->assertFalse(EbaySpricePush::liveMatches($this->metric(['ebay_price' => 0]), 24.99));
        $this->assertFalse(EbaySpricePush::liveMatches(null, 24.99));
    }
}
