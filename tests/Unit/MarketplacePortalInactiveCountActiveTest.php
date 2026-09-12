<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplacePortalInactiveCount;
use PHPUnit\Framework\TestCase;

class MarketplacePortalInactiveCountActiveTest extends TestCase
{
    public function test_sku_is_active_matches_normalized_key(): void
    {
        $active = ['WF 6.5 100 PP 4OHM' => true];

        $this->assertTrue(MarketplacePortalInactiveCount::skuIsActive('WF 6.5 100 PP 4OHM', $active));
        $this->assertFalse(MarketplacePortalInactiveCount::skuIsActive('OTHER SKU', $active));
        $this->assertFalse(MarketplacePortalInactiveCount::skuIsActive('WF 6.5 100 PP 4OHM', []));
    }
}
