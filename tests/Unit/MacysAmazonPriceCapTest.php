<?php

namespace Tests\Unit;

use App\Support\MacysAmazonPriceCap;
use Tests\TestCase;

class MacysAmazonPriceCapTest extends TestCase
{
    public function test_floors_sprice_when_below_amazon(): void
    {
        $this->assertSame(19.99, MacysAmazonPriceCap::cap(18.50, 19.99));
    }

    public function test_keeps_sprice_when_at_or_above_amazon(): void
    {
        $this->assertSame(24.50, MacysAmazonPriceCap::cap(24.50, 19.99));
        $this->assertSame(19.99, MacysAmazonPriceCap::cap(19.99, 19.99));
    }

    public function test_skips_floor_when_amazon_price_is_missing(): void
    {
        $this->assertSame(24.50, MacysAmazonPriceCap::cap(24.50, 0));
        $this->assertSame(24.50, MacysAmazonPriceCap::cap(24.50, null));
    }
}
