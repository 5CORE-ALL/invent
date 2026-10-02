<?php

namespace Tests\Unit;

use App\Services\AlibabaApiService;
use Tests\TestCase;

class AlibabaListedPriceTest extends TestCase
{
    public function test_pull_uses_unit_price_at_minimum_order_not_the_first_bulk_tier(): void
    {
        $rows = app(AlibabaApiService::class)->extractSkuRowsFromProductInfo([
            'subject' => 'Mic stand',
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'MS 080 YLW',
                        'bulkDiscountPrices' => [
                            ['startQuantity' => 10, 'price' => '18.00'],
                            ['startQuantity' => 1, 'price' => '20.99'],
                        ],
                    ],
                ],
            ],
        ], '10000049298625', null);

        $this->assertCount(1, $rows);
        $this->assertSame('MS 080 YLW', $rows[0]['sku']);
        $this->assertEqualsWithDelta(20.99, $rows[0]['price'], 0.001);
    }

    public function test_flat_listing_price_with_start_quantity_minus_one_is_kept(): void
    {
        $rows = app(AlibabaApiService::class)->extractSkuRowsFromProductInfo([
            'product_sku' => [
                'skus' => [
                    'sku_definition' => [
                        [
                            'sku_code' => 'CAPO RED 4Pk',
                            'bulk_discount_prices' => [
                                ['start_quantity' => -1, 'price' => '3.62'],
                            ],
                        ],
                    ],
                ],
            ],
        ], '10000043326840', null);

        $this->assertCount(1, $rows);
        $this->assertSame('CAPO RED 4Pk', $rows[0]['sku']);
        $this->assertEqualsWithDelta(3.62, $rows[0]['price'], 0.001);
    }
}
