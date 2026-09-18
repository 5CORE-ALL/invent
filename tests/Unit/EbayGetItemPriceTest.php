<?php

namespace Tests\Unit;

use App\Support\EbayGetItemPrice;
use Tests\TestCase;

class EbayGetItemPriceTest extends TestCase
{
    public function test_current_price_wins_over_start_price(): void
    {
        $item = [
            'StartPrice' => 105.99,
            'SellingStatus' => [
                'CurrentPrice' => 147.04,
            ],
        ];

        $this->assertSame(147.04, EbayGetItemPrice::fromItem($item));
    }

    public function test_variation_current_price_wins_over_start_price(): void
    {
        $item = [
            'StartPrice' => 99.00,
            'Variations' => [
                'Variation' => [
                    'SKU' => 'LS 120 CRANK',
                    'StartPrice' => 105.99,
                    'SellingStatus' => [
                        'CurrentPrice' => ['@content' => 147.04],
                    ],
                ],
            ],
        ];

        $this->assertSame(147.04, EbayGetItemPrice::fromItem($item, 'LS 120 CRANK'));
    }

    public function test_falls_back_to_start_price_when_current_missing(): void
    {
        $this->assertSame(105.99, EbayGetItemPrice::fromItem([
            'StartPrice' => ['#text' => '105.99'],
        ]));
    }
}
