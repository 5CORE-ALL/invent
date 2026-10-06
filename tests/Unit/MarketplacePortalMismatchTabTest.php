<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplacePortalStatusTabs;
use App\Support\Marketplace\EbayListingEnded;
use PHPUnit\Framework\TestCase;

class MarketplacePortalMismatchTabTest extends TestCase
{
    public function test_auction_ended_291_is_an_ended_listing(): void
    {
        $message = 'Auction ended. (eBay code: 291)';

        $this->assertTrue(EbayListingEnded::looksEndedError($message));
        $this->assertTrue(EbayListingEnded::looksEndedError('{"ErrorCode":"291","ShortMessage":"Auction ended."}'));
    }

    public function test_inactive_listing_leaves_the_mismatch_tab(): void
    {
        $result = MarketplacePortalStatusTabs::overlayQtyAndPortal(
            [],
            ['KEPT'],
            ['LIVE-SKU', 'WF 810H 4 OHM 2PCS'],
            [],
            [
                ['sku' => 'LIVE-SKU', 'state' => 'active'],
                ['sku' => 'WF 810H 4 OHM 2PCS', 'state' => 'inactive', 'inactive_reason' => 'Unsold/ended'],
            ]
        );

        $this->assertSame(['LIVE-SKU'], $result['mismatchActive']);
        $this->assertSame(1, $result['counts']['mismatch']);
        $this->assertContains('WF 810H 4 OHM 2PCS', $result['matchedInactive']);
    }

    public function test_ended_sibling_does_not_replace_the_active_listing_qty(): void
    {
        $map = MarketplaceListingStockResolver::stockMapFromLiveListingRows([
            ['sku' => 'WF 10 140 PP 4OHM', 'state' => 'active', 'inventory' => 50],
            ['sku' => 'WF 10 140 PP 4OHM', 'state' => 'inactive', 'inventory' => 5],
        ]);

        $this->assertSame(50, $map['WF 10 140 PP 4OHM']);
    }

    public function test_active_listing_qty_replaces_an_earlier_ended_row(): void
    {
        $map = MarketplaceListingStockResolver::stockMapFromLiveListingRows([
            ['sku' => 'WF 10140 4OHM 2PCS', 'state' => 'ended', 'inventory' => 0],
            ['sku' => 'WF 10140 4OHM 2PCS', 'state' => 'active', 'inventory' => 99],
        ]);

        $this->assertSame(99, $map['WF 10140 4OHM 2PCS']);
    }

    public function test_linked_mismatch_drops_an_ended_listing(): void
    {
        $rows = [
            ['sku' => 'ENDED', 'state' => 'ended'],
            ['sku' => 'STILL', 'state' => 'active'],
        ];

        $this->assertSame(
            ['STILL'],
            MarketplacePortalStatusTabs::withoutInactiveSkus(['ENDED', 'STILL'], $rows)
        );
    }
}
