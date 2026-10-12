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

    public function test_sku_price_push_sets_the_single_sku_price(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
            'productSku' => [
                'skus' => [[
                    'skuCode' => 'CS 06 2W',
                    'skuId' => 10001889397471,
                    'bulkDiscountPrices' => [
                        ['startQuantity' => -1, 'price' => '11.86'],
                    ],
                ]],
            ],
        ], 13.09, 'CS 06 2W');

        $this->assertStringContainsString('<field id="marketPrice" type="singleCheck"><value>3</value></field>', $xml);
        $this->assertStringContainsString('<field id="skuId" type="input"><value>10001889397471</value></field>', $xml);
        $this->assertStringContainsString('<field id="price" type="input"><value>13.09</value></field>', $xml);
    }

    public function test_sku_price_push_keeps_the_other_sku_price(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'CS 06 2W',
                        'skuId' => 11,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => -1, 'price' => '11.86'],
                        ],
                    ],
                    [
                        'skuCode' => 'CS 06 2W WoG',
                        'skuId' => 22,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => -1, 'price' => '10.55'],
                        ],
                    ],
                ],
            ],
        ], 13.09, 'CS 06 2W');

        $this->assertStringContainsString('<field id="skuId" type="input"><value>11</value></field><field id="price" type="input"><value>13.09</value></field>', $xml);
        $this->assertStringContainsString('<field id="skuId" type="input"><value>22</value></field><field id="price" type="input"><value>10.55</value></field>', $xml);
    }

    public function test_sku_price_without_a_sku_id_is_not_sent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
        ], 12);
    }

    public function test_sku_price_push_matches_a_piece_count_suffix_and_keeps_the_sibling(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'MS 080 YLW',
                        'skuId' => 11,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => 1, 'price' => '20.99'],
                        ],
                    ],
                    [
                        'skuCode' => 'MS 080 RED',
                        'skuId' => 22,
                        'skuPrice' => '18.40',
                    ],
                ],
            ],
        ], 22.5, 'MS 080 YLW 2PCS');

        $this->assertStringContainsString('<field id="skuId" type="input"><value>11</value></field><field id="price" type="input"><value>22.50</value></field>', $xml);
        $this->assertStringContainsString('<field id="skuId" type="input"><value>22</value></field><field id="price" type="input"><value>18.40</value></field>', $xml);
    }

    public function test_sku_price_push_matches_spacing_in_the_piece_count(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'skuPrice',
            'productType' => 'wholesale',
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'CAPO RED 4 PCS',
                        'skuId' => 31,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => -1, 'price' => '3.62'],
                        ],
                    ],
                    [
                        'skuCode' => 'CAPO BLK 4PCS',
                        'skuId' => 32,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => -1, 'price' => '3.10'],
                        ],
                    ],
                ],
            ],
        ], 4.25, 'CAPO RED 4PCS');

        $this->assertStringContainsString('<field id="skuId" type="input"><value>31</value></field><field id="price" type="input"><value>4.25</value></field>', $xml);
        $this->assertStringContainsString('<field id="skuId" type="input"><value>32</value></field><field id="price" type="input"><value>3.10</value></field>', $xml);
    }

    public function test_sku_price_push_skips_a_sibling_that_has_no_sku_id(): void
    {
        $xml = app(AlibabaApiService::class)->listedPriceUpdateXml([
            'priceType' => 'sku_price',
            'productType' => 'wholesale',
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'CS 06 2W',
                        'skuId' => 11,
                        'bulkDiscountPrices' => [
                            ['startQuantity' => -1, 'price' => '11.86'],
                        ],
                    ],
                    [
                        'skuCode' => 'PLACEHOLDER',
                    ],
                ],
            ],
        ], 13.09, 'CS 06 2W');

        $this->assertStringContainsString('<field id="skuId" type="input"><value>11</value></field><field id="price" type="input"><value>13.09</value></field>', $xml);
        $this->assertStringNotContainsString('PLACEHOLDER', $xml);
    }

    public function test_every_listing_on_a_product_is_kept_with_its_price(): void
    {
        $rows = app(AlibabaApiService::class)->analyticsRowsFromProduct([
            'display' => 'Y',
            'status' => 'approved',
            'sourcingTrade' => [
                'fobMinPrice' => '19.00',
            ],
            'productSku' => [
                'skus' => [
                    [
                        'skuCode' => 'MS 080 YLW',
                        'bulkDiscountPrices' => [
                            ['startQuantity' => 1, 'price' => '20.99'],
                        ],
                    ],
                    [
                        'skuCode' => 'MS 080 RED',
                        'bulkDiscountPrices' => [
                            ['startQuantity' => 1, 'price' => '18.40'],
                        ],
                    ],
                    [
                        'skuCode' => 'MS 080 BLK',
                    ],
                ],
            ],
        ], '10000049298625');

        $this->assertSame(['MS 080 YLW', 'MS 080 RED', 'MS 080 BLK'], array_column($rows, 'sku'));
        $this->assertEqualsWithDelta(20.99, $rows[0]['sku_price'], 0.001);
        $this->assertEqualsWithDelta(18.40, $rows[1]['sku_price'], 0.001);
        $this->assertEqualsWithDelta(19.00, $rows[2]['sku_price'], 0.001);
        $this->assertSame('Active', $rows[0]['status']);
    }
}
