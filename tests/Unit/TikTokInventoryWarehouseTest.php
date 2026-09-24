<?php

namespace Tests\Unit;

use App\Services\TikTokShopService;
use PHPUnit\Framework\TestCase;

class TikTokInventoryWarehouseTest extends TestCase
{
    public function test_second_warehouse_is_kept_and_subtracted_from_the_main_one(): void
    {
        $rows = TikTokShopService::pushRowsForWarehouseStock([
            ['warehouse_id' => 'main', 'quantity' => 824],
            ['warehouse_id' => 'extra', 'quantity' => 2],
        ], 824);

        $this->assertSame([
            ['warehouse_id' => 'main', 'quantity' => 822],
        ], $rows);
    }

    public function test_single_warehouse_is_set_to_the_shopify_qty(): void
    {
        $rows = TikTokShopService::pushRowsForWarehouseStock([
            ['warehouse_id' => 'main', 'quantity' => 99],
        ], 199);

        $this->assertSame([
            ['warehouse_id' => 'main', 'quantity' => 199],
        ], $rows);
    }

    public function test_shopify_zero_clears_the_only_warehouse(): void
    {
        $rows = TikTokShopService::pushRowsForWarehouseStock([
            ['warehouse_id' => 'main', 'quantity' => 103],
        ], 0);

        $this->assertSame([
            ['warehouse_id' => 'main', 'quantity' => 0],
        ], $rows);
    }

    public function test_product_search_warehouse_is_included_when_inventory_search_misses_it(): void
    {
        $merged = TikTokShopService::mergeWarehouseInventoryRows(
            [
                ['warehouse_id' => 'main', 'quantity' => 824],
                ['warehouse_id' => 'extra', 'quantity' => 2],
            ],
            [
                ['warehouse_id' => 'main', 'quantity' => 824],
            ]
        );

        $rows = TikTokShopService::pushRowsForWarehouseStock($merged, 824);

        $this->assertSame([
            ['warehouse_id' => 'main', 'quantity' => 822],
        ], $rows);
    }

    public function test_rejected_adjust_falls_back_to_zeroing_the_extra_warehouse(): void
    {
        $warehouses = [
            ['warehouse_id' => 'main', 'quantity' => 25],
            ['warehouse_id' => 'extra', 'quantity' => 2],
        ];
        $first = TikTokShopService::pushRowsForWarehouseStock($warehouses, 25);
        $alt = TikTokShopService::alternatePushRows($warehouses, 25, null, $first);

        $this->assertSame([
            ['warehouse_id' => 'main', 'quantity' => 25],
            ['warehouse_id' => 'extra', 'quantity' => 0],
        ], $alt);
    }
}
