<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\EbayPortalListingStatusSync;
use PHPUnit\Framework\TestCase;

class EbayPortalListingStatusSyncTest extends TestCase
{
    public function test_active_relist_replaces_remembered_unsold(): void
    {
        $this->assertTrue(EbayPortalListingStatusSync::shouldReplaceRemembered('INACTIVE', 'ACTIVE'));
        $this->assertFalse(EbayPortalListingStatusSync::shouldReplaceRemembered('ACTIVE', 'INACTIVE'));
        $this->assertFalse(EbayPortalListingStatusSync::shouldReplaceRemembered('ACTIVE', 'ACTIVE'));
    }
}
