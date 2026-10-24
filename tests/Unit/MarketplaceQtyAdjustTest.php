<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use PHPUnit\Framework\TestCase;

class MarketplaceQtyAdjustTest extends TestCase
{
    public function test_no_adjust_sends_the_normal_qty(): void
    {
        $this->assertSame(10, MarketplaceLiveInventoryRules::qtyFromLiveShopify(10, 100, null));
        $this->assertSame(10, MarketplaceLiveInventoryRules::qtyFromLiveShopify(10, 100, null, 0));
    }

    public function test_negative_adjust_subtracts_from_shopify_qty(): void
    {
        $this->assertSame(9, MarketplaceLiveInventoryRules::qtyFromLiveShopify(10, 100, null, -1));
        $this->assertSame(8, MarketplaceLiveInventoryRules::qtyFromLiveShopify(10, 100, null, '-2'));
    }

    public function test_adjust_never_goes_below_zero_and_zero_stock_stays_zero(): void
    {
        $this->assertSame(0, MarketplaceLiveInventoryRules::qtyFromLiveShopify(1, 100, null, -1));
        $this->assertSame(0, MarketplaceLiveInventoryRules::qtyFromLiveShopify(2, 100, null, -5));
        $this->assertSame(0, MarketplaceLiveInventoryRules::qtyFromLiveShopify(0, 100, null, -1));
    }

    public function test_adjust_is_applied_after_percent_and_max_cap(): void
    {
        // 50% of 20 = 10, then -1.
        $this->assertSame(9, MarketplaceLiveInventoryRules::qtyFromLiveShopify(20, 50, null, -1));
        // Shopify 200 hits the cap of 100, then -2.
        $this->assertSame(98, MarketplaceLiveInventoryRules::qtyFromLiveShopify(200, 100, 100, -2));
    }

    public function test_positive_adjust_is_ignored_so_stock_is_never_raised(): void
    {
        $this->assertSame(10, MarketplaceLiveInventoryRules::qtyFromLiveShopify(10, 100, null, 3));
        $this->assertSame(0, MarketplaceLiveInventoryRules::normalizeOffset(5));
        $this->assertSame(-3, MarketplaceLiveInventoryRules::normalizeOffset('-3'));
        $this->assertSame(0, MarketplaceLiveInventoryRules::normalizeOffset(''));
        $this->assertSame(0, MarketplaceLiveInventoryRules::normalizeOffset(null));
        $this->assertSame(-999, MarketplaceLiveInventoryRules::normalizeOffset(-5000));
    }

    public function test_mismatch_push_carries_the_adjust(): void
    {
        $this->assertSame(6, MarketplaceLiveInventoryRules::qtyForMismatchPush(7, true, 100, null, -1));
    }
}
