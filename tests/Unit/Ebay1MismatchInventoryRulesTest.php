<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\Ebay1InventorySyncService;
use PHPUnit\Framework\TestCase;

class Ebay1MismatchInventoryRulesTest extends TestCase
{
    public function test_trading_limit_matches_getapiaccessrules_popup(): void
    {
        $this->assertTrue(Ebay1InventorySyncService::looksLikeTradingLimit(
            'Your application has exceeded usage limit on this call, please make call to GetAPIAccessRules to check your call usage. (ebay error: 518)'
        ));
        $this->assertTrue(Ebay1InventorySyncService::looksLikeTradingLimit(
            'eBay error 518: Call usage limit has been exceeded.'
        ));
        $this->assertFalse(Ebay1InventorySyncService::looksLikeTradingLimit(
            'ReviseInventoryStatus failed for ItemID 365518123456: SKU does not exist.'
        ));
    }
}
