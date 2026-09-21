<?php

namespace Tests\Unit;

use App\Support\Marketplace\ListingManagerPublishStatus;
use PHPUnit\Framework\TestCase;

class ListingManagerPublishStatusAppPublishTest extends TestCase
{
    public function test_topdawg_shein_aliexpress_newegg_require_app_publish(): void
    {
        foreach (['TopDawg', 'Shein', 'AliExpress', 'Newegg', 'Newegg B2C', 'Newegg B2B'] as $channel) {
            $this->assertTrue(
                ListingManagerPublishStatus::requiresAppPublishForActive($channel),
                $channel.' Active should require a Listing Manager publish'
            );
        }
    }

    public function test_tiktok_and_ebay_can_use_marketplace_metrics(): void
    {
        foreach (['TikTok Shop', 'TikTok 2', 'eBay', 'Faire', 'Wayfair'] as $channel) {
            $this->assertFalse(
                ListingManagerPublishStatus::requiresAppPublishForActive($channel),
                $channel.' should still use marketplace metrics'
            );
        }
    }

    public function test_published_from_listing_manager_note(): void
    {
        $this->assertTrue(ListingManagerPublishStatus::wasPublishedFromListingManager(
            "Added from Listing Manager\nPublished to TopDawg via Listing Manager."
        ));
        $this->assertFalse(ListingManagerPublishStatus::wasPublishedFromListingManager(
            'Live on TopDawg via channel_registry.'
        ));
        $this->assertFalse(ListingManagerPublishStatus::wasPublishedFromListingManager(null));
    }
}
