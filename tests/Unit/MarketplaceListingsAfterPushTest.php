<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceListingsAfterPush;
use PHPUnit\Framework\TestCase;

class MarketplaceListingsAfterPushTest extends TestCase
{
    public function test_pushed_qty_is_written_onto_matching_cached_rows(): void
    {
        $rows = [
            ['sku' => 'WF 472 2PCS', 'inventory' => 9, 'status' => 'Active'],
            ['sku' => 'OTHER-1', 'inventory' => 4, 'status' => 'Active'],
        ];

        $patched = MarketplaceListingsAfterPush::patchRows($rows, ['wf-472-2pcs' => 3]);

        $this->assertSame(3, $patched[0]['inventory']);
        $this->assertSame('Active', $patched[0]['status']);
        $this->assertSame(4, $patched[1]['inventory']);
    }

    public function test_no_pushed_qty_leaves_rows_untouched(): void
    {
        $rows = [['sku' => 'A', 'inventory' => 1]];

        $this->assertSame($rows, MarketplaceListingsAfterPush::patchRows($rows, []));
    }

    public function test_only_skus_the_marketplace_accepted_are_kept(): void
    {
        $rows = [
            ['sku' => 'A', 'inventory' => 1],
            ['sku' => 'b', 'inventory' => 2],
            ['sku' => 'C', 'inventory' => 3],
        ];

        $kept = MarketplaceListingsAfterPush::acceptedRows($rows, ['failed' => 1, 'updated_skus' => ['a', 'B']]);

        $this->assertSame(['A', 'b'], array_column($kept, 'sku'));
    }

    public function test_failed_batch_without_sku_list_keeps_nothing(): void
    {
        $rows = [['sku' => 'A', 'inventory' => 1]];

        $this->assertSame([], MarketplaceListingsAfterPush::acceptedRows($rows, ['failed' => 1]));
        $this->assertSame($rows, MarketplaceListingsAfterPush::acceptedRows($rows, ['failed' => 0]));
    }
}
