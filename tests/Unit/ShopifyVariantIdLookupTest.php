<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ShopifyVariantIdLookup;
use PHPUnit\Framework\TestCase;

class ShopifyVariantIdLookupTest extends TestCase
{
    public function test_does_not_use_the_first_variant_when_its_sku_differs(): void
    {
        $id = ShopifyVariantIdLookup::matchingVariantId([
            ['id' => 111, 'sku' => 'HISE 4X10'],
            ['id' => 222, 'sku' => 'KS Z1 RED HD'],
            ['id' => 333, 'sku' => 'GAS 01 BLK'],
        ], 'GAS 01 BLK');

        $this->assertSame(333, $id);
    }

    public function test_returns_null_when_the_only_variant_is_a_different_sku(): void
    {
        $this->assertNull(ShopifyVariantIdLookup::matchingVariantId([
            ['id' => 111, 'sku' => 'HISE 4X10'],
        ], 'KS Z1 RED HD'));
    }

    public function test_reads_numeric_id_from_graphql_gid(): void
    {
        $this->assertSame(49358322041069, ShopifyVariantIdLookup::numericVariantId('gid://shopify/ProductVariant/49358322041069'));
        $this->assertNull(ShopifyVariantIdLookup::numericVariantId(''));
    }
}
