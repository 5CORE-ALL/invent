<?php

namespace Tests\Unit;

use App\Support\Shopify\ShopifySkuPriceSync;
use Tests\TestCase;

class ShopifySkuPriceSyncTest extends TestCase
{
    public function test_variant_price_fills_the_app_price_and_b2c_price(): void
    {
        $this->assertSame([
            'price' => 23.9,
            'b2c_price' => 23.9,
            'b2b_price' => 55.0,
        ], ShopifySkuPriceSync::columnsFromVariant('23.90', '55.00'));
    }

    public function test_missing_selling_price_does_not_invent_a_zero(): void
    {
        $this->assertSame([], ShopifySkuPriceSync::columnsFromVariant(null, null));
        $this->assertSame([], ShopifySkuPriceSync::columnsFromVariant('0', ''));
    }

    public function test_crawl_does_not_overwrite_a_price_saved_after_it_started(): void
    {
        $this->assertFalse(ShopifySkuPriceSync::crawlMayOverwrite(
            '2026-10-05 11:28:03',
            '2026-10-05 11:00:00'
        ));
    }

    public function test_crawl_writes_price_when_nothing_newer_was_saved(): void
    {
        $this->assertTrue(ShopifySkuPriceSync::crawlMayOverwrite(
            '2026-10-03 15:29:34',
            '2026-10-05 11:00:00'
        ));
        $this->assertTrue(ShopifySkuPriceSync::crawlMayOverwrite(null, '2026-10-05 11:00:00'));
    }
}
