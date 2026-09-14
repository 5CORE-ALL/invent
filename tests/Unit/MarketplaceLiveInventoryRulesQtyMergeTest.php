<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use PHPUnit\Framework\TestCase;

class MarketplaceLiveInventoryRulesQtyMergeTest extends TestCase
{
    public function test_scheduled_sync_restores_cp_master_stock_when_live_is_zero(): void
    {
        $merged = MarketplaceLiveInventoryRules::mergeLiveAndListingsQty(
            ['SS HD 2 PK YLW BAG' => 0],
            ['SS HD 2 PK YLW BAG' => 60],
            false
        );

        $this->assertSame(60, $merged['SS HD 2 PK YLW BAG']);
    }

    public function test_scheduled_sync_fills_missing_live_from_cp_master(): void
    {
        $merged = MarketplaceLiveInventoryRules::mergeLiveAndListingsQty(
            [],
            ['SPONGE' => 12],
            false
        );

        $this->assertSame(12, $merged['SPONGE']);
    }

    public function test_scheduled_sync_keeps_positive_live_qty(): void
    {
        $merged = MarketplaceLiveInventoryRules::mergeLiveAndListingsQty(
            ['SKU-A' => 100],
            ['SKU-A' => 60],
            false
        );

        $this->assertSame(100, $merged['SKU-A']);
    }

    public function test_exact_mode_overwrites_with_cp_master(): void
    {
        $merged = MarketplaceLiveInventoryRules::mergeLiveAndListingsQty(
            ['SKU-A' => 100],
            ['SKU-A' => 60],
            true
        );

        $this->assertSame(60, $merged['SKU-A']);
    }

    public function test_local_zero_stays_zero_when_live_missing(): void
    {
        $merged = MarketplaceLiveInventoryRules::mergeLiveAndListingsQty(
            [],
            ['SKU-A' => 0],
            false
        );

        $this->assertSame(0, $merged['SKU-A']);
    }
}
