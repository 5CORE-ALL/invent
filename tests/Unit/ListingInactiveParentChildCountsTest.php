<?php

namespace Tests\Unit;

use App\Support\Marketplace\ListingInactiveParentChildCounts;
use PHPUnit\Framework\TestCase;

class ListingInactiveParentChildCountsTest extends TestCase
{
    public function test_keeps_cp_master_inactive_sku_with_inventory(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertCount(1, $kept);
        $this->assertSame('WF 6.5 100 PP 4OHM', $kept[0]['sku']);
    }

    public function test_drops_zero_inventory_even_when_in_cp_master_and_marketplace(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 0],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertSame([], $kept);
    }

    public function test_drops_marketplace_only_sku_not_in_cp_master(): void
    {
        $rows = [
            ['sku' => 'MARKETPLACE-ONLY', 'kind' => 'child', 'inv' => 8],
        ];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, ['WF 6.5 100 PP 4OHM' => true]);

        $this->assertSame([], $kept);
    }

    public function test_matches_normalized_cp_master_sku(): void
    {
        $rows = [
            ['sku' => 'WF-6.5 100 PP-4OHM', 'kind' => 'child', 'inv' => 4],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertCount(1, $kept);
    }
}
