<?php

namespace Tests\Unit;

use App\Support\Marketplace\FaireDuplicateSkuListing;
use PHPUnit\Framework\TestCase;

class FaireDuplicateSkuListingTest extends TestCase
{
    public function test_prefers_in_stock_listing_over_published_empty_duplicate(): void
    {
        $stale = [
            'product_id' => 'p_cjz8eqceu2',
            'qty' => 0,
            'price' => 39.19,
            'lifecycle' => 'PUBLISHED',
        ];
        $live = [
            'product_id' => 'p_fxdhm85qgb',
            'qty' => 73,
            'price' => 27.50,
            'lifecycle' => 'PUBLISHED',
        ];

        $this->assertTrue(FaireDuplicateSkuListing::preferIncoming($live, $stale));
        $this->assertFalse(FaireDuplicateSkuListing::preferIncoming($stale, $live));
    }

    public function test_same_product_id_always_refreshes(): void
    {
        $current = [
            'product_id' => 'p_fxdhm85qgb',
            'qty' => 73,
            'price' => 27.50,
            'lifecycle' => 'PUBLISHED',
        ];
        $refresh = [
            'product_id' => 'p_fxdhm85qgb',
            'qty' => 70,
            'price' => 27.50,
            'lifecycle' => 'PUBLISHED',
        ];

        $this->assertTrue(FaireDuplicateSkuListing::preferIncoming($refresh, $current));
    }

    public function test_first_listing_is_accepted(): void
    {
        $this->assertTrue(FaireDuplicateSkuListing::preferIncoming([
            'product_id' => 'p_one',
            'qty' => 0,
            'price' => 10,
        ], null));
    }

    public function test_wholesale_from_prices_array_when_cents_missing(): void
    {
        $minor = FaireDuplicateSkuListing::wholesaleMinor([
            'wholesale_price' => null,
            'wholesale_price_cents' => null,
            'retail_price_cents' => 11000,
            'prices' => [[
                'geo_constraint' => ['country' => 'USA'],
                'wholesale_price' => ['amount_minor' => 2750, 'currency' => 'USD'],
                'retail_price' => ['amount_minor' => 11000, 'currency' => 'USD'],
            ]],
        ]);

        $this->assertSame(2750, $minor);
    }

    public function test_wholesale_does_not_fall_back_to_retail(): void
    {
        $this->assertNull(FaireDuplicateSkuListing::wholesaleMinor([
            'retail_price_cents' => 11000,
            'prices' => [[
                'retail_price' => ['amount_minor' => 11000, 'currency' => 'USD'],
            ]],
        ]));
    }

    public function test_wholesale_from_cents(): void
    {
        $this->assertSame(2750, FaireDuplicateSkuListing::wholesaleMinor([
            'wholesale_price_cents' => 2750,
        ]));
    }
}
