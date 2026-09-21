<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use PHPUnit\Framework\TestCase;

class MarketplaceListingStockResolverClassifyTest extends TestCase
{
    public function test_missing_live_inventory_is_zero_by_default(): void
    {
        $map = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            [['sku' => 'LS 100-6 RED', 'inventory' => null]],
            ['LS 100-6 RED' => 79]
        );

        $this->assertSame(0, $map['LS 100-6 RED']);
    }

    public function test_aliexpress_keeps_local_qty_when_list_cache_has_no_stock(): void
    {
        $map = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            [['sku' => 'LS 100-6 RED', 'inventory' => null]],
            ['LS 100-6 RED' => 79],
            false
        );

        $this->assertSame(79, $map['LS 100-6 RED']);
    }

    public function test_explicit_live_zero_still_wins(): void
    {
        $map = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
            [['sku' => 'LS 100-6 RED', 'inventory' => 0]],
            ['LS 100-6 RED' => 79],
            false
        );

        $this->assertSame(0, $map['LS 100-6 RED']);
    }
}
