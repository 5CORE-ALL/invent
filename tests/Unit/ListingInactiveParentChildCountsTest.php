<?php

namespace Tests\Unit;

use App\Support\Marketplace\ListingInactiveParentChildCounts;
use PHPUnit\Framework\TestCase;

class ListingInactiveParentChildCountsTest extends TestCase
{
    public function test_keeps_cp_master_inactive_sku_with_inventory(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'inactive'],
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
            ['sku' => 'WF-6.5 100 PP-4OHM', 'kind' => 'child', 'inv' => 4, 'state' => 'inactive'],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertCount(1, $kept);
    }

    public function test_drops_sku_that_is_active_on_the_marketplace(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'active'],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertSame([], $kept);
    }

    public function test_keeps_marketplace_inactive_sku_even_with_leftover_channel_qty(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'channel_inv' => 12, 'state' => 'inactive'],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertCount(1, $kept);
        $this->assertSame('WF 6.5 100 PP 4OHM', $kept[0]['sku']);
    }

    public function test_drops_sku_in_marketplace_active_key_set(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'inactive'],
        ];
        $cpKeys = ['WF 6.5 100 PP 4OHM' => true];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows(
            $rows,
            $cpKeys,
            ['WF 6.5 100 PP 4OHM' => true]
        );

        $this->assertSame([], $kept);
    }

    public function test_drops_missing_and_never_listed_skus(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'missing'],
            ['sku' => 'WF 6.5 100 PP 4OHM B', 'kind' => 'child', 'inv' => 12, 'state' => 'not_listed'],
        ];
        $cpKeys = [
            'WF 6.5 100 PP 4OHM' => true,
            'WF 6.5 100 PP 4OHM B' => true,
        ];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertSame([], $kept);
    }

    public function test_drops_ended_sold_and_draft_listings(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'sold'],
            ['sku' => 'WF 6.5 100 PP 4OHM B', 'kind' => 'child', 'inv' => 12, 'state' => 'ended'],
            ['sku' => 'WF 6.5 100 PP 4OHM C', 'kind' => 'child', 'inv' => 12, 'state' => 'draft'],
        ];
        $cpKeys = [
            'WF 6.5 100 PP 4OHM' => true,
            'WF 6.5 100 PP 4OHM B' => true,
            'WF 6.5 100 PP 4OHM C' => true,
        ];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertSame([], $kept);
    }

    public function test_keeps_compliance_holds_and_drops_out_of_stock_reason(): void
    {
        $rows = [
            ['sku' => 'WF 6.5 100 PP 4OHM', 'kind' => 'child', 'inv' => 12, 'state' => 'suppressed'],
            ['sku' => 'WF 6.5 100 PP 4OHM B', 'kind' => 'child', 'inv' => 12, 'state' => 'unable to list'],
            ['sku' => 'WF 6.5 100 PP 4OHM C', 'kind' => 'child', 'inv' => 12, 'state' => 'inactive', 'inactive_reason' => 'Out of stock'],
        ];
        $cpKeys = [
            'WF 6.5 100 PP 4OHM' => true,
            'WF 6.5 100 PP 4OHM B' => true,
            'WF 6.5 100 PP 4OHM C' => true,
        ];

        $kept = ListingInactiveParentChildCounts::keepCpMasterInStockInactiveRows($rows, $cpKeys);

        $this->assertCount(2, $kept);
        $this->assertSame(['WF 6.5 100 PP 4OHM', 'WF 6.5 100 PP 4OHM B'], array_column($kept, 'sku'));
    }
}
