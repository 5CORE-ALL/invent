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

    public function test_fob_price_push_sets_the_single_piece_price_in_usd(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'fob_price',
            'productType' => 'sourcing',
            'sourcingTrade' => [
                'fobCurrency' => 'USD',
                'fobMinPrice' => '8.23',
                'fobMaxPrice' => '8.23',
                'minOrderQuantity' => '1.0',
            ],
        ], 9.5);

        $this->assertStringContainsString('<field id="scPrice" type="singleCheck"><value>2</value></field>', $xml);
        $this->assertStringContainsString('<field id="range_min" type="input"><value>9.50</value></field>', $xml);
        $this->assertStringContainsString('<field id="range_max" type="input"><value>9.50</value></field>', $xml);
        $this->assertStringContainsString('<field id="unit_type" type="singleCheck"><value>1</value></field>', $xml);
        $this->assertStringContainsString('<field id="minOrderQuantity" type="input"><value>1</value></field>', $xml);
    }

    public function test_ladder_price_push_changes_only_the_minimum_order_tier(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'ladder_price',
            'productType' => 'sourcing',
            'productSku' => [
                'skus' => [[
                    'skuCode' => 'MS 080 YLW',
                    'bulkDiscountPrices' => [
                        ['startQuantity' => 10, 'price' => '18.00'],
                        ['startQuantity' => 1, 'price' => '20.99'],
                    ],
                ]],
            ],
        ], 21.5, 'MS 080 YLW');

        $this->assertStringContainsString('<field id="scPrice" type="singleCheck"><value>1</value></field>', $xml);
        $this->assertStringContainsString('<field id="ladderPrice_0" type="complex"><complex-value><field id="quantity" type="input"><value>1</value></field><field id="price" type="input"><value>21.50</value></field>', $xml);
        $this->assertStringContainsString('<field id="ladderPrice_1" type="complex"><complex-value><field id="quantity" type="input"><value>10</value></field><field id="price" type="input"><value>18.00</value></field>', $xml);
    }

    public function test_sku_price_listings_are_not_rewritten(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
        ], 12);
    }
}
