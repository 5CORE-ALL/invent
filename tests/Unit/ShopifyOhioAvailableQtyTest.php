<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\MarketplaceQtyReadBack;
use App\Services\MarketplaceManager\ShopifyOhioAvailableQty;
use PHPUnit\Framework\TestCase;

class ShopifyOhioAvailableQtyTest extends TestCase
{
    public function test_ohio_available_wins_over_all_locations_total(): void
    {
        $node = [
            'sku' => 'ABC',
            'inventoryQuantity' => 165,
            'inventoryItem' => [
                'inventoryLevel' => [
                    'quantities' => [
                        ['name' => 'available', 'quantity' => 158],
                    ],
                ],
            ],
        ];

        $this->assertSame(158, ShopifyOhioAvailableQty::qtyFromVariantNode($node, true));
    }

    public function test_variant_not_stocked_at_ohio_is_zero(): void
    {
        $node = [
            'sku' => 'ABC',
            'inventoryQuantity' => 12,
            'inventoryItem' => ['inventoryLevel' => null],
        ];

        $this->assertSame(0, ShopifyOhioAvailableQty::qtyFromVariantNode($node, true));
    }

    public function test_falls_back_to_inventory_quantity_without_an_ohio_location(): void
    {
        $node = ['sku' => 'ABC', 'inventoryQuantity' => 12];

        $this->assertSame(12, ShopifyOhioAvailableQty::qtyFromVariantNode($node, false));
        $this->assertNull(ShopifyOhioAvailableQty::qtyFromVariantNode(['sku' => 'ABC'], false));
    }

    public function test_variant_selection_is_empty_without_a_location(): void
    {
        $this->assertSame('', ShopifyOhioAvailableQty::variantSelection(null));
        $this->assertStringContainsString(
            'inventoryLevel(locationId: "gid://shopify/Location/123")',
            ShopifyOhioAvailableQty::variantSelection('gid://shopify/Location/123')
        );
    }

    public function test_read_back_support_is_per_channel(): void
    {
        $this->assertTrue(MarketplaceQtyReadBack::supports('amazon'));
        $this->assertTrue(MarketplaceQtyReadBack::supports('Faire'));
        $this->assertFalse(MarketplaceQtyReadBack::supports('ebay2'));
        $this->assertFalse(MarketplaceQtyReadBack::supports('tiktok'));
    }
}
