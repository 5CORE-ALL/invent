<?php

namespace Tests\Unit;

use App\Services\ShopifyStockTransferGraphql;
use PHPUnit\Framework\TestCase;

class ShopifyStockTransferGraphqlTest extends TestCase
{
    public function test_parses_available_quantity_at_ohio(): void
    {
        $parsed = ShopifyStockTransferGraphql::parseVariantInventory([
            'data' => [
                'productVariant' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/555',
                        'inventoryLevel' => [
                            'quantities' => [
                                ['name' => 'available', 'quantity' => 12],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('ok', $parsed['status']);
        $this->assertSame('555', $parsed['inventory_item_id']);
        $this->assertSame(12, $parsed['available']);
    }

    public function test_variant_without_location_level_keeps_item_id(): void
    {
        $parsed = ShopifyStockTransferGraphql::parseVariantInventory([
            'data' => [
                'productVariant' => [
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/9',
                        'inventoryLevel' => null,
                    ],
                ],
            ],
        ]);

        $this->assertSame('ok', $parsed['status']);
        $this->assertSame('9', $parsed['inventory_item_id']);
        $this->assertNull($parsed['available']);
    }

    public function test_missing_variant_is_not_a_transport_failure(): void
    {
        $parsed = ShopifyStockTransferGraphql::parseVariantInventory([
            'data' => ['productVariant' => null],
        ]);

        $this->assertSame('missing', $parsed['status']);
    }

    public function test_throttled_response_is_a_failure_to_retry(): void
    {
        $json = [
            'errors' => [[
                'message' => 'Throttled',
                'extensions' => ['code' => 'THROTTLED'],
            ]],
        ];

        $this->assertTrue(ShopifyStockTransferGraphql::isThrottled($json));
        $this->assertSame('failed', ShopifyStockTransferGraphql::parseVariantInventory($json)['status']);
        $this->assertSame(4, ShopifyStockTransferGraphql::throttleWaitSeconds(null, [
            'extensions' => [
                'cost' => [
                    'requestedQueryCost' => 200,
                    'throttleStatus' => [
                        'currentlyAvailable' => 0,
                        'restoreRate' => 50,
                    ],
                ],
            ],
        ], 2));
    }

    public function test_adjust_user_error_is_returned(): void
    {
        $parsed = ShopifyStockTransferGraphql::parseAdjust([
            'data' => [
                'inventoryAdjustQuantities' => [
                    'userErrors' => [['field' => ['input'], 'message' => 'Inventory item not stocked at location']],
                    'inventoryAdjustmentGroup' => null,
                ],
            ],
        ]);

        $this->assertFalse($parsed['success']);
        $this->assertSame('Inventory item not stocked at location', $parsed['error']);
    }

    public function test_adjust_success(): void
    {
        $parsed = ShopifyStockTransferGraphql::parseAdjust([
            'data' => [
                'inventoryAdjustQuantities' => [
                    'userErrors' => [],
                    'inventoryAdjustmentGroup' => [
                        'reason' => 'correction',
                        'changes' => [
                            ['name' => 'available', 'delta' => 3, 'quantityAfterChange' => 14],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($parsed['success']);
        $this->assertSame(14, $parsed['available']);
    }

    public function test_picks_ohio_over_main_warehouse(): void
    {
        $id = ShopifyStockTransferGraphql::parseOhioLocationId([
            'data' => [
                'locations' => [
                    'nodes' => [
                        ['id' => 'gid://shopify/Location/1', 'name' => 'Main Warehouse', 'isActive' => true],
                        ['id' => 'gid://shopify/Location/2', 'name' => 'Ohio', 'isActive' => true],
                    ],
                ],
            ],
        ]);

        $this->assertSame('2', $id);
    }
}
