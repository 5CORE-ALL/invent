<?php

namespace Tests\Unit;

use App\Services\SheinApiService;
use Tests\TestCase;

class SheinPricePushTest extends TestCase
{
    public function test_dead_sku_code_messages_are_detected(): void
    {
        $this->assertTrue(SheinApiService::isDeadSkuCodeMessage('SKU does not exist'));
        $this->assertTrue(SheinApiService::isDeadSkuCodeMessage('Invalid skuCode: [I11abc]'));
        $this->assertTrue(SheinApiService::isDeadSkuCodeMessage('无效的skuCode: [I11abc]'));
        $this->assertFalse(SheinApiService::isDeadSkuCodeMessage('Price is lower than the minimum'));
        $this->assertFalse(SheinApiService::isDeadSkuCodeMessage(''));
    }

    public function test_build_price_save_entry_uses_special_when_shop_is_higher(): void
    {
        $row = app(SheinApiService::class)->buildPriceSaveEntry(
            'I11mesukkwwr',
            19.99,
            29.99,
            29.99,
            'shein-us',
            'USD'
        );

        $this->assertSame('I11mesukkwwr', $row['productCode']);
        $this->assertSame('shein-us', $row['site']);
        $this->assertSame('USD', $row['currencyCode']);
        $this->assertSame(29.99, $row['shopPrice']);
        $this->assertSame(19.99, $row['specialPrice']);
        $this->assertArrayNotHasKey('riseReason', $row);
    }

    public function test_build_price_save_entry_raises_shop_to_sale_and_sets_rise_reason(): void
    {
        $row = app(SheinApiService::class)->buildPriceSaveEntry(
            'I11mesukkwwr',
            40.00,
            29.99,
            29.99,
            'shein-us',
            'USD'
        );

        $this->assertSame(40.00, $row['shopPrice']);
        $this->assertNull($row['specialPrice']);
        $this->assertSame(SheinApiService::PRICE_RISE_REASON_MARKET_ADJUSTMENT, $row['riseReason']);
        $this->assertIsInt($row['riseReason']);
    }
}
