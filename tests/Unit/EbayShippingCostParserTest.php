<?php

namespace Tests\Unit;

use App\Support\Marketplace\EbayShippingCostParser;
use PHPUnit\Framework\TestCase;

class EbayShippingCostParserTest extends TestCase
{
    public function test_reads_serpapi_extracted_price_not_just_amount(): void
    {
        $parsed = EbayShippingCostParser::fromProduct([
            'shipping' => [
                'options' => [
                    [
                        'via' => 'eBay International Shipping',
                        'price' => [
                            'raw' => 'US $36.13',
                            'extracted' => 36.13,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($parsed['known']);
        $this->assertSame(36.13, $parsed['cost']);
    }

    public function test_reads_search_style_shipping_object(): void
    {
        $parsed = EbayShippingCostParser::fromProduct([
            'shipping' => [
                'raw' => '+$10.00',
                'extracted' => 10,
            ],
        ]);

        $this->assertTrue($parsed['known']);
        $this->assertSame(10.0, $parsed['cost']);
    }

    public function test_free_shipping_text_is_known_zero(): void
    {
        $parsed = EbayShippingCostParser::fromText('Free shipping');

        $this->assertTrue($parsed['known']);
        $this->assertSame(0.0, $parsed['cost']);
    }

    public function test_free_returns_is_not_free_shipping(): void
    {
        $parsed = EbayShippingCostParser::fromText('Breathe easy. Free returns.');

        $this->assertFalse($parsed['known']);
        $this->assertSame(0.0, $parsed['cost']);
    }

    public function test_missing_shipping_is_unknown_not_free(): void
    {
        $parsed = EbayShippingCostParser::fromProduct(['title' => 'Drum throne']);

        $this->assertFalse($parsed['known']);
        $this->assertSame(0.0, $parsed['cost']);
    }

    public function test_html_international_shipping_amount(): void
    {
        $html = 'Shipping, returns, and payments US $36.13 eBay International Shipping Located in: Rowland Heights';
        $parsed = EbayShippingCostParser::fromHtml($html);

        $this->assertTrue($parsed['known']);
        $this->assertSame(36.13, $parsed['cost']);
    }

    public function test_prefer_existing_keeps_paid_shipping_when_live_is_unknown(): void
    {
        $live = EbayShippingCostParser::preferExisting([
            'price' => 24.50,
            'shipping_cost' => 0,
            'shipping_known' => false,
            'total_price' => 24.50,
        ], 10);

        $this->assertSame(10.0, $live['shipping_cost']);
        $this->assertSame(34.50, $live['total_price']);
    }

    public function test_prefer_existing_does_not_let_claimed_free_wipe_paid_shipping(): void
    {
        $live = EbayShippingCostParser::preferExisting([
            'price' => 24.50,
            'shipping_cost' => 0,
            'shipping_known' => true,
            'total_price' => 24.50,
        ], 10);

        $this->assertSame(10.0, $live['shipping_cost']);
        $this->assertSame(34.50, $live['total_price']);
    }

    public function test_html_economy_shipping_amount(): void
    {
        $html = 'Shipping, returns, and payments Shipping: US $10.00 Economy Shipping Located in: Rowland Heights';
        $parsed = EbayShippingCostParser::fromHtml($html);

        $this->assertTrue($parsed['known']);
        $this->assertSame(10.0, $parsed['cost']);
    }

    public function test_browse_option_shipping_cost_value(): void
    {
        $parsed = EbayShippingCostParser::fromOption([
            'shippingCost' => ['value' => '36.13', 'currency' => 'USD'],
        ]);

        $this->assertTrue($parsed['known']);
        $this->assertSame(36.13, $parsed['cost']);
    }

    public function test_paid_shipping_cost_wins_over_free_type(): void
    {
        $parsed = EbayShippingCostParser::fromOption([
            'shippingCostType' => 'FREE',
            'shippingCost' => ['value' => '10.00'],
        ]);

        $this->assertTrue($parsed['known']);
        $this->assertSame(10.0, $parsed['cost']);
    }
}
