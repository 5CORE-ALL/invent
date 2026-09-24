<?php

namespace Tests\Unit;

use App\Services\TopDawgApiService;
use Tests\TestCase;

class TopDawgExtractListingPriceTest extends TestCase
{
    public function test_prefers_site_cost_over_a_different_price_key(): void
    {
        $this->assertSame(12.08, TopDawgApiService::extractListingPrice([
            'price' => 35.99,
            'cost' => '12.08',
            'msrp' => 41.60,
        ]));
    }

    public function test_uses_cost_when_price_is_zero(): void
    {
        $this->assertSame(29.99, TopDawgApiService::extractListingPrice([
            'price' => 0,
            'cost' => 29.99,
            'msrp' => 34.99,
        ]));
    }

    public function test_ignores_empty_price_and_reads_msrp(): void
    {
        $this->assertSame(18.50, TopDawgApiService::extractListingPrice([
            'price' => '',
            'cost' => 0,
            'msrp' => '18.50',
        ]));
    }

    public function test_unwraps_nested_amount(): void
    {
        $this->assertSame(12.00, TopDawgApiService::extractListingPrice([
            'price' => ['amount' => '12.00'],
        ]));
    }

    public function test_reads_nested_product_cost(): void
    {
        $this->assertSame(9.25, TopDawgApiService::extractListingPrice([
            'price' => 0,
            'product' => ['cost' => 9.25],
        ]));
    }

    public function test_returns_null_when_all_amounts_are_zero(): void
    {
        $this->assertNull(TopDawgApiService::extractListingPrice([
            'price' => 0,
            'cost' => '0.00',
            'msrp' => null,
        ]));
    }
}
